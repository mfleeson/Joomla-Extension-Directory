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
use Joomla\CMS\Component\ComponentHelper;
use Jed\Component\Jed\Administrator\Model\TicketinternalnoteModel;
use Jed\Component\Jed\Administrator\Model\TicketModel;
use Jed\Component\Jed\Administrator\Ticket\TicketTypeHandlerRegistry;
use Joomla\CMS\Factory;
use Joomla\CMS\Filter\InputFilter;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\FormController;
use Joomla\CMS\Router\Route;
use Jed\Component\Jed\Administrator\Table\TicketTable;
use Joomla\Database\DatabaseDriver;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

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
     * getTemplate
     *
     * function for ajax getting specific template
     *
     * @since  4.0.0
     * @throws Exception
     */
    public function getTemplate(): void
    {
        $app = Factory::getApplication();

        // com_jed, not com_tickets: the ticket system moved into com_jed and com_tickets no longer
        // exists, so this asked about an asset nobody can configure. The same right as internal
        // notes and the JED Checker tab - the JED team's.
        if (!$app->getIdentity()->authorise('core.manage', 'com_jed')) {
            echo '||';
            $app->close();
        }
        $templateId = $app->getInput()->get('itemId', '', 'string');
        $db         = Factory::getContainer()->get('DatabaseDriver');

        $querySelect = $db->getQuery(true)
            ->select($db->qn(['subject', 'htmlbody', 'params']))
            ->from($db->quoteName('#__mail_templates'))
            ->where($db->qn('template_id').' = '.$db->quote($templateId))
            ->where($db->qn('extension').' = '.$db->quote('com_jed'));
        $db->setQuery($querySelect);
        $result = $db->loadObject();

        if ($result === null) {
            echo '||';

            return;
        }

        $params       = json_decode((string)$result->params, true) ?: [];
        $ticketStatus = $params['ticket_status'] ?? '';

        // Subject and body are language keys (script.php seeds them that way, and a team member
        // may have overridden one with literal text in Mail Templates - Text::_() returns an
        // unknown key unchanged, so both cases come out as text).
        echo Text::_($result->subject) . '|' . Text::_($result->htmlbody) . '|' . $ticketStatus;
    }

    /**
     * Ticket Send and Store Message
     *
     * @since  4.0.0
     * @throws \PHPMailer\PHPMailer\Exception
     */
    public function sendMessage(): void
    {
        $this->checkToken();

        $app  = $this->app;
        $user = $app->getIdentity();

        $jform = $this->input->post->get('jform', [], 'array');
        $id    = (int) ($jform['id'] ?? 0);

        // Back to the ticket that was being worked - com_jed's own ticket screen. This used to
        // point at option=com_tickets, the component the ticket system lived in before it moved
        // into com_jed, so every reply ended on "component not found" although it had been sent.
        $ticketUrl = Route::_('index.php?option=com_jed&view=ticket&layout=edit&id=' . $id, false);

        if (!$user->authorise('core.manage', 'com_jed')) {
            throw new Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $subject = trim((string) InputFilter::getInstance()->clean($jform['message_subject'] ?? '', 'string'));
        $message = ComponentHelper::filterText((string) ($jform['message_text'] ?? ''));

        // The recipient is whoever opened the ticket, never a value posted with the form.
        $db        = Factory::getContainer()->get(DatabaseInterface::class);
        $createdBy = $id > 0 ? (int) $db->setQuery(
            $db->getQuery(true)
                ->select($db->quoteName('created_by'))
                ->from($db->quoteName('#__jed_tickets'))
                ->where($db->quoteName('id') . ' = :id')
                ->bind(':id', $id, ParameterType::INTEGER)
        )->loadResult() : 0;

        if ($id <= 0 || $createdBy <= 0) {
            throw new Exception(Text::_('JERROR_AN_ERROR_HAS_OCCURRED'), 400);
        }

        // An empty subject or body is a slip, not a fault: say so on the ticket rather than
        // answering with an error page.
        if ($subject === '' || trim(strip_tags($message)) === '') {
            $app->enqueueMessage(Text::_('COM_JED_TICKET_MESSAGE_EMPTY'), 'warning');
            $this->setRedirect($ticketUrl);

            return;
        }

        $mailError = JedemailHelper::sendEmail($subject, $message, JedHelper::getUserById($createdBy), $user->name);
        $this->storeMessage($id, $subject, $message);

        // A message template can set the ticket status (its `ticket_status` param fills the
        // Status drop-down). Sending is when that decision is made, so the status goes with the
        // message instead of being lost unless the ticket was saved as well.
        $statusChanged = $this->applyStatusFromForm($id, $jform['ticket_status'] ?? null);

        if ($mailError !== '') {
            $app->enqueueMessage(Text::sprintf('COM_JED_TICKET_MESSAGE_STORED_MAIL_FAILED', $mailError), 'warning');
        } else {
            $app->enqueueMessage(Text::_('COM_JED_TICKET_MESSAGE_SENT'));
        }

        if ($statusChanged !== null) {
            $app->enqueueMessage(Text::sprintf('COM_JED_TICKET_STATUS_SET', Text::_('COM_JED_TICKETS_TICKET_STATUS_OPTION_' . $statusChanged)));
        }

        $this->setRedirect($ticketUrl);
    }

    /**
     * Set the ticket's status from the posted form, when it differs from the stored one.
     *
     * Through the table, so a move to or from Resolved/Closed is written to the action log like
     * any other (TicketTable::store()).
     *
     * @param int   $ticketId The ticket.
     * @param mixed $posted   The posted `ticket_status`, or null when the form had none.
     *
     * @return int|null  The new status when it was changed, null otherwise.
     *
     * @since 4.0.0
     */
    private function applyStatusFromForm(int $ticketId, mixed $posted): ?int
    {
        if ($posted === null || $posted === '' || !is_numeric($posted)) {
            return null;
        }

        $status = (int) $posted;

        // 0 New, 1 Awaiting User, 2 Awaiting JED, 3 Resolved, 4 Closed, 5 Updated.
        if ($status < 0 || $status > 5) {
            return null;
        }

        $table = new TicketTable(Factory::getContainer()->get(DatabaseDriver::class));

        if (!$table->load($ticketId) || (int) $table->ticket_status === $status) {
            return null;
        }

        $table->ticket_status = $status;
        $table->modified_on   = Factory::getDate()->toSql();
        $table->modified_by   = (int) $this->app->getIdentity()->id;

        return $table->store() ? $status : null;
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
        $handler     = TicketTypeHandlerRegistry::createDefault(Factory::getContainer()->get(DatabaseInterface::class))->get($type);
        $extensionId = $handler->getExtensionId((int) ($item->linked_item_id ?? 0));

        $stateKey = 'com_jed.ticket.jedcheck.' . $ticketId;

        if ($extensionId === null) {
            $app->setUserState($stateKey, ['error' => Text::_('COM_JED_JEDCHECKER_NO_EXTENSION')]);
            $this->setRedirect($redirect);

            return;
        }

        try {
            $result = (new JedCheckerService(Factory::getContainer()->get(DatabaseInterface::class)))->check($extensionId);

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
