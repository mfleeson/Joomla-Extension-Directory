<?php

/**
 * @package JED
 *
 * @subpackage VEL
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Vel\Site\Helper;

// No direct access.
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Jed\Component\Vel\Administrator\Enum\AdvisoryStatus;
use Jed\Component\Vel\Administrator\Service\AdvisorySerializer;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

/**
 * Small site-side conveniences.
 *
 * @since 4.0.0
 */
final class VelHelper
{
    /**
     * @param object $advisory An advisory row with id and alias.
     * @param bool   $xhtml    Whether to HTML-escape the URL (false for redirects and headers).
     *
     * @return string  The SEF route.
     *
     * @since 4.0.0
     */
    public static function route(object $advisory, bool $xhtml = true): string
    {
        return Route::_('index.php?option=com_vel&view=advisory&id=' . (int) $advisory->id . ':' . (string) $advisory->alias, $xhtml);
    }

    /**
     * A tab of the public list: one of the advisory statuses or "all". Anything else - a typo in a
     * URL, a menu item saved before a status was renamed - is the default tab, "live".
     *
     * Shared by the list model, which reads it from the request or the menu item's "Default tab",
     * and the list view, which needs to know which tab each list menu item opens on.
     *
     * @param string|null $status The requested tab.
     *
     * @return string
     *
     * @since 4.0.0
     */
    public static function listStatus(?string $status): string
    {
        $status = strtolower(trim((string) $status));

        return $status === 'all' || AdvisoryStatus::tryFrom($status) !== null ? $status : AdvisoryStatus::LIVE->value;
    }

    /**
     * @return AdvisorySerializer  Configured for this site.
     *
     * @since 4.0.0
     */
    public static function serializer(): AdvisorySerializer
    {
        return new AdvisorySerializer(rtrim(Uri::root(), '/'), self::listPath());
    }

    /**
     * The path of the advisories menu item, e.g. "/vulnerable-extensions".
     *
     * @return string
     *
     * @since 4.0.0
     */
    public static function listPath(): string
    {
        static $path = null;

        if ($path === null) {
            $route = Route::_('index.php?option=com_vel&view=advisories', false);
            $base  = Uri::base(true);
            $path  = '/' . trim(str_starts_with($route, $base) ? substr($route, \strlen($base)) : $route, '/');
            $path  = preg_replace('~^/index\.php~', '', $path) ?? $path;
            $path  = preg_replace('/\?.*$/', '', $path) ?? $path;

            if ($path === '/' || $path === '') {
                $path = '/vulnerable-extensions';
            }
        }

        return $path;
    }

    /**
     * @param string $key      An option.
     * @param mixed  $fallback Default.
     *
     * @return mixed
     *
     * @since 4.0.0
     */
    public static function option(string $key, mixed $fallback = null): mixed
    {
        $value = ComponentHelper::getParams('com_vel')->get($key, null);

        return $value === null || $value === '' ? $fallback : $value;
    }

    /**
     * Is the legacy JSON feed (/vulnerable-extensions/json-feed) being served? It is unless
     * "Serve the legacy JSON feed" is switched off or its sunset date has passed.
     *
     * @return bool
     *
     * @since 4.0.0
     */
    public static function legacyFeedAvailable(): bool
    {
        if ((int) self::option('legacy_feed_enabled', 1) !== 1) {
            return false;
        }

        $sunset = (string) (self::option('legacy_feed_sunset_date') ?? '');

        return $sunset === '' || strtotime($sunset) === false || strtotime($sunset) >= time();
    }
}
