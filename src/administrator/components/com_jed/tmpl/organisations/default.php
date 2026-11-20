<?php

/**
 * @package JED
 *
 * @subpackage Organisations
 *
 * @copyright (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

// No direct access
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;

/** @var \Jed\Component\Jed\Administrator\View\Organisations\HtmlView $this */

HTMLHelper::_('behavior.multiselect');

$listOrder = $this->escape($this->state->get('list.ordering'));
$listDirn  = $this->escape($this->state->get('list.direction'));
?>
<form action="<?php echo Route::_('index.php?option=com_jed&view=organisations'); ?>" method="post" name="adminForm" id="adminForm">
    <div class="row">
        <div class="col-md-12">
            <div id="j-main-container" class="j-main-container">
                <?php echo LayoutHelper::render('joomla.searchtools.default', ['view' => $this]); ?>

                <?php if (empty($this->items)) : ?>
                    <div class="alert alert-info"><?php echo Text::_('JGLOBAL_NO_MATCHING_RESULTS'); ?></div>
                <?php else : ?>
                    <table class="table" id="organisationList">
                        <caption class="visually-hidden"><?php echo Text::_('COM_JED_TITLE_ORGANISATIONS'); ?></caption>
                        <thead>
                            <tr>
                                <td class="w-1 text-center"><?php echo HTMLHelper::_('grid.checkall'); ?></td>
                                <th scope="col" class="w-1 text-center"><?php echo Text::_('JSTATUS'); ?></th>
                                <th scope="col"><?php echo HTMLHelper::_('searchtools.sort', 'COM_JED_ORGANISATION_FIELD_TITLE_LABEL', 'a.title', $listDirn, $listOrder); ?></th>
                                <th scope="col"><?php echo Text::_('COM_JED_ORGANISATION_UID'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_JED_ORGANISATION_PRIMARY_CONTACT'); ?></th>
                                <th scope="col" class="text-center"><?php echo Text::_('COM_JED_ORGANISATION_MEMBERS'); ?></th>
                                <th scope="col" class="text-center"><?php echo Text::_('COM_JED_ORGANISATION_REQUESTS_HEADING'); ?></th>
                                <th scope="col" class="text-center"><?php echo Text::_('COM_JED_ORGANISATION_LISTINGS'); ?></th>
                                <th scope="col" class="w-5 d-none d-md-table-cell"><?php echo HTMLHelper::_('searchtools.sort', 'JGRID_HEADING_ID', 'a.id', $listDirn, $listOrder); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($this->items as $i => $item) : ?>
                                <tr>
                                    <td class="text-center"><?php echo HTMLHelper::_('grid.id', $i, $item->id, false, 'cid', 'cb', $item->title); ?></td>
                                    <td class="text-center">
                                        <?php echo HTMLHelper::_('jgrid.published', (int) $item->state, $i, 'organisations.', $this->getCurrentUser()->authorise('core.edit.state', 'com_jed')); ?>
                                    </td>
                                    <th scope="row">
                                        <?php if ($item->checked_out) : ?>
                                            <?php echo HTMLHelper::_('jgrid.checkedout', $i, '', $item->checked_out_time, 'organisations.', true); ?>
                                        <?php endif; ?>
                                        <a href="<?php echo Route::_('index.php?option=com_jed&task=organisation.edit&id=' . (int) $item->id); ?>">
                                            <?php echo $this->escape($item->title); ?>
                                        </a>
                                        <?php if ((int) $item->is_personal === 1) : ?>
                                            <span class="badge bg-secondary"><?php echo Text::_('COM_JED_ORGANISATIONS_FILTER_KIND_PERSONAL'); ?></span>
                                        <?php endif; ?>
                                        <div class="small text-muted"><?php echo $this->escape($item->contact_email); ?></div>
                                    </th>
                                    <td><code><?php echo $this->escape($item->uid); ?></code></td>
                                    <td><?php echo $this->escape((string) $item->primary_name); ?></td>
                                    <td class="text-center"><?php echo (int) $item->member_count; ?></td>
                                    <td class="text-center">
                                        <?php if ((int) $item->request_count > 0) : ?>
                                            <span class="badge bg-warning text-dark"><?php echo (int) $item->request_count; ?></span>
                                        <?php else : ?>
                                            0
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <a href="<?php echo Route::_('index.php?option=com_jed&view=extensions&filter[search]=' . urlencode('org:' . $item->uid)); ?>"><?php echo (int) $item->listing_count; ?></a>
                                    </td>
                                    <td class="d-none d-md-table-cell"><?php echo (int) $item->id; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <?php echo $this->pagination->getListFooter(); ?>
                <?php endif; ?>

                <input type="hidden" name="task" value="">
                <input type="hidden" name="boxchecked" value="0">
                <?php echo HTMLHelper::_('form.token'); ?>
            </div>
        </div>
    </div>
</form>
