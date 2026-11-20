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

$item    = $this->item;
$viewer  = $item->viewer;
$isAdmin = $viewer->isAdmin;
$id      = (int) $item->id;

/**
 * One small POST form per action: each carries the token, and each action is decided by
 * OrganisationService against the database - the buttons shown here are only a convenience.
 */
$action = function (string $task, array $fields, string $label, string $class = 'btn-outline-secondary', string $confirm = '') use ($id): string {
    $html = '<form action="' . Route::_('index.php?option=com_jed&task=organisation.' . $task) . '" method="post" class="d-inline"'
        . ($confirm !== '' ? ' onsubmit="return confirm(' . htmlspecialchars(json_encode($confirm, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8') . ');"' : '')
        . '>';
    $html .= '<input type="hidden" name="id" value="' . $id . '">';

    foreach ($fields as $name => $value) {
        $html .= '<input type="hidden" name="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '" value="' . htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') . '">';
    }

    return $html . '<button type="submit" class="btn btn-sm ' . $class . '">' . $label . '</button>' . HTMLHelper::_('form.token') . '</form>';
};
?>
<div class="com-jed-organisation">
    <p><a href="<?php echo Route::_('index.php?option=com_jed&view=organisations'); ?>">&larr; <?php echo Text::_('COM_JED_ORGANISATIONS_MY'); ?></a></p>

    <div class="page-header d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="mb-0">
            <?php echo $this->escape($item->title); ?>
            <?php if ((int) $item->state !== 1) : ?>
                <span class="badge bg-danger fs-6"><?php echo Text::_('COM_JED_ORGANISATION_DISABLED'); ?></span>
            <?php endif; ?>
        </h1>
        <?php if ($isAdmin) : ?>
            <a class="btn btn-outline-primary" href="<?php echo Route::_('index.php?option=com_jed&view=organisation&layout=edit&id=' . $id); ?>">
                <span class="icon-edit" aria-hidden="true"></span> <?php echo Text::_('COM_JED_ORGANISATION_EDIT'); ?>
            </a>
        <?php endif; ?>
    </div>

    <dl class="row">
        <dt class="col-sm-3"><?php echo Text::_('COM_JED_ORGANISATION_UID'); ?></dt>
        <dd class="col-sm-9">
            <code class="fs-5"><?php echo $this->escape($item->uid); ?></code>
            <div class="form-text"><?php echo Text::_('COM_JED_ORGANISATION_UID_SHARE'); ?></div>
        </dd>
        <?php if ((string) $item->description !== '') : ?>
            <dt class="col-sm-3"><?php echo Text::_('COM_JED_ORGANISATION_FIELD_DESCRIPTION_LABEL'); ?></dt>
            <dd class="col-sm-9"><?php echo nl2br($this->escape($item->description)); ?></dd>
        <?php endif; ?>
        <?php if ((string) $item->website !== '') : ?>
            <dt class="col-sm-3"><?php echo Text::_('COM_JED_ORGANISATION_FIELD_WEBSITE_LABEL'); ?></dt>
            <dd class="col-sm-9"><a href="<?php echo $this->escape($item->website); ?>" rel="nofollow noopener" target="_blank"><?php echo $this->escape($item->website); ?></a></dd>
        <?php endif; ?>
        <dt class="col-sm-3"><?php echo Text::_('COM_JED_ORGANISATION_FIELDSET_CONTACT'); ?></dt>
        <dd class="col-sm-9">
            <?php echo $this->escape($item->contact_name); ?>
            <?php if ((string) $item->contact_email !== '') : ?>
                &lt;<?php echo $this->escape($item->contact_email); ?>&gt;
            <?php endif; ?>
            <?php if ((string) $item->contact_phone !== '') : ?>
                &middot; <?php echo $this->escape($item->contact_phone); ?>
            <?php endif; ?>
            <?php if ((string) $item->address !== '' || (string) $item->country !== '') : ?>
                <div class="small text-muted"><?php echo nl2br($this->escape(trim($item->address . ' ' . $item->country))); ?></div>
            <?php endif; ?>
        </dd>
        <dt class="col-sm-3"><?php echo Text::_('COM_JED_ORGANISATION_FIELD_ACCEPT_REQUESTS_LABEL'); ?></dt>
        <dd class="col-sm-9">
            <?php echo Text::_((int) $item->accept_requests === 1 ? 'JYES' : 'JNO'); ?>
            <?php if ($isAdmin) : ?>
                <?php echo $action(
                    'requests',
                    ['accept' => (int) $item->accept_requests === 1 ? 0 : 1],
                    Text::_((int) $item->accept_requests === 1 ? 'COM_JED_ORGANISATION_REQUESTS_SWITCH_OFF' : 'COM_JED_ORGANISATION_REQUESTS_SWITCH_ON')
                ); ?>
            <?php endif; ?>
        </dd>
    </dl>

    <?php if ($isAdmin && !empty($item->requests)) : ?>
        <div class="card mb-4 border-info">
            <div class="card-header"><h2 class="h5 mb-0"><?php echo Text::_('COM_JED_ORGANISATION_REQUESTS_HEADING'); ?></h2></div>
            <ul class="list-group list-group-flush">
                <?php foreach ($item->requests as $request) : ?>
                    <li class="list-group-item">
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                            <span>
                                <strong><?php echo $this->escape($request->name ?: $request->username); ?></strong>
                                <span class="text-muted small">&lt;<?php echo $this->escape($request->email); ?>&gt;</span>
                                <?php if (!empty($request->requested_time)) : ?>
                                    <span class="text-muted small">&middot; <?php echo HTMLHelper::_('date', $request->requested_time, Text::_('DATE_FORMAT_LC4')); ?></span>
                                <?php endif; ?>
                            </span>
                            <span>
                                <?php echo $action('decide', ['user_id' => (int) $request->user_id, 'approve' => 1], Text::_('COM_JED_ORGANISATION_REQUEST_APPROVE'), 'btn-success'); ?>
                                <?php echo $action('decide', ['user_id' => (int) $request->user_id, 'approve' => 0], Text::_('COM_JED_ORGANISATION_REQUEST_REJECT'), 'btn-outline-danger'); ?>
                            </span>
                        </div>
                        <?php if ((string) ($request->request_message ?? '') !== '') : ?>
                            <div class="small mt-1"><?php echo nl2br($this->escape($request->request_message)); ?></div>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header"><h2 class="h5 mb-0"><?php echo Text::_('COM_JED_ORGANISATION_MEMBERS'); ?></h2></div>
        <div class="card-body p-0">
            <table class="table table-striped mb-0">
                <thead>
                    <tr>
                        <th scope="col"><?php echo Text::_('JGLOBAL_FIELD_USER_LABEL'); ?></th>
                        <th scope="col"><?php echo Text::_('COM_JED_ORGANISATION_ROLE'); ?></th>
                        <?php if ($isAdmin) : ?>
                            <th scope="col"><?php echo Text::_('JACTION'); ?></th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($item->members as $member) : ?>
                        <?php $isPrimary = (int) $member->user_id === (int) $item->primary_user_id; ?>
                        <tr>
                            <th scope="row">
                                <?php echo $this->escape($member->name ?: $member->username); ?>
                                <?php if ($isAdmin && !empty($member->email)) : ?>
                                    <span class="text-muted small">&lt;<?php echo $this->escape($member->email); ?>&gt;</span>
                                <?php endif; ?>
                            </th>
                            <td>
                                <?php echo Text::_('COM_JED_ORGANISATION_ROLE_' . strtoupper((string) $member->role)); ?>
                                <?php if ($isPrimary) : ?>
                                    <span class="badge bg-info"><?php echo Text::_('COM_JED_ORGANISATION_PRIMARY_CONTACT'); ?></span>
                                <?php endif; ?>
                            </td>
                            <?php if ($isAdmin) : ?>
                                <td class="text-nowrap">
                                    <?php if (!$isPrimary) : ?>
                                        <?php if ($member->role === 'admin') : ?>
                                            <?php echo $action('role', ['user_id' => (int) $member->user_id, 'role' => 'member'], Text::_('COM_JED_ORGANISATION_MAKE_MEMBER')); ?>
                                            <?php if ($viewer->isPrimary || $viewer->isStaff) : ?>
                                                <?php echo $action('primary', ['user_id' => (int) $member->user_id], Text::_('COM_JED_ORGANISATION_MAKE_PRIMARY'), 'btn-outline-primary', Text::_('COM_JED_ORGANISATION_MAKE_PRIMARY_CONFIRM')); ?>
                                            <?php endif; ?>
                                        <?php else : ?>
                                            <?php echo $action('role', ['user_id' => (int) $member->user_id, 'role' => 'admin'], Text::_('COM_JED_ORGANISATION_MAKE_ADMIN')); ?>
                                        <?php endif; ?>
                                        <?php echo $action('remove', ['user_id' => (int) $member->user_id], Text::_('COM_JED_ORGANISATION_REMOVE'), 'btn-outline-danger', Text::_('COM_JED_ORGANISATION_REMOVE_CONFIRM')); ?>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($isAdmin) : ?>
                        <?php foreach ($item->invited as $invited) : ?>
                            <tr class="text-muted">
                                <th scope="row"><?php echo $this->escape($invited->name ?: $invited->username); ?></th>
                                <td><?php echo Text::_('COM_JED_ORGANISATION_MEMBERSHIP_STATE_INVITED'); ?></td>
                                <td><?php echo $action('remove', ['user_id' => (int) $invited->user_id], Text::_('COM_JED_ORGANISATION_WITHDRAW_INVITATION'), 'btn-outline-secondary'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if ($isAdmin) : ?>
        <div class="card mb-4">
            <div class="card-header"><h2 class="h5 mb-0"><?php echo Text::_('COM_JED_ORGANISATION_INVITE_HEADING'); ?></h2></div>
            <div class="card-body">
                <p class="text-muted"><?php echo Text::_('COM_JED_ORGANISATION_INVITE_INTRO'); ?></p>
                <form action="<?php echo Route::_('index.php?option=com_jed&task=organisation.invite'); ?>" method="post" class="row g-2 align-items-end">
                    <div class="col-md-6">
                        <label class="form-label" for="jed-invite-email"><?php echo Text::_('COM_JED_ORGANISATION_INVITE_EMAIL'); ?></label>
                        <input type="email" class="form-control" id="jed-invite-email" name="email" required autocomplete="off">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="jed-invite-role"><?php echo Text::_('COM_JED_ORGANISATION_ROLE'); ?></label>
                        <select class="form-select" id="jed-invite-role" name="role">
                            <option value="member"><?php echo Text::_('COM_JED_ORGANISATION_ROLE_MEMBER'); ?></option>
                            <option value="admin"><?php echo Text::_('COM_JED_ORGANISATION_ROLE_ADMIN'); ?></option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary w-100"><?php echo Text::_('COM_JED_ORGANISATION_INVITE_SUBMIT'); ?></button>
                    </div>
                    <input type="hidden" name="id" value="<?php echo $id; ?>">
                    <?php echo HTMLHelper::_('form.token'); ?>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header"><h2 class="h5 mb-0"><?php echo Text::_('COM_JED_ORGANISATION_LISTINGS'); ?></h2></div>
        <ul class="list-group list-group-flush">
            <?php foreach ($item->listings as $listing) : ?>
                <li class="list-group-item">
                    <a href="<?php echo Route::_('index.php?option=com_jed&view=extension&id=' . (int) $listing->id); ?>"><?php echo $this->escape($listing->name); ?></a>
                </li>
            <?php endforeach; ?>
            <?php if (empty($item->listings)) : ?>
                <li class="list-group-item text-muted"><?php echo Text::_('JNONE'); ?></li>
            <?php endif; ?>
        </ul>
    </div>

    <?php if ($viewer->isMember && !$viewer->isPrimary) : ?>
        <?php echo $action('leave', [], Text::_('COM_JED_ORGANISATION_LEAVE'), 'btn-outline-danger', Text::_('COM_JED_ORGANISATION_LEAVE_CONFIRM')); ?>
    <?php endif; ?>
</div>
