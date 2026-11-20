<?php

/**
 * @package JED
 *
 * @subpackage TICKETS
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Site\Controller;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Exception;
use Jed\Component\Jed\Administrator\Enum\TicketType;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\FormController;
use Joomla\CMS\Router\Route;

/**
 * Ticket class.
 *
 * @since 4.0.0
 */
class TicketformController extends FormController
{
    /**
     * Method to abort current operation
     *
     * @param null $key
     *
     * @return void
     *
     * @since  4.0.0
     * @throws Exception
     */
    public function cancel($key = null): void
    {
        /* @var $app \Joomla\CMS\Application\SiteApplication */
        $app = Factory::getApplication();

        // Get the current edit id.
        $editId = (int) $app->getUserState('com_jed.edit.ticket.id');

        // Get the model.
        $model = $this->getModel('Ticketform', 'Site');

        // Check in the item
        if ($editId) {
            $model->checkin($editId);
        }

        $menu = Factory::getApplication()->getMenu();
        $item = $menu->getActive();
        $url  = (empty($item->link) ? 'index.php?option=com_jed&view=tickets' : $item->link);
        $this->setRedirect(Route::_($url, false));
    }

    /**
     * Method to check out an item for editing and redirect to the edit form.
     *
     * @param null $key
     * @param null $urlVar
     *
     * @return void
     *
     * @since 4.0.0
     *
     * @throws Exception
     */
    public function edit($key = null, $urlVar = null): void
    {
        /* @var $app \Joomla\CMS\Application\SiteApplication */
        $app = Factory::getApplication();

        // Get the previous edit id (if any) and the current edit id.
        $previousId = (int) $app->getUserState('com_jed.edit.ticket.id');
        $editId     = $app->getInput()->getInt('id', 0);

        // Get the model.
        $model = $this->getModel('Ticketform', 'Site');

        // A ticket is only ever opened by the person who raised it (or the JED team). Checking out
        // somebody else's ticket locked it against the team's own edit screen.
        if ($editId > 0) {
            $user  = $app->getIdentity();
            $table = $model->getTable();

            if (
                (int) $user->id <= 0
                || !$table->load($editId)
                || ((int) $table->created_by !== (int) $user->id && !$user->authorise('core.manage', 'com_jed'))
            ) {
                throw new Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
            }
        }

        // Set the user id for the user to edit in the session.
        $app->setUserState('com_jed.edit.ticket.id', $editId);

        // Check out the item
        if ($editId) {
            $model->checkout($editId);
        }

        // Check in the previous user.
        if ($previousId) {
            $model->checkin($previousId);
        }

        // Redirect to the edit screen.
        $this->setRedirect(Route::_('index.php?option=com_jed&view=ticketform&layout=viewticket&id=' . $editId, false));
    }

    /**
     * Method to remove data
     *
     * @return void
     *
     * @since  4.0.0
     * @throws Exception
     */
    public function remove(): void
    {
        // Tickets are not deleted from the site. This used to call TicketformModel::delete(), a
        // method that does not exist (FormModel has none), so the request died with a fatal error
        // - and it did so without a token check. A ticket is closed by the JED team instead.
        $this->checkToken('request');

        $this->setMessage(Text::_('JERROR_ALERTNOAUTHOR'), 'error');
        $this->setRedirect(Route::_('index.php?option=com_jed&view=tickets', false));
    }

    /**
     * Method to save data.
     *
     * @param null $key
     * @param null $urlVar
     *
     * @return void
     *
     * @since  4.0.0
     * @throws Exception
     */
    public function save($key = null, $urlVar = null): void
    {
        // Check for request forgeries.
        $this->checkToken();

        // Initialise variables.
        $app   = Factory::getApplication();
        $model = $this->getModel('Ticketform', 'Site');

        // Get the user data.
        $data = Factory::getApplication()->input->get('jform', [], 'array');

        // Validate the posted data.
        $form = $model->getForm();

        if (!$form) {
            throw new Exception(Text::_('COM_JED_ERROR_COULD_NOT_VALIDATE'), 500);
        }

        // Validate the posted data.
        $data = $model->validate($form, $data);

        // Check for errors.
        if ($data === false) {
            $this->app->enqueueMessage(Text::_('COM_JED_ERROR_SAVING_DATA'), 'warning');

            $jform = $this->input->get('jform', [], 'ARRAY');

            // Save the data in the session.
            $app->setUserState('com_jed.edit.ticket.data', $jform);

            // Redirect back to the edit screen.
            $id = (int) $app->getUserState('com_jed.edit.ticket.id');
            $this->setRedirect(Route::_('index.php?option=com_jed&view=tickets', false));
            $this->redirect();
        }

        // "My Extension" (category 2) names one of the submitter's own listings; "Reported
        // Extension" (14) any listing. Which listings the submitter may actually attach is decided
        // by TicketformModel::resolveLinkedItem(), not here.
        if ((int) ($data['ticket_category_type'] ?? 0) === 2) {
            $data['linked_item_type'] = TicketType::Extension->value;
            $data['linked_item_id']   = (int) ($data['myextension_varied_name'] ?? 0);
        }

        if ((int) ($data['ticket_category_type'] ?? 0) === 14) {
            $data['linked_item_type'] = TicketType::Extension->value;
            $data['linked_item_id']   = (int) ($data['reported_varied_name'] ?? 0);
        }

        // Attempt to save the data.
        $return = $model->save($data);

        // Check for errors.
        if ($return === false) {
            // Save the data in the session.
            $app->setUserState('com_jed.edit.ticket.data', $data);

            // Redirect back to the edit screen.
            $id = (int) $app->getUserState('com_jed.edit.ticket.id');
            $this->setMessage(Text::_('JLIB_APPLICATION_ERROR_SAVE_FAILED'), 'warning');
            $this->setRedirect(Route::_('index.php?option=com_jed&view=ticketform&layout=edit&id=' . $id, false));

            // Without this the failure fell through to "saved successfully" and the tickets list.
            return;
        }

        // Check in the profile.
        if ($return) {
            $model->checkin($return);
        }

        // Clear the profile id from the session.
        $app->setUserState('com_jed.edit.ticket.id', null);

        // Redirect to the list screen.
        $this->setMessage(Text::_('COM_JED_GENERAL_ITEM_SAVED_SUCCESSFULLY_LABEL'));
        $menu = Factory::getApplication()->getMenu();
        $item = $menu->getActive();
        //   $url  = (empty($item->link) ? 'index.php?option=com_jed&view=tickets' : $item->link);

        $this->setRedirect(Route::_('index.php?option=com_jed&view=tickets', false));

        // Flush the data from the session.
        $app->setUserState('com_jed.edit.ticket.data', null);
    }
}
