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
use Joomla\CMS\Router\Route;

/** @var \Jed\Component\Jed\Site\View\Organisation\HtmlView $this */

$this->getDocument()->getWebAssetManager()->useScript('form.validate');

$id = (int) ($this->item->id ?? 0);
?>
<div class="com-jed-organisation-edit">
    <h1><?php echo $id > 0 ? $this->escape($this->item->title) : Text::_('COM_JED_ORGANISATION_NEW'); ?></h1>

    <?php if ($id === 0) : ?>
        <p class="text-muted"><?php echo Text::_('COM_JED_ORGANISATION_NEW_INTRO'); ?></p>
    <?php endif; ?>

    <form action="<?php echo Route::_('index.php?option=com_jed&task=organisation.save&id=' . $id); ?>"
          method="post" class="form-validate">
        <?php foreach ($this->form->getFieldsets() as $fieldset) : ?>
            <fieldset class="mb-4">
                <legend><?php echo Text::_($fieldset->label); ?></legend>
                <?php echo $this->form->renderFieldset($fieldset->name); ?>
            </fieldset>
        <?php endforeach; ?>

        <button type="submit" class="btn btn-primary validate"><?php echo Text::_('JSAVE'); ?></button>
        <a class="btn btn-secondary"
           href="<?php echo Route::_($id > 0 ? 'index.php?option=com_jed&view=organisation&id=' . $id : 'index.php?option=com_jed&view=organisations'); ?>">
            <?php echo Text::_('JCANCEL'); ?>
        </a>

        <input type="hidden" name="id" value="<?php echo $id; ?>">
        <?php echo HTMLHelper::_('form.token'); ?>
    </form>
</div>
