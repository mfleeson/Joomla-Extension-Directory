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
 * A member's role in an organisation (PRD-09).
 *
 * Both roles look after every listing the organisation owns. Administrators also run the
 * organisation: its details, who belongs to it, and handing its listings to somebody else.
 *
 * @since 4.1.0
 */
enum OrganisationRole: string
{
    case ADMIN  = 'admin';
    case MEMBER = 'member';

    /**
     * @return string  Language key.
     *
     * @since 4.1.0
     */
    public function label(): string
    {
        return 'COM_JED_ORGANISATION_ROLE_' . strtoupper($this->value);
    }
}
