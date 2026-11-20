<?php

/**
 * @package JED
 *
 * @subpackage Organisations
 *
 * @copyright (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Site\Model;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Exception;
use Jed\Component\Jed\Administrator\Organisation\MembershipState;
use Jed\Component\Jed\Administrator\Organisation\OrganisationRole;
use Jed\Component\Jed\Administrator\Organisation\OrganisationService;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\FormModel;
use Joomla\Database\ParameterType;

/**
 * One organisation as its members see it, and its edit form (PRD-09).
 *
 * Nobody outside the organisation can open it: an id that is not theirs answers 404, the same
 * as an id that does not exist, so the page cannot be used to find out which organisations exist
 * or who is in them. The JED team (core.edit) can open any.
 *
 * What a member sees is cut to their role: everybody sees the active members' names and the
 * listings; administrators also see addresses, open requests with their messages, and open
 * invitations.
 *
 * @since 4.1.0
 */
class OrganisationModel extends FormModel
{
    /**
     * @var object|null|false  false = not loaded yet.
     *
     * @since 4.1.0
     */
    private object|null|false $item = false;

    /**
     * @return void
     *
     * @since 4.1.0
     */
    protected function populateState(): void
    {
        $this->setState('organisation.id', Factory::getApplication()->getInput()->getInt('id', 0));
        $this->setState('params', Factory::getApplication()->getParams('com_jed'));
    }

    /**
     * @param array $data     Data for the form.
     * @param bool  $loadData Load the item's data into it.
     *
     * @return Form|false
     *
     * @since 4.1.0
     */
    public function getForm($data = [], $loadData = true)
    {
        return $this->loadForm('com_jed.organisation', 'organisation', ['control' => 'jform', 'load_data' => $loadData]) ?: false;
    }

    /**
     * @return array|object
     *
     * @since 4.1.0
     */
    protected function loadFormData()
    {
        $data = Factory::getApplication()->getUserState('com_jed.edit.organisation.data', []);

        if (!empty($data)) {
            return $data;
        }

        if ((int) $this->getState('organisation.id') > 0) {
            return $this->getItem() ?? [];
        }

        // A new organisation starts with the founder as its contact.
        $user = $this->getCurrentUser();

        return ['contact_name' => (string) $user->name, 'contact_email' => (string) $user->email, 'accept_requests' => 1];
    }

    /**
     * The organisation with what the current user may see of it.
     *
     * @param int|null $organisationId The organisation; the requested one when null.
     *
     * @return object|null  Null for a new organisation (id 0).
     *
     * @throws Exception  404 when the user may not see it.
     *
     * @since 4.1.0
     */
    public function getItem(?int $organisationId = null): ?object
    {
        if ($this->item !== false && $organisationId === null) {
            return $this->item;
        }

        $organisationId ??= (int) $this->getState('organisation.id');

        if ($organisationId <= 0) {
            return $this->item = null;
        }

        $service      = new OrganisationService($this->getDatabase());
        $organisation = $service->find($organisationId);
        $userId       = (int) $this->getCurrentUser()->id;
        $isStaff      = $service->isStaff($userId);
        $membership   = $organisation !== null ? $service->membership($organisationId, $userId) : null;
        $isMember     = $membership !== null && (int) $membership->state === MembershipState::ACTIVE->value;

        if ($organisation === null || (!$isMember && !$isStaff)) {
            throw new Exception(Text::_('COM_JED_ORGANISATION_ERROR_NOT_FOUND'), 404);
        }

        $isAdmin = $isStaff || ($isMember && (string) $membership->role === OrganisationRole::ADMIN->value);

        $organisation->viewer = (object) [
            'isMember'  => $isMember,
            'isAdmin'   => ($isAdmin && (int) $organisation->state === 1) || $isStaff,
            'isPrimary' => (int) $organisation->primary_user_id === $userId,
            'isStaff'   => $isStaff,
            'role'      => $membership->role ?? null,
        ];

        $members = $service->members($organisationId, $isAdmin
            ? [MembershipState::ACTIVE, MembershipState::INVITED, MembershipState::REQUESTED]
            : [MembershipState::ACTIVE]);

        // Addresses are for the people who run the organisation, not for every member.
        if (!$isAdmin) {
            foreach ($members as $member) {
                unset($member->email, $member->request_message);
            }
        }

        $organisation->members  = array_values(array_filter($members, static fn (object $m): bool => (int) $m->state === MembershipState::ACTIVE->value));
        $organisation->invited  = array_values(array_filter($members, static fn (object $m): bool => (int) $m->state === MembershipState::INVITED->value));
        $organisation->requests = array_values(array_filter($members, static fn (object $m): bool => (int) $m->state === MembershipState::REQUESTED->value));
        $organisation->listings = $this->listings($organisationId);

        return $this->item = $organisation;
    }

    /**
     * The organisation's listings, whatever their state, except soft-deleted ones.
     *
     * @param int $organisationId The organisation.
     *
     * @return object[]
     *
     * @since 4.1.0
     */
    private function listings(int $organisationId): array
    {
        $db = $this->getDatabase();

        return $db->setQuery(
            $db->getQuery(true)
                ->select($db->quoteName(['id', 'name', 'state', 'approved', 'blocked']))
                ->from($db->quoteName('#__jed_extensions'))
                ->where($db->quoteName('organisation_id') . ' = :oid')
                ->where($db->quoteName('deleted') . ' = 0')
                ->bind(':oid', $organisationId, ParameterType::INTEGER)
                ->order($db->quoteName('name') . ' ASC')
        )->loadObjectList() ?: [];
    }
}
