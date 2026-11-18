<?php

/**
 * @package JED
 *
 * @subpackage VEL
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Vel\Site\Service;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Component\Router\RouterView;
use Joomla\CMS\Component\Router\Rules\RulesInterface;

/**
 * The two URL shapes StandardRules cannot express (VEL2-PRD §8.4):
 *
 *   /vulnerable-extensions/api/v2/<resource>[/<id>]   view=api&resource=<resource>&id=<id>
 *   /vulnerable-extensions/json-feed                  view=legacyfeed
 *
 * The first needs several segments under one view; the second needs a segment that is not a
 * view name, because the legacy feed's URL is fixed by the checkers that already read it.
 *
 * Attached before StandardRules (which must not see these segments). Both shapes hang off the
 * advisories ("Vulnerable Extensions") menu item; a menu item of type "VEL: JSON Feed
 * (legacy)" (view=legacyfeed) is its own URL and needs no segment at all.
 *
 * @since 4.0.0
 */
class ApiRules implements RulesInterface
{
    /**
     * @param RouterView $router The router.
     *
     * @since 4.0.0
     */
    public function __construct(private readonly RouterView $router)
    {
    }

    /**
     * Make sure a URL for one of our views carries the advisories menu item, so the path
     * starts with /vulnerable-extensions even when built from another component's page.
     *
     * @param array $query The query.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function preprocess(&$query): void
    {
        if (isset($query['Itemid']) || !isset($query['view'])) {
            return;
        }

        if (!\in_array($query['view'], ['api', 'legacyfeed', 'advisory', 'advisories', 'report', 'fixnotice', 'case'], true)) {
            return;
        }

        $items = $this->router->menu->getItems('link', 'index.php?option=com_vel&view=advisories');

        if (!empty($items)) {
            $query['Itemid'] = (int) reset($items)->id;
        }
    }

    /**
     * @param array $segments The segments still to parse.
     * @param array $vars     The query vars found so far.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function parse(&$segments, &$vars): void
    {
        if ($segments === []) {
            return;
        }

        // RouterView::parse() starts every rule with empty $vars - no rule before this one fills
        // in the view - so the view comes from the menu item the path matched (SiteRouter makes
        // it the active item before calling the component router), as in StandardRules.
        $view = (string) ($vars['view'] ?? '');

        if ($view === '') {
            $active = $this->router->menu->getActive();
            $view   = $active && ($active->query['option'] ?? '') === 'com_vel' ? (string) ($active->query['view'] ?? '') : '';
        }

        if ($view !== 'advisories') {
            return;
        }

        $first = (string) $segments[0];

        if ($first === 'json-feed') {
            $vars['view']   = 'legacyfeed';
            $vars['format'] = 'json';
            array_shift($segments);

            return;
        }

        if ($first !== 'api') {
            return;
        }

        // api / v2 / resource[.json] / id[.json]
        $parts = $segments;
        array_shift($parts);

        $version = (string) ($parts[0] ?? '');

        if ($version !== 'v2') {
            // Unknown version: leave the segments, which ends in a 404.
            return;
        }

        $resource = (string) ($parts[1] ?? 'index');
        $id       = (string) ($parts[2] ?? '');

        $vars['view']     = 'api';
        $vars['resource'] = preg_replace('/\.json$/', '', $resource);
        $vars['format']   = 'json';

        if ($id !== '') {
            $vars['id'] = preg_replace('/\.json$/', '', $id);
        }

        $segments = [];
    }

    /**
     * @param array $query    The query.
     * @param array $segments The segments.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function build(&$query, &$segments): void
    {
        $view = (string) ($query['view'] ?? '');

        if ($view === 'legacyfeed') {
            // The path always means JSON (parse() sets format=json), so ?format=json is noise.
            // When the URL is built on a "JSON feed" menu item, that item's own path already is
            // the feed (e.g. /vulnerable-extensions/json-feed): adding the segment again would
            // give /json-feed/json-feed.
            if (!$this->isFeedMenuItem((int) ($query['Itemid'] ?? 0))) {
                $segments[] = 'json-feed';
            }

            unset($query['view'], $query['format']);

            return;
        }

        if ($view !== 'api') {
            return;
        }

        $resource = (string) ($query['resource'] ?? 'index');
        $id       = (string) ($query['id'] ?? '');

        $segments[] = 'api';
        $segments[] = 'v2';
        $segments[] = $id !== '' ? $resource : $resource . '.json';

        if ($id !== '') {
            $segments[] = $id . '.json';
        }

        unset($query['view'], $query['resource'], $query['id'], $query['format']);
    }

    /**
     * Is the menu item a "JSON feed" menu item (view=legacyfeed) rather than the list?
     *
     * @param int $itemId A menu item id.
     *
     * @return bool
     *
     * @since 4.0.0
     */
    private function isFeedMenuItem(int $itemId): bool
    {
        if ($itemId === 0) {
            return false;
        }

        $item = $this->router->menu->getItem($itemId);

        return $item !== null
            && ($item->query['option'] ?? '') === 'com_vel'
            && ($item->query['view'] ?? '') === 'legacyfeed';
    }
}
