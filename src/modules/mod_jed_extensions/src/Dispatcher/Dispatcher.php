<?php

/**
 * @package JED
 *
 * @subpackage mod_jed_extensions
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Module\Extensions\Site\Dispatcher;

use Jed\Module\Extensions\Site\Helper\ExtensionsHelper;
use Joomla\CMS\Dispatcher\AbstractModuleDispatcher;
use Joomla\CMS\Helper\HelperFactoryAwareInterface;
use Joomla\CMS\Helper\HelperFactoryAwareTrait;
use Joomla\CMS\Helper\ModuleHelper;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Dispatcher class for mod_jed_extensions.
 *
 * @since 4.0.0
 */
class Dispatcher extends AbstractModuleDispatcher implements HelperFactoryAwareInterface
{
    use HelperFactoryAwareTrait;

    /**
     * Runs the dispatcher.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function dispatch()
    {
        $this->loadLanguage();

        $displayData = $this->getLayoutData();

        $loader = static function (array $displayData) {
            extract($displayData);

            /**
             * Extracted variables
             * -------------------
             * @var \stdClass $module
             * @var Registry  $params
             * @var array     $extensions
             */

            require ModuleHelper::getLayoutPath('mod_jed_extensions', $params->get('layout', 'default'));
        };

        $loader($displayData);
    }

    /**
     * Load the module's language, and com_jed's.
     *
     * The module renders the shared extension card (layouts/cards/extension.php) and the "Includes"
     * and version labels from com_jed's helpers. All of those use com_jed strings, and a module is
     * shown on any page - on one that is not a JED page com_jed's language is not loaded, and the
     * cards came out as raw keys ("COM_JED_CARD_BY_DEVELOPER"). Site side, global language folder
     * first and the component's own folder second, like Joomla loads a component.
     *
     * @return void
     *
     * @since 4.0.0
     */
    protected function loadLanguage()
    {
        parent::loadLanguage();

        $language = $this->getApplication()->getLanguage();

        $language->load('com_jed', JPATH_SITE)
            || $language->load('com_jed', JPATH_SITE . '/components/com_jed');
    }

    /**
     * Returns the layout data.
     *
     * @return array
     *
     * @since 4.0.0
     */
    protected function getLayoutData()
    {
        $data = parent::getLayoutData();

        /** @var ExtensionsHelper $helper */
        $helper             = $this->getHelperFactory()->getHelper('ExtensionsHelper');
        $data['extensions'] = $helper->getExtensions($data['params']);

        return $data;
    }
}
