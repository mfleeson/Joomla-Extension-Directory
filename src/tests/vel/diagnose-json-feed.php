<?php

/**
 * Diagnose /vulnerable-extensions/json-feed (and the v2 API) on a running site.
 *
 * Copy this file to the Joomla root (next to index.php), open
 *   https://your-site/diagnose-json-feed.php?run=1
 * read the report, then DELETE the file. It changes nothing: it boots the site application,
 * asks the real router what it makes of the feed's URL and lists the menu items involved.
 *
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

// phpcs:disable PSR1.Files.SideEffects

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Router\SiteRouter;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseInterface;

if (($_GET['run'] ?? '') !== '1') {
    http_response_code(403);
    exit('Add ?run=1 to the URL to run the diagnosis, and delete this file afterwards.');
}

header('Content-Type: text/plain; charset=utf-8');

\define('_JEXEC', 1);
\defined('JPATH_BASE') || \define('JPATH_BASE', __DIR__);

require_once JPATH_BASE . '/includes/defines.php';
require_once JPATH_BASE . '/includes/framework.php';

$container = Factory::getContainer();
$container->alias('session.web', 'session.web.site')
    ->alias('session', 'session.web.site')
    ->alias('JSession', 'session.web.site')
    ->alias(\Joomla\CMS\Session\Session::class, 'session.web.site')
    ->alias(\Joomla\Session\Session::class, 'session.web.site')
    ->alias(\Joomla\Session\SessionInterface::class, 'session.web.site');

$app = $container->get(\Joomla\CMS\Application\SiteApplication::class);
Factory::$application = $app;

// What execute() does before routing (extension autoloading, plugins, language), without routing or
// rendering this request.
(function () {
    $this->createExtensionNamespaceMap();
    \Joomla\CMS\Plugin\PluginHelper::importPlugin('behaviour', null, true, $this->getDispatcher());
    \Joomla\CMS\Plugin\PluginHelper::importPlugin('system', null, true, $this->getDispatcher());
    $this->initialiseApp();
})->call($app);

$out = static function (string $line = ''): void {
    echo $line, "\n";
};
$section = static function (string $title) use ($out): void {
    $out();
    $out('== ' . $title . ' ' . str_repeat('=', max(0, 70 - \strlen($title))));
};
$problems = [];

// ---- 1. configuration ----------------------------------------------------------------------------
$section('Configuration');
$out('Search Engine Friendly URLs (sef): ' . ($app->get('sef') ? 'yes' : 'NO'));
$out('Use URL Rewriting (sef_rewrite):   ' . ($app->get('sef_rewrite') ? 'yes' : 'no - URLs need /index.php/...'));
$out('Add Suffix to URL (sef_suffix):    ' . ($app->get('sef_suffix') ? 'yes' : 'no'));
$out('Site root: ' . Uri::root());

if (!$app->get('sef_rewrite')) {
    $problems[] = 'URL rewriting is off: the feed is at ' . Uri::root() . 'index.php/vulnerable-extensions/json-feed - without index.php/ the web server answers 404.';
}

// ---- 2. component and code ------------------------------------------------------------------------
$section('com_vel');
$enabled = ComponentHelper::isEnabled('com_vel');
$out('Installed and enabled: ' . ($enabled ? 'yes' : 'NO'));

if (!$enabled) {
    $problems[] = 'com_vel is not installed or is disabled.';
}

$params = ComponentHelper::getParams('com_vel');
$out('Serve the legacy JSON feed: ' . ((int) $params->get('legacy_feed_enabled', 1) === 1 ? 'yes' : 'no (answers 410, not 404)'));
$out('Legacy feed sunset date:    ' . ($params->get('legacy_feed_sunset_date') ?: '-'));

$rules = JPATH_SITE . '/components/com_vel/src/Service/ApiRules.php';
$code  = is_file($rules) ? (string) file_get_contents($rules) : '';
$fixed = str_contains($code, 'getActive()') && str_contains($code, 'isFeedMenuItem');
$out('ApiRules.php has the json-feed routing fix: ' . ($fixed ? 'yes' : 'NO'));

if (!$fixed) {
    $problems[] = 'components/com_vel/src/Service/ApiRules.php on this site is the old version (json-feed and /api/v2 URLs are 404 with it). Deploy the new file.';
}

foreach (['Jed\\Component\\Vel\\Site\\View\\Legacyfeed\\JsonView', 'Jed\\Component\\Vel\\Site\\View\\Legacyfeed\\HtmlView'] as $class) {
    $exists = class_exists($class);
    $out($class . ': ' . ($exists ? 'found' : 'MISSING'));

    if (!$exists) {
        $problems[] = $class . ' is missing on this site.';
    }
}

if (\function_exists('opcache_get_status') && \is_array($status = @opcache_get_status(false)) && !empty($status['opcache_enabled'])) {
    $out('OPcache: on, validate_timestamps=' . (ini_get('opcache.validate_timestamps') ? 'on' : 'OFF - changed PHP files are not picked up until PHP is restarted'));
}

// ---- 3. menu items --------------------------------------------------------------------------------
$section('Menu items (site menus, any state)');
$db    = $container->get(DatabaseInterface::class);
$query = $db->getQuery(true)
    ->select($db->quoteName(['m.id', 'm.menutype', 'm.title', 'm.alias', 'm.path', 'm.link', 'm.type', 'm.published', 'm.language', 'm.access', 'm.parent_id', 'e.element']))
    ->from($db->quoteName('#__menu', 'm'))
    ->join('LEFT', $db->quoteName('#__extensions', 'e'), $db->quoteName('e.extension_id') . ' = ' . $db->quoteName('m.component_id'))
    ->where($db->quoteName('m.client_id') . ' = 0')
    ->where('(' . $db->quoteName('m.path') . ' LIKE ' . $db->quote('vulnerable-extensions%')
        . ' OR ' . $db->quoteName('m.alias') . ' LIKE ' . $db->quote('json-feed%')
        . ' OR ' . $db->quoteName('m.link') . ' LIKE ' . $db->quote('%option=com_vel%') . ')')
    ->order($db->quoteName('m.lft'));
$items  = $db->setQuery($query)->loadObjectList();
$states = [1 => 'published', 0 => 'UNPUBLISHED', -2 => 'TRASHED'];

foreach ($items as $item) {
    $out(\sprintf(
        '#%d  %-28s %-12s %-10s /%s',
        $item->id,
        mb_strimwidth($item->title, 0, 28, '…'),
        $item->menutype,
        $states[(int) $item->published] ?? (string) $item->published,
        $item->path
    ));
    $out('      ' . $item->type . ($item->element ? ' ' . $item->element : '') . '  ' . ($item->link ?: '(no link)') . '  language=' . $item->language . ' access=' . $item->access);
}

$holders   = array_values(array_filter($items, static fn ($i) => $i->path === 'vulnerable-extensions'));
$published = array_values(array_filter($holders, static fn ($i) => (int) $i->published === 1));
$vel       = array_values(array_filter($items, static fn ($i) => (int) $i->parent_id === 1 && str_contains((string) $i->link, 'option=com_vel&view=advisories')));
$velNote   = $vel && $vel[0]->path !== 'vulnerable-extensions'
    ? ' The VEL list menu item is #' . $vel[0]->id . ' at /' . $vel[0]->path . ' (the JED Main Menu sample data had to change its alias).'
    : '';
$describe  = static fn ($i) => '#' . $i->id . ' "' . $i->title . '" (' . $i->menutype . ', ' . ($states[(int) $i->published] ?? $i->published) . ', ' . $i->type . ($i->element ? ' ' . $i->element : '') . ')';

if ($holders === []) {
    $problems[] = 'No menu item has the path "vulnerable-extensions", so nothing answers /vulnerable-extensions/...' . $velNote;
} elseif ($published === []) {
    $problems[] = 'The alias "vulnerable-extensions" is held by ' . $describe($holders[0]) . ', which is not published, so /vulnerable-extensions/... is a 404.'
        . $velNote . ' Delete ' . '#' . $holders[0]->id . ' (empty it from the trash) or give the VEL list item the alias "vulnerable-extensions" - or run the JED Main Menu sample data again once it is gone.';
} elseif ($published[0]->type !== 'component' || $published[0]->element !== 'com_vel') {
    $problems[] = '/vulnerable-extensions belongs to ' . $describe($published[0]) . ', not to the VEL list, so /vulnerable-extensions/json-feed is not handled by com_vel.'
        . $velNote . ' Delete (and empty from the trash) or rename #' . $published[0]->id . ', then run the JED Main Menu sample data again.';
}

foreach ($items as $item) {
    if ($item->path === 'vulnerable-extensions/json-feed' && (int) $item->published === 1 && !str_contains((string) $item->link, 'view=legacyfeed')) {
        $problems[] = 'Menu item #' . $item->id . ' "' . $item->title . '" owns /vulnerable-extensions/json-feed but links to ' . $item->link
            . ' - the old placeholder. Run the JED Main Menu sample data again (menu.json must be valid JSON) or change the item to "VEL: JSON Feed (legacy)".';
    }
}

// ---- 4. what the router makes of it ----------------------------------------------------------------
$section('Router');
/** @var SiteRouter $router */
$router = $container->get(SiteRouter::class);

foreach (['vulnerable-extensions/json-feed', 'vulnerable-extensions/api/v2/index.json'] as $path) {
    $uri = new Uri(Uri::root() . ($app->get('sef_rewrite') ? '' : 'index.php/') . $path);

    try {
        $vars = $router->parse($uri, false);
        $out('/' . $path . '  =>  ' . json_encode($vars, JSON_UNESCAPED_SLASHES));

        if (str_ends_with($path, 'json-feed') && (($vars['option'] ?? '') !== 'com_vel' || ($vars['view'] ?? '') !== 'legacyfeed')) {
            $problems[] = '/' . $path . ' is routed to ' . json_encode($vars, JSON_UNESCAPED_SLASHES) . ', not to the feed.';
        }
    } catch (\Throwable $e) {
        $out('/' . $path . '  =>  ' . \get_class($e) . ' (' . $e->getCode() . '): ' . $e->getMessage());
        $problems[] = '/' . $path . ' does not route: ' . $e->getMessage();
    }
}

try {
    $out('Link the site builds for the feed: ' . Route::_('index.php?option=com_vel&view=legacyfeed&format=json', false));
} catch (\Throwable $e) {
    $out('Link the site builds for the feed: ' . \get_class($e) . ': ' . $e->getMessage());
}

// ---- verdict --------------------------------------------------------------------------------------
$section('Result');

if ($problems === []) {
    $out('No problem found: /vulnerable-extensions/json-feed routes to the feed. If the browser still shows a 404, it is');
    $out('not coming from Joomla\'s router: check the web server rewrite rules, any page/CDN cache, and the exact URL.');
} else {
    foreach ($problems as $i => $problem) {
        $out(($i + 1) . '. ' . $problem);
    }
}

$out();
$out('Delete this file now.');
