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

/**
 * Where a membership of an organisation stands (`#__jed_organisation_members.state`, PRD-09).
 *
 * Two ways in, and each needs the *other* side's yes: a user asks (REQUESTED) and an
 * administrator decides, or an administrator asks (INVITED) and the user decides. Only ACTIVE
 * grants anything. The refusals are kept apart so "how many requests did this organisation turn
 * down" and "how many invitations were declined" stay answerable.
 *
 * @since 4.1.0
 */
enum MembershipState: int
{
    case REQUESTED = 0;
    case ACTIVE    = 1;
    case INVITED   = 2;
    case DECLINED  = -1;
    case REJECTED  = -2;

    /**
     * @return bool  Whether the row is waiting for somebody's decision.
     *
     * @since 4.1.0
     */
    public function isOpen(): bool
    {
        return $this === self::REQUESTED || $this === self::INVITED;
    }

    /**
     * @return string  Language key.
     *
     * @since 4.1.0
     */
    public function label(): string
    {
        return 'COM_JED_ORGANISATION_MEMBERSHIP_STATE_' . $this->name;
    }
}
