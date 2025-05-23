<?php

/**
 * @package     Joomla.Site
 * @subpackage  com_users
 *
 * @copyright   (C) 2009 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

//var_dump($this->profileform);exit();
/** @var Jed\Component\Jed\Site\View\Controlpanel\HtmlView $this */

HTMLHelper::_('bootstrap.tooltip', '.hasTooltip');



/** @var Joomla\CMS\WebAsset\WebAssetManager $wa */
$wa = $this->getDocument()->getWebAssetManager();
$wa->useScript('keepalive')
   ->useScript('form.validate');

?>
<div class="com-users-profile__edit profile-edit">


    <form id="member-profile" action="<?php echo Route::_('index.php?option=com_users'); ?>" method="post" class="com-users-profile__edit-form form-validate form-horizontal well" enctype="multipart/form-data">
        <?php // Iterate through the form fieldsets and display each one.?>
        <?php foreach ($this->profileform->getFieldsets() as $group => $fieldset) : ?>
            <?php $fields = $this->profileform->getFieldset($group); ?>
            <?php if (count($fields)) : ?>
                <fieldset>
                    <?php // If the fieldset has a label set, display it as the legend.?>
                    <?php if (isset($fieldset->label)) : ?>
                        <legend>
                            <?php echo Text::_($fieldset->label); ?>
                        </legend>
                    <?php endif; ?>
                    <?php if (isset($fieldset->description) && trim($fieldset->description)) : ?>
                        <p>
                            <?php echo $this->escape(Text::_($fieldset->description)); ?>
                        </p>
                    <?php endif; ?>
                    <?php // Iterate through the fields in the set and display them.?>
                    <?php foreach ($fields as $field) : ?>
                        <?php echo $field->renderField(); ?>
                    <?php endforeach; ?>
                </fieldset>
            <?php endif; ?>
        <?php endforeach; ?>

        <?php if ($this->profilemfaConfigurationUI) : ?>
            <fieldset class="com-users-profile__multifactor">
                <legend><?php echo Text::_('COM_USERS_PROFILE_MULTIFACTOR_AUTH'); ?></legend>
                <?php echo $this->profilemfaConfigurationUI ?>
            </fieldset>
        <?php endif; ?>

        <div class="com-users-profile__edit-submit control-group">
            <div class="controls">
                <button type="submit" class="btn btn-primary validate" name="task" value="profile.save">
                    <span class="icon-check" aria-hidden="true"></span>
                    <?php echo Text::_('JSAVE'); ?>
                </button>
                <button type="submit" class="btn btn-danger" name="task" value="profile.cancel" formnovalidate>
                    <span class="icon-times" aria-hidden="true"></span>
                    <?php echo Text::_('JCANCEL'); ?>
                </button>
                <input type="hidden" name="option" value="com_users">
            </div>
        </div>
        <?php echo HTMLHelper::_('form.token'); ?>
    </form>
</div>
