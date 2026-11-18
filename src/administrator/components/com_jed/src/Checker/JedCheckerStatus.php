<?php

/**
 * @package JED
 *
 * @subpackage Tickets
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Administrator\Checker;

// No direct access.
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Whether JED Checker (com_jedchecker) can be run from the ticket screen, and if not, why not
 * (PRD-07).
 *
 * @since 4.0.0
 */
enum JedCheckerStatus: string
{
    // Installed, enabled, and it has CheckerApi::check().
    case Available = 'available';

    // No com_jedchecker component in #__extensions (files left on disk do not count).
    case NotInstalled = 'not_installed';

    // Installed but switched off in System > Manage > Extensions.
    case Disabled = 'disabled';

    // Installed and enabled, but a version without the CheckerApi entry point.
    case Unsupported = 'unsupported';

    /**
     * The language key of the message that explains this status on the ticket screen.
     *
     * @return string
     *
     * @since 4.0.0
     */
    public function messageKey(): string
    {
        return match ($this) {
            self::Available    => '',
            self::NotInstalled => 'COM_JED_JEDCHECKER_NOT_INSTALLED',
            self::Disabled     => 'COM_JED_JEDCHECKER_DISABLED',
            self::Unsupported  => 'COM_JED_JEDCHECKER_UNSUPPORTED',
        };
    }
}
