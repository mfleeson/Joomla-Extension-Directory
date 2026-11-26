<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

// No direct access
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Jed\Component\Jed\Site\Helper\JedHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;

/** @var \Jed\Component\Jed\Site\View\Reviewform\HtmlView $this */

$wa = $this->getDocument()->getWebAssetManager();
$wa->getRegistry()->addExtensionRegistryFile('com_jed');
$wa->useScript('keepalive')
    ->useScript('form.validate')
    ->useScript('com_jed.reviewForm-showHideEntryForm')
    ->useScript('com_jed.reviewForm-changeRequired');
HTMLHelper::_('bootstrap.tooltip');

echo LayoutHelper::render('review.guidelines', $this->extension_details);

?>
    <div id="reviewForm" style="display: none">
        <div class="review-edit front-end-edit">
                <form id="form-review"
                      action="<?php echo Route::_('index.php?option=com_jed&task=reviewform.save'); ?>"
                      method="post" class="form-validate form-horizontal" enctype="multipart/form-data">

                    <?php

                    /*
                    // The "review" fieldset only, never every fieldset the form declares.
                    // reviewform.xml keeps state/flagged/ip_address in a separate "moderation"
                    // fieldset, and rendering them here is what put a publish checkbox on the
                    // public form in the first place (9.2). Naming the one fieldset means a
                    // field added to "moderation" later cannot leak onto this page by default.
                    echo $this->form->renderFieldset('review');*/
                    $fieldsets['overview']['title']       = Text::_('COM_JED_REVIEW_OVERVIEW_TITLE') . $this->extension_details->name;
                    $fieldsets['overview']['description'] = Text::_('COM_JED_REVIEW_OVERVIEW_DESCR');
                    $fieldsets['overview']['fields']      = ['id',
                            'supply_option_id', 'version',
                            'extension_id', 'used_for'];
                    $fieldsets['overview']['hidden']      = ['id', 'extension_id'];
                    $fieldsets['details']['title']       = Text::_('COM_JED_REVIEW_DETAILS_TITLE');
                    $fieldsets['details']['description'] = Text::_('COM_JED_REVIEW_DETAILS_DESCR');
                    $fieldsets['details']['fields']      = ['title',
                            'alias',
                            'body'];
                    $fieldsets['details']['hidden']      = ['alias'];

                    $fieldsets['scores']['title']       = Text::_('COM_JED_REVIEW_SCORES_TITLE');
                    $fieldsets['scores']['description'] = Text::_('COM_JED_REVIEW_SCORES_DESCR');
                    $fieldsets['scores']['fields']      = [
                            'func_num',
                            'ease_num',
                            'support_num',
                            'doc_num',
                            'value_num',
                            'functionality',
                            'ease_of_use',
                            'support',
                            'documentation',
                            'value_for_money',
                            'overall_score'];
                    $fieldsets['scores']['hidden'] = ['overall_score',
                            'func_num',
                            'ease_num',
                            'support_num',
                            'doc_num',
                            'value_num'];
                    $fieldsets['comments']['title']       = Text::_('COM_JED_REVIEW_COMMENTS_TITLE');
                    $fieldsets['comments']['description'] = Text::_('COM_JED_REVIEW_COMMENTS_DESCR');
                    $fieldsets['comments']['fields']      = ['functionality_comment',
                            'ease_of_use_comment',
                            'support_comment',
                            'documentation_comment',
                            'value_for_money_comment'];
                    $fieldsets['comments']['hidden']      = [];

                    $fieldsets['hidden']['title']       = '';
                    $fieldsets['hidden']['description'] = '';

                    JedHelper::outputFieldsets( $fieldsets, $this->form);
?>


                    <div class="control-group">
                        <div class="controls">

                            <?php if ($this->canSave) : ?>
                                <button type="submit" class="validate btn btn-primary"
                                        onclick="mfTest()">
                                    <span class="fas fa-check" aria-hidden="true"></span>
                                    <?php echo Text::_('JSUBMIT'); ?>
                                </button>
                            <?php endif; ?>
                            <a class="btn btn-danger"
                               href="<?php echo Route::_('index.php?option=com_jed&task=reviewform.cancel'); ?>"
                               title="<?php echo Text::_('JCANCEL'); ?>">
                                <span class="fas fa-times" aria-hidden="true"></span>
                                <?php echo Text::_('JCANCEL'); ?>
                            </a>
                          <?php /*  <button class="btn btn-info"
                                    onclick="mfTest()"
                            >
                                <span class="fas fa-times" aria-hidden="true"></span>
                                TEST
                            </button>
                            */?>
                        </div>
                    </div>

                    <input type="hidden" name="option" value="com_jed"/>
                    <input type="hidden" name="task"
                           value="reviewform.save"/>
                    <?php echo HTMLHelper::_('form.token'); ?>
                </form>
        </div>
    </div>

<?php
echo LayoutHelper::render('review.report', $this->extension_details);

?>
