<?php

/**
 * @package JED
 *
 * @subpackage Organisations
 *
 * @copyright (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Site\View\Organisations;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Jed\Component\Jed\Administrator\Organisation\MembershipState;
use Jed\Component\Jed\Site\Helper\JedHelper;
use Jed\Component\Jed\Site\Model\OrganisationsModel;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Route;

/**
 * My Organisations (PRD-09).
 *
 * @since 4.1.0
 */
class HtmlView extends BaseHtmlView
{
    /**
     * @var object[]  Active memberships.
     *
     * @since 4.1.0
     */
    protected array $memberships = [];

    /**
     * @var object[]  Invitations waiting for the user.
     *
     * @since 4.1.0
     */
    protected array $invitations = [];

    /**
     * @var object[]  The user's own open requests.
     *
     * @since 4.1.0
     */
    protected array $requests = [];

    /**
     * @var object[]  Organisations the user could ask to join.
     *
     * @since 4.1.0
     */
    protected array $joinable = [];

    /**
     * @param string|null $tpl The template.
     *
     * @return void
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

        /** @var OrganisationsModel $model */
        $model = $this->getModel();

        $this->memberships = $model->getMine([MembershipState::ACTIVE]);
        $this->invitations = $model->getMine([MembershipState::INVITED]);
        $this->requests    = $model->getMine([MembershipState::REQUESTED]);
        $this->joinable    = $model->getJoinable();

        $this->getDocument()->setTitle(Text::_('COM_JED_ORGANISATIONS_MY'));
        $this->getDocument()->setMetaData('robots', 'noindex, nofollow');

        parent::display($tpl);
    }
}
