<?php

/**
 * @package JED
 *
 * @subpackage Organisations
 *
 * @copyright (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Administrator\View\Organisations;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Pagination\Pagination;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\Registry\Registry;

/**
 * The Organisations list (PRD-09).
 *
 * @since 4.1.0
 */
class HtmlView extends BaseHtmlView
{
    public ?Form $filterForm    = null;
    public array $activeFilters = [];
    protected array $items      = [];
    protected Pagination $pagination;
    protected Registry $state;

    /**
     * @param string|null $tpl The template.
     *
     * @return void
     *
     * @since 4.1.0
     */
    public function display($tpl = null): void
    {
        $model = $this->getModel();

        $this->items         = (array) $model->getItems();
        $this->pagination    = $model->getPagination();
        $this->state         = $model->getState();
        $this->filterForm    = $model->getFilterForm();
        $this->activeFilters = $model->getActiveFilters();

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
        $user = $this->getCurrentUser();

        ToolbarHelper::title(Text::_('COM_JED_TITLE_ORGANISATIONS'), 'users');

        if ($user->authorise('core.create', 'com_jed')) {
            ToolbarHelper::addNew('organisation.add');
        }

        if ($user->authorise('core.edit.state', 'com_jed')) {
            ToolbarHelper::publish('organisations.publish', 'COM_JED_ORGANISATION_ENABLE', true);
            ToolbarHelper::unpublish('organisations.unpublish', 'COM_JED_ORGANISATION_DISABLE', true);
            ToolbarHelper::checkin('organisations.checkin');
        }

        if ($user->authorise('core.delete', 'com_jed')) {
            ToolbarHelper::deleteList('COM_JED_ORGANISATIONS_DELETE_CONFIRM', 'organisations.delete');
        }

        if ($user->authorise('core.admin', 'com_jed')) {
            ToolbarHelper::custom('organisations.migrate', 'refresh', '', 'COM_JED_ORGANISATIONS_MIGRATE', false);
            ToolbarHelper::preferences('com_jed');
        }
    }
}
