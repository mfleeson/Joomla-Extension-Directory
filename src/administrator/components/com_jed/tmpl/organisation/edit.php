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

use Jed\Component\Jed\Administrator\Organisation\MembershipState;
use Joomla\CMS\Form\Form;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/** @var \Jed\Component\Jed\Administrator\View\Organisation\HtmlView $this */

HTMLHelper::_('behavior.formvalidator');
HTMLHelper::_('behavior.keepalive');

$item  = $this->item;
$id    = (int) ($item->id ?? 0);
$state = static fn (object $m): MembershipState => MembershipState::tryFrom((int) $m->state) ?? MembershipState::REQUESTED;

// The user picker for "add a member" sits outside jform: OrganisationController::addMember()
// reads it by this name, and a normal Save ignores it.
$memberForm = Form::getInstance(
    'com_jed.organisation.member',
    '<form><field name="member_user_id" type="user" label="JGLOBAL_FIELD_USER_LABEL"/></form>'
);
?>
<form action="<?php echo Route::_('index.php?option=com_jed&layout=edit&id=' . $id); ?>" method="post" name="adminForm" id="organisation-form" class="form-validate">
    <div class="row">
        <div class="col-lg-6">
            <?php foreach ($this->form->getFieldsets() as $fieldset) : ?>
                <fieldset class="options-form">
                    <legend><?php echo Text::_($fieldset->label); ?></legend>
                    <?php echo $this->form->renderFieldset($fieldset->name); ?>
                </fieldset>
            <?php endforeach; ?>
        </div>

        <?php if ($id > 0) : ?>
            <div class="col-lg-6">
                <fieldset class="options-form">
                    <legend><?php echo Text::_('COM_JED_ORGANISATION_MEMBERS'); ?></legend>
                    <p class="text-muted small">
                        <?php echo Text::plural('COM_JED_ORGANISATION_N_LISTINGS', (int) $item->listing_count); ?>
                    </p>
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th scope="col"><?php echo Text::_('JGLOBAL_FIELD_USER_LABEL'); ?></th>
                                <th scope="col"><?php echo Text::_('JSTATUS'); ?></th>
                                <th scope="col"><?php echo Text::_('JACTION'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($item->members as $member) : ?>
                                <?php
                                $memberState = $state($member);
                                $isPrimary   = (int) $member->user_id === (int) $item->primary_user_id;
                                ?>
                                <tr>
                                    <th scope="row">
                                        <a href="<?php echo Route::_('index.php?option=com_users&task=user.edit&id=' . (int) $member->user_id); ?>">
                                            <?php echo $this->escape($member->name ?: $member->username ?: ('#' . (int) $member->user_id)); ?>
                                        </a>
                                        <div class="small text-muted"><?php echo $this->escape((string) $member->email); ?></div>
                                        <?php if ($memberState === MembershipState::REQUESTED && (string) $member->request_message !== '') : ?>
                                            <div class="small"><?php echo nl2br($this->escape($member->request_message)); ?></div>
                                        <?php endif; ?>
                                    </th>
                                    <td>
                                        <?php echo Text::_($memberState->label()); ?>
                                        <?php if ($memberState === MembershipState::ACTIVE) : ?>
                                            &middot; <?php echo Text::_('COM_JED_ORGANISATION_ROLE_' . strtoupper((string) $member->role)); ?>
                                        <?php endif; ?>
                                        <?php if ($isPrimary) : ?>
                                            <span class="badge bg-info"><?php echo Text::_('COM_JED_ORGANISATION_PRIMARY_CONTACT'); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-nowrap">
                                        <?php if ($memberState === MembershipState::REQUESTED) : ?>
                                            <button type="button" class="btn btn-sm btn-success" data-jed-member="decide" data-user="<?php echo (int) $member->user_id; ?>" data-approve="1"><?php echo Text::_('COM_JED_ORGANISATION_REQUEST_APPROVE'); ?></button>
                                            <button type="button" class="btn btn-sm btn-outline-danger" data-jed-member="decide" data-user="<?php echo (int) $member->user_id; ?>" data-approve="0"><?php echo Text::_('COM_JED_ORGANISATION_REQUEST_REJECT'); ?></button>
                                        <?php elseif ($memberState === MembershipState::ACTIVE && !$isPrimary) : ?>
                                            <?php if ($member->role === 'admin') : ?>
                                                <button type="button" class="btn btn-sm btn-outline-secondary" data-jed-member="setRole" data-user="<?php echo (int) $member->user_id; ?>" data-role="member"><?php echo Text::_('COM_JED_ORGANISATION_MAKE_MEMBER'); ?></button>
                                                <button type="button" class="btn btn-sm btn-outline-primary" data-jed-member="setPrimary" data-user="<?php echo (int) $member->user_id; ?>"><?php echo Text::_('COM_JED_ORGANISATION_MAKE_PRIMARY'); ?></button>
                                            <?php else : ?>
                                                <button type="button" class="btn btn-sm btn-outline-secondary" data-jed-member="setRole" data-user="<?php echo (int) $member->user_id; ?>" data-role="admin"><?php echo Text::_('COM_JED_ORGANISATION_MAKE_ADMIN'); ?></button>
                                            <?php endif; ?>
                                            <button type="button" class="btn btn-sm btn-outline-danger" data-jed-member="removeMember" data-user="<?php echo (int) $member->user_id; ?>"><?php echo Text::_('COM_JED_ORGANISATION_REMOVE'); ?></button>
                                        <?php elseif ($memberState === MembershipState::INVITED) : ?>
                                            <button type="button" class="btn btn-sm btn-outline-secondary" data-jed-member="removeMember" data-user="<?php echo (int) $member->user_id; ?>"><?php echo Text::_('COM_JED_ORGANISATION_WITHDRAW_INVITATION'); ?></button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <h3 class="h6"><?php echo Text::_('COM_JED_ORGANISATION_ADD_MEMBER'); ?></h3>
                    <?php echo $memberForm->renderField('member_user_id'); ?>
                    <div class="control-group">
                        <div class="control-label"><label for="jed-member-role"><?php echo Text::_('COM_JED_ORGANISATION_ROLE'); ?></label></div>
                        <div class="controls">
                            <select class="form-select" id="jed-member-role" name="member_role">
                                <option value="member"><?php echo Text::_('COM_JED_ORGANISATION_ROLE_MEMBER'); ?></option>
                                <option value="admin"><?php echo Text::_('COM_JED_ORGANISATION_ROLE_ADMIN'); ?></option>
                            </select>
                        </div>
                    </div>
                    <button type="button" class="btn btn-primary" data-jed-member="addMember"><?php echo Text::_('COM_JED_ORGANISATION_ADD_MEMBER'); ?></button>
                    <p class="form-text"><?php echo Text::_('COM_JED_ORGANISATION_ADD_MEMBER_BACKEND_DESC'); ?></p>
                </fieldset>
            </div>
        <?php endif; ?>
    </div>

    <input type="hidden" name="task" value="">
    <input type="hidden" name="id" value="<?php echo $id; ?>">
    <input type="hidden" name="user_id" value="" id="jed-member-user">
    <input type="hidden" name="role" value="" id="jed-member-newrole">
    <input type="hidden" name="approve" value="" id="jed-member-approve">
    <?php echo HTMLHelper::_('form.token'); ?>
</form>
<?php
$this->getDocument()->getWebAssetManager()->addInlineScript(
    <<<'JS'
    document.addEventListener('click', function (event) {
        var button = event.target.closest('[data-jed-member]');

        if (!button) {
            return;
        }

        var form = document.getElementById('organisation-form');

        document.getElementById('jed-member-user').value    = button.dataset.user || '';
        document.getElementById('jed-member-newrole').value = button.dataset.role || '';
        document.getElementById('jed-member-approve').value = button.dataset.approve || '';

        // Joomla.submitform, not Joomla.submitbutton: these are member actions, not a save of the
        // organisation's details, so the form's own validation must not block them.
        Joomla.submitform('organisation.' + button.dataset.jedMember, form);
    });
    JS
);
