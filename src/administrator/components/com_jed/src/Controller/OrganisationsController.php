<?php

/**
 * @package JED
 *
 * @subpackage Organisations
 *
 * @copyright (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Administrator\Controller;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Jed\Component\Jed\Administrator\Organisation\OrganisationMigration;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\AdminController;
use Joomla\CMS\Router\Route;
use Joomla\Database\DatabaseInterface;

/**
 * The Organisations list (PRD-09): publish/unpublish (active/disabled), check-in and delete come
 * from AdminController; the model refuses deleting an organisation that still owns listings.
 *
 * @since 4.1.0
 */
class OrganisationsController extends AdminController
{
    /**
     * @param string $name   The model name.
     * @param string $prefix The class prefix.
     * @param array  $config Configuration.
     *
     * @return \Joomla\CMS\MVC\Model\BaseDatabaseModel
     *
     * @since 4.1.0
     */
    public function getModel($name = 'Organisation', $prefix = 'Administrator', $config = ['ignore_request' => true])
    {
        return parent::getModel($name, $prefix, $config);
    }

    /**
     * Give every owner without an organisation one, and convert their maintainers (PRD-09 R14).
     *
     * The same migration script.php runs on install and update. Offered here because listings can
     * arrive later - the JED3 import (plg_sampledata_jed_migrate) writes owners and maintainers
     * without organisations - and it only ever touches listings that still have none.
     *
     * @return void
     *
     * @since 4.1.0
     */
    public function migrate(): void
    {
        $this->checkToken();

        $back = Route::_('index.php?option=com_jed&view=organisations', false);

        if (!$this->app->getIdentity()->authorise('core.admin', 'com_jed')) {
            $this->setRedirect($back, Text::_('JERROR_ALERTNOAUTHOR'), 'error');

            return;
        }

        $report = (new OrganisationMigration(Factory::getContainer()->get(DatabaseInterface::class)))->run();

        $this->setRedirect($back, Text::sprintf(
            'COM_JED_ORGANISATIONS_MIGRATED',
            $report['owners'],
            $report['listings'],
            $report['members'],
            $report['requests'],
            $report['skipped']
        ));
    }
}
