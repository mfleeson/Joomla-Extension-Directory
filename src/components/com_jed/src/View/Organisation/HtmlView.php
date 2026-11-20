<?php

/**
 * @package JED
 *
 * @subpackage Organisations
 *
 * @copyright (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Site\View\Organisation;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Exception;
use Jed\Component\Jed\Site\Helper\JedHelper;
use Jed\Component\Jed\Site\Model\OrganisationModel;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Route;

/**
 * One organisation: the members' page (default layout) and the create/edit form (edit layout).
 *
 * @since 4.1.0
 */
class HtmlView extends BaseHtmlView
{
    /**
     * @var object|null
     *
     * @since 4.1.0
     */
    protected ?object $item = null;

    /**
     * @var Form|null
     *
     * @since 4.1.0
     */
    protected ?Form $form = null;

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
        $app = Factory::getApplication();

        if (!JedHelper::isLoggedIn()) {
            $app->enqueueMessage(Text::_('COM_JED_ORGANISATION_LOGIN_REQUIRED'), 'warning');
            $app->redirect(Route::_(JedHelper::getLoginlink(), false));

            return;
        }

        /** @var OrganisationModel $model */
        $model      = $this->getModel();
        $this->item = $model->getItem();

        if ($this->getLayout() === 'edit') {
            // Editing an existing organisation is for its administrators; a new one for anybody
            // logged in (OrganisationService::create() checks their privileges).
            if ($this->item !== null && !$this->item->viewer->isAdmin) {
                throw new Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
            }

            $this->form = $model->getForm();
        } elseif ($this->item === null) {
            throw new Exception(Text::_('COM_JED_ORGANISATION_ERROR_NOT_FOUND'), 404);
        }

        $this->getDocument()->setTitle($this->item !== null ? (string) $this->item->title : Text::_('COM_JED_ORGANISATION_NEW'));
        $this->getDocument()->setMetaData('robots', 'noindex, nofollow');

        parent::display($tpl);
    }
}
