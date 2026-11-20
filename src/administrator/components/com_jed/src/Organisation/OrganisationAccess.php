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

/**
 * Who may look after a listing (PRD-09).
 *
 * Until 4.1.0 the answer was "the owner, or an accepted maintainer of this listing". It is now
 * "an active member of the organisation that owns the listing" - and, for the decisions that
 * change who runs it, "an active administrator of that organisation".
 *
 * Two deliberate details:
 *
 * - The **write** questions ({@see isMemberOfExtensionOwner()}, {@see isAdminOfExtensionOwner()})
 *   also require the organisation to be active: a disabled organisation keeps its members but
 *   nobody can change anything on its behalf. The **visibility** condition
 *   ({@see memberCondition()}) does not, so members still see their own listings on the
 *   dashboard while the organisation is disabled.
 * - A listing the 4.1.0 migration has not reached yet (`organisation_id` NULL) falls back to the
 *   old rule's first half - `owner` - so an interrupted migration never locks anybody out.
 *   `created_by` plays no part, as before (8.8.1).
 *
 * Answers are cached per request and user; anything that changes a membership calls
 * {@see clearCache()}.
 *
 * @since 4.1.0
 */
final class OrganisationAccess
{
    /**
     * @var array<string, bool>
     *
     * @since 4.1.0
     */
    private static array $cache = [];

    /**
     * Whether the user is an active member (any role) of the organisation owning the listing.
     *
     * @param int      $extensionId The listing.
     * @param int|null $userId      The user; the current one when null.
     *
     * @return bool
     *
     * @since 4.1.0
     */
    public static function isMemberOfExtensionOwner(int $extensionId, ?int $userId = null): bool
    {
        return self::check($extensionId, $userId, false);
    }

    /**
     * Whether the user is an active administrator of the organisation owning the listing.
     *
     * @param int      $extensionId The listing.
     * @param int|null $userId      The user; the current one when null.
     *
     * @return bool
     *
     * @since 4.1.0
     */
    public static function isAdminOfExtensionOwner(int $extensionId, ?int $userId = null): bool
    {
        return self::check($extensionId, $userId, true);
    }

    /**
     * Whether the user is an active member of the organisation - and, with $adminOnly, an
     * administrator. The organisation itself must be active.
     *
     * @param int      $organisationId The organisation.
     * @param int|null $userId         The user; the current one when null.
     * @param bool     $adminOnly      Require the administrator role.
     *
     * @return bool
     *
     * @since 4.1.0
     */
    public static function isMember(int $organisationId, ?int $userId = null, bool $adminOnly = false): bool
    {
        $userId ??= self::currentUserId();

        if ($userId <= 0 || $organisationId <= 0) {
            return false;
        }

        $key = 'o' . $organisationId . ':' . $userId . ':' . (int) $adminOnly;

        if (\array_key_exists($key, self::$cache)) {
            return self::$cache[$key];
        }

        $db     = self::db();
        $active = MembershipState::ACTIVE->value;
        $query  = $db->getQuery(true)
            ->select('1')
            ->from($db->quoteName('#__jed_organisation_members', 'm'))
            ->innerJoin($db->quoteName('#__jed_organisations', 'o'), $db->quoteName('o.id') . ' = ' . $db->quoteName('m.organisation_id'))
            ->where($db->quoteName('m.organisation_id') . ' = :oid')
            ->where($db->quoteName('m.user_id') . ' = :uid')
            ->where($db->quoteName('m.state') . ' = :active')
            ->where($db->quoteName('o.state') . ' = 1')
            ->bind(':oid', $organisationId, ParameterType::INTEGER)
            ->bind(':uid', $userId, ParameterType::INTEGER)
            ->bind(':active', $active, ParameterType::INTEGER);

        if ($adminOnly) {
            $admin = OrganisationRole::ADMIN->value;
            $query->where($db->quoteName('m.role') . ' = :role')->bind(':role', $admin);
        }

        return self::$cache[$key] = (bool) $db->setQuery($query, 0, 1)->loadResult();
    }

    /**
     * SQL condition: the listing aliased `$alias` is owned by an organisation the user is an
     * active member of (or, for an unmigrated row, the user is its `owner`).
     *
     * The user id is cast and inlined, never bound, so the fragment can be dropped into any
     * query - the same contract JedHelper::getOwnedOrMaintainedCondition() always had.
     *
     * @param DatabaseInterface $db     The database.
     * @param string            $alias  The table alias of #__jed_extensions in the query.
     * @param int|null          $userId The user; the current one when null.
     *
     * @return string  A condition; '0' for a guest.
     *
     * @since 4.1.0
     */
    public static function memberCondition(DatabaseInterface $db, string $alias = 'a', ?int $userId = null): string
    {
        $userId = (int) ($userId ?? self::currentUserId());

        if ($userId <= 0) {
            return '0';
        }

        $membership = 'EXISTS (SELECT 1 FROM ' . $db->quoteName('#__jed_organisation_members', 'jom')
            . ' WHERE ' . $db->quoteName('jom.organisation_id') . ' = ' . $db->quoteName($alias . '.organisation_id')
            . ' AND ' . $db->quoteName('jom.user_id') . ' = ' . $userId
            . ' AND ' . $db->quoteName('jom.state') . ' = ' . MembershipState::ACTIVE->value . ')';

        $legacy = '(' . $db->quoteName($alias . '.organisation_id') . ' IS NULL AND '
            . $db->quoteName($alias . '.owner') . ' = ' . $userId . ')';

        return '(' . $membership . ' OR ' . $legacy . ')';
    }

    /**
     * The organisation that owns a listing, or null (unknown listing, or not migrated yet).
     *
     * @param int $extensionId The listing.
     *
     * @return int|null
     *
     * @since 4.1.0
     */
    public static function organisationIdOf(int $extensionId): ?int
    {
        if ($extensionId <= 0) {
            return null;
        }

        $db = self::db();
        $id = $db->setQuery(
            $db->getQuery(true)
                ->select($db->quoteName('organisation_id'))
                ->from($db->quoteName('#__jed_extensions'))
                ->where($db->quoteName('id') . ' = :eid')
                ->bind(':eid', $extensionId, ParameterType::INTEGER)
        )->loadResult();

        return $id === null ? null : (int) $id;
    }

    /**
     * Forget every cached answer. Called after any membership or ownership change.
     *
     * @return void
     *
     * @since 4.1.0
     */
    public static function clearCache(): void
    {
        self::$cache = [];
    }

    /**
     * @param int      $extensionId The listing.
     * @param int|null $userId      The user.
     * @param bool     $adminOnly   Administrator role required.
     *
     * @return bool
     *
     * @since 4.1.0
     */
    private static function check(int $extensionId, ?int $userId, bool $adminOnly): bool
    {
        $userId ??= self::currentUserId();

        if ($userId <= 0 || $extensionId <= 0) {
            return false;
        }

        $key = 'e' . $extensionId . ':' . $userId . ':' . (int) $adminOnly;

        if (\array_key_exists($key, self::$cache)) {
            return self::$cache[$key];
        }

        $db  = self::db();
        $row = $db->setQuery(
            $db->getQuery(true)
                ->select($db->quoteName(['organisation_id', 'owner']))
                ->from($db->quoteName('#__jed_extensions'))
                ->where($db->quoteName('id') . ' = :eid')
                ->bind(':eid', $extensionId, ParameterType::INTEGER)
        )->loadObject();

        if ($row === null) {
            return self::$cache[$key] = false;
        }

        if ($row->organisation_id === null) {
            // Not migrated yet: the owner alone, exactly as the old rule's first half.
            return self::$cache[$key] = (int) $row->owner === $userId;
        }

        return self::$cache[$key] = self::isMember((int) $row->organisation_id, $userId, $adminOnly);
    }

    /**
     * @return int
     *
     * @since 4.1.0
     */
    private static function currentUserId(): int
    {
        try {
            return (int) (Factory::getApplication()->getIdentity()->id ?? 0);
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * @return DatabaseInterface
     *
     * @since 4.1.0
     */
    private static function db(): DatabaseInterface
    {
        return Factory::getContainer()->get(DatabaseInterface::class);
    }
}
