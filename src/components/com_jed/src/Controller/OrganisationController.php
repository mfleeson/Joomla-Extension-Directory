<?php

/**
 * @package JED
 *
 * @subpackage Organisations
 *
 * @copyright (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Site\Controller;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Exception;
use Jed\Component\Jed\Administrator\Organisation\MembershipState;
use Jed\Component\Jed\Administrator\Organisation\OrganisationAccess;
use Jed\Component\Jed\Administrator\Organisation\OrganisationRole;
use Jed\Component\Jed\Administrator\Organisation\OrganisationService;
use Jed\Component\Jed\Administrator\Transfer\TransferService;
use Jed\Component\Jed\Site\Helper\JedHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Joomla\Database\DatabaseInterface;
use RuntimeException;

/**
 * What a developer does with organisations on the site (PRD-09).
 *
 * Every task checks the CSRF token and that somebody is logged in, then hands the acting user's
 * id to OrganisationService, which decides from the database whether that user may do it. Nothing
 * here trusts a role, a state or an owner from the request.
 *
 * @since 4.1.0
 */
class OrganisationController extends BaseController
{
    /**
     * Create an organisation (id 0) or change one's details (administrators).
     *
     * @return void
     *
     * @since 4.1.0
     */
    public function save(): void
    {
        $this->checkToken();

        if (!$this->requireLogin()) {
            return;
        }

        $organisationId = $this->input->getInt('id', 0);
        $model          = $this->getModel('Organisation', 'Site');
        $data           = (array) $this->input->post->get('jform', [], 'array');
        $form           = $model->getForm($data, false);
        $valid          = $form !== false ? $model->validate($form, $data) : false;
        $back           = Route::_('index.php?option=com_jed&view=organisation&layout=edit&id=' . $organisationId, false);

        if ($valid === false) {
            $this->app->setUserState('com_jed.edit.organisation.data', $data);

            foreach ($model->getErrors() as $error) {
                $this->app->enqueueMessage($error instanceof Exception ? $error->getMessage() : (string) $error, 'warning');
            }

            $this->setRedirect($back);

            return;
        }

        try {
            if ($organisationId > 0) {
                $this->service()->update($organisationId, $valid, $this->userId());
            } else {
                $organisationId = $this->service()->create($valid, $this->userId());
            }
        } catch (RuntimeException $e) {
            $this->app->setUserState('com_jed.edit.organisation.data', $data);
            $this->setRedirect($back, $e->getMessage(), 'error');

            return;
        }

        $this->app->setUserState('com_jed.edit.organisation.data', null);
        $this->setRedirect($this->pageOf($organisationId), Text::_('COM_JED_ORGANISATION_SAVED'));
    }

    /**
     * Ask to join an organisation, chosen from the list or by its code.
     *
     * @return void
     *
     * @since 4.1.0
     */
    public function request(): void
    {
        $this->checkToken();

        if (!$this->requireLogin()) {
            return;
        }

        $service      = $this->service();
        $organisation = $this->input->getInt('organisation_id', 0) > 0
            ? $service->find($this->input->getInt('organisation_id', 0))
            : $service->findByUid((string) $this->input->getString('uid', ''));

        if ($organisation === null) {
            $this->setRedirect($this->listPage(), Text::_('COM_JED_ORGANISATION_ERROR_NOT_FOUND'), 'error');

            return;
        }

        $this->run(
            fn () => $service->request((int) $organisation->id, $this->userId(), (string) $this->input->getString('message', '')) === MembershipState::ACTIVE
                ? Text::sprintf('COM_JED_ORGANISATION_JOINED', $organisation->title)
                : Text::sprintf('COM_JED_ORGANISATION_REQUESTED', $organisation->title),
            $this->listPage()
        );
    }

    /**
     * Withdraw one's own open request.
     *
     * @return void
     *
     * @since 4.1.0
     */
    public function cancelRequest(): void
    {
        $this->checkToken();

        if (!$this->requireLogin()) {
            return;
        }

        $this->run(function (): string {
            $this->service()->cancelRequest($this->input->getInt('id', 0), $this->userId());

            return Text::_('COM_JED_ORGANISATION_REQUEST_CANCELLED');
        }, $this->listPage());
    }

    /**
     * Accept or decline an invitation - the current user's own, never anybody else's.
     *
     * GET with the token in the query: the dashboard offers it as two plain links.
     *
     * @return void
     *
     * @since 4.1.0
     */
    public function respond(): void
    {
        $this->checkToken('get');

        if (!$this->requireLogin()) {
            return;
        }

        $organisationId = $this->input->getInt('id', 0);
        $accept         = $this->input->getInt('accept', 0) === 1;

        $this->run(function () use ($organisationId, $accept): string {
            $this->service()->respondToInvitation($organisationId, $this->userId(), $accept);

            return Text::_($accept ? 'COM_JED_ORGANISATION_INVITATION_ACCEPTED' : 'COM_JED_ORGANISATION_INVITATION_DECLINED');
        }, $accept ? $this->pageOf($organisationId) : $this->listPage());
    }

    /**
     * An administrator approves or rejects a membership request.
     *
     * @return void
     *
     * @since 4.1.0
     */
    public function decide(): void
    {
        $this->checkToken();

        if (!$this->requireLogin()) {
            return;
        }

        $organisationId = $this->input->getInt('id', 0);
        $approve        = $this->input->getInt('approve', 0) === 1;

        $this->run(function () use ($organisationId, $approve): string {
            $this->service()->decideRequest($organisationId, $this->input->getInt('user_id', 0), $approve, $this->userId());

            return Text::_($approve ? 'COM_JED_ORGANISATION_REQUEST_APPROVED' : 'COM_JED_ORGANISATION_REQUEST_REJECTED');
        }, $this->pageOf($organisationId));
    }

    /**
     * An administrator brings an existing user in, found by their account e-mail address.
     *
     * The address lookup is the rate-limited, logged one transfers use (PRD-09 S5), and it is
     * only reached by an administrator of the organisation: nobody else gets to use it as an
     * "is this address registered" oracle.
     *
     * @return void
     *
     * @since 4.1.0
     */
    public function invite(): void
    {
        $this->checkToken();

        if (!$this->requireLogin()) {
            return;
        }

        $organisationId = $this->input->getInt('id', 0);
        $actorId        = $this->userId();
        $role           = OrganisationRole::tryFrom($this->input->getCmd('role', 'member')) ?? OrganisationRole::MEMBER;

        if (!OrganisationAccess::isMember($organisationId, $actorId, true) && !$this->service()->isStaff($actorId)) {
            $this->setRedirect($this->pageOf($organisationId), Text::_('COM_JED_ORGANISATION_ERROR_NOT_ADMIN'), 'error');

            return;
        }

        $this->run(function () use ($organisationId, $actorId, $role): string {
            $db        = \Joomla\CMS\Factory::getContainer()->get(DatabaseInterface::class);
            $recipient = (new TransferService($db))->findRecipient((string) $this->input->getString('email', ''), $actorId, 0);
            $state     = $this->service()->invite($organisationId, (int) $recipient->id, $actorId, $role);

            return Text::sprintf(
                $state === MembershipState::ACTIVE ? 'COM_JED_ORGANISATION_MEMBER_ADDED' : 'COM_JED_ORGANISATION_MEMBER_INVITED',
                $recipient->name
            );
        }, $this->pageOf($organisationId));
    }

    /**
     * An administrator takes a member out, or withdraws an invitation.
     *
     * @return void
     *
     * @since 4.1.0
     */
    public function remove(): void
    {
        $this->checkToken();

        if (!$this->requireLogin()) {
            return;
        }

        $organisationId = $this->input->getInt('id', 0);

        $this->run(function () use ($organisationId): string {
            $this->service()->remove($organisationId, $this->input->getInt('user_id', 0), $this->userId());

            return Text::_('COM_JED_ORGANISATION_MEMBER_REMOVED');
        }, $this->pageOf($organisationId));
    }

    /**
     * Leave an organisation.
     *
     * @return void
     *
     * @since 4.1.0
     */
    public function leave(): void
    {
        $this->checkToken();

        if (!$this->requireLogin()) {
            return;
        }

        $organisationId = $this->input->getInt('id', 0);

        $this->run(function () use ($organisationId): string {
            $this->service()->leave($organisationId, $this->userId());

            return Text::_('COM_JED_ORGANISATION_LEFT');
        }, $this->listPage(), $this->pageOf($organisationId));
    }

    /**
     * An administrator changes a member's role.
     *
     * @return void
     *
     * @since 4.1.0
     */
    public function role(): void
    {
        $this->checkToken();

        if (!$this->requireLogin()) {
            return;
        }

        $organisationId = $this->input->getInt('id', 0);
        $role           = OrganisationRole::tryFrom($this->input->getCmd('role', '')) ?? OrganisationRole::MEMBER;

        $this->run(function () use ($organisationId, $role): string {
            $this->service()->setRole($organisationId, $this->input->getInt('user_id', 0), $role, $this->userId());

            return Text::_('COM_JED_ORGANISATION_ROLE_CHANGED');
        }, $this->pageOf($organisationId));
    }

    /**
     * The primary contact hands the role to another administrator.
     *
     * @return void
     *
     * @since 4.1.0
     */
    public function primary(): void
    {
        $this->checkToken();

        if (!$this->requireLogin()) {
            return;
        }

        $organisationId = $this->input->getInt('id', 0);

        $this->run(function () use ($organisationId): string {
            $this->service()->setPrimary($organisationId, $this->input->getInt('user_id', 0), $this->userId());

            return Text::_('COM_JED_ORGANISATION_PRIMARY_CHANGED');
        }, $this->pageOf($organisationId));
    }

    /**
     * An administrator switches membership requests on or off (R8).
     *
     * @return void
     *
     * @since 4.1.0
     */
    public function requests(): void
    {
        $this->checkToken();

        if (!$this->requireLogin()) {
            return;
        }

        $organisationId = $this->input->getInt('id', 0);
        $accept         = $this->input->getInt('accept', 0) === 1;

        $this->run(function () use ($organisationId, $accept): string {
            $this->service()->setAcceptRequests($organisationId, $accept, $this->userId());

            return Text::_($accept ? 'COM_JED_ORGANISATION_REQUESTS_ON' : 'COM_JED_ORGANISATION_REQUESTS_OFF');
        }, $this->pageOf($organisationId));
    }

    /**
     * Run an action, turn a refusal into a message.
     *
     * @param callable    $action    Returns the success message.
     * @param string      $success   Where to go when it worked.
     * @param string|null $failure   Where to go when it did not (defaults to $success).
     *
     * @return void
     *
     * @since 4.1.0
     */
    private function run(callable $action, string $success, ?string $failure = null): void
    {
        try {
            $message = (string) $action();
        } catch (RuntimeException $e) {
            $this->setRedirect($failure ?? $success, $e->getMessage(), 'error');

            return;
        }

        $this->setRedirect($success, $message);
    }

    /**
     * @return bool  Whether somebody is logged in; otherwise the login page is set as redirect.
     *
     * @since 4.1.0
     */
    private function requireLogin(): bool
    {
        if (JedHelper::isLoggedIn()) {
            return true;
        }

        $this->setRedirect(Route::_(JedHelper::getLoginlink(), false), Text::_('COM_JED_ORGANISATION_LOGIN_REQUIRED'), 'warning');

        return false;
    }

    /**
     * @return int
     *
     * @since 4.1.0
     */
    private function userId(): int
    {
        return (int) $this->app->getIdentity()->id;
    }

    /**
     * @return OrganisationService
     *
     * @since 4.1.0
     */
    private function service(): OrganisationService
    {
        return new OrganisationService(\Joomla\CMS\Factory::getContainer()->get(DatabaseInterface::class));
    }

    /**
     * @param int $organisationId The organisation.
     *
     * @return string
     *
     * @since 4.1.0
     */
    private function pageOf(int $organisationId): string
    {
        return Route::_('index.php?option=com_jed&view=organisation&id=' . $organisationId, false);
    }

    /**
     * @return string
     *
     * @since 4.1.0
     */
    private function listPage(): string
    {
        return Route::_('index.php?option=com_jed&view=organisations', false);
    }
}
