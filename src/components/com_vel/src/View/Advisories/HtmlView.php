<?php

/**
 * @package JED
 *
 * @subpackage VEL
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Vel\Site\View\Advisories;

// No direct access
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Exception;
use Jed\Component\Vel\Administrator\Enum\AdvisoryStatus;
use Jed\Component\Vel\Site\Helper\VelHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Pagination\Pagination;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\Registry\Registry;

/**
 * The public list (brief §4.1).
 *
 * @since 4.0.0
 */
class HtmlView extends BaseHtmlView
{
    public array $items            = [];
    public ?Pagination $pagination = null;
    public ?Registry $state        = null;
    public ?Registry $params       = null;

    /**
     * @var array<string, int>
     *
     * @since 4.0.0
     */
    public array $counts = [];

    /**
     * @var array<string, mixed>  The active filter values, for the form.
     *
     * @since 4.0.0
     */
    public array $filters = [];

    /**
     * @var array<string, int>  For each tab, the list menu item that opens on it - the closest one
     *                          to the current page when there are several.
     *
     * @since 4.0.0
     */
    private array $statusItemIds = [];

    /**
     * @var array<int, string>  For each list menu item, the tab it opens on ("Default tab").
     *
     * @since 4.0.0
     */
    private array $itemStatus = [];

    /**
     * @var int  The current list menu item, 0 when the page was not reached through one.
     *
     * @since 4.0.0
     */
    private int $activeItemId = 0;

    /**
     * @param string|null $tpl The template name.
     *
     * @return void
     *
     * @throws Exception
     *
     * @since 4.0.0
     */
    public function display($tpl = null): void
    {
        $model = $this->getModel();

        $this->items      = (array) $model->getItems();
        $this->pagination = $model->getPagination();
        $this->state      = $model->getState();
        $this->counts     = $model->getStatusCounts();
        $this->params     = Factory::getApplication()->getParams('com_vel');

        $this->filters = [
            'status'   => (string) $this->state->get('filter.status', 'live'),
            'search'   => (string) $this->state->get('filter.search', ''),
            'severity' => (array) $this->state->get('filter.severity', []),
            'cvss_min' => $this->state->get('filter.cvss_min'),
            'cvss_max' => $this->state->get('filter.cvss_max'),
            'vendor'   => (string) $this->state->get('filter.vendor', ''),
            'type'     => (string) $this->state->get('filter.type', ''),
            'sort'     => (string) $this->state->get('list.ordering', 'a.date_published'),
            'dir'      => (string) $this->state->get('list.direction', 'DESC'),
        ];

        $this->loadListMenuItems();

        // The filters travel with the pagination links explicitly, so a page 2 of "critical and
        // live" is still that. The pages stay on the current menu item, so the status only has to
        // be added when it is not the tab that menu item opens on anyway.
        if ($this->activeItemId === 0 || $this->itemStatus[$this->activeItemId] !== $this->filters['status']) {
            $this->pagination->setAdditionalUrlParam('status', $this->filters['status']);
        }

        foreach (['search', 'vendor', 'type', 'cvss_min', 'cvss_max'] as $key) {
            $value = $this->filters[$key] ?? null;

            if ($value !== null && $value !== '') {
                $this->pagination->setAdditionalUrlParam($key, (string) $value);
            }
        }

        if ($this->filters['severity'] !== []) {
            $this->pagination->setAdditionalUrlParam('severity', implode(',', $this->filters['severity']));
        }

        $this->prepareDocument();

        parent::display($tpl);
    }

    /**
     * The URL of one tab of the list, with any other list parameters.
     *
     * A tab that has its own menu item - Live, Patched and Abandoned in the JED main menu - links to
     * that menu item, so the menu shows the right entry as active and the URL is the menu item's
     * own (/vulnerable-extensions/patched). A tab without one stays on the current menu item and
     * carries ?status= instead.
     *
     * @param string               $status The tab: live, patched, abandoned or all.
     * @param array<string, mixed> $query  Further query parameters (search, sort, ...).
     * @param bool                 $xhtml  Escape the URL for use in HTML.
     *
     * @return string
     *
     * @since 4.0.0
     */
    public function statusUrl(string $status, array $query = [], bool $xhtml = true): string
    {
        $status = VelHelper::listStatus($status);
        $itemId = $this->statusItemIds[$status] ?? $this->activeItemId;
        $base   = ['option' => 'com_vel', 'view' => 'advisories'];

        if ($itemId > 0) {
            $base['Itemid'] = $itemId;
        }

        // A menu item opens on its own tab; only a different tab has to be asked for.
        if ($itemId === 0 || $this->itemStatus[$itemId] !== $status) {
            $base['status'] = $status;
        }

        return Route::_('index.php?' . http_build_query(array_merge($base, $query)), $xhtml);
    }

    /**
     * Where the filter form submits to: the current tab's URL, split into the path and the query
     * parameters. A GET form replaces the query string of its action, so anything in it - the
     * status, and without SEF URLs option, view and Itemid too - has to travel as hidden fields.
     *
     * @return array{0: string, 1: array<string, string>}  The action and the hidden fields.
     *
     * @since 4.0.0
     */
    public function filterFormTarget(): array
    {
        $uri    = new Uri($this->statusUrl($this->filters['status'], [], false));
        $hidden = $uri->getQuery(true);

        return [$uri->toString(['scheme', 'user', 'pass', 'host', 'port', 'path']), \is_array($hidden) ? $hidden : []];
    }

    /**
     * Find the list menu items of the site and the tab each one opens on.
     *
     * When several open on the same tab, the one closest to the current page wins: the current item
     * itself, then its siblings, then its children, then the rest in menu order. So from the JED
     * main menu's "Patched" entry the Live tab leads to its sibling "Live", and from the
     * "Vulnerable Extensions" parent (which opens on Live itself) it stays where it is.
     *
     * @return void
     *
     * @throws Exception
     *
     * @since 4.0.0
     */
    private function loadListMenuItems(): void
    {
        $menu   = Factory::getApplication()->getMenu();
        $active = $menu->getActive();

        // The site menu only returns published items the visitor may see, in their language.
        $items = array_values(array_filter(
            (array) $menu->getItems('component', 'com_vel'),
            static fn ($item): bool => ($item->query['view'] ?? '') === 'advisories'
        ));

        foreach ($items as $item) {
            $this->itemStatus[(int) $item->id] = VelHelper::listStatus((string) $item->getParams()->get('default_status', 'live'));
        }

        if ($active !== null && isset($this->itemStatus[(int) $active->id])) {
            $this->activeItemId = (int) $active->id;

            $rank = static fn ($item): int => match (true) {
                (int) $item->id === (int) $active->id => 0,
                (int) $item->parent_id === (int) $active->parent_id
                    && $item->menutype === $active->menutype => 1,
                (int) $item->parent_id === (int) $active->id => 2,
                default => 3,
            };

            // usort() is stable, so items of the same rank keep their menu order.
            usort($items, static fn ($a, $b): int => $rank($a) <=> $rank($b));
        }

        foreach ($items as $item) {
            $this->statusItemIds[$this->itemStatus[(int) $item->id]] ??= (int) $item->id;
        }
    }

    /**
     * @return void
     *
     * @since 4.0.0
     */
    protected function prepareDocument(): void
    {
        $document = $this->getDocument();
        $title    = (string) $this->params->get('page_title', Text::_('COM_VEL_LIST_HEADING'));
        $status   = $this->filters['status'];

        // The menu item's browser title describes the tab it opens on. Another tab shown on the same
        // menu item - "All", which has no menu item of its own - gets a title that says which.
        if ($status !== ($this->itemStatus[$this->activeItemId] ?? 'live')) {
            $title = Text::sprintf(
                'COM_VEL_LIST_PAGE_TITLE_TAB',
                $status === 'all' ? Text::_('JALL') : Text::_(AdvisoryStatus::from($status)->label())
            );
        }

        $this->setDocumentTitle($title);

        // The feeds and the API, discoverable from the page head. The feeds are of the tab on show.
        $document->addHeadLink($this->feedUrl('rss'), 'alternate', 'rel', ['type' => 'application/rss+xml', 'title' => Text::_('COM_VEL_FEED_TITLE')]);
        $document->addHeadLink($this->feedUrl('atom'), 'alternate', 'rel', ['type' => 'application/atom+xml', 'title' => Text::_('COM_VEL_FEED_TITLE')]);
        $document->addHeadLink(Route::_('index.php?option=com_vel&view=api&resource=advisories'), 'alternate', 'rel', ['type' => 'application/json', 'title' => Text::_('COM_VEL_API_TITLE')]);

        if (VelHelper::legacyFeedAvailable()) {
            $document->addHeadLink(Route::_('index.php?option=com_vel&view=legacyfeed&format=json'), 'alternate', 'rel', ['type' => 'application/json', 'title' => Text::_('COM_VEL_LEGACY_FEED_TITLE')]);
        }

        $document->setMetaData('description', Text::_('COM_VEL_LIST_META_DESCRIPTION'));
    }

    /**
     * The feed of the tab on show. The status is always in the URL: the feed is usually fetched
     * by a reader that did not come through this menu item.
     *
     * @param string $type rss or atom.
     *
     * @return string
     *
     * @since 4.0.0
     */
    public function feedUrl(string $type): string
    {
        return Route::_('index.php?' . http_build_query([
            'option' => 'com_vel',
            'view'   => 'advisories',
            'format' => 'feed',
            'type'   => $type,
            'status' => $this->filters['status'],
        ]));
    }
}
