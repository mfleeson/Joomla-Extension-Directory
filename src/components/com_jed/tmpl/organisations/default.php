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
use Joomla\CMS\Session\Session;

/** @var \Jed\Component\Jed\Site\View\Organisations\HtmlView $this */
?>
<div class="com-jed-organisations">
    <div class="page-header d-flex justify-content-between align-items-center mb-3">
        <h1><?php echo Text::_('COM_JED_ORGANISATIONS_MY'); ?></h1>
        <a class="btn btn-primary" href="<?php echo Route::_('index.php?option=com_jed&view=organisation&layout=edit&id=0'); ?>">
            <span class="icon-plus" aria-hidden="true"></span> <?php echo Text::_('COM_JED_ORGANISATION_CREATE'); ?>
        </a>
    </div>

    <p class="text-muted"><?php echo Text::_('COM_JED_ORGANISATIONS_INTRO'); ?></p>

    <?php if (!empty($this->invitations)) : ?>
        <div class="card mb-4 border-info">
            <div class="card-header"><h2 class="h5 mb-0"><?php echo Text::_('COM_JED_ORGANISATION_INVITATIONS_HEADING'); ?></h2></div>
            <div class="card-body">
                <p><?php echo Text::_('COM_JED_ORGANISATION_INVITATIONS_INTRO'); ?></p>
                <ul class="list-group">
                    <?php foreach ($this->invitations as $invite) : ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span>
                                <strong><?php echo $this->escape($invite->title); ?></strong>
                                <code class="small"><?php echo $this->escape($invite->uid); ?></code>
                                <?php if (!empty($invite->invited_by_name)) : ?>
                                    <span class="text-muted small">&mdash; <?php echo Text::sprintf('COM_JED_ORGANISATION_INVITED_BY', $this->escape($invite->invited_by_name)); ?></span>
                                <?php endif; ?>
                            </span>
                            <span class="btn-group btn-group-sm">
                                <?php foreach ([1 => 'ACCEPT', 0 => 'DECLINE'] as $accept => $label) : ?>
                                    <a class="btn <?php echo $accept ? 'btn-success' : 'btn-outline-secondary'; ?>"
                                       href="<?php echo Route::_('index.php?option=com_jed&task=organisation.respond&id=' . (int) $invite->id . '&accept=' . $accept . '&' . Session::getFormToken() . '=1'); ?>">
                                        <?php echo Text::_('COM_JED_ORGANISATION_INVITATION_' . $label); ?>
                                    </a>
                                <?php endforeach; ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header"><h2 class="h5 mb-0"><?php echo Text::_('COM_JED_ORGANISATIONS_MEMBER_OF'); ?></h2></div>
        <div class="card-body p-0">
            <?php if (empty($this->memberships)) : ?>
                <p class="p-3 mb-0 text-muted"><?php echo Text::_('COM_JED_ORGANISATIONS_NONE'); ?></p>
            <?php else : ?>
                <table class="table table-striped mb-0">
                    <thead>
                        <tr>
                            <th scope="col"><?php echo Text::_('COM_JED_ORGANISATION_FIELD_TITLE_LABEL'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_JED_ORGANISATION_UID'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_JED_ORGANISATION_YOUR_ROLE'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_JED_ORGANISATION_LISTINGS'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($this->memberships as $organisation) : ?>
                            <tr>
                                <th scope="row">
                                    <a href="<?php echo Route::_('index.php?option=com_jed&view=organisation&id=' . (int) $organisation->id); ?>">
                                        <?php echo $this->escape($organisation->title); ?>
                                    </a>
                                    <?php if ((int) $organisation->state !== 1) : ?>
                                        <span class="badge bg-danger"><?php echo Text::_('COM_JED_ORGANISATION_DISABLED'); ?></span>
                                    <?php endif; ?>
                                </th>
                                <td><code><?php echo $this->escape($organisation->uid); ?></code></td>
                                <td><?php echo Text::_('COM_JED_ORGANISATION_ROLE_' . strtoupper((string) $organisation->member_role)); ?></td>
                                <td><?php echo (int) $organisation->listing_count; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($this->requests)) : ?>
        <div class="card mb-4">
            <div class="card-header"><h2 class="h5 mb-0"><?php echo Text::_('COM_JED_ORGANISATION_MY_REQUESTS'); ?></h2></div>
            <ul class="list-group list-group-flush">
                <?php foreach ($this->requests as $request) : ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span>
                            <strong><?php echo $this->escape($request->title); ?></strong>
                            <span class="text-muted small"><?php echo Text::_('COM_JED_ORGANISATION_REQUEST_WAITING'); ?></span>
                        </span>
                        <form action="<?php echo Route::_('index.php?option=com_jed&task=organisation.cancelRequest'); ?>" method="post" class="mb-0">
                            <input type="hidden" name="id" value="<?php echo (int) $request->id; ?>">
                            <button type="submit" class="btn btn-sm btn-outline-secondary"><?php echo Text::_('COM_JED_ORGANISATION_REQUEST_CANCEL'); ?></button>
                            <?php echo HTMLHelper::_('form.token'); ?>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header"><h2 class="h5 mb-0"><?php echo Text::_('COM_JED_ORGANISATION_JOIN_HEADING'); ?></h2></div>
        <div class="card-body">
            <p class="text-muted"><?php echo Text::_('COM_JED_ORGANISATION_JOIN_INTRO'); ?></p>
            <form action="<?php echo Route::_('index.php?option=com_jed&task=organisation.request'); ?>" method="post" class="row g-2">
                <div class="col-md-5">
                    <label class="form-label" for="jed-join-organisation"><?php echo Text::_('COM_JED_ORGANISATION_JOIN_CHOOSE'); ?></label>
                    <select class="form-select" id="jed-join-organisation" name="organisation_id">
                        <option value="0"><?php echo Text::_('COM_JED_ORGANISATION_JOIN_BY_CODE'); ?></option>
                        <?php foreach ($this->joinable as $organisation) : ?>
                            <option value="<?php echo (int) $organisation->id; ?>">
                                <?php echo $this->escape($organisation->title . ' (' . $organisation->uid . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="jed-join-uid"><?php echo Text::_('COM_JED_ORGANISATION_UID'); ?></label>
                    <input type="text" class="form-control" id="jed-join-uid" name="uid" placeholder="ORG-XXXX-XXXX" autocomplete="off">
                </div>
                <div class="col-12">
                    <label class="form-label" for="jed-join-message"><?php echo Text::_('COM_JED_ORGANISATION_JOIN_MESSAGE'); ?></label>
                    <textarea class="form-control" id="jed-join-message" name="message" rows="2" maxlength="1000"></textarea>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary"><?php echo Text::_('COM_JED_ORGANISATION_JOIN_SUBMIT'); ?></button>
                </div>
                <?php echo HTMLHelper::_('form.token'); ?>
            </form>
        </div>
    </div>
</div>
