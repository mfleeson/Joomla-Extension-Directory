<?php

/**
 * @package JED
 *
 * @subpackage Organisations
 *
 * @copyright (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Administrator\View\Organisation;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Exception;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;

/**
 * One organisation, its details and its members (PRD-09).
 *
 * @since 4.1.0
 */
class HtmlView extends BaseHtmlView
{
    protected ?Form $form = null;
    protected mixed $item = null;

    /**
     * @param string|null $tpl The template.
     *
     * @return void
     *
     * @throws Exception
     *
     * @since 4.1.0
     */
    public function display($tpl = null): void
    {
        $model      = $this->getModel();
        $this->item = $model->getItem();
        $this->form = $model->getForm();

        if ($this->item === false) {
            throw new Exception(Text::_('COM_JED_ORGANISATION_ERROR_NOT_FOUND'), 404);
        }

        $this->addToolbar();

        parent::display($tpl);
    }

    /**
     * @return void
     *
     * @since 4.1.0
     */
    protected function addToolbar(): void
    {
        Factory::getApplication()->getInput()->set('hidemainmenu', true);

        $isNew = empty($this->item->id);

        ToolbarHelper::title(Text::_($isNew ? 'COM_JED_ORGANISATION_NEW' : 'COM_JED_ORGANISATION_EDIT'), 'users');

        if ($this->getCurrentUser()->authorise('core.edit', 'com_jed') || ($isNew && $this->getCurrentUser()->authorise('core.create', 'com_jed'))) {
            ToolbarHelper::apply('organisation.apply');
            ToolbarHelper::save('organisation.save');
        }

        ToolbarHelper::cancel('organisation.cancel', $isNew ? 'JTOOLBAR_CANCEL' : 'JTOOLBAR_CLOSE');
    }
}
