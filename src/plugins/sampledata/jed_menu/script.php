<?php

/**
 * @package     JED
 * @subpackage  Sampledata.jed_menu
 *
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Factory;
use Joomla\CMS\Installer\Installer;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Installer\InstallerScriptInterface;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

/**
 * Installer script for plg_sampledata_jed_menu.
 *
 * This plugin used to be called "jed2" (plugins/sampledata/jed2). A package update installs jed_menu as a new
 * extension but leaves the old one registered and on disk, so the Sample Data panel would offer the JED menu twice.
 * After installing, this carries the old plugin's settings over (enabled, access level, ordering, parameters - a new
 * plugin is installed disabled, which would take it off the panel) and then uninstalls the old one.
 *
 * @since  4.0.0
 */
return new class () implements InstallerScriptInterface {
    /**
     * The element the plugin had before it was renamed.
     *
     * @since  4.0.0
     */
    private const OLD_ELEMENT = 'jed2';

    /**
     * This plugin's element.
     *
     * @since  4.0.0
     */
    private const NEW_ELEMENT = 'jed_menu';

    /**
     * @param   InstallerAdapter  $adapter  The adapter calling this method.
     *
     * @return  boolean
     *
     * @since   4.0.0
     */
    public function install(InstallerAdapter $adapter): bool
    {
        return true;
    }

    /**
     * @param   InstallerAdapter  $adapter  The adapter calling this method.
     *
     * @return  boolean
     *
     * @since   4.0.0
     */
    public function update(InstallerAdapter $adapter): bool
    {
        return true;
    }

    /**
     * @param   InstallerAdapter  $adapter  The adapter calling this method.
     *
     * @return  boolean
     *
     * @since   4.0.0
     */
    public function uninstall(InstallerAdapter $adapter): bool
    {
        return true;
    }

    /**
     * @param   string            $type     install, update, discover_install or uninstall.
     * @param   InstallerAdapter  $adapter  The adapter calling this method.
     *
     * @return  boolean
     *
     * @since   4.0.0
     */
    public function preflight(string $type, InstallerAdapter $adapter): bool
    {
        return true;
    }

    /**
     * Replace the old "jed2" plugin, if it is still installed.
     *
     * Never fails the installation: if the old plugin cannot be removed, the administrator is told to remove it.
     *
     * @param   string            $type     install, update, discover_install or uninstall.
     * @param   InstallerAdapter  $adapter  The adapter calling this method.
     *
     * @return  boolean
     *
     * @since   4.0.0
     */
    public function postflight(string $type, InstallerAdapter $adapter): bool
    {
        if (!\in_array($type, ['install', 'update', 'discover_install'], true)) {
            return true;
        }

        $app = Factory::getApplication();

        try {
            $db  = Factory::getContainer()->get(DatabaseInterface::class);
            $old = $this->findPlugin($db, self::OLD_ELEMENT);

            if ($old === null) {
                return true;
            }

            $new = $this->findPlugin($db, self::NEW_ELEMENT);

            if ($new !== null) {
                $this->copySettings($db, $old, $new);
            }

            $installer = new Installer();
            $installer->setDatabase($db);

            // The package is replacing one of its own plugins, so it may remove it like a package uninstall would.
            $installer->setPackageUninstall(true);

            if ($installer->uninstall('plugin', (int) $old->extension_id)) {
                $app->enqueueMessage(Text::_('PLG_SAMPLEDATA_JED_MENU_INSTALL_REPLACED_OLD'));
            } else {
                $app->enqueueMessage(Text::_('PLG_SAMPLEDATA_JED_MENU_INSTALL_OLD_NOT_REMOVED'), 'warning');
            }
        } catch (\Throwable $e) {
            $app->enqueueMessage(Text::_('PLG_SAMPLEDATA_JED_MENU_INSTALL_OLD_NOT_REMOVED') . ' ' . $e->getMessage(), 'warning');
        }

        return true;
    }

    /**
     * A sample data plugin's #__extensions row.
     *
     * @param   DatabaseInterface  $db       The database.
     * @param   string             $element  The plugin element.
     *
     * @return  object|null
     *
     * @since   4.0.0
     */
    private function findPlugin(DatabaseInterface $db, string $element): ?object
    {
        $query = $db->createQuery()
            ->select($db->quoteName(['extension_id', 'enabled', 'access', 'ordering', 'params']))
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
            ->where($db->quoteName('folder') . ' = ' . $db->quote('sampledata'))
            ->where($db->quoteName('element') . ' = :element')
            ->bind(':element', $element);

        return $db->setQuery($query)->loadObject() ?: null;
    }

    /**
     * Give the new plugin the old one's enabled state, access level, ordering and parameters.
     *
     * @param   DatabaseInterface  $db   The database.
     * @param   object             $old  The old plugin's row.
     * @param   object             $new  The new plugin's row.
     *
     * @return  void
     *
     * @since   4.0.0
     */
    private function copySettings(DatabaseInterface $db, object $old, object $new): void
    {
        $enabled  = (int) $old->enabled;
        $access   = (int) $old->access;
        $ordering = (int) $old->ordering;
        $params   = (string) ($old->params ?: '{}');
        $id       = (int) $new->extension_id;

        $db->setQuery(
            $db->createQuery()
                ->update($db->quoteName('#__extensions'))
                ->set($db->quoteName('enabled') . ' = :enabled')
                ->set($db->quoteName('access') . ' = :access')
                ->set($db->quoteName('ordering') . ' = :ordering')
                ->set($db->quoteName('params') . ' = :params')
                ->where($db->quoteName('extension_id') . ' = :id')
                ->bind(':enabled', $enabled, ParameterType::INTEGER)
                ->bind(':access', $access, ParameterType::INTEGER)
                ->bind(':ordering', $ordering, ParameterType::INTEGER)
                ->bind(':params', $params)
                ->bind(':id', $id, ParameterType::INTEGER)
        )->execute();
    }
};
