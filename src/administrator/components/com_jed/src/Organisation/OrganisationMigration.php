<?php

/**
 * @package JED
 *
 * @subpackage Organisations
 *
 * @copyright (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Administrator\Organisation;

// No direct access.
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Throwable;

/**
 * Owners and maintainers become organisations and members (PRD-09 R14).
 *
 * For every user who owns a listing without an organisation:
 *
 * 1. their personal organisation - found again if an earlier run created it, otherwise created,
 *    with them as active administrator and primary contact;
 * 2. their listings (and those listings' revisions) are given to it;
 * 3. their listings' maintainers are converted **without widening anybody's access** (S3):
 *    - an accepted maintainer of *every* listing of that owner becomes an active member - they
 *      could already edit all of them;
 *    - an accepted maintainer of *some* of them becomes a pending membership request the
 *      administrator decides on, because membership would give them the rest as well;
 *    - an invitation never answered, for every listing, becomes an organisation invitation;
 *      for some, it is dropped - the owner only ever meant part of the catalogue.
 *
 * Nobody is mailed: a migration that writes to thousands of people is not a migration. The
 * dashboards show the requests and invitations.
 *
 * Safe to run again, and to be interrupted: it only touches listings whose `organisation_id` is
 * still NULL and never overwrites a membership row. Owners whose account no longer exists are
 * skipped; their listings stay on the legacy `owner` rule (which then grants nobody anything)
 * until the JED team transfers them.
 *
 * @since 4.1.0
 */
final class OrganisationMigration
{
    /**
     * @param DatabaseInterface        $db      The database.
     * @param OrganisationService|null $service The service (for UIDs, aliases and names).
     *
     * @since 4.1.0
     */
    public function __construct(
        private readonly DatabaseInterface $db,
        private ?OrganisationService $service = null
    ) {
        $this->service ??= new OrganisationService($db);
    }

    /**
     * @param int $limit Most owners to migrate in this call (0 = all).
     *
     * @return array{owners: int, organisations_created: int, listings: int, members: int, requests: int, invitations: int, skipped: int}
     *
     * @since 4.1.0
     */
    public function run(int $limit = 0): array
    {
        $report = ['owners' => 0, 'organisations_created' => 0, 'listings' => 0, 'members' => 0, 'requests' => 0, 'invitations' => 0, 'skipped' => 0];

        if (!$this->tablesExist()) {
            return $report;
        }

        $query = $this->db->getQuery(true)
            ->select('DISTINCT ' . $this->db->quoteName('e.owner'))
            ->from($this->db->quoteName('#__jed_extensions', 'e'))
            ->where($this->db->quoteName('e.organisation_id') . ' IS NULL')
            ->where($this->db->quoteName('e.owner') . ' > 0')
            ->order($this->db->quoteName('e.owner') . ' ASC');

        $owners = array_map('intval', $this->db->setQuery($query, 0, max(0, $limit))->loadColumn() ?: []);

        foreach ($owners as $ownerId) {
            $user = $this->db->setQuery(
                $this->db->getQuery(true)
                    ->select($this->db->quoteName(['id', 'name', 'email']))
                    ->from($this->db->quoteName('#__users'))
                    ->where($this->db->quoteName('id') . ' = :id')
                    ->bind(':id', $ownerId, ParameterType::INTEGER)
            )->loadObject();

            if ($user === null) {
                $report['skipped']++;

                continue;
            }

            $this->db->transactionStart();

            try {
                [$organisationId, $created] = $this->personalOrganisation($user);

                $report['organisations_created'] += $created ? 1 : 0;
                $report['listings']              += $this->moveListings($ownerId, $organisationId);

                foreach ($this->convertMaintainers($ownerId, $organisationId) as $key => $count) {
                    $report[$key] += $count;
                }

                $this->db->transactionCommit();
                $report['owners']++;
            } catch (Throwable $e) {
                $this->db->transactionRollback();
                $report['skipped']++;

                try {
                    \Joomla\CMS\Log\Log::add(\sprintf('com_jed: organisation migration of owner %d failed: %s', $ownerId, $e->getMessage()), \Joomla\CMS\Log\Log::WARNING, 'com_jed');
                } catch (Throwable) {
                    // Nothing more to do.
                }
            }
        }

        OrganisationAccess::clearCache();

        return $report;
    }

    /**
     * @param object $user The owner (id, name, email).
     *
     * @return array{0: int, 1: bool}  [organisation id, whether it was created now]
     *
     * @since 4.1.0
     */
    private function personalOrganisation(object $user): array
    {
        $userId   = (int) $user->id;
        $existing = $this->db->setQuery(
            $this->db->getQuery(true)
                ->select($this->db->quoteName('id'))
                ->from($this->db->quoteName('#__jed_organisations'))
                ->where($this->db->quoteName('primary_user_id') . ' = :uid')
                ->where($this->db->quoteName('is_personal') . ' = 1')
                ->bind(':uid', $userId, ParameterType::INTEGER)
                ->order($this->db->quoteName('id') . ' ASC'),
            0,
            1
        )->loadResult();

        if ($existing) {
            $this->ensureMember((int) $existing, $userId, OrganisationRole::ADMIN, MembershipState::ACTIVE);

            return [(int) $existing, false];
        }

        $title = $this->service->developerName($userId) ?: trim((string) $user->name) ?: ('Developer #' . $userId);
        $now   = Factory::getDate()->toSql();

        // Written directly rather than through OrganisationService::create(): the owner's account
        // may have had its privileges withdrawn since, and a migration must move every listing -
        // a banned developer's listings still need an organisation to belong to.
        $row = (object) [
            'uid'             => $this->service->newUid(),
            'title'           => mb_substr($title, 0, 255),
            'alias'           => $this->service->uniqueAlias($title),
            'contact_name'    => mb_substr(trim((string) $user->name), 0, 255),
            'contact_email'   => mb_substr(mb_strtolower(trim((string) $user->email)), 0, 255),
            'accept_requests' => 0,
            'is_personal'     => 1,
            'state'           => 1,
            'primary_user_id' => $userId,
            'created'         => $now,
            'created_by'      => 0,
        ];

        $this->db->insertObject('#__jed_organisations', $row, 'id');

        $this->ensureMember((int) $row->id, $userId, OrganisationRole::ADMIN, MembershipState::ACTIVE);

        return [(int) $row->id, true];
    }

    /**
     * @param int $ownerId        The owner.
     * @param int $organisationId Their organisation.
     *
     * @return int  Listings moved.
     *
     * @since 4.1.0
     */
    private function moveListings(int $ownerId, int $organisationId): int
    {
        $this->db->setQuery(
            $this->db->getQuery(true)
                ->update($this->db->quoteName('#__jed_extensions'))
                ->set($this->db->quoteName('organisation_id') . ' = :oid')
                ->where($this->db->quoteName('owner') . ' = :uid')
                ->where($this->db->quoteName('organisation_id') . ' IS NULL')
                ->bind(':oid', $organisationId, ParameterType::INTEGER)
                ->bind(':uid', $ownerId, ParameterType::INTEGER)
        )->execute();

        $moved = (int) $this->db->getAffectedRows();

        // Revisions say which organisation they were written for - for the ones written before
        // there were organisations, the one the listing was given to now.
        $this->db->setQuery(
            'UPDATE ' . $this->db->quoteName('#__jed_extensions_history', 'h')
            . ' INNER JOIN ' . $this->db->quoteName('#__jed_extensions', 'e')
            . ' ON ' . $this->db->quoteName('e.id') . ' = ' . $this->db->quoteName('h.extension_id')
            . ' SET ' . $this->db->quoteName('h.organisation_id') . ' = ' . $this->db->quoteName('e.organisation_id')
            . ' WHERE ' . $this->db->quoteName('h.organisation_id') . ' IS NULL'
            . ' AND ' . $this->db->quoteName('e.organisation_id') . ' = ' . $organisationId
        )->execute();

        return $moved;
    }

    /**
     * S3: maintainers of every listing become members, of some become requests.
     *
     * @param int $ownerId        The owner.
     * @param int $organisationId Their organisation.
     *
     * @return array{members: int, requests: int, invitations: int}
     *
     * @since 4.1.0
     */
    private function convertMaintainers(int $ownerId, int $organisationId): array
    {
        $result = ['members' => 0, 'requests' => 0, 'invitations' => 0];

        $listingIds = array_map('intval', $this->db->setQuery(
            $this->db->getQuery(true)
                ->select($this->db->quoteName('id'))
                ->from($this->db->quoteName('#__jed_extensions'))
                ->where($this->db->quoteName('organisation_id') . ' = :oid')
                ->where($this->db->quoteName('deleted') . ' = 0')
                ->bind(':oid', $organisationId, ParameterType::INTEGER)
        )->loadColumn() ?: []);

        if ($listingIds === []) {
            return $result;
        }

        $rows = $this->db->setQuery(
            $this->db->getQuery(true)
                ->select([$this->db->quoteName('user_id'), $this->db->quoteName('state'), 'COUNT(*) AS ' . $this->db->quoteName('n'), 'MIN(' . $this->db->quoteName('invited_by') . ') AS ' . $this->db->quoteName('invited_by')])
                ->from($this->db->quoteName('#__jed_extensions_maintainers'))
                ->whereIn($this->db->quoteName('extension_id'), $listingIds)
                ->where($this->db->quoteName('user_id') . ' <> :owner')
                ->whereIn($this->db->quoteName('state'), [0, 1])
                ->bind(':owner', $ownerId, ParameterType::INTEGER)
                ->group([$this->db->quoteName('user_id'), $this->db->quoteName('state')])
        )->loadObjectList() ?: [];

        $total = \count($listingIds);

        foreach ($rows as $row) {
            $userId = (int) $row->user_id;
            $all    = (int) $row->n >= $total;

            if ((int) $row->state === 1) {
                $state  = $all ? MembershipState::ACTIVE : MembershipState::REQUESTED;
                $note   = $all ? '' : \sprintf('Maintained %d of %d listings before organisations were introduced (added by the 4.1.0 migration).', (int) $row->n, $total);
                $result[$all ? 'members' : 'requests'] += $this->ensureMember($organisationId, $userId, OrganisationRole::MEMBER, $state, $note, (int) $row->invited_by) ? 1 : 0;

                continue;
            }

            if ($all) {
                $result['invitations'] += $this->ensureMember($organisationId, $userId, OrganisationRole::MEMBER, MembershipState::INVITED, '', (int) $row->invited_by) ? 1 : 0;
            }
        }

        return $result;
    }

    /**
     * Insert a membership row unless the pair already has one.
     *
     * @param int              $organisationId The organisation.
     * @param int              $userId         The user.
     * @param OrganisationRole $role           The role.
     * @param MembershipState  $state          The state.
     * @param string           $note           The request note.
     * @param int              $invitedBy      Who invited them originally.
     *
     * @return bool  Whether a row was inserted.
     *
     * @since 4.1.0
     */
    private function ensureMember(int $organisationId, int $userId, OrganisationRole $role, MembershipState $state, string $note = '', int $invitedBy = 0): bool
    {
        $exists = $this->db->setQuery(
            $this->db->getQuery(true)
                ->select('1')
                ->from($this->db->quoteName('#__jed_organisation_members'))
                ->where($this->db->quoteName('organisation_id') . ' = :oid')
                ->where($this->db->quoteName('user_id') . ' = :uid')
                ->bind(':oid', $organisationId, ParameterType::INTEGER)
                ->bind(':uid', $userId, ParameterType::INTEGER),
            0,
            1
        )->loadResult();

        if ($exists) {
            return false;
        }

        $now = Factory::getDate()->toSql();
        $row = (object) [
            'organisation_id' => $organisationId,
            'user_id'         => $userId,
            'role'            => $role->value,
            'state'           => $state->value,
            'request_message' => $note,
            'requested_time'  => $state === MembershipState::REQUESTED ? $now : null,
            'invited_by'      => $invitedBy > 0 ? $invitedBy : null,
            'invited_time'    => $state === MembershipState::INVITED ? $now : null,
            'joined_time'     => $state === MembershipState::ACTIVE ? $now : null,
        ];

        $this->db->insertObject('#__jed_organisation_members', $row);

        return true;
    }

    /**
     * Whether the 4.1.0 schema is in place (the migration is also called on fresh installs and
     * could be called before the update file ran).
     *
     * @return bool
     *
     * @since 4.1.0
     */
    private function tablesExist(): bool
    {
        try {
            $tables  = $this->db->getTableList();
            $prefix  = $this->db->getPrefix();
            $columns = $this->db->getTableColumns('#__jed_extensions', false);

            return \in_array($prefix . 'jed_organisations', $tables, true)
                && \in_array($prefix . 'jed_extensions_maintainers', $tables, true)
                && isset($columns['organisation_id']);
        } catch (Throwable) {
            return false;
        }
    }
}
