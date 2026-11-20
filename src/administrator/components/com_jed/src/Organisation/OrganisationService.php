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

use Jed\Component\Jed\Administrator\Access\JedAccessHelper;
use Jed\Component\Jed\Administrator\Access\Privilege;
use Jed\Component\Jed\Administrator\Log\JedActionLog;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Filter\OutputFilter;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Mail\MailTemplate;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\User\User;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use RuntimeException;
use Throwable;

/**
 * Organisations, their members, and who owns a listing (PRD-09).
 *
 * This class is the only writer of `#__jed_organisations`, `#__jed_organisation_members`,
 * `#__jed_extensions.organisation_id` (outside TransferService) and the `owner` mirror. Every
 * public method that changes something takes the acting user's id and decides *itself* whether
 * that user may do it - from the database, never from the request - so a controller that forgets
 * a check cannot open a hole.
 *
 * "Staff" below means a user with `core.edit` on com_jed: the JED team, acting from the backend.
 * Staff pass every role check and may add members without an invitation; they do not pass the
 * structural rules (the primary contact is an active administrator, an organisation with listings
 * keeps an administrator), which protect the data rather than anybody's privileges.
 *
 * @since 4.1.0
 */
class OrganisationService
{
    /**
     * Most membership requests one user may have waiting at a time (PRD-09 S6).
     *
     * @since 4.1.0
     */
    public const MAX_OPEN_REQUESTS = 3;

    /**
     * @param DatabaseInterface $db The database driver.
     *
     * @since 4.1.0
     */
    public function __construct(private readonly DatabaseInterface $db)
    {
    }

    // -------------------------------------------------------------------------------------------
    // Reading
    // -------------------------------------------------------------------------------------------

    /**
     * @param int $organisationId The organisation.
     *
     * @return object|null
     *
     * @since 4.1.0
     */
    public function find(int $organisationId): ?object
    {
        if ($organisationId <= 0) {
            return null;
        }

        return $this->db->setQuery(
            $this->db->getQuery(true)
                ->select('*')
                ->from($this->db->quoteName('#__jed_organisations'))
                ->where($this->db->quoteName('id') . ' = :id')
                ->bind(':id', $organisationId, ParameterType::INTEGER)
        )->loadObject() ?: null;
    }

    /**
     * @param string $uid The public code, in any spelling OrganisationUid::normalise() accepts.
     *
     * @return object|null
     *
     * @since 4.1.0
     */
    public function findByUid(string $uid): ?object
    {
        $uid = OrganisationUid::normalise($uid);

        if ($uid === '') {
            return null;
        }

        return $this->db->setQuery(
            $this->db->getQuery(true)
                ->select('*')
                ->from($this->db->quoteName('#__jed_organisations'))
                ->where($this->db->quoteName('uid') . ' = :uid')
                ->bind(':uid', $uid)
        )->loadObject() ?: null;
    }

    /**
     * The membership row of a user in an organisation, whatever its state.
     *
     * @param int $organisationId The organisation.
     * @param int $userId         The user.
     *
     * @return object|null
     *
     * @since 4.1.0
     */
    public function membership(int $organisationId, int $userId): ?object
    {
        return $this->db->setQuery(
            $this->db->getQuery(true)
                ->select('*')
                ->from($this->db->quoteName('#__jed_organisation_members'))
                ->where($this->db->quoteName('organisation_id') . ' = :oid')
                ->where($this->db->quoteName('user_id') . ' = :uid')
                ->bind(':oid', $organisationId, ParameterType::INTEGER)
                ->bind(':uid', $userId, ParameterType::INTEGER)
        )->loadObject() ?: null;
    }

    /**
     * Members of an organisation with their account names, administrators first.
     *
     * The address is included because the callers are the organisation's own screens and the
     * backend; templates decide what to show to whom.
     *
     * @param int                    $organisationId The organisation.
     * @param MembershipState[]|null $states         Only these states; all when null.
     *
     * @return object[]
     *
     * @since 4.1.0
     */
    public function members(int $organisationId, ?array $states = null): array
    {
        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName(['m.organisation_id', 'm.user_id', 'm.role', 'm.state', 'm.request_message', 'm.requested_time', 'm.invited_time', 'm.joined_time', 'm.decided_time']))
            ->select($this->db->quoteName(['u.name', 'u.username', 'u.email']))
            ->from($this->db->quoteName('#__jed_organisation_members', 'm'))
            ->leftJoin($this->db->quoteName('#__users', 'u'), $this->db->quoteName('u.id') . ' = ' . $this->db->quoteName('m.user_id'))
            ->where($this->db->quoteName('m.organisation_id') . ' = :oid')
            ->bind(':oid', $organisationId, ParameterType::INTEGER)
            ->order($this->db->quoteName('m.role') . ' ASC')
            ->order($this->db->quoteName('u.name') . ' ASC');

        if ($states !== null) {
            $query->whereIn($this->db->quoteName('m.state'), array_map(static fn (MembershipState $s): int => $s->value, $states));
        }

        return $this->db->setQuery($query)->loadObjectList() ?: [];
    }

    /**
     * The organisations a user has any membership row in, with their role and state.
     *
     * @param int                    $userId The user.
     * @param MembershipState[]|null $states Only these states; all when null.
     *
     * @return object[]
     *
     * @since 4.1.0
     */
    public function organisationsOf(int $userId, ?array $states = null): array
    {
        if ($userId <= 0) {
            return [];
        }

        $query = $this->db->getQuery(true)
            ->select('o.*')
            ->select($this->db->quoteName(['m.role', 'm.state'], ['member_role', 'member_state']))
            ->select($this->db->quoteName(['m.requested_time', 'm.invited_time', 'm.joined_time']))
            ->select($this->db->quoteName('inviter.name', 'invited_by_name'))
            ->select(
                '(SELECT COUNT(*) FROM ' . $this->db->quoteName('#__jed_extensions', 'e')
                . ' WHERE ' . $this->db->quoteName('e.organisation_id') . ' = ' . $this->db->quoteName('o.id')
                . ' AND ' . $this->db->quoteName('e.deleted') . ' = 0) AS ' . $this->db->quoteName('listing_count')
            )
            ->from($this->db->quoteName('#__jed_organisation_members', 'm'))
            ->innerJoin($this->db->quoteName('#__jed_organisations', 'o'), $this->db->quoteName('o.id') . ' = ' . $this->db->quoteName('m.organisation_id'))
            ->leftJoin($this->db->quoteName('#__users', 'inviter'), $this->db->quoteName('inviter.id') . ' = ' . $this->db->quoteName('m.invited_by'))
            ->where($this->db->quoteName('m.user_id') . ' = :uid')
            ->bind(':uid', $userId, ParameterType::INTEGER)
            ->order($this->db->quoteName('o.title') . ' ASC');

        if ($states !== null) {
            $query->whereIn($this->db->quoteName('m.state'), array_map(static fn (MembershipState $s): int => $s->value, $states));
        }

        return $this->db->setQuery($query)->loadObjectList() ?: [];
    }

    /**
     * Open membership requests in every organisation the user administers.
     *
     * @param int $userId The administrator.
     *
     * @return object[]
     *
     * @since 4.1.0
     */
    public function requestsAwaiting(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        $active    = MembershipState::ACTIVE->value;
        $requested = MembershipState::REQUESTED->value;
        $admin     = OrganisationRole::ADMIN->value;

        return $this->db->setQuery(
            $this->db->getQuery(true)
                ->select($this->db->quoteName(['r.organisation_id', 'r.user_id', 'r.request_message', 'r.requested_time']))
                ->select($this->db->quoteName(['o.title', 'u.name', 'u.username'], ['organisation_title', 'requester_name', 'requester_username']))
                ->from($this->db->quoteName('#__jed_organisation_members', 'r'))
                ->innerJoin(
                    $this->db->quoteName('#__jed_organisation_members', 'me'),
                    $this->db->quoteName('me.organisation_id') . ' = ' . $this->db->quoteName('r.organisation_id')
                    . ' AND ' . $this->db->quoteName('me.user_id') . ' = :uid'
                    . ' AND ' . $this->db->quoteName('me.state') . ' = :active'
                    . ' AND ' . $this->db->quoteName('me.role') . ' = :admin'
                )
                ->innerJoin($this->db->quoteName('#__jed_organisations', 'o'), $this->db->quoteName('o.id') . ' = ' . $this->db->quoteName('r.organisation_id'))
                ->leftJoin($this->db->quoteName('#__users', 'u'), $this->db->quoteName('u.id') . ' = ' . $this->db->quoteName('r.user_id'))
                ->where($this->db->quoteName('r.state') . ' = :requested')
                ->where($this->db->quoteName('o.state') . ' = 1')
                ->bind(':uid', $userId, ParameterType::INTEGER)
                ->bind(':active', $active, ParameterType::INTEGER)
                ->bind(':admin', $admin)
                ->bind(':requested', $requested, ParameterType::INTEGER)
                ->order($this->db->quoteName('r.requested_time') . ' ASC')
        )->loadObjectList() ?: [];
    }

    /**
     * Organisations that currently take membership requests, for the "request to join" lists.
     *
     * Titles and codes only: both are public anyway (an organisation's title is shown on its
     * listings), and nothing else is needed to choose.
     *
     * @param int $limit Most rows.
     *
     * @return object[]  id, uid, title.
     *
     * @since 4.1.0
     */
    public function acceptingRequests(int $limit = 1000): array
    {
        return $this->db->setQuery(
            $this->db->getQuery(true)
                ->select($this->db->quoteName(['id', 'uid', 'title']))
                ->from($this->db->quoteName('#__jed_organisations'))
                ->where($this->db->quoteName('state') . ' = 1')
                ->where($this->db->quoteName('accept_requests') . ' = 1')
                ->where($this->db->quoteName('is_personal') . ' = 0')
                ->order($this->db->quoteName('title') . ' ASC'),
            0,
            max(1, $limit)
        )->loadObjectList() ?: [];
    }

    /**
     * The organisation that owns a listing.
     *
     * @param int $extensionId The listing.
     *
     * @return int|null
     *
     * @since 4.1.0
     */
    public function organisationIdOfExtension(int $extensionId): ?int
    {
        $id = $this->db->setQuery(
            $this->db->getQuery(true)
                ->select($this->db->quoteName('organisation_id'))
                ->from($this->db->quoteName('#__jed_extensions'))
                ->where($this->db->quoteName('id') . ' = :eid')
                ->bind(':eid', $extensionId, ParameterType::INTEGER)
        )->loadResult();

        return $id === null ? null : (int) $id;
    }

    /**
     * Everybody who speaks for a listing: the active members of the organisation that owns it,
     * administrators first - and, for a listing the migration has not reached, its `owner`.
     *
     * This replaces "owner plus accepted maintainers" for every mail about a listing: the
     * abandonware owner contact, the VEL vendor notification, the link-check notice (R17).
     *
     * @param int $extensionId The listing.
     *
     * @return int[]
     *
     * @since 4.1.0
     */
    public function extensionContactUserIds(int $extensionId): array
    {
        $row = $this->db->setQuery(
            $this->db->getQuery(true)
                ->select($this->db->quoteName(['organisation_id', 'owner']))
                ->from($this->db->quoteName('#__jed_extensions'))
                ->where($this->db->quoteName('id') . ' = :eid')
                ->bind(':eid', $extensionId, ParameterType::INTEGER)
        )->loadObject();

        if ($row === null) {
            return [];
        }

        if ($row->organisation_id === null) {
            return (int) $row->owner > 0 ? [(int) $row->owner] : [];
        }

        $ids = array_map(
            static fn (object $m): int => (int) $m->user_id,
            $this->members((int) $row->organisation_id, [MembershipState::ACTIVE])
        );

        return array_values(array_unique(array_filter($ids, static fn (int $id): bool => $id > 0)));
    }

    /**
     * Active administrators of an organisation.
     *
     * @param int $organisationId The organisation.
     *
     * @return int[]
     *
     * @since 4.1.0
     */
    public function adminIds(int $organisationId): array
    {
        $active = MembershipState::ACTIVE->value;
        $admin  = OrganisationRole::ADMIN->value;

        return array_map('intval', $this->db->setQuery(
            $this->db->getQuery(true)
                ->select($this->db->quoteName('user_id'))
                ->from($this->db->quoteName('#__jed_organisation_members'))
                ->where($this->db->quoteName('organisation_id') . ' = :oid')
                ->where($this->db->quoteName('state') . ' = :active')
                ->where($this->db->quoteName('role') . ' = :admin')
                ->bind(':oid', $organisationId, ParameterType::INTEGER)
                ->bind(':active', $active, ParameterType::INTEGER)
                ->bind(':admin', $admin)
        )->loadColumn() ?: []);
    }

    /**
     * How many listings an organisation owns.
     *
     * @param int  $organisationId The organisation.
     * @param bool $withDeleted    Count soft-deleted listings too (they still reference it).
     *
     * @return int
     *
     * @since 4.1.0
     */
    public function listingCount(int $organisationId, bool $withDeleted = false): int
    {
        $query = $this->db->getQuery(true)
            ->select('COUNT(*)')
            ->from($this->db->quoteName('#__jed_extensions'))
            ->where($this->db->quoteName('organisation_id') . ' = :oid')
            ->bind(':oid', $organisationId, ParameterType::INTEGER);

        if (!$withDeleted) {
            $query->where($this->db->quoteName('deleted') . ' = 0');
        }

        return (int) $this->db->setQuery($query)->loadResult();
    }

    // -------------------------------------------------------------------------------------------
    // The organisation itself
    // -------------------------------------------------------------------------------------------

    /**
     * Create an organisation run by its creator: active administrator and primary contact.
     *
     * @param array    $data      title, contact_* , website, address, country, description,
     *                            accept_requests.
     * @param int      $creatorId Who runs it: first administrator and primary contact.
     * @param bool     $personal  A single developer's organisation.
     * @param int|null $actorId   Who creates it, when that is somebody else - the JED team, from
     *                            the backend. Only staff may create on somebody's behalf.
     *
     * @return int  The new organisation id.
     *
     * @throws RuntimeException  When the data is not acceptable or the creator may not.
     *
     * @since 4.1.0
     */
    public function create(array $data, int $creatorId, bool $personal = false, ?int $actorId = null): int
    {
        $this->loadLanguage();

        $actorId ??= $creatorId;

        if ($creatorId <= 0 || ($actorId !== $creatorId && !$this->isStaff($actorId))) {
            throw new RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'));
        }

        if ((int) $this->user($creatorId)->id !== $creatorId) {
            throw new RuntimeException(Text::_('COM_JED_ORGANISATION_ERROR_NO_ACCOUNT'));
        }

        // The right to list software is the right to found something that owns listings. A banned
        // account must not be able to set up a new home for its listings (P1-05).
        JedAccessHelper::assertMay($creatorId, Privilege::CREATE_LISTING);

        $clean = $this->cleanDetails($data, true);
        $now   = Factory::getDate()->toSql();

        $row = (object) array_merge($clean, [
            'uid'             => $this->newUid(),
            'alias'           => $this->uniqueAlias($clean['title']),
            'is_personal'     => $personal ? 1 : 0,
            'state'           => 1,
            'primary_user_id' => $creatorId,
            'created'         => $now,
            'created_by'      => $actorId,
        ]);

        if (!\array_key_exists('accept_requests', $clean)) {
            // A personal organisation is one person; a company takes requests until it says not to.
            $row->accept_requests = $personal ? 0 : 1;
        }

        $this->db->transactionStart();

        try {
            $this->db->insertObject('#__jed_organisations', $row, 'id');

            $member = (object) [
                'organisation_id' => (int) $row->id,
                'user_id'         => $creatorId,
                'role'            => OrganisationRole::ADMIN->value,
                'state'           => MembershipState::ACTIVE->value,
                'joined_time'     => $now,
                'decided_by'      => $actorId,
                'decided_time'    => $now,
            ];

            $this->db->insertObject('#__jed_organisation_members', $member);
            $this->db->transactionCommit();
        } catch (Throwable $e) {
            $this->db->transactionRollback();

            throw $e;
        }

        OrganisationAccess::clearCache();

        $this->log(JedActionLog::ORGANISATION_CREATE, (int) $row->id, (string) $row->title);

        return (int) $row->id;
    }

    /**
     * Change an organisation's details. Administrators and staff.
     *
     * @param int   $organisationId The organisation.
     * @param array $data           The new details (title and contact_email required).
     * @param int   $actorId        Who.
     *
     * @return void
     *
     * @since 4.1.0
     */
    public function update(int $organisationId, array $data, int $actorId): void
    {
        $this->loadLanguage();

        $organisation = $this->requireOrganisation($organisationId, $actorId);
        $this->assertAdmin($organisation, $actorId);

        $clean                 = $this->cleanDetails($data, false);
        $clean['id']           = $organisationId;
        $clean['modified']     = Factory::getDate()->toSql();
        $clean['modified_by']  = $actorId;

        $update = (object) $clean;

        $this->db->updateObject('#__jed_organisations', $update, 'id');
    }

    /**
     * Switch membership requests on or off (R8). Open requests stay decidable.
     *
     * @param int  $organisationId The organisation.
     * @param bool $accept         Whether requests are taken.
     * @param int  $actorId        Who.
     *
     * @return void
     *
     * @since 4.1.0
     */
    public function setAcceptRequests(int $organisationId, bool $accept, int $actorId): void
    {
        $this->loadLanguage();

        $organisation = $this->requireOrganisation($organisationId, $actorId);
        $this->assertAdmin($organisation, $actorId);

        $update = (object) [
            'id'              => $organisationId,
            'accept_requests' => $accept ? 1 : 0,
            'modified'        => Factory::getDate()->toSql(),
            'modified_by'     => $actorId,
        ];

        $this->db->updateObject('#__jed_organisations', $update, 'id');
    }

    /**
     * The organisation a user submits under when they have none: theirs if they run exactly one,
     * a new personal one otherwise (R12).
     *
     * @param int  $userId The user.
     * @param bool $create Create one when there is none.
     *
     * @return int|null
     *
     * @since 4.1.0
     */
    public function personalOrganisationFor(int $userId, bool $create = true): ?int
    {
        if ($userId <= 0) {
            return null;
        }

        $primary = $this->db->setQuery(
            $this->db->getQuery(true)
                ->select($this->db->quoteName('id'))
                ->from($this->db->quoteName('#__jed_organisations'))
                ->where($this->db->quoteName('primary_user_id') . ' = :uid')
                ->where($this->db->quoteName('state') . ' = 1')
                ->bind(':uid', $userId, ParameterType::INTEGER)
                ->order($this->db->quoteName('is_personal') . ' DESC')
                ->order($this->db->quoteName('id') . ' ASC')
        )->loadColumn() ?: [];

        if ($primary !== []) {
            return (int) $primary[0];
        }

        if (!$create) {
            return null;
        }

        $user = $this->user($userId);

        return $this->create([
            'title'         => $this->developerName($userId) ?: (string) $user->name,
            'contact_name'  => (string) $user->name,
            'contact_email' => (string) $user->email,
        ], $userId, true);
    }

    // -------------------------------------------------------------------------------------------
    // Membership
    // -------------------------------------------------------------------------------------------

    /**
     * A user asks to join (R6, R7). The administrators are told.
     *
     * If the organisation had already invited the user, asking is taken as accepting.
     *
     * @param int    $organisationId The organisation.
     * @param int    $userId         The user asking.
     * @param string $message        A note to the administrators.
     *
     * @return MembershipState  The state the row ended up in.
     *
     * @since 4.1.0
     */
    public function request(int $organisationId, int $userId, string $message = ''): MembershipState
    {
        $this->loadLanguage();

        if ($userId <= 0) {
            throw new RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'));
        }

        $organisation = $this->find($organisationId);

        if ($organisation === null || (int) $organisation->state !== 1) {
            throw new RuntimeException(Text::_('COM_JED_ORGANISATION_ERROR_NOT_FOUND'));
        }

        JedAccessHelper::assertMay($userId, Privilege::EDIT_LISTING);

        $existing = $this->membership($organisationId, $userId);
        $state    = $existing !== null ? MembershipState::tryFrom((int) $existing->state) : null;

        if ($state === MembershipState::ACTIVE) {
            throw new RuntimeException(Text::_('COM_JED_ORGANISATION_ERROR_ALREADY_MEMBER'));
        }

        if ($state === MembershipState::INVITED) {
            $this->respondToInvitation($organisationId, $userId, true);

            return MembershipState::ACTIVE;
        }

        if ($state === MembershipState::REQUESTED) {
            throw new RuntimeException(Text::_('COM_JED_ORGANISATION_ERROR_ALREADY_REQUESTED'));
        }

        if ((int) $organisation->accept_requests !== 1) {
            throw new RuntimeException(Text::_('COM_JED_ORGANISATION_ERROR_NOT_ACCEPTING'));
        }

        if ($this->countOpenRequests($userId) >= self::MAX_OPEN_REQUESTS) {
            throw new RuntimeException(Text::sprintf('COM_JED_ORGANISATION_ERROR_TOO_MANY_REQUESTS', self::MAX_OPEN_REQUESTS));
        }

        $now = Factory::getDate()->toSql();
        $row = (object) [
            'organisation_id' => $organisationId,
            'user_id'         => $userId,
            'role'            => OrganisationRole::MEMBER->value,
            'state'           => MembershipState::REQUESTED->value,
            'request_message' => mb_substr(trim(strip_tags($message)), 0, 1000),
            'requested_time'  => $now,
            'invited_by'      => null,
            'invited_time'    => null,
            'decided_by'      => null,
            'decided_time'    => null,
        ];

        $this->upsertMembership($row, $existing !== null);

        $requester = $this->user($userId);

        foreach ($this->adminIds($organisationId) as $adminId) {
            $this->notify($this->user($adminId), 'com_jed.organisation_request', [
                'ORGANISATION' => (string) $organisation->title,
                'REQUESTER'    => (string) $requester->name,
                'MESSAGE'      => (string) $row->request_message,
                'LINK'         => $this->link('index.php?option=com_jed&view=organisation&id=' . $organisationId),
            ]);
        }

        return MembershipState::REQUESTED;
    }

    /**
     * The user withdraws their own open request.
     *
     * @param int $organisationId The organisation.
     * @param int $userId         The user.
     *
     * @return void
     *
     * @since 4.1.0
     */
    public function cancelRequest(int $organisationId, int $userId): void
    {
        $requested = MembershipState::REQUESTED->value;

        $this->db->setQuery(
            $this->db->getQuery(true)
                ->delete($this->db->quoteName('#__jed_organisation_members'))
                ->where($this->db->quoteName('organisation_id') . ' = :oid')
                ->where($this->db->quoteName('user_id') . ' = :uid')
                ->where($this->db->quoteName('state') . ' = :requested')
                ->bind(':oid', $organisationId, ParameterType::INTEGER)
                ->bind(':uid', $userId, ParameterType::INTEGER)
                ->bind(':requested', $requested, ParameterType::INTEGER)
        )->execute();
    }

    /**
     * An administrator approves or rejects a request. The requester is told.
     *
     * @param int  $organisationId The organisation.
     * @param int  $userId         The requester.
     * @param bool $approve        Approve, or reject.
     * @param int  $actorId        The administrator (or staff).
     *
     * @return void
     *
     * @since 4.1.0
     */
    public function decideRequest(int $organisationId, int $userId, bool $approve, int $actorId): void
    {
        $this->loadLanguage();

        $organisation = $this->requireOrganisation($organisationId, $actorId);
        $this->assertAdmin($organisation, $actorId);

        $row = $this->membership($organisationId, $userId);

        if ($row === null || (int) $row->state !== MembershipState::REQUESTED->value) {
            throw new RuntimeException(Text::_('COM_JED_ORGANISATION_ERROR_NO_REQUEST'));
        }

        if ($approve) {
            // Checked at the moment of joining, not only when asking: a ban imposed while the
            // request waited applies.
            JedAccessHelper::assertMay($userId, Privilege::EDIT_LISTING);
        }

        $now = Factory::getDate()->toSql();

        $update = (object) [
            'organisation_id' => $organisationId,
            'user_id'         => $userId,
            'state'           => ($approve ? MembershipState::ACTIVE : MembershipState::REJECTED)->value,
            'decided_by'      => $actorId,
            'decided_time'    => $now,
            'joined_time'     => $approve ? $now : null,
        ];

        $this->db->updateObject('#__jed_organisation_members', $update, ['organisation_id', 'user_id'], true);

        OrganisationAccess::clearCache();

        $this->notify($this->user($userId), $approve ? 'com_jed.organisation_request_approved' : 'com_jed.organisation_request_rejected', [
            'ORGANISATION' => (string) $organisation->title,
            'LINK'         => $this->link('index.php?option=com_jed&view=organisations'),
        ]);

        $this->log(
            $approve ? JedActionLog::ORGANISATION_REQUEST_APPROVE : JedActionLog::ORGANISATION_REQUEST_REJECT,
            $organisationId,
            (string) $organisation->title,
            $userId
        );
    }

    /**
     * An administrator brings an existing user in (R5).
     *
     * By default this is an invitation the user accepts (PRD-09 S4); with the component option
     * `organisation_invite_requires_acceptance` off, or when the JED team does it, the user is
     * added directly and told. A user who had asked to join is simply approved.
     *
     * @param int              $organisationId The organisation.
     * @param int              $userId         The user being brought in.
     * @param int              $actorId        The administrator (or staff).
     * @param OrganisationRole $role           The role they get.
     *
     * @return MembershipState  INVITED or ACTIVE.
     *
     * @since 4.1.0
     */
    public function invite(int $organisationId, int $userId, int $actorId, OrganisationRole $role = OrganisationRole::MEMBER): MembershipState
    {
        $this->loadLanguage();

        $organisation = $this->requireOrganisation($organisationId, $actorId);
        $this->assertAdmin($organisation, $actorId);

        $target = $this->user($userId);

        if ((int) $target->id <= 0 || (int) $target->block === 1) {
            throw new RuntimeException(Text::_('COM_JED_ORGANISATION_ERROR_NO_ACCOUNT'));
        }

        $existing = $this->membership($organisationId, $userId);
        $state    = $existing !== null ? MembershipState::tryFrom((int) $existing->state) : null;

        if ($state === MembershipState::ACTIVE) {
            throw new RuntimeException(Text::_('COM_JED_ORGANISATION_ERROR_ALREADY_MEMBER'));
        }

        if ($state === MembershipState::INVITED) {
            throw new RuntimeException(Text::_('COM_JED_ORGANISATION_ERROR_ALREADY_INVITED'));
        }

        $now         = Factory::getDate()->toSql();
        $direct      = $state === MembershipState::REQUESTED
            || $this->isStaff($actorId)
            || (int) ComponentHelper::getParams('com_jed')->get('organisation_invite_requires_acceptance', 1) !== 1;

        if ($direct) {
            JedAccessHelper::assertMay($userId, Privilege::EDIT_LISTING);
        }

        $row = (object) [
            'organisation_id' => $organisationId,
            'user_id'         => $userId,
            'role'            => $role->value,
            'state'           => ($direct ? MembershipState::ACTIVE : MembershipState::INVITED)->value,
            'invited_by'      => $actorId,
            'invited_time'    => $now,
            'decided_by'      => $direct ? $actorId : null,
            'decided_time'    => $direct ? $now : null,
            'joined_time'     => $direct ? $now : null,
        ];

        $this->upsertMembership($row, $existing !== null);
        OrganisationAccess::clearCache();

        $this->notify($target, $direct ? 'com_jed.organisation_added' : 'com_jed.organisation_invitation', [
            'ORGANISATION' => (string) $organisation->title,
            'INVITER'      => (string) $this->user($actorId)->name,
            'LINK'         => $this->link('index.php?option=com_jed&view=organisations'),
        ]);

        $this->log(JedActionLog::ORGANISATION_MEMBER_ADD, $organisationId, (string) $organisation->title, $userId);

        return $direct ? MembershipState::ACTIVE : MembershipState::INVITED;
    }

    /**
     * The invited user accepts or declines.
     *
     * @param int  $organisationId The organisation.
     * @param int  $userId         The invited user (the current user - never somebody else).
     * @param bool $accept         Accept, or decline.
     *
     * @return void
     *
     * @since 4.1.0
     */
    public function respondToInvitation(int $organisationId, int $userId, bool $accept): void
    {
        $this->loadLanguage();

        $row = $this->membership($organisationId, $userId);

        if ($row === null || (int) $row->state !== MembershipState::INVITED->value) {
            throw new RuntimeException(Text::_('COM_JED_ORGANISATION_ERROR_NO_INVITATION'));
        }

        $organisation = $this->find($organisationId);

        if ($organisation === null || (int) $organisation->state !== 1) {
            throw new RuntimeException(Text::_('COM_JED_ORGANISATION_ERROR_NOT_FOUND'));
        }

        if ($accept) {
            JedAccessHelper::assertMay($userId, Privilege::EDIT_LISTING);
        }

        $now = Factory::getDate()->toSql();

        $update = (object) [
            'organisation_id' => $organisationId,
            'user_id'         => $userId,
            'state'           => ($accept ? MembershipState::ACTIVE : MembershipState::DECLINED)->value,
            'decided_by'      => $userId,
            'decided_time'    => $now,
            'joined_time'     => $accept ? $now : null,
        ];

        $this->db->updateObject('#__jed_organisation_members', $update, ['organisation_id', 'user_id'], true);

        OrganisationAccess::clearCache();
    }

    /**
     * An administrator takes somebody out: an active member, or an invitation not yet answered.
     *
     * @param int $organisationId The organisation.
     * @param int $userId         The member.
     * @param int $actorId        The administrator (or staff).
     *
     * @return void
     *
     * @since 4.1.0
     */
    public function remove(int $organisationId, int $userId, int $actorId): void
    {
        $this->loadLanguage();

        $organisation = $this->requireOrganisation($organisationId, $actorId);
        $this->assertAdmin($organisation, $actorId);

        if ((int) $organisation->primary_user_id === $userId) {
            throw new RuntimeException(Text::_('COM_JED_ORGANISATION_ERROR_PRIMARY_CANNOT_GO'));
        }

        $row = $this->membership($organisationId, $userId);

        if ($row === null) {
            return;
        }

        $this->deleteMembership($organisationId, $userId);
        $this->log(JedActionLog::ORGANISATION_MEMBER_REMOVE, $organisationId, (string) $organisation->title, $userId);
    }

    /**
     * A member leaves of their own accord (R10).
     *
     * @param int $organisationId The organisation.
     * @param int $userId         The member (the current user).
     *
     * @return void
     *
     * @since 4.1.0
     */
    public function leave(int $organisationId, int $userId): void
    {
        $this->loadLanguage();

        $organisation = $this->find($organisationId);
        $row          = $this->membership($organisationId, $userId);

        if ($organisation === null || $row === null || (int) $row->state !== MembershipState::ACTIVE->value) {
            throw new RuntimeException(Text::_('COM_JED_ORGANISATION_ERROR_NOT_MEMBER'));
        }

        if ((int) $organisation->primary_user_id === $userId) {
            throw new RuntimeException(Text::_('COM_JED_ORGANISATION_ERROR_PRIMARY_CANNOT_GO'));
        }

        if (
            (string) $row->role === OrganisationRole::ADMIN->value
            && \count($this->adminIds($organisationId)) <= 1
            && $this->listingCount($organisationId) > 0
        ) {
            throw new RuntimeException(Text::_('COM_JED_ORGANISATION_ERROR_LAST_ADMIN'));
        }

        $this->deleteMembership($organisationId, $userId);
        $this->log(JedActionLog::ORGANISATION_MEMBER_REMOVE, $organisationId, (string) $organisation->title, $userId);
    }

    /**
     * Make an active member an administrator, or an administrator a member.
     *
     * @param int              $organisationId The organisation.
     * @param int              $userId         The member.
     * @param OrganisationRole $role           The new role.
     * @param int              $actorId        The administrator (or staff).
     *
     * @return void
     *
     * @since 4.1.0
     */
    public function setRole(int $organisationId, int $userId, OrganisationRole $role, int $actorId): void
    {
        $this->loadLanguage();

        $organisation = $this->requireOrganisation($organisationId, $actorId);
        $this->assertAdmin($organisation, $actorId);

        $row = $this->membership($organisationId, $userId);

        if ($row === null || (int) $row->state !== MembershipState::ACTIVE->value) {
            throw new RuntimeException(Text::_('COM_JED_ORGANISATION_ERROR_NOT_MEMBER'));
        }

        if ($role === OrganisationRole::MEMBER && (int) $organisation->primary_user_id === $userId) {
            throw new RuntimeException(Text::_('COM_JED_ORGANISATION_ERROR_PRIMARY_CANNOT_GO'));
        }

        if ((string) $row->role === $role->value) {
            return;
        }

        $update = (object) [
            'organisation_id' => $organisationId,
            'user_id'         => $userId,
            'role'            => $role->value,
        ];

        $this->db->updateObject('#__jed_organisation_members', $update, ['organisation_id', 'user_id']);

        OrganisationAccess::clearCache();
        $this->log(JedActionLog::ORGANISATION_ROLE, $organisationId, (string) $organisation->title, $userId, ['role' => Text::_($role->label())]);
    }

    /**
     * Hand the primary-contact role to another active administrator (R10). Only the current
     * primary contact or staff may. Every listing's `owner` follows.
     *
     * @param int $organisationId The organisation.
     * @param int $userId         The new primary contact.
     * @param int $actorId        The current primary contact (or staff).
     *
     * @return void
     *
     * @since 4.1.0
     */
    public function setPrimary(int $organisationId, int $userId, int $actorId): void
    {
        $this->loadLanguage();

        $organisation = $this->requireOrganisation($organisationId, $actorId);

        if ((int) $organisation->primary_user_id !== $actorId && !$this->isStaff($actorId)) {
            throw new RuntimeException(Text::_('COM_JED_ORGANISATION_ERROR_ONLY_PRIMARY'));
        }

        $row = $this->membership($organisationId, $userId);

        if (
            $row === null
            || (int) $row->state !== MembershipState::ACTIVE->value
            || (string) $row->role !== OrganisationRole::ADMIN->value
        ) {
            throw new RuntimeException(Text::_('COM_JED_ORGANISATION_ERROR_PRIMARY_MUST_BE_ADMIN'));
        }

        $this->writePrimary($organisationId, $userId, $actorId);
        $this->log(JedActionLog::ORGANISATION_PRIMARY, $organisationId, (string) $organisation->title, $userId);
    }

    /**
     * Change an organisation's state (JED team).
     *
     * @param int  $organisationId The organisation.
     * @param bool $active         Active or disabled.
     * @param int  $actorId        Staff.
     *
     * @return void
     *
     * @since 4.1.0
     */
    public function setState(int $organisationId, bool $active, int $actorId): void
    {
        $this->loadLanguage();

        if (!$this->isStaff($actorId)) {
            throw new RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'));
        }

        $update = (object) [
            'id'          => $organisationId,
            'state'       => $active ? 1 : 0,
            'modified'    => Factory::getDate()->toSql(),
            'modified_by' => $actorId,
        ];

        $this->db->updateObject('#__jed_organisations', $update, 'id');

        OrganisationAccess::clearCache();
    }

    // -------------------------------------------------------------------------------------------
    // Listings
    // -------------------------------------------------------------------------------------------

    /**
     * Give a listing to an organisation: set `organisation_id` and the `owner` mirror.
     *
     * For a listing being created, and for the migration. Moving an existing listing between
     * organisations is a transfer and goes through TransferService.
     *
     * @param int $extensionId    The listing.
     * @param int $organisationId The organisation.
     *
     * @return void
     *
     * @since 4.1.0
     */
    public function assignListing(int $extensionId, int $organisationId): void
    {
        $organisation = $this->find($organisationId);

        if ($organisation === null) {
            throw new RuntimeException(Text::_('COM_JED_ORGANISATION_ERROR_NOT_FOUND'));
        }

        $primary = (int) $organisation->primary_user_id;

        $this->db->setQuery(
            $this->db->getQuery(true)
                ->update($this->db->quoteName('#__jed_extensions'))
                ->set($this->db->quoteName('organisation_id') . ' = :oid')
                ->set($this->db->quoteName('owner') . ' = :owner')
                ->where($this->db->quoteName('id') . ' = :eid')
                ->bind(':oid', $organisationId, ParameterType::INTEGER)
                ->bind(':owner', $primary, ParameterType::INTEGER)
                ->bind(':eid', $extensionId, ParameterType::INTEGER)
        )->execute();

        OrganisationAccess::clearCache();
    }

    /**
     * Make `owner` equal the primary contact on every listing of the organisation.
     *
     * Called whenever the primary contact changes; also the repair for any drift.
     *
     * @param int $organisationId The organisation.
     *
     * @return int  Rows changed.
     *
     * @since 4.1.0
     */
    public function syncOwner(int $organisationId): int
    {
        $organisation = $this->find($organisationId);

        if ($organisation === null || (int) $organisation->primary_user_id <= 0) {
            return 0;
        }

        $primary = (int) $organisation->primary_user_id;

        $this->db->setQuery(
            $this->db->getQuery(true)
                ->update($this->db->quoteName('#__jed_extensions'))
                ->set($this->db->quoteName('owner') . ' = :owner')
                ->where($this->db->quoteName('organisation_id') . ' = :oid')
                ->where($this->db->quoteName('owner') . ' <> :owner2')
                ->bind(':owner', $primary, ParameterType::INTEGER)
                ->bind(':owner2', $primary, ParameterType::INTEGER)
                ->bind(':oid', $organisationId, ParameterType::INTEGER)
        )->execute();

        return (int) $this->db->getAffectedRows();
    }

    // -------------------------------------------------------------------------------------------
    // Privacy (R15)
    // -------------------------------------------------------------------------------------------

    /**
     * Take a person out of every organisation before their account data is erased.
     *
     * Where they were the primary contact and another active administrator exists, the role
     * passes to that administrator - the organisation's listings then have an accountable
     * person again and are not blocked. Where nobody can take over, the organisation keeps the
     * erased user as primary contact on paper; PrivacyRemovalService blocks those listings and
     * clears `owner` exactly as before. A personal organisation's contact details are the
     * person's, so they are scrubbed.
     *
     * @param int $userId The person.
     *
     * @return array{memberships_deleted: int, primary_handed_on: int, organisations_scrubbed: int}
     *
     * @since 4.1.0
     */
    public function handleErasure(int $userId): array
    {
        $report = ['memberships_deleted' => 0, 'primary_handed_on' => 0, 'organisations_scrubbed' => 0];

        if ($userId <= 0) {
            return $report;
        }

        $primaryOf = $this->db->setQuery(
            $this->db->getQuery(true)
                ->select($this->db->quoteName(['id', 'is_personal']))
                ->from($this->db->quoteName('#__jed_organisations'))
                ->where($this->db->quoteName('primary_user_id') . ' = :uid')
                ->bind(':uid', $userId, ParameterType::INTEGER)
        )->loadObjectList() ?: [];

        foreach ($primaryOf as $organisation) {
            $successor = array_values(array_filter(
                $this->adminIds((int) $organisation->id),
                static fn (int $id): bool => $id !== $userId
            ));

            if ($successor !== []) {
                $this->writePrimary((int) $organisation->id, $successor[0], 0);
                $report['primary_handed_on']++;

                continue;
            }

            if ((int) $organisation->is_personal === 1) {
                $update = (object) [
                    'id'            => (int) $organisation->id,
                    'contact_name'  => '',
                    'contact_email' => '',
                    'contact_phone' => '',
                    'address'       => '',
                    'modified'      => Factory::getDate()->toSql(),
                ];

                $this->db->updateObject('#__jed_organisations', $update, 'id');
                $report['organisations_scrubbed']++;
            }
        }

        $this->db->setQuery(
            $this->db->getQuery(true)
                ->delete($this->db->quoteName('#__jed_organisation_members'))
                ->where($this->db->quoteName('user_id') . ' = :uid')
                ->bind(':uid', $userId, ParameterType::INTEGER)
        )->execute();
        $report['memberships_deleted'] = (int) $this->db->getAffectedRows();

        foreach (['invited_by', 'decided_by'] as $column) {
            $this->db->setQuery(
                $this->db->getQuery(true)
                    ->update($this->db->quoteName('#__jed_organisation_members'))
                    ->set($this->db->quoteName($column) . ' = NULL')
                    ->where($this->db->quoteName($column) . ' = :uid')
                    ->bind(':uid', $userId, ParameterType::INTEGER)
            )->execute();
        }

        OrganisationAccess::clearCache();

        return $report;
    }

    // -------------------------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------------------------

    /**
     * @param int $userId The user.
     *
     * @return int  Open requests the user has.
     *
     * @since 4.1.0
     */
    public function countOpenRequests(int $userId): int
    {
        $requested = MembershipState::REQUESTED->value;

        return (int) $this->db->setQuery(
            $this->db->getQuery(true)
                ->select('COUNT(*)')
                ->from($this->db->quoteName('#__jed_organisation_members'))
                ->where($this->db->quoteName('user_id') . ' = :uid')
                ->where($this->db->quoteName('state') . ' = :requested')
                ->bind(':uid', $userId, ParameterType::INTEGER)
                ->bind(':requested', $requested, ParameterType::INTEGER)
        )->loadResult();
    }

    /**
     * Whether the user is JED staff (core.edit on com_jed).
     *
     * @param int $userId The user.
     *
     * @return bool
     *
     * @since 4.1.0
     */
    public function isStaff(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        try {
            return (bool) $this->user($userId)->authorise('core.edit', 'com_jed');
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * The organisation, refused when it does not exist or - unless staff act - is disabled.
     *
     * @param int $organisationId The organisation.
     * @param int $actorId        Who is acting.
     *
     * @return object
     *
     * @since 4.1.0
     */
    private function requireOrganisation(int $organisationId, int $actorId): object
    {
        $organisation = $this->find($organisationId);

        if ($organisation === null) {
            throw new RuntimeException(Text::_('COM_JED_ORGANISATION_ERROR_NOT_FOUND'));
        }

        if ((int) $organisation->state !== 1 && !$this->isStaff($actorId)) {
            throw new RuntimeException(Text::_('COM_JED_ORGANISATION_ERROR_DISABLED'));
        }

        return $organisation;
    }

    /**
     * Refuse unless the actor is an active administrator of the organisation, or staff.
     *
     * Read from the database at this moment - an administrator demoted in another tab cannot
     * finish an action in this one.
     *
     * @param object $organisation The organisation row.
     * @param int    $actorId      Who is acting.
     *
     * @return void
     *
     * @since 4.1.0
     */
    private function assertAdmin(object $organisation, int $actorId): void
    {
        if ($this->isStaff($actorId)) {
            return;
        }

        $row = $this->membership((int) $organisation->id, $actorId);

        if (
            $row === null
            || (int) $row->state !== MembershipState::ACTIVE->value
            || (string) $row->role !== OrganisationRole::ADMIN->value
        ) {
            throw new RuntimeException(Text::_('COM_JED_ORGANISATION_ERROR_NOT_ADMIN'));
        }
    }

    /**
     * @param int $organisationId The organisation.
     * @param int $userId         The new primary contact.
     * @param int $actorId        Who (0 for the system).
     *
     * @return void
     *
     * @since 4.1.0
     */
    private function writePrimary(int $organisationId, int $userId, int $actorId): void
    {
        $update = (object) [
            'id'              => $organisationId,
            'primary_user_id' => $userId,
            'modified'        => Factory::getDate()->toSql(),
            'modified_by'     => $actorId ?: null,
        ];

        $this->db->updateObject('#__jed_organisations', $update, 'id', true);

        $this->syncOwner($organisationId);
        OrganisationAccess::clearCache();
    }

    /**
     * Insert a membership row, or overwrite the refused/closed one already there.
     *
     * @param object $row    The row.
     * @param bool   $exists Whether a row for the pair exists.
     *
     * @return void
     *
     * @since 4.1.0
     */
    private function upsertMembership(object $row, bool $exists): void
    {
        if ($exists) {
            $this->db->updateObject('#__jed_organisation_members', $row, ['organisation_id', 'user_id'], true);

            return;
        }

        $this->db->insertObject('#__jed_organisation_members', $row);
    }

    /**
     * @param int $organisationId The organisation.
     * @param int $userId         The user.
     *
     * @return void
     *
     * @since 4.1.0
     */
    private function deleteMembership(int $organisationId, int $userId): void
    {
        $this->db->setQuery(
            $this->db->getQuery(true)
                ->delete($this->db->quoteName('#__jed_organisation_members'))
                ->where($this->db->quoteName('organisation_id') . ' = :oid')
                ->where($this->db->quoteName('user_id') . ' = :uid')
                ->bind(':oid', $organisationId, ParameterType::INTEGER)
                ->bind(':uid', $userId, ParameterType::INTEGER)
        )->execute();

        OrganisationAccess::clearCache();
    }

    /**
     * Validate and clean the editable details.
     *
     * @param array $data  The submitted details.
     * @param bool  $isNew Whether this is for a new organisation.
     *
     * @return array<string, mixed>
     *
     * @since 4.1.0
     */
    private function cleanDetails(array $data, bool $isNew): array
    {
        $title = trim(strip_tags((string) ($data['title'] ?? '')));
        $email = mb_strtolower(trim((string) ($data['contact_email'] ?? '')));

        if ($title === '') {
            throw new RuntimeException(Text::_('COM_JED_ORGANISATION_ERROR_TITLE_REQUIRED'));
        }

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException(Text::_('COM_JED_ORGANISATION_ERROR_EMAIL_REQUIRED'));
        }

        $website = trim((string) ($data['website'] ?? ''));

        if ($website !== '' && !preg_match('~^https?://[^\s/$.?#].[^\s]*$~i', $website)) {
            throw new RuntimeException(Text::_('COM_JED_ORGANISATION_ERROR_WEBSITE'));
        }

        $country = strtoupper(trim((string) ($data['country'] ?? '')));

        $clean = [
            'title'         => mb_substr($title, 0, 255),
            'contact_name'  => mb_substr(trim(strip_tags((string) ($data['contact_name'] ?? ''))), 0, 255),
            'contact_email' => mb_substr($email, 0, 255),
            'contact_phone' => mb_substr(preg_replace('/[^0-9+()\-. ]/', '', (string) ($data['contact_phone'] ?? '')) ?? '', 0, 50),
            'website'       => mb_substr($website, 0, 255),
            'address'       => mb_substr(trim(strip_tags((string) ($data['address'] ?? ''))), 0, 2000),
            'country'       => preg_match('/^[A-Z]{2}$/', $country) ? $country : '',
            'description'   => mb_substr(trim(strip_tags((string) ($data['description'] ?? ''))), 0, 5000),
        ];

        if (\array_key_exists('accept_requests', $data)) {
            $clean['accept_requests'] = (int) $data['accept_requests'] === 1 ? 1 : 0;
        }

        return $clean;
    }

    /**
     * @return string  A UID nobody has.
     *
     * @since 4.1.0
     */
    public function newUid(): string
    {
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $uid = OrganisationUid::generate();

            $taken = $this->db->setQuery(
                $this->db->getQuery(true)
                    ->select('1')
                    ->from($this->db->quoteName('#__jed_organisations'))
                    ->where($this->db->quoteName('uid') . ' = :uid')
                    ->bind(':uid', $uid),
                0,
                1
            )->loadResult();

            if (!$taken) {
                return $uid;
            }
        }

        throw new RuntimeException('Could not allocate an organisation code.');
    }

    /**
     * @param string $title The title.
     *
     * @return string  An alias nobody has.
     *
     * @since 4.1.0
     */
    public function uniqueAlias(string $title): string
    {
        $base  = OutputFilter::stringURLSafe($title) ?: 'organisation';
        $base  = mb_substr($base, 0, 240);
        $alias = $base;

        for ($n = 2; $n < 10000; $n++) {
            $taken = $this->db->setQuery(
                $this->db->getQuery(true)
                    ->select('1')
                    ->from($this->db->quoteName('#__jed_organisations'))
                    ->where($this->db->quoteName('alias') . ' = :alias')
                    ->bind(':alias', $alias),
                0,
                1
            )->loadResult();

            if (!$taken) {
                return $alias;
            }

            $alias = $base . '-' . $n;
        }

        return $base . '-' . bin2hex(random_bytes(4));
    }

    /**
     * The developer name a person gave in their profile (the `developer_name` custom field).
     *
     * @param int $userId The user.
     *
     * @return string
     *
     * @since 4.1.0
     */
    public function developerName(int $userId): string
    {
        try {
            $context = 'com_users.user';
            $name    = 'developer_name';
            $itemId  = (string) $userId;

            return trim((string) $this->db->setQuery(
                $this->db->getQuery(true)
                    ->select($this->db->quoteName('v.value'))
                    ->from($this->db->quoteName('#__fields', 'f'))
                    ->innerJoin($this->db->quoteName('#__fields_values', 'v'), $this->db->quoteName('v.field_id') . ' = ' . $this->db->quoteName('f.id'))
                    ->where($this->db->quoteName('f.context') . ' = :context')
                    ->where($this->db->quoteName('f.name') . ' = :name')
                    ->where($this->db->quoteName('v.item_id') . ' = :uid')
                    ->bind(':context', $context)
                    ->bind(':name', $name)
                    ->bind(':uid', $itemId)
            )->loadResult());
        } catch (Throwable) {
            return '';
        }
    }

    /**
     * @param int $userId The user.
     *
     * @return User
     *
     * @since 4.1.0
     */
    private function user(int $userId): User
    {
        return Factory::getContainer()->get(UserFactoryInterface::class)->loadUserById($userId);
    }

    /**
     * An absolute, non-SEF link - built without the router for the same reason as the transfer
     * links: it must come out right from the CLI and the scheduler too.
     *
     * @param string $query The non-SEF query string.
     *
     * @return string
     *
     * @since 4.1.0
     */
    private function link(string $query): string
    {
        return rtrim(Uri::root(), '/') . '/' . $query;
    }

    /**
     * Best-effort mail: a mail server having a bad day must not undo a membership decision.
     *
     * @param User   $recipient The recipient.
     * @param string $template  The #__mail_templates id.
     * @param array  $data      Template data.
     *
     * @return void
     *
     * @since 4.1.0
     */
    private function notify(User $recipient, string $template, array $data): void
    {
        if (empty($recipient->email) || (int) $recipient->block === 1) {
            return;
        }

        try {
            $mailer = new MailTemplate($template, Factory::getApplication()->getLanguage()->getTag());
            $mailer->addTemplateData(array_merge(['SITENAME' => (string) Factory::getApplication()->get('sitename')], $data));
            $mailer->addRecipient($recipient->email, $recipient->name);
            $mailer->send();
        } catch (Throwable $e) {
            Log::add(\sprintf('com_jed: organisation mail "%s" to user %d failed: %s', $template, (int) $recipient->id, $e->getMessage()), Log::WARNING, 'com_jed');
        }
    }

    /**
     * @param string $action         A JedActionLog constant.
     * @param int    $organisationId The organisation.
     * @param string $title          Its title.
     * @param int    $memberId       The member concerned, if any.
     * @param array  $extra          More message data.
     *
     * @return void
     *
     * @since 4.1.0
     */
    private function log(string $action, int $organisationId, string $title, int $memberId = 0, array $extra = []): void
    {
        JedActionLog::loadWording();

        $data = ['title' => $title];

        if ($memberId > 0) {
            $data['member']     = (string) $this->user($memberId)->name;
            $data['memberlink'] = 'index.php?option=com_users&task=user.edit&id=' . $memberId;
        }

        JedActionLog::record($action, 'com_jed.organisation', $organisationId, $data + $extra);
    }

    /**
     * Both sides' strings: this service runs on the site, in the backend and from the scheduler.
     *
     * @return void
     *
     * @since 4.1.0
     */
    private function loadLanguage(): void
    {
        static $loaded = false;

        if ($loaded) {
            return;
        }

        $loaded = true;

        try {
            $language = Factory::getApplication()->getLanguage();
            $language->load('com_jed', JPATH_BASE) || $language->load('com_jed', JPATH_BASE . '/components/com_jed');
            $language->load('com_jed', JPATH_ADMINISTRATOR . '/components/com_jed');
        } catch (Throwable) {
            // No application: messages stay keys.
        }
    }
}
