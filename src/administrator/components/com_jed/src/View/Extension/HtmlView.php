<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Administrator\View\Extension;

// No direct access
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Jed\Component\Jed\Administrator\Helper\JedHelper;
use Jed\Component\Jed\Administrator\Listing\BlockReasons;
use Jed\Component\Jed\Administrator\Model\ExtensionModel;
use Jed\Component\Jed\Administrator\Organisation\OrganisationService;
use Jed\Component\Jed\Administrator\Transfer\TransferService;
use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use stdClass;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\Registry\Registry;

/**
 * View class for a single Extension.
 *
 * @since 4.0.0
 */
class HtmlView extends BaseHtmlView
{
    protected Registry $state;
    protected mixed $item;
    protected ?Form $form = null;
    protected mixed $forms;
    protected mixed $extensionimages;
    protected mixed $extensionimage;
    protected mixed $extensionform;
    protected ?stdClass $historyItem = null;
    protected array $images          = [];
    protected array $files           = [];
    protected array $categories      = [];
    protected ?object $organisation  = null;
    protected array $history         = [];
    protected array $reviews         = [];

    protected ?object $compareLeft       = null;
    protected ?object $compareRight      = null;
    protected int $compareExtensionId    = 0;
    protected ?int $compareLeftId        = null;
    protected ?int $compareRightId       = null;
    protected ?int $compareApprovableId  = null;

    /**
     * Whether the right-hand revision of the compare page is the one awaiting a decision, and can
     * therefore be rejected (an older revision can only be approved - that is a restore).
     *
     * @var bool
     *
     * @since 4.0.0
     */
    protected bool $compareCanReject = false;

    /**
     * The reason codes offered by the compare page's Reject panel, code => label.
     *
     * @var array<string, string>
     *
     * @since 4.0.0
     */
    protected array $rejectReasons = [];

    /**
     * What the Ownership tab shows: the current owner (from the live row, not the revision) and any
     * open handover. Null when the tab is not shown - a new listing, or no Force Ownership
     * Transfer permission.
     *
     * @var array{owner: ?\Joomla\CMS\User\User, open: ?array, openTo: ?\Joomla\CMS\User\User}|null
     *
     * @since 4.0.0
     */
    protected ?array $ownership = null;

    /**
     * Display the view
     *
     * @param string $tpl Template name
     *
     * @return void
     *
     * @throws Exception
     *
     * @since 4.0.0
     */
    public function display($tpl = null): void
    {
        /** @var ExtensionModel $model */
        $model = $this->getModel();
        $model->setUseExceptions(true);

        // DisplayController::display() never passes $tpl - it selects the template
        // via setLayout() instead, so branch on the actual layout, not $tpl.
        $layout = $this->getLayout();

        if ($layout === 'historylist') {
            $this->history = $model->getHistory();
            $this->item    = $model->getItem();
            // Rendered both as a quick-view modal fragment (tmpl=component, which
            // suppresses the toolbar/chrome regardless of this call) and as a full
            // standalone page (with the Compare toolbar button) when opened directly.
            $this->addToolbar();
            parent::display($tpl);
            return;
        }

        if ($layout === 'compare') {
            $input       = Factory::getApplication()->getInput();
            $extensionId = $input->getInt('id');
            $left        = $input->getInt('left', 0) ?: null;
            $right       = $input->getInt('right', 0) ?: null;

            // Only field/fieldset metadata is needed here (labels, grouping) - not
            // form data, so loadData is skipped.
            $this->form = $model->getForm([], false) ?: null;

            [$this->compareLeft, $this->compareRight, $this->compareRightId] =
                $model->getCompareItems($extensionId, $left, $right);
            $this->compareExtensionId = $extensionId;
            $this->compareLeftId      = $left;
            // "right" always resolves to a history row (explicit, or defaulted to
            // the latest one) - Approve always promotes whichever history row that
            // is, regardless of what "left" represents.
            $this->compareApprovableId = $this->compareRightId;
            $this->compareCanReject    = $this->compareApprovableId !== null
                && $this->compareApprovableId === $model->getPendingHistoryId($extensionId);

            if ($this->compareCanReject) {
                $this->rejectReasons = BlockReasons::options(Factory::getContainer()->get(DatabaseInterface::class));
            }

            $this->addToolbar();
            parent::display($tpl);
            return;
        }

        $this->item = $model->getItem();

        // A soft-deleted listing is shown through a view that has no form and no save task -
        // read-only by absence rather than by a disabled input (8.8).
        if ((int) ($this->item->deleted ?? 0) === 1) {
            $this->setLayout('deleted');
            $this->images      = $model->getImages();
            $this->history     = $model->getHistory();
            $this->addToolbar();
            parent::display(null);

            return;
        }

        $this->form        = $model->getForm();
        $this->state       = $model->getState();
        $this->images      = $model->getImages();
        $this->files       = $model->getFiles();
        $this->categories  = $model->getCategories();
        $this->organisation = $model->getOrganisation((int) ($this->item->extension_id ?? 0));
        $this->history     = $model->getHistory();
        $this->reviews     = $model->getReviews();
        $this->ownership   = $this->loadOwnership((int) ($this->item->extension_id ?? 0));

        $this->addToolbar();
        parent::display($tpl);
    }

    /**
     * The data of the Ownership tab, or null when the tab is not offered.
     *
     * @param int $extensionId The listing.
     *
     * @return array|null
     *
     * @since 4.0.0
     */
    private function loadOwnership(int $extensionId): ?array
    {
        if ($extensionId <= 0 || !$this->getCurrentUser()->authorise('jed.transfer.force', 'com_jed')) {
            return null;
        }

        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $users = Factory::getContainer()->get(UserFactoryInterface::class);

        $ownerId = (int) $db->setQuery(
            $db->getQuery(true)
                ->select($db->quoteName('owner'))
                ->from($db->quoteName('#__jed_extensions'))
                ->where($db->quoteName('id') . ' = :id')
                ->bind(':id', $extensionId, ParameterType::INTEGER)
        )->loadResult();

        $open = (new TransferService($db))->getOpenTransfer($extensionId);

        $organisations = new OrganisationService($db);
        $organisation  = $organisations->organisationIdOfExtension($extensionId);

        return [
            'owner'        => $ownerId > 0 ? $users->loadUserById($ownerId) : null,
            'organisation' => $organisation !== null ? $organisations->find($organisation) : null,
            'open'         => $open,
            'openTo'       => $open !== null ? $users->loadUserById((int) $open['to_user_id']) : null,
        ];
    }

    /**
     * Add the page title and toolbar.
     *
     * @return void
     *
     * @throws Exception
     *
     * @since 4.0.0
     */
    protected function addToolbar(): void
    {
        Factory::getApplication()->input->set('hidemainmenu', true);

        if ($this->getLayout() === 'compare') {
            ToolbarHelper::title(Text::_('COM_JED_EXTENSION_COMPARE_LABEL'), 'generic');
            ToolbarHelper::back('JTOOLBAR_BACK', Route::_('index.php?option=com_jed&view=extensions', false));

            if ($this->compareApprovableId && JedHelper::getActions('com_jed')->get('jed.approve')) {
                ToolbarHelper::custom('extension.approve', 'publish.png', 'publish_f2.png', 'COM_JED_APPROVE_LABEL', false);

                // Reject sits next to Approve, but only for the revision actually awaiting a
                // decision. It submits the reason and notes from the Decision panel on the page;
                // tmpl/extension/compare.php refuses to submit without a reason.
                if ($this->compareCanReject) {
                    ToolbarHelper::custom('extension.reject', 'cancel-circle', 'cancel-circle', 'COM_JED_REJECT_LABEL', false);
                }
            }

            return;
        }

        if ($this->getLayout() === 'historylist') {
            ToolbarHelper::title(Text::_('COM_JED_EXTENSION_HISTORY_LABEL'), 'generic');
            ToolbarHelper::back('JTOOLBAR_BACK', Route::_('index.php?option=com_jed&view=extensions', false));

            if (JedHelper::getActions('com_jed')->get('core.edit')) {
                // Not a list-check button: the checkbox selection isn't tracked via
                // the standard cb/boxchecked convention - ExtensionController::
                // compareHistory() validates the selection itself.
                ToolbarHelper::custom('extension.compareHistory', 'contract.png', 'contract.png', 'COM_JED_EXTENSION_HISTORY_COMPARE_LABEL', false);
            }

            return;
        }

        $user  = $this->getCurrentUser();
        $isNew = ($this->item->id == 0);

        if (isset($this->item->checked_out)) {
            $checkedOut = !($this->item->checked_out == 0 || $this->item->checked_out == $user->id);
        } else {
            $checkedOut = false;
        }

        $canDo = JedHelper::getActions('com_jed');

        ToolbarHelper::title(Text::_('COM_JED_EXTENSION'), "generic");

        // 8.8 is explicit that a soft-deleted listing must be read-only because the save action
        // is *absent*, not because a form was disabled while its controller stayed reachable.
        // The 'deleted' layout has no form and therefore no save task to post to; the only
        // action offered is Restore.
        if ($this->getLayout() === 'deleted') {
            ToolbarHelper::back('JTOOLBAR_BACK', Route::_('index.php?option=com_jed&view=extensions', false));

            if ($canDo->get('core.delete')) {
                ToolbarHelper::custom('extension.restore', 'undo-2', 'undo-2', 'COM_JED_EXTENSION_RESTORE_LABEL', false);
            }

            return;
        }

        // If not checked out, can save the item.
        if (!$checkedOut && ($canDo->get('core.edit') || ($canDo->get('core.create')))) {
            ToolbarHelper::apply('extension.apply', 'JTOOLBAR_APPLY');
            ToolbarHelper::save('extension.save', 'JTOOLBAR_SAVE');
        }

        // Blocking never writes `state`, so these sit apart from the publish buttons on purpose
        // (CLAUDE.md invariant, 4.8). Block posts the "blocking" fieldset with the form; the
        // model refuses a block without a known reason code.
        if (!$isNew && $canDo->get('jed.block')) {
            if ((int) ($this->item->blocked ?? 0) === 1) {
                ToolbarHelper::custom('extension.unblock', 'unlock', 'unlock', 'COM_JED_EXTENSION_UNBLOCK_LABEL', false);
            } else {
                ToolbarHelper::custom('extension.block', 'lock', 'lock', 'COM_JED_EXTENSION_BLOCK_LABEL', false);
            }
        }

        if (!$isNew && $canDo->get('core.delete')) {
            ToolbarHelper::custom('extension.softDelete', 'trash', 'trash', 'COM_JED_EXTENSION_SOFT_DELETE_LABEL', false);
        }

        if (!$checkedOut && ($canDo->get('core.create'))) {
            ToolbarHelper::custom('extension.save2new', 'save-new.png', 'save-new_f2.png', 'JTOOLBAR_SAVE_AND_NEW', false);
        }

        // If an existing item, can save to a copy.
        if (!$isNew && $canDo->get('core.create')) {
            ToolbarHelper::custom('extension.save2copy', 'save-copy.png', 'save-copy_f2.png', 'JTOOLBAR_SAVE_AS_COPY', false);
        }

        // If an existing item, queue a manual, per-extension score recalculation job.
        if (!$isNew && !$checkedOut && $canDo->get('core.edit')) {
            ToolbarHelper::custom(
                'extension.recalculateScore',
                'refresh.png',
                'refresh.png',
                'COM_JED_TOOLBAR_RECALCULATE_SCORE',
                false
            );
        }

        if (empty($this->item->id)) {
            ToolbarHelper::cancel('extension.cancel', 'JTOOLBAR_CANCEL');
        } else {
            ToolbarHelper::cancel('extension.cancel', 'JTOOLBAR_CLOSE');
        }
    }
}
