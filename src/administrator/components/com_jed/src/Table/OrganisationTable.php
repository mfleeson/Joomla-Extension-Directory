<?php

/**
 * @package JED
 *
 * @subpackage Organisations
 *
 * @copyright (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Administrator\Table;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseDriver;

/**
 * `#__jed_organisations` (PRD-09).
 *
 * Used by the backend list for check-in and publish/unpublish (the `state` carrier). Creating
 * and editing go through OrganisationService, which generates the UID and alias and owns the
 * membership rows - see Model\OrganisationModel::save().
 *
 * @since 4.1.0
 */
class OrganisationTable extends Table
{
    /**
     * @param DatabaseDriver $db The database.
     *
     * @since 4.1.0
     */
    public function __construct(DatabaseDriver $db)
    {
        $this->typeAlias = 'com_jed.organisation';

        parent::__construct('#__jed_organisations', 'id', $db);

        $this->setColumnAlias('published', 'state');
    }
}
