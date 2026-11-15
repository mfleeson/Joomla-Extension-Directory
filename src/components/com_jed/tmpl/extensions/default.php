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
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;

/** @var \Jed\Component\Jed\Site\View\Extensions\HtmlView $this */

HTMLHelper::_('bootstrap.tooltip');
HTMLHelper::_('behavior.multiselect');


$user       = $this->getCurrentUser();
$userId     = $user->id;
$listOrder  = $this->state->get('list.ordering', '');
$listDirn   = $this->state->get('list.direction');
$canCreate  = $user->authorise('core.create', 'com_jed');
$canEdit    = $user->authorise('core.edit', 'com_jed');
$canCheckin = $user->authorise('core.manage', 'com_jed');
$canChange  = $user->authorise('core.edit.state', 'com_jed');
$canDelete  = $user->authorise('core.delete', 'com_jed');

// Import CSS
/**
 * @var Joomla\CMS\WebAsset\WebAssetManager $wa
*/
$wa = Factory::getApplication()->getDocument()->getWebAssetManager();
$wa->useStyle('com_jed.style');

if (JedHelper::isLoggedIn()) {
    $wa->useScript('com_jed.favorite');
    // The cards render identically for everyone so the page can be cached; this fills the
    // visitor's own bookmarks in afterwards (P1-13).
    $wa->useScript('com_jed.favoritestate');
}

// Only show a "<Category> Extensions" heading when this is actually a single-category browse
// (catid URL param set); presets like Top Rated/Most Reviewed span every category, so they use
// the page's own heading (menu title, or the global default) instead.
$catid   = Factory::getApplication()->getInput()->getInt('id', 0);
$heading = ($catid && !empty($this->items)) ? $this->items[0]->category_title . ' Extensions' : $this->params->get('page_heading');
?>
<?php if (JedHelper::isLoggedIn()) : ?>
    <div id="jed-favorite-i18n" class="d-none"
         data-ajax-url="<?php echo Route::_('index.php?option=com_jed&format=raw'); ?>"
         data-csrf-token="<?php echo Session::getFormToken(); ?>"></div>
<?php endif; ?>

<div class="jed-cards-wrapper mb-4">
    <div class="jed-container">
        <h2 class="heading heading--m"><?php echo $this->escape($heading); ?></h2>
        <?php if ($catid && !empty($this->items)) : ?>
            <p class="font-size-s"><?php echo $this->items[0]->category_hierarchy; ?></p>
        <?php endif; ?>

        <?php
        // The search box, sort and Joomla version drop-downs submit this form (searchtools looks
        // for #adminForm); without it they had nothing to submit. It posts back to the page's own
        // URL so the menu item - and with it the browse list and the per-menu-item filter state -
        // is kept. limitstart=0: a new search or filter starts again at page 1.
        ?>
        <form action="<?php echo htmlspecialchars(Uri::getInstance()->toString(), ENT_QUOTES, 'UTF-8'); ?>"
              method="post" name="adminForm" id="adminForm">
            <?php echo LayoutHelper::render('joomla.searchtools.default', ['view' => $this]); ?>
            <input type="hidden" name="limitstart" value="0">
        </form>

        <ul class="jed-grid jed-grid--1-1-1">
            <?php foreach ($this->items as $item) : ?>
                <?php // One card, one mapping (P1-14). See JedHelper::cardData().?>
                <?php echo LayoutHelper::render('cards.extension', JedHelper::cardData($item)); ?>
            <?php endforeach; ?>
        </ul>
    </div>
</div>


<?php echo $this->pagination->getPaginationLinks(); ?>
