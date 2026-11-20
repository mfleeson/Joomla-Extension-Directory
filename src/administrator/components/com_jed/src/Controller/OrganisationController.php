<?php

/**
 * @package JED
 *
 * @subpackage Organisations
 *
 * @copyright (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Administrator\Controller;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Jed\Component\Jed\Administrator\Organisation\OrganisationRole;
use Jed\Component\Jed\Administrator\Organisation\OrganisationService;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\FormController;
use Joomla\CMS\Router\Route;
use Joomla\Database\DatabaseInterface;
use RuntimeException;

/**
 * One organisation in the backend (PRD-09 R13): edit and save come from FormController; the
 * member actions are here. The JED team acts as "staff" in OrganisationService - past the role
 * checks, and adding people directly rather than inviting them - but not past the structural
 * rules (the primary contact is an active administrator).
 *
 * @since 4.1.0
 */
class OrganisationController extends FormController
{
    /**
     * @var string
     *
     * @since 4.1.0
     */
    protected $view_list = 'organisations';

    /**
     * Add an existing user, as member or administrator, without an invitation.
     *
     * @return void
     *
     * @since 4.1.0
     */
    public function addMember(): void
    {
        $role = OrganisationRole::tryFrom($this->input->getCmd('member_role', 'member')) ?? OrganisationRole::MEMBER;

        $this->memberAction(fn (OrganisationService $s, int $org, int $actor) => $s->invite($org, $this->input->getInt('member_user_id', 0), $actor, $role), 'COM_JED_ORGANISATION_MEMBER_ADDED_BACKEND');
    }

    /**
     * Take a member out, or withdraw an invitation.
     *
     * @return void
     *
     * @since 4.1.0
     */
    public function removeMember(): void
    {
        $this->memberAction(fn (OrganisationService $s, int $org, int $actor) => $s->remove($org, $this->input->getInt('user_id', 0), $actor), 'COM_JED_ORGANISATION_MEMBER_REMOVED');
    }

    /**
     * Change a member's role.
     *
     * @return void
     *
     * @since 4.1.0
     */
    public function setRole(): void
    {
        $role = OrganisationRole::tryFrom($this->input->getCmd('role', '')) ?? OrganisationRole::MEMBER;

        $this->memberAction(fn (OrganisationService $s, int $org, int $actor) => $s->setRole($org, $this->input->getInt('user_id', 0), $role, $actor), 'COM_JED_ORGANISATION_ROLE_CHANGED');
    }

    /**
     * Make an administrator the primary contact.
     *
     * @return void
     *
     * @since 4.1.0
     */
    public function setPrimary(): void
    {
        $this->memberAction(fn (OrganisationService $s, int $org, int $actor) => $s->setPrimary($org, $this->input->getInt('user_id', 0), $actor), 'COM_JED_ORGANISATION_PRIMARY_CHANGED');
    }

    /**
     * Approve or reject a membership request.
     *
     * @return void
     *
     * @since 4.1.0
     */
    public function decide(): void
    {
        $approve = $this->input->getInt('approve', 0) === 1;

        $this->memberAction(
            fn (OrganisationService $s, int $org, int $actor) => $s->decideRequest($org, $this->input->getInt('user_id', 0), $approve, $actor),
            $approve ? 'COM_JED_ORGANISATION_REQUEST_APPROVED' : 'COM_JED_ORGANISATION_REQUEST_REJECTED'
        );
    }

    /**
     * @param callable $action     The service call.
     * @param string   $successKey Language key.
     *
     * @return void
     *
     * @since 4.1.0
     */
    private function memberAction(callable $action, string $successKey): void
    {
        $this->checkToken();

        $organisationId = $this->input->getInt('id', 0);
        $back           = Route::_('index.php?option=com_jed&view=organisation&layout=edit&id=' . $organisationId, false);
        $user           = $this->app->getIdentity();

        if (!$user->authorise('core.edit', 'com_jed')) {
            $this->setRedirect($back, Text::_('JLIB_APPLICATION_ERROR_EDIT_NOT_PERMITTED'), 'error');

            return;
        }

        try {
            $action(new OrganisationService(Factory::getContainer()->get(DatabaseInterface::class)), $organisationId, (int) $user->id);
        } catch (RuntimeException $e) {
            $this->setRedirect($back, $e->getMessage(), 'error');

            return;
        }

        $this->setRedirect($back, Text::_($successKey));
    }
}
