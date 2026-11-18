<?php

/**
 * @package JED
 *
 * @subpackage VEL
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Vel\Site\View\Legacyfeed;

// No direct access
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * The legacy feed is JSON whatever format the request asked for.
 *
 * /vulnerable-extensions/json-feed always parses to format=json, but a "JSON feed" menu item
 * saved without the format (or a hand-written index.php?option=com_vel&view=legacyfeed link)
 * arrives here as an HTML request. Rather than a "view not found" error, answer with the same
 * JSON document: the view sends its own headers and closes the application, so no HTML page
 * is ever rendered around it.
 *
 * @since 4.0.0
 */
class HtmlView extends JsonView
{
}
