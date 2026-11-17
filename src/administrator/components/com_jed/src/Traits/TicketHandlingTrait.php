<?php

/**
 * @package JED
 *
 * @subpackage Tickets
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Administrator\Traits;

// No direct access.
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Jed\Component\Jed\Site\Helper\JedHelper;
use Jed\Component\Jed\Administrator\Enum\TicketType;
use Jed\Component\Jed\Administrator\Model\TicketmessageModel;
use Jed\Component\Jed\Administrator\Model\TicketModel;
use Jed\Component\Jed\Administrator\Table\TicketTable;
use Jed\Component\Jed\Site\Helper\TicketHelper;
use Joomla\CMS\Factory;
use Joomla\Database\DatabaseDriver;
use Joomla\Database\ParameterType;

/**
 * Creates #__jed_tickets entries for events happening on other items (an extension submitted for
 * approval, a review flagged, a VEL report filed, ...).
 *
 * @since 4.0.0
 */
trait TicketHandlingTrait
{
    /**
     * Creates a new ticket linked to the given item, records the triggering event as the ticket's
     * first (incoming) message, and sends/stores the standard confirmation reply - the same three
     * steps the deprecated `_ReportformModel::save()` used to do by hand for VEL reports, now
     * centralised so every trigger gets identical behaviour.
     *
     * @param TicketType $type  The kind of item the ticket is about.
     * @param int        $id    The linked item's primary key (e.g. #__jed_extensions.id, #__jed_reviews.id).
     * @param string     $event A short description of the triggering event; used as the ticket subject.
     *
     * @return int The new ticket's #__jed_tickets.id, or 0 on failure.
     *
     * @since 4.0.0
     */
    public function triggerTicket(TicketType $type, int $id, string $event): int
    {
        $user  = Factory::getApplication()->getIdentity();
        $model = new TicketModel();

        $data = [
            'id'                   => 0,
            'ticket_origin'        => 0,
            'ticket_category_type' => 0,
            'ticket_subject'       => $event,
            'ticket_text'          => '<p>' . htmlspecialchars($event) . '</p>',
            'linked_item_type'     => $type->value,
            'linked_item_id'       => $id,
            'ticket_status'        => 0,
            'allocated_group'      => 0,
            'allocated_to'         => 0,
            'parent_id'            => -1,
            'state'                => 1,
            'created_by'           => $user->id,
        ];

        if (!$model->save($data)) {
            return 0;
        }

        $ticketId = (int) $model->getState($model->getName() . '.id');

        $messageModel = new TicketmessageModel();
        $messageModel->save([
            'id'                => 0,
            'ticket_id'         => $ticketId,
            'subject'           => $event,
            'message'           => '<p>' . htmlspecialchars($event) . '</p>',
            'message_direction' => 1,
            'internal'          => 0,
            'created_by'        => $user->id,
        ]);

        $confirmation = JedHelper::sendMailTemplate(TicketHelper::MAIL_TEMPLATE_TICKET_CONFIRMATION, $user);

        if ($confirmation !== null) {
            $messageModel->save([
                'id'                => 0,
                'ticket_id'         => $ticketId,
                'subject'           => $confirmation->subject,
                'message'           => $confirmation->htmlbody,
                'message_direction' => 0,
                'internal'          => 0,
                'created_by'        => -1,
            ]);
        }

        return $ticketId;
    }

    /**
     * Record a decision on the most recent ticket about an item, as an outgoing message.
     *
     * The decision itself is made on the item - a listing is rejected, not a ticket - but the
     * ticket is where the developer and the next team member look for what happened. Writing it
     * there means the reason and the notes of a decision are visible in the developer's My Tickets
     * even when the decision's e-mail template has no place for free text, and the ticket's status
     * can follow the decision in the same step.
     *
     * Nothing happens when no ticket is linked to the item: a decision never fails for want of a
     * ticket to note it on.
     *
     * @param TicketType $type    The kind of item.
     * @param int        $itemId  The item's primary key.
     * @param string     $subject The message subject.
     * @param string     $html    The message body (HTML, already escaped by the caller).
     * @param int|null   $status  A new ticket status (0 New .. 5 Updated), or null to leave it.
     *
     * @return int  The ticket the message was written to, 0 when there was none.
     *
     * @since 4.0.0
     */
    public function recordOnLinkedTicket(TicketType $type, int $itemId, string $subject, string $html, ?int $status = null): int
    {
        /** @var DatabaseDriver $db */
        $db       = Factory::getContainer()->get(DatabaseDriver::class);
        $typeId   = $type->value;
        $ticketId = (int) $db->setQuery(
            $db->getQuery(true)
                ->select($db->quoteName('id'))
                ->from($db->quoteName('#__jed_tickets'))
                ->where($db->quoteName('linked_item_type') . ' = :type')
                ->where($db->quoteName('linked_item_id') . ' = :item')
                ->order($db->quoteName('id') . ' DESC')
                ->bind(':type', $typeId, ParameterType::INTEGER)
                ->bind(':item', $itemId, ParameterType::INTEGER),
            0,
            1
        )->loadResult();

        if ($ticketId === 0) {
            return 0;
        }

        (new TicketmessageModel())->save([
            'id'                => 0,
            'ticket_id'         => $ticketId,
            'subject'           => $subject,
            'message'           => $html,
            'message_direction' => 0,
            'internal'          => 0,
            'created_by'        => (int) Factory::getApplication()->getIdentity()->id,
        ]);

        if ($status !== null) {
            // Through the table, so the status change is logged like any other (TicketTable::store()).
            $table = new TicketTable($db);

            if ($table->load($ticketId) && (int) $table->ticket_status !== $status) {
                $table->ticket_status = $status;
                $table->modified_on   = Factory::getDate()->toSql();
                $table->modified_by   = (int) Factory::getApplication()->getIdentity()->id;
                $table->store();
            }
        }

        return $ticketId;
    }
}
