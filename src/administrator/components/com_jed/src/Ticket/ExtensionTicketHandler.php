<?php

/**
 * @package JED
 *
 * @subpackage Tickets
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Administrator\Ticket;

// No direct access.
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Jed\Component\Jed\Administrator\Model\ExtensionModel;
use Jed\Component\Jed\Administrator\Enum\TicketType;
use Jed\Component\Jed\Administrator\Listing\BlockReasons;
use Joomla\CMS\User\User;
use Joomla\Database\DatabaseInterface;

/**
 * Handler for `TicketType::Extension` tickets - a new or edited extension pending
 * review. Master data is the same live-vs-pending-history compare
 * {@see \Jed\Component\Jed\Administrator\Model\ExtensionModel::getCompareItems()}
 * already computes for the standalone compare layout.
 *
 * @since 4.0.0
 */
final class ExtensionTicketHandler implements TicketTypeHandlerInterface
{
    /**
     * @param DatabaseInterface $db The database connector object.
     *
     * @since 4.0.0
     */
    public function __construct(private readonly DatabaseInterface $db)
    {
    }

    /**
     * @inheritDoc
     *
     * @since 4.0.0
     */
    public static function type(): TicketType
    {
        return TicketType::Extension;
    }

    /**
     * @inheritDoc
     *
     * @since 4.0.0
     */
    public function getMasterData(int $linkedItemId): ?object
    {
        // "Pending" means a revision the developer submitted that nobody has decided yet
        // (getPendingHistoryId()), not "the newest revision differs from the live row": blocks,
        // unblocks, deletions and restores also write revisions, and after one of those the panel
        // used to announce a pending change - and link to that state change's snapshot.
        $model            = new ExtensionModel();
        $live             = $model->getLiveItem($linkedItemId);
        $pendingHistoryId = $model->getPendingHistoryId($linkedItemId);
        $pending          = $pendingHistoryId > 0 ? $model->getItem($linkedItemId, $pendingHistoryId) : null;

        if (!$live && !$pending) {
            return null;
        }

        return (object) [
            'extensionId'      => $linkedItemId,
            'live'             => $live,
            'pending'          => $pending ?: null,
            'pendingHistoryId' => $pendingHistoryId,
            'isPending'        => $pendingHistoryId > 0,
        ];
    }

    /**
     * @inheritDoc
     *
     * @since 4.0.0
     */
    public function getMasterDataLayout(): string
    {
        return 'ticket.masterdata_extension';
    }

    /**
     * @inheritDoc
     *
     * @since 4.0.0
     */
    public function getExtensionId(int $linkedItemId): ?int
    {
        return $linkedItemId > 0 ? $linkedItemId : null;
    }

    /**
     * @inheritDoc
     *
     * @since 4.0.0
     */
    public function getActions(int $linkedItemId, User $user): array
    {
        if (!$user->authorise('jed.approve', 'com_jed')) {
            return [];
        }

        // Not getCompareItems(), which resolves to the newest revision of any kind. Since P1-01
        // a block or a soft delete also writes a revision, so the newest one is regularly a state
        // change - and offering "Approve" on it would promote a stale snapshot over the live row.
        // getPendingHistoryId() answers the question actually being asked: is there something a
        // developer submitted that nobody has decided yet?
        $model            = new ExtensionModel();
        $pendingHistoryId = $model->getPendingHistoryId($linkedItemId);

        if (!$pendingHistoryId) {
            return [];
        }

        return [
            new TicketAction(
                label: 'COM_JED_TICKET_ACTION_APPROVE',
                task: 'extension.approve',
                icon: 'publish',
                hiddenFields: ['extension_id' => $linkedItemId, 'history_id' => $pendingHistoryId],
                option: 'com_jed'
            ),
            // A rejection is only a decision the developer can act on when it says what to
            // correct, so the reason code is required (ExtensionModel::reject() refuses one
            // without) and the notes are offered alongside. Both travel with the action's own
            // form, under the names ExtensionController::reject() reads.
            new TicketAction(
                label: 'COM_JED_TICKET_ACTION_REJECT',
                task: 'extension.reject',
                icon: 'cancel-circle',
                confirmMessage: 'COM_JED_TICKET_ACTION_REJECT_CONFIRM',
                hiddenFields: ['extension_id' => $linkedItemId, 'history_id' => $pendingHistoryId],
                option: 'com_jed',
                fields: [
                    [
                        'name'     => 'reject_reason_code',
                        'type'     => 'select',
                        'label'    => 'COM_JED_REJECT_REASON_LABEL',
                        'options'  => BlockReasons::options($this->db),
                        'required' => true,
                    ],
                    [
                        'name'  => 'reject_reason_notes',
                        'type'  => 'textarea',
                        'label' => 'COM_JED_REJECT_NOTES_LABEL',
                        'hint'  => 'COM_JED_REJECT_NOTES_DESC',
                    ],
                ]
            ),
        ];
    }
}
