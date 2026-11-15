<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Administrator\Browse;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\QueryInterface;

/**
 * The browse lists the JED offers, defined once.
 *
 * These exist in three places at once - as a menu item, as a module instance, and as an ordering
 * in the list model - and the whole point of this enum is that all three name the same thing. The
 * legacy site had them as hand-built `/browse/<list>` pages and the current code has them as menu
 * items carrying `list_fullordering` in a query string, which is why "New" ended up meaning two
 * different things depending on where you clicked.
 *
 * **`RECENTLY_ADDED` and `NOTEWORTHY` are both here on purpose.** The legacy `New & Noteworthy`
 * list is computed from hits over a two-week window, and the current "New" preset sorts by
 * creation date - a different list wearing a very similar name. Both are worth having: one
 * answers "what has just arrived", the other "what are people actually looking at". Only the
 * naming was the problem, so the date-based one is `Recently Added` and `New & Noteworthy` keeps
 * the name it is publicly known by.
 *
 * What each list is:
 *
 *   TOP_RATED         All listings, best average rating first; equal ratings by review count, most first.
 *   MOST_REVIEWED     All listings, most reviews first; equal counts by rating, best first.
 *   RECENTLY_ADDED    ("New") Only listings created in the last {@see self::NEW_DAYS} days, newest first.
 *   RECENTLY_UPDATED  All listings, most recently modified first.
 *   NOTEWORTHY        Only listings viewed in the last {@see self::NOTEWORTHY_DAYS} days, most viewed first.
 *
 * {@see self::applyTo()} is the one place that turns a list into SQL, so the browse page
 * (ExtensionsModel) and the module (mod_jed_extensions) cannot drift apart.
 *
 * @since 4.0.0
 */
enum BrowseList: string
{
    case TOP_RATED         = 'top-rated';
    case MOST_REVIEWED     = 'most-reviewed';
    case RECENTLY_ADDED    = 'recently-added';
    case RECENTLY_UPDATED  = 'recently-updated';
    case NOTEWORTHY        = 'new-noteworthy';

    /**
     * How many days of hit data `NOTEWORTHY` looks at.
     *
     * Two weeks, which is what the legacy `view_jed_new_noteworthy` used. Kept rather than
     * improved: the list is publicly known and people have expectations of it, and there is no
     * evidence that a different window would be better.
     *
     * @since 4.0.0
     */
    public const NOTEWORTHY_DAYS = 14;

    /**
     * How many days back `RECENTLY_ADDED` ("New") looks.
     *
     * Measured on `created`, not `approved_time`: approving a later *edit* copies the revision onto
     * the live row and stamps approved_time again, so an old listing would keep re-appearing as new.
     * `created` is set once, when the listing is first saved, and preserved on every edit.
     *
     * @since 4.0.0
     */
    public const NEW_DAYS = 30;

    /**
     * The ordering this list applies, as `column direction`.
     *
     * `NOTEWORTHY` has none of its own - it is not a sort of the listing table but a join against
     * the hit aggregate, so the model builds it rather than ordering by a column.
     *
     * @return string
     *
     * @since 4.0.0
     */
    public function ordering(): string
    {
        return match ($this) {
            self::TOP_RATED        => 'score_overall DESC',
            self::MOST_REVIEWED    => 'score_count DESC',
            self::RECENTLY_ADDED   => 'a.created DESC',
            self::RECENTLY_UPDATED => 'a.modified DESC',
            self::NOTEWORTHY       => 'noteworthy DESC',
        };
    }

    /**
     * The tie-break applied after this list's own ordering.
     *
     * Without one these lists are not deterministic, and that is not theoretical: several hundred
     * listings share a `score_overall` of 5.00, so "Top Rated" was returning a different set on
     * the page and in the module - two things with the same name disagreeing in front of the
     * visitor. MySQL is free to order equal rows however it likes, and it does.
     *
     * The tie-break is not arbitrary either: among equally rated listings the one more people
     * have reviewed goes first, and `id` settles the rest so the answer is stable across requests.
     *
     * @return string
     *
     * @since 4.0.0
     */
    public function tieBreak(): string
    {
        return match ($this) {
            self::TOP_RATED     => 'a.score_count DESC, a.id ASC',
            // Equal review counts - most of all the many listings with none - best rated first.
            self::MOST_REVIEWED => 'a.score_overall DESC, a.id ASC',
            default             => 'a.id ASC',
        };
    }

    /**
     * How many days back this list looks, or null when it covers every listing.
     *
     * `NOTEWORTHY` returns null here on purpose: its window is over the hit statistics, not over a
     * column of the listing, and {@see self::applyTo()} handles it in its join.
     *
     * @return integer|null
     *
     * @since 4.0.0
     */
    public function windowDays(): ?int
    {
        return match ($this) {
            self::RECENTLY_ADDED => self::NEW_DAYS,
            default              => null,
        };
    }

    /**
     * Apply this list to a query over `#__jed_extensions`: its date window, its ordering and its
     * tie-break, plus the hit statistics join for `NOTEWORTHY`.
     *
     * The query must select from `#__jed_extensions` with the alias `a`. Any ordering already on it
     * is kept ahead of this list's, so callers should not add one.
     *
     * The window starts at midnight (UTC, as the columns are stored) rather than at this second, so
     * the SQL - and therefore the page and query caches - stay the same for the whole day.
     *
     * @param QueryInterface    $query The query to add to.
     * @param DatabaseInterface $db    The database driver, for quoting.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function applyTo(QueryInterface $query, DatabaseInterface $db): void
    {
        $days = $this->windowDays();

        if ($days !== null) {
            $since = Factory::getDate('-' . $days . ' days')->format('Y-m-d') . ' 00:00:00';

            $query->where($db->quoteName('a.created') . ' >= ' . $db->quote($since));
        }

        if ($this === self::NOTEWORTHY) {
            // Not a sort of the listing table but what people have actually been looking at, from
            // P1-12's aggregate. INNER JOIN, because a listing nobody viewed is not noteworthy -
            // that is the definition, not an omission. A derived table, so there is no table name
            // for quoteName() to wrap, only its alias.
            $since = Factory::getDate('-' . self::NOTEWORTHY_DAYS . ' days')->format('Y-m-d');

            $query->select($db->quoteName('hits.views', 'noteworthy'))
                ->innerJoin(
                    '(SELECT ' . $db->quoteName('extension_id') . ', SUM(' . $db->quoteName('views') . ') AS '
                    . $db->quoteName('views') . ' FROM ' . $db->quoteName('#__jed_hit_stats')
                    . ' WHERE ' . $db->quoteName('period') . ' >= ' . $db->quote($since)
                    . ' GROUP BY ' . $db->quoteName('extension_id')
                    . ' HAVING SUM(' . $db->quoteName('views') . ') > 0) AS ' . $db->quoteName('hits'),
                    $db->quoteName('hits.extension_id') . ' = ' . $db->quoteName('a.id')
                );
        }

        [$column, $direction] = array_pad(explode(' ', $this->ordering(), 2), 2, 'DESC');

        $query->order($db->quoteName($column) . ' ' . (strtoupper($direction) === 'ASC' ? 'ASC' : 'DESC'))
            ->order($this->tieBreak());
    }

    /**
     * The language key for this list's name.
     *
     * @return string
     *
     * @since 4.0.0
     */
    public function label(): string
    {
        return 'COM_JED_BROWSE_' . strtoupper(str_replace('-', '_', $this->value));
    }

    /**
     * The list for a key, or null when the key is not one of these.
     *
     * @param string|null $key The key, e.g. from a menu item parameter.
     *
     * @return self|null
     *
     * @since 4.0.0
     */
    public static function fromKey(?string $key): ?self
    {
        return self::tryFrom(trim((string) $key));
    }
}
