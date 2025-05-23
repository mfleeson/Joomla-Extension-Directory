<?php

/**
 * @package       JED
 *
 * @copyright (C) 2022 Open Source Matters, Inc.  <https://www.joomla.org>
 * @license       GNU General Public License version 2 or later; see LICENSE.txt
 */

// No direct access
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Jed\Component\Jed\Site\Helper\JedHelper;
use Jed\Component\Jed\Site\Helper\JedscoreHelper;
use Jed\Component\Jed\Site\Helper\JedtrophyHelper;
use Jed\Component\Jed\Site\View\Extension\HtmlView;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

/**
 * @var HtmlView $this
 */

HTMLHelper::_('bootstrap.tooltip');
HTMLHelper::_('behavior.multiselect');
HTMLHelper::_('formbehavior.chosen', 'select');

$user       = $this->getCurrentUser();
$userId     = $user->id;
$listOrder  = $this->state->get('list.ordering');
$listDirn   = $this->state->get('list.direction');
$canCreate  = $user->authorise('core.create', 'com_jed');
$canEdit    = $user->authorise('core.edit', 'com_jed');
$canCheckin = $user->authorise('core.manage', 'com_jed');
$canChange  = $user->authorise('core.edit.state', 'com_jed');
$canDelete  = $user->authorise('core.delete', 'com_jed');

// Import CSS
$this->document->getWebAssetManager()->useStyle('com_jed.jazstyle');

?>
<div class="jed-cards-wrapper">
    <article class="container mb-5">
        <header class="row gap-2">
            <div class="col d-flex flex-column gap-2 mb-3">
                <h2 class="fs-1 m-0 d-flex flex-row gap-2 align-items-center">
                    <?php
                    echo $this->escape($this->item->title) ?>
                </h2>
                <div class="d-flex flex-row gap-3">
                    <div class="jed-extension-header__developer">By <a
                                href="#"><?php
                                echo $this->item->created_by_name ?></a></div>
                    <div class="stars-wrapper">
                        <?php
                        echo $this->item->score_string ?>
                        <span class="text-muted">
                    <?php
                    echo $this->item->review_string ?>
                </span>
                    </div>
                </div>
                <?php
                echo $this->item->category_hierarchy ?>
            </div>
            <div class="col text-end">
                <?php
                $canCheckin = $this->getCurrentUser()->authorise('core.manage', 'com_jed.' . $this->item->id); ?>
                <?php
                if ($canEdit) : ?>
                    <a class="btn btn-sm btn-outline-primary" role="button"
                       href="<?php
                        echo Route::_('index.php?option=com_jed&task=extension.edit&id=' . $this->item->id) ?>">
                        <span class="icon-pencil" aria-hidden="true"></span>
                        <?php
                        echo Text::_("JACTION_EDIT") ?>
                    </a>
                    <?php
                endif; ?>
                <?php
                if (false) : // TODO Only show to the developer?
                    //
                    ?>
                    <a class="btn btn-sm btn-outline-warning" role="button" href="#">                        <span class="fa fa-life-ring" aria-hidden="true"></span>                        Support                    </a>
                    <?php
                endif ?>
            </div>
        </header>

        <img src="<?php
        echo $this->item->logo ?>" alt="<?php
        echo $this->escape($this->item->title) ?>"
             class="rounded img-fluid mx-auto d-block" style="max-height: 525px">

        <div class="row gap-2">
            <div class="col-8 ">
                <?php
                echo $this->item->intro_text ?>
            </div>
            <div class="col">
                <dl>
                    <div class="row">
                        <dt class="col">Version</dt>
                        <dd class="col"><?php
                            echo $this->item->version ?></dd>
                    </div>
                    <div class="row">
                        <dt class="col">Last updated</dt>
                        <dd class="col">
                            <?php
                            echo HTMLHelper::_('date', $this->item->modified_on, 'M j Y') ?>
                        </dd>
                    </div>
                    <div class="row">
                        <dt class="col">Date added</dt>
                        <dd class="col">
                            <?php
                            echo HTMLHelper::_('date', $this->item->created_on, 'M j Y') ?>
                        </dd>
                    </div>
                    <div class="row">
                        <dt class="col">Includes</dt>
                        <dd class="col"><?php
                            echo JedtrophyHelper::getTrophyIncludesStringFull($this->item->includes) ?></dd>
                    </div>
                    <div class="row">
                        <dt class="col">Compatibility</dt>
                        <dd class="col"><?php
                            echo JedtrophyHelper::getTrophyVersionsStringFull($this->item->joomla_versions) ?></dd>
                    </div>
                    <div class="row">
                        <div class="col col-lg-8 offset-lg-2">
                            <a href="?option=com_jed&view=reviewform&action=add&extension_id=<?php
                            echo $this->item->id ?>"
                               class="btn btn-outline-success">
                                <span class="fa fa-pencil" aria-hidden="true"></span>
                                Write a review
                            </a>
                        </div>
                    </div>
                </dl>
            </div>
        </div>

        <?php
        echo HTMLHelper::_('uitab.startTabSet', 'supply_option_tabs') ?>
        <?php
        $varieddata = $this->item->varied_data;
        $tabid              = 0;
        foreach ($varieddata as $vr) {
            //  echo "<pre>";print_r($vr);echo "</pre>";
            echo HTMLHelper::_('uitab.addTab', 'supply_option_tabs', 'supply_tab_' . $vr->supply_type, $vr->supply_type);
            $subItemId  = md5(serialize($vr));
            ?>
        <div class="jed-wrapper jed-extension margin-bottom">
            <div class="jed-extension__image">
                <?php if ($vr->logo) : ?>
                    <img src=" <?php echo $this->item->logo ?>" alt=" <?php echo $this->escape($this->item->title) ?>"
                         class="rounded img-fluid mx-auto d-block" style="max-height: 525px">
                <?php endif; ?>
            </div>
            <div class="jed-grid jed-grid--2-1 margin-bottom">
                <div class="jed-grid__item">
                    <div class="jed-subitem-intro mb-2">
                         <?php echo $vr->intro_text ?>
                        <?php if (!empty(trim(strip_tags($vr->description)))) : ?>
                            <?php HTMLHelper::_('bootstrap.collapse') ?>
                            <button type="button" class="btn btn-sm btn-outline-secondary my-2"
                                    data-bs-toggle="collapse" href="#description- <?php echo $subItemId ?>"
                                    aria-expanded="false" aria-controls="description- <?php echo $subItemId ?>"
                            >
                                Show/hide
                            </button>
                        <?php endif ?>
                    </div>

                    <div class="jed-subitem-description mb-2 collapse" id="description- <?php echo $subItemId ?>">
                         <?php echo $vr->description ?>
                    </div>

                    <p class="button-group">
                        <a href=" <?php echo $vr->homepage_link ?>" class="button button--grey">Website</a>
                        <a href=" <?php echo $vr->demo_link ?>" class="button button--grey">Demo</a>
                        <a href=" <?php echo $vr->documentation_link ?>" class="button button--grey">Documentation</a>
                        <a href=" <?php echo $vr->support_link ?>" class="button button--grey">Support</a>
                        <a href=" <?php echo $vr->license_link ?>" class="button button--grey">License</a>
                    </p>
                </div>
                <div class="jed-grid__item">
                    <p>
                        <span class="button-group display-block align-right">
                            <!--https://burninglight.biz/j5-restruct/index.php?option=com_jed&view=ticketform&Itemid=203-->
                            <?php

                            $url =  Route::_('index.php?option=com_jed&view=ticketform&litem=2&lid=' . $this->item->id . '&vr=' . $vr->id)
                            ?>
                            <a href="<?php echo $url; ?>" class="button button--grey">Report</a>
                            <a href="#" class="button button--grey">Share</a>
                        </span>

                    </p>
                </div>
            </div>

            <div class="jed-grid jed-grid--1-2">
                <div class="jed-grid__item">
                    <h2 class="heading heading--m">Reviews for <?php echo $vr->supply_type; ?> version</h2>
                    <hr>
                    <?php

                    echo HTMLHelper::_('bootstrap.startAccordion', 'review_extension_group', $slidesOptions);
                    $slideid = 0;
                    foreach ($this->item->reviews[$vr->supply_type] as $rev) {
                        echo HTMLHelper::_(
                            'bootstrap.addSlide',
                            'review_extension_group',
                            $rev->version . ' - ' .
                            $rev->title . ' - ' . JedscoreHelper::getStars($rev->overall_score) . ' ' . JedHelper::prettyShortDate($rev->created_on),
                            'review_extension_group' . '_slide' . ($slideid++)
                        );
                        echo "<p>" . $rev->body . "</p>";
                        echo "<p>Functionality (" . $rev->functionality . ") - " . $rev->functionality_comment . "</p>";
                        echo "<p>Ease of use (" . $rev->ease_of_use . ") - " . $rev->ease_of_use_comment . "</p>";
                        echo "<p>Documentation (" . $rev->documentation . ") - " . $rev->documentation_comment . "</p>";
                        echo "<p>Value For Money (" . $rev->value_for_money . ") - " . $rev->value_for_money_comment . "</p>";
                        echo "<p>Used for - " . $rev->used_for . "</p>";
                        echo HTMLHelper::_('bootstrap.endSlide');
                    }
                    echo HTMLHelper::_('bootstrap.endAccordion');

                    ?>
                </div>

            </div>
        </div>
            <?php

            echo HTMLHelper::_('uitab.endTab');
        }?>
    </article>
</div>


