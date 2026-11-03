<?php

/**
 * @package JED
 *
 * @subpackage Tickets
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Administrator\Controller;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;

// phpcs:enable PSR1.Files.SideEffects


use Exception;
use Jed\Component\Jed\Administrator\Checker\JedCheckerService;
use Jed\Component\Jed\Administrator\Enum\TicketType;
use Jed\Component\Jed\Administrator\Helper\JedemailHelper;
use Jed\Component\Jed\Administrator\Helper\JedHelper;
use Jed\Component\Jed\Administrator\Model\TicketinternalnoteModel;
use Jed\Component\Jed\Administrator\Model\TicketModel;
use Jed\Component\Jed\Administrator\Ticket\TicketTypeHandlerRegistry;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\FormController;
use Joomla\CMS\Router\Route;

/**
 * JED Ticket Controller class
 *
 * @since 4.0.0
 */
class TicketController extends FormController
{
    /**
     * A string showing the plural of the current object
     *
     * @var string
     *
     * @since 4.0.0
     */
    protected $view_list = 'tickets';


    /**
     * @since  4.0.0
     * @throws Exception
     */
    public function assignDeveloperUpdatetoVEL()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $this->task = $_POST['task'] ?? '';

        if ($this->task == "ticket.assignDeveloperUpdatetoVEL") {
            $ticketId    = $_POST["jform"]['id'];
            $reportId    = $_POST["jform"]['linked_item_id'];
            $vel_item_id = $_POST["jform"]['vel_item_id'];
            $queryUpdate = $db->getQuery(true)
                ->update('#__jed_vel_developer_update')
                ->set($db->qn('vel_item_id') . ' = ' . (int)$vel_item_id)
                ->where($db->qn('id') . ' = ' . (int)$reportId);

            $db->setQuery($queryUpdate);
            $db->execute();

            $app = Factory::getApplication();
            $app->enqueueMessage('Developer Update linked to Existing VEL Item', 'success');
            $this->setRedirect(
                Route::_('index.php?option=com_jed&view=ticket&layout=edit&id=' . (int)$ticketId, false)
            );
        }
    }

    /**
     * Takes an Abandoned Component Report and creates a VEL entry for it.
     *
     * @since 4.0.0
     */
    public function copyAbandonedReporttoVEL()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $this->task = $_POST['task'] ?? '';

        if ($this->task == "ticket.copyAbandonedReporttoVEL") {
            $reportId = (int)$_POST["jform"]['linked_item_id'];

            $querySelect = $db->getQuery(true)
                ->select(
                    "0,`extension_name` as vulnerable_item_name, `extension_version` as vulnerable_item_version, CONCAT(`extension_name`,', ',`extension_version`,', ','Abandoned') AS title,'',3 AS 'status'," . $reportId . ",'','' AS 'risk_level', `extension_version`, `extension_version`,'' AS 'patch_version','','',5 AS 'exploit_type','' AS 'exploit_other_description','' AS 'xml_manifest','' AS 'manifest_location', '' AS 'install_data', `reporter_fullname` AS 'discovered_by',''"
                )
                ->from('#__jed_vel_abandoned_report')
                ->where('id = ' . $reportId);

            $queryInsert = $db->getQuery(true)
                ->insert('#__jed_vel_vulnerable_item')
                ->columns(
                    $db->qn(
                        [
                            'id',
                            'vulnerable_item_name',
                            'vulnerable_item_version',
                            'title',
                            'internal_description',
                            'status',
                            'report_id',
                            'jed',
                            'risk_level',
                            'start_version',
                            'vulnerable_version',
                            'patch_version',
                            'recommendation',
                            'update_notice',
                            'exploit_type',
                            'exploit_other_description',
                            'xml_manifest',
                            'manifest_location',
                            'install_data',
                            'discovered_by',
                            'public_description',
                        ]
                    )
                )
                ->values($querySelect);

            $db->setQuery($queryInsert);
            $db->execute();

            $newVel = $db->insertid();

            $queryUpdate = $db->getQuery(true)
                ->update('#__jed_vel_abandoned_report')
                ->set(
                    [
                        $db->qn('passed_to_vel') . ' = 1',
                        ($db->qn('vel_item_id') . ' = ' . (int)$newVel),
                    ]
                )
                ->where($db->qn('id') . ' = ' . $reportId);

            $db->setQuery($queryUpdate);
            $db->execute();

            /* CHECK IN THIS MODEL */
            $model = $this->getModel();
            $cid   = $this->input->get('id', [], 'array');
            if (!empty($cid)) {
                $model->checkIn($cid[0]);
            }

            $this->setRedirect(
                Route::_(
                    'index.php?option=com_jed&view=velvulnerableitem&task=velvulnerableitem.edit&id=' . (int)$newVel,
                    false
                )
            );
        }
    }

    /**
     * Function to respond to copyReporttoVEL button on viewing a reported vulnerability
     * It needs to take the data and create a new vulnerable item and then open that
     * report for editing
     *
     * @since 4.0.0
     */
    public function copyReporttoVEL()
    {
        $db         = Factory::getContainer()->get('DatabaseDriver');
        $this->task = $_POST['task'] ?? '';
        if ($this->task == "ticket.copyReporttoVEL") {
            $reportId = (int)$_POST["jform"]['linked_item_id'];

            $exploit_string = Text::_(
                'COM_JED_VEL_GENERAL_EXPLOIT_TYPE_OPTION_' . ($_POST["jform"]['exploit_type'] ?? '')
            );

            $querySelect = $db->getQuery(true)
                ->select(
                    "0,vulnerable_item_name, vulnerable_item_version, CONCAT(`vulnerable_item_name`,', ',`vulnerable_item_version`,', ','" . $exploit_string . "') as title,'',0 AS 'status',`id`,`jed_url`,'' AS 'risk_level',`vulnerable_item_version`, `vulnerable_item_version`,'' AS 'patch_version','','',`exploit_type`,`exploit_other_description`, '' AS 'xml_manifest','' AS 'manifest_location', '' AS 'install_data', `reporter_fullname` AS 'discovered_by','',now()"
                )
                ->from('#__jed_vel_report')
                ->where('id = ' . $reportId);

            $queryInsert = $db->getQuery(true)
                ->insert('#__jed_vel_vulnerable_item')
                ->columns(
                    $db->qn(
                        [
                            'id',
                            'vulnerable_item_name',
                            'vulnerable_item_version',
                            'title',
                            'internal_description',
                            'status',
                            'report_id',
                            'jed',
                            'risk_level',
                            'start_version',
                            'vulnerable_version',
                            'patch_version',
                            'recommendation',
                            'update_notice',
                            'exploit_type',
                            'exploit_other_description',
                            'xml_manifest',
                            'manifest_location',
                            'install_data',
                            'discovered_by',
                            'public_description',
                            'created',
                        ]
                    )
                )
                ->values($querySelect);

            $db->setQuery($queryInsert);
            $db->execute();

            $newVel = $db->insertid();

            $queryUpdate = $db->getQuery(true)
                ->update('#__jed_vel_report')
                ->set(
                    [
                        $db->qn('passed_to_vel') . ' = 1',
                        ($db->qn('vel_item_id') . ' = ' . (int)$newVel),
                    ]
                )
                ->where($db->qn('id') . ' = ' . $reportId);

            $db->setQuery($queryUpdate);
            $db->execute();

            /* CHECK IN THIS MODEL */
            $model = $this->getModel();
            $cid   = $this->input->get('id', [], 'array');
            if (!empty($cid)) {
                $model->checkIn($cid[0]);
            }

            $this->setRedirect(
                Route::_(
                    'index.php?option=com_jed&view=velvulnerableitem&task=velvulnerableitem.edit&id=' . (int)$newVel,
                    false
                )
            );
        }
    }

    /**
     * Function to respond to gotoVEL button on viewing a ticket.
     * It checks in the current ticket and performs a redirect
     *
     * @since 4.0.0
     */
    public function gotoVEL()
    {
        $this->task = $_POST['task'] ?? '';

        if ($this->task == "ticket.gotoVEL") {
            $jform     = $_POST['jform'];
            $ticket_id = $jform['id'];
            $vel_id    = $jform['vel_item_id'];

            /* CHECK IN THIS MODEL */
            $model = $this->getModel();
            $model->checkIn($ticket_id);

            $this->setRedirect(
                Route::_(
                    'index.php?option=com_jed&view=velvulnerableitem&task=velvulnerableitem.edit&id=' . (int)$vel_id,
                    false
                )
            );
        }
    }

    /**
     * Function to link a submitted developer update to Vulnerable item.
     * It checks in the current ticket and performs a redirect
     *
     * @since 4.0.0
     */
    public function linkDeveloperUpdatetoVEL()
    {
        $db         = Factory::getContainer()->get('DatabaseDriver');
        $this->task = $_POST['task'] ?? '';
        if ($this->task == "ticket.linkDeveloperUpdatetoVEL") {
            $jform           = $_POST['jform'];
            $vel_id          = $jform['vel_item_id'];
            $vel_devupdateid = $jform['veldeveloperupdate_id'];

            $queryUpdate = $db->getQuery(true)
                ->update('#__jed_vel_developer_update')
                ->set($db->qn('vel_item_id') . ' = ' . (int)$vel_id)
                ->where($db->qn('id') . ' = ' . (int)$vel_devupdateid);

            $db->setQuery($queryUpdate);
            $db->execute();

            /* CHECK IN THIS MODEL */
            $model = $this->getModel();
            $cid   = $this->input->get('id', [], 'array');
            if (!empty($cid)) {
                $model->checkIn($cid[0]);
            }

            $this->setRedirect(
                Route::_(
                    'index.php?option=com_jed&view=velvulnerableitem&task=velvulnerableitem.edit&id=' . (int)$vel_id,
                    false
                )
            );
        }
    }


    /**
     * getTemplate
     *
     * function for ajax getting specific template
     *
     * @since  4.0.0
     * @throws Exception
     */
    public function getTemplate(): void
    {
        //  Session::checkToken('post') or die;
        $app        = Factory::getApplication();
        $templateId = $app->getInput()->get('itemId', '', 'string');
        $db         = Factory::getContainer()->get('DatabaseDriver');

        $querySelect = $db->getQuery(true)
            ->select($db->qn(['subject', 'htmlbody', 'params']))
            ->from($db->quoteName('#__mail_templates'))
            ->where($db->qn('template_id') . ' = ' . $db->quote($templateId))
            ->where($db->qn('extension') . ' = ' . $db->quote('com_jed'));
        $db->setQuery($querySelect);
        $result = $db->loadObject();

        if ($result === null) {
            echo '||';

            return;
        }

        $params       = json_decode((string) $result->params, true) ?: [];
        $ticketStatus = $params['ticket_status'] ?? '';

        echo $result->subject . '|' . $result->htmlbody . '|' . $ticketStatus;
    }

    /**
     * Ticket Send and Store Message
     *
     * @since  4.0.0
     * @throws \PHPMailer\PHPMailer\Exception
     */
    public function sendMessage(): void
    {
        $this->task = $_POST['task'];


        if ($this->task == "ticket.sendmessage") {
            /* Functionality
            1 - Verify
            2 - Send email with message
            3 - Store message to database
            4 - Redirect back to ticket so message history reloads
            */
            // var_dump($_POST['jform']);exit();
            $subject = $_POST['jform']['message_subject'];
            $message = $_POST['jform']['message_text'];

            $id          = $_POST['jform']['id'];
            $ticket_user = JedHelper::getUserById($_POST['jform']['created_by_num']);
            JedemailHelper::sendEmail($subject, $message, $ticket_user, 'dummy@joomla.org');
            $this->storeMessage($id, $subject, $message);
            $this->setRedirect(Route::_('index.php?option=com_jed&view=ticket&layout=edit&id=' . (int)$id, false));
        }
    }

    /**
     * Add an internal note to the ticket - the JED team's own record, never shown to the
     * ticket's creator. Submitted from the "Add Note" box on the ticket edit screen.
     *
     * @return void
     *
     * @since  4.0.0
     * @throws Exception
     */
    public function storeInternalNote(): void
    {
        $this->checkToken();

        $app      = Factory::getApplication();
        $data     = $app->getInput()->post->get('jform', [], 'array');
        $ticketId = (int) ($data['id'] ?? 0);
        $summary  = trim((string) ($data['note_summary'] ?? ''));
        $note     = (string) ($data['note_text'] ?? '');

        $redirect = Route::_('index.php?option=com_jed&view=ticket&layout=edit&id=' . $ticketId, false);

        if (!$app->getIdentity()->authorise('core.manage', 'com_jed')) {
            $app->enqueueMessage(Text::_('JERROR_ALERTNOAUTHOR'), 'error');
            $this->setRedirect($redirect);

            return;
        }

        if ($ticketId <= 0 || $summary === '') {
            $app->enqueueMessage(Text::_('COM_JED_TICKET_INTERNAL_NOTE_SUMMARY_REQUIRED'), 'warning');
            $this->setRedirect($redirect);

            return;
        }

        /** @var TicketinternalnoteModel $model */
        $model = $this->getModel('Ticketinternalnote', 'Administrator');

        $saved = $model->save([
            'id'        => 0,
            'ticket_id' => $ticketId,
            'summary'   => $summary,
            'note'      => ComponentHelper::filterText($note),
            'state'     => 1,
        ]);

        if ($saved) {
            $app->enqueueMessage(Text::_('COM_JED_TICKET_INTERNAL_NOTE_SAVED'));
        } else {
            $app->enqueueMessage($model->getError() ?: Text::_('JERROR_AN_ERROR_HAS_OCCURRED'), 'error');
        }

        $this->setRedirect($redirect);
    }

    /**
     * Run JED Checker against the listing the ticket is about, for the JED Checker tab.
     *
     * A task rather than part of display because a check unpacks and reads a whole package.
     * The result goes into the session, keyed by ticket, and the ticket screen reads it back;
     * a failure goes the same way, as the message the team sees in the tab.
     *
     * @return void
     *
     * @since  4.0.0
     * @throws Exception
     */
    public function jedcheck(): void
    {
        $this->checkToken();

        $app      = Factory::getApplication();
        $ticketId = (int) ($app->getInput()->post->get('jform', [], 'array')['id'] ?? 0) ?: $app->getInput()->getInt('id', 0);
        $redirect = Route::_('index.php?option=com_jed&view=ticket&layout=edit&id=' . $ticketId, false);

        if (!$app->getIdentity()->authorise('core.manage', 'com_jed')) {
            $app->enqueueMessage(Text::_('JERROR_ALERTNOAUTHOR'), 'error');
            $this->setRedirect($redirect);

            return;
        }

        /** @var TicketModel $model */
        $model = $this->getModel('Ticket', 'Administrator');
        $item  = $model->getItem($ticketId);

        $type        = TicketType::tryFrom((int) ($item->linked_item_type ?? 0)) ?? TicketType::Other;
        $handler     = TicketTypeHandlerRegistry::createDefault($model->getDatabase())->get($type);
        $extensionId = $handler->getExtensionId((int) ($item->linked_item_id ?? 0));

        $stateKey = 'com_jed.ticket.jedcheck.' . $ticketId;

        if ($extensionId === null) {
            $app->setUserState($stateKey, ['error' => Text::_('COM_JED_JEDCHECKER_NO_EXTENSION')]);
            $this->setRedirect($redirect);

            return;
        }

        try {
            $result = (new JedCheckerService($model->getDatabase()))->check($extensionId);

            $app->setUserState($stateKey, ['result' => $result]);
            $app->enqueueMessage(
                Text::sprintf('COM_JED_JEDCHECKER_DONE', $result['totals']['errors'], $result['totals']['warnings'])
            );
        } catch (\Throwable $e) {
            $app->setUserState($stateKey, ['error' => $e->getMessage()]);
            $app->enqueueMessage($e->getMessage(), 'warning');
        }

        $this->setRedirect($redirect);
    }

    /**
     * Store Ticket Message back to database
     *
     * @param   int  $ticket_id
     * @param        $subject
     * @param        $message
     *
     * @since 4.0.0
     *
     * @throws Exception
     */
    public function storeMessage(int $ticket_id, $subject, $message)
    {
        $user                                = Factory::getApplication()->getIdentity();
        $ticket_message_model                = $this->getModel('Ticketmessage', 'Administrator');

        $ticket_message['id']                = 0;
        $ticket_message['ticket_id']         = $ticket_id;
        $ticket_message['subject']           = $subject;
        $ticket_message['message']           = $message;
        $ticket_message['message_direction'] = 0; /* 1 for coming in, 0 for going out */
        $ticket_message['created_by']        = $user->id;
        $ticket_message['created_on']        = 'now()';
        $ticket_message['modified_on']       = 'now()';

        $ticket_message_model->save($ticket_message);
    }
}
