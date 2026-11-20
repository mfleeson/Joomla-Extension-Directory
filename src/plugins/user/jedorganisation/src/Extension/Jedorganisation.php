<?php

/**
 * @package     Joomla.Plugin
 * @subpackage  User.jedorganisation
 *
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Plugin\User\Jedorganisation\Extension;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Jed\Component\Jed\Administrator\Organisation\OrganisationService;
use Joomla\CMS\Event\Model\PrepareFormEvent;
use Joomla\CMS\Event\User\AfterSaveEvent;
use Joomla\CMS\Event\User\BeforeSaveEvent;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Event\SubscriberInterface;
use Throwable;

/**
 * "Your organisation" on the registration form (PRD-09 R6).
 *
 * A new user can found an organisation (becoming its administrator and primary contact) or ask to
 * join one that takes requests - chosen from a list or by its code. The choice is checked in
 * onUserBeforeSave, *before* the account exists, so a refused choice (an unknown code, an
 * organisation that stopped taking requests) is a form error rather than an account half set up.
 * It is carried out in onUserAfterSave, once the account has an id; the administrators are told
 * about a request by OrganisationService.
 *
 * Only on the site's registration: the profile form, the backend user form and the API are not
 * touched. Everything here is optional - "no organisation" is the default, and somebody who
 * submits a listing without one gets a personal organisation then.
 *
 * @since 4.1.0
 */
final class Jedorganisation extends CMSPlugin implements SubscriberInterface
{
    use DatabaseAwareTrait;

    /**
     * @var bool
     *
     * @since 4.1.0
     */
    protected $autoloadLanguage = true;

    /**
     * @return array
     *
     * @since 4.1.0
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onContentPrepareForm' => 'onContentPrepareForm',
            'onUserBeforeSave'     => 'onUserBeforeSave',
            'onUserAfterSave'      => 'onUserAfterSave',
        ];
    }

    /**
     * Add the fieldset to the site registration form.
     *
     * @param PrepareFormEvent $event The event.
     *
     * @return void
     *
     * @since 4.1.0
     */
    public function onContentPrepareForm(PrepareFormEvent $event): void
    {
        $form = $event->getForm();

        if (!$form instanceof Form || $form->getName() !== 'com_users.registration' || !$this->available()) {
            return;
        }

        $form->loadFile(JPATH_PLUGINS . '/user/jedorganisation/forms/registration.xml', false);

        if ((int) $this->params->get('offer_join', 1) !== 1) {
            // Joining switched off by the site: the fields go, and choice() ignores "join".
            foreach (['organisation_id', 'uid', 'message'] as $field) {
                $form->removeField($field, 'jedorganisation');
            }
        }
    }

    /**
     * Refuse the registration when the organisation choice cannot be carried out.
     *
     * @param BeforeSaveEvent $event The event.
     *
     * @return void
     *
     * @since 4.1.0
     */
    public function onUserBeforeSave(BeforeSaveEvent $event): void
    {
        if (!$event->getIsNew() || !$this->isRegistration() || !$this->available()) {
            return;
        }

        $choice = $this->choice($event->getData());

        if ($choice === null) {
            return;
        }

        $problem = match ($choice['choice']) {
            'create' => trim($choice['title']) === ''
                ? 'PLG_USER_JEDORGANISATION_ERROR_TITLE'
                : ($choice['contact_email'] !== '' && !filter_var($choice['contact_email'], FILTER_VALIDATE_EMAIL) ? 'PLG_USER_JEDORGANISATION_ERROR_EMAIL' : null),
            'join'   => $this->joinProblem($choice),
            default  => null,
        };

        if ($problem !== null) {
            $this->getApplication()->enqueueMessage(Text::_($problem), 'error');
            $event->addResult(false);
        }
    }

    /**
     * Carry the choice out for the account that now exists.
     *
     * @param AfterSaveEvent $event The event.
     *
     * @return void
     *
     * @since 4.1.0
     */
    public function onUserAfterSave(AfterSaveEvent $event): void
    {
        if (!$event->getIsNew() || !$event->getSavingResult() || !$this->isRegistration() || !$this->available()) {
            return;
        }

        $user   = $event->getUser();
        $userId = (int) ($user['id'] ?? 0);
        $choice = $this->choice($user);

        if ($userId <= 0 || $choice === null) {
            return;
        }

        // The service's messages are com_jed strings; registration runs in com_users, which has
        // not loaded them.
        $language = $this->getApplication()->getLanguage();
        $language->load('com_jed', JPATH_SITE . '/components/com_jed') || $language->load('com_jed', JPATH_SITE);

        $service = new OrganisationService($this->getDatabase());

        try {
            if ($choice['choice'] === 'create') {
                $service->create([
                    'title'         => $choice['title'],
                    'contact_name'  => (string) ($user['name'] ?? ''),
                    'contact_email' => $choice['contact_email'] !== '' ? $choice['contact_email'] : (string) ($user['email'] ?? ''),
                    'website'       => $choice['website'],
                ], $userId);

                $this->getApplication()->enqueueMessage(Text::sprintf('PLG_USER_JEDORGANISATION_CREATED', $choice['title']));

                return;
            }

            $organisation = $this->target($service, $choice);

            if ($organisation !== null) {
                $service->request((int) $organisation->id, $userId, $choice['message']);
                $this->getApplication()->enqueueMessage(Text::sprintf('PLG_USER_JEDORGANISATION_REQUESTED', $organisation->title));
            }
        } catch (Throwable $e) {
            // The account exists; the organisation step did not work. Say so - it can be done again
            // from My Organisations - rather than failing a registration that has already happened.
            $this->getApplication()->enqueueMessage(Text::sprintf('PLG_USER_JEDORGANISATION_FAILED', $e->getMessage()), 'warning');

            try {
                Log::add('plg_user_jedorganisation: ' . $e->getMessage(), Log::WARNING, 'com_jed');
            } catch (Throwable) {
                // Nothing more to do.
            }
        }
    }

    /**
     * The submitted choice, cleaned; null for "no organisation".
     *
     * @param array $data The user data.
     *
     * @return array{choice: string, title: string, contact_email: string, website: string, organisation_id: int, uid: string, message: string}|null
     *
     * @since 4.1.0
     */
    private function choice(array $data): ?array
    {
        $raw = $data['jedorganisation'] ?? null;

        if (\is_object($raw)) {
            $raw = (array) $raw;
        }

        if (!\is_array($raw)) {
            return null;
        }

        $choice = (string) ($raw['choice'] ?? 'none');

        if (!\in_array($choice, ['create', 'join'], true)) {
            return null;
        }

        if ($choice === 'join' && (int) $this->params->get('offer_join', 1) !== 1) {
            return null;
        }

        return [
            'choice'          => $choice,
            'title'           => trim((string) ($raw['title'] ?? '')),
            'contact_email'   => trim((string) ($raw['contact_email'] ?? '')),
            'website'         => trim((string) ($raw['website'] ?? '')),
            'organisation_id' => (int) ($raw['organisation_id'] ?? 0),
            'uid'             => trim((string) ($raw['uid'] ?? '')),
            'message'         => trim((string) ($raw['message'] ?? '')),
        ];
    }

    /**
     * @param array $choice The cleaned choice.
     *
     * @return string|null  A language key, or null when the organisation can be asked.
     *
     * @since 4.1.0
     */
    private function joinProblem(array $choice): ?string
    {
        $organisation = $this->target(new OrganisationService($this->getDatabase()), $choice);

        if ($organisation === null || (int) $organisation->state !== 1) {
            return 'PLG_USER_JEDORGANISATION_ERROR_NOT_FOUND';
        }

        if ((int) $organisation->accept_requests !== 1) {
            return 'PLG_USER_JEDORGANISATION_ERROR_NOT_ACCEPTING';
        }

        return null;
    }

    /**
     * The organisation the user wants to join: by code when one was typed, otherwise from the list.
     *
     * @param OrganisationService $service The service.
     * @param array               $choice  The cleaned choice.
     *
     * @return object|null
     *
     * @since 4.1.0
     */
    private function target(OrganisationService $service, array $choice): ?object
    {
        if ($choice['uid'] !== '') {
            return $service->findByUid($choice['uid']);
        }

        return $choice['organisation_id'] > 0 ? $service->find($choice['organisation_id']) : null;
    }

    /**
     * @return bool  Whether this request is the site's registration.
     *
     * @since 4.1.0
     */
    private function isRegistration(): bool
    {
        $app = $this->getApplication();

        if (!$app->isClient('site')) {
            return false;
        }

        $input = $app->getInput();

        return $input->getCmd('option') === 'com_users'
            && strtolower((string) $input->getCmd('controller')) === 'registration';
    }

    /**
     * @return bool  Whether com_jed (and its organisations) is installed.
     *
     * @since 4.1.0
     */
    private function available(): bool
    {
        return class_exists(OrganisationService::class);
    }
}
