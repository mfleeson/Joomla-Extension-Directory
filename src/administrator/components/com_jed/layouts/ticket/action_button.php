<?php

/**
 * @package JED
 *
 * @subpackage Tickets
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 *
 * Renders one Jed\Component\Jed\Administrator\Ticket\TicketAction.
 *
 * The ticket screen is one big <form id="ticket-form">, and the actions are shown inside it. A
 * <form> inside a <form> does not exist in HTML: the browser drops the inner start tag, so an
 * action's button used to submit the *ticket* form - whose own empty `task` field came last and
 * won - and no action ever reached its controller. The action is therefore rendered in two parts:
 *
 *  - part "controls": the button (and, for an action with fields, its inputs), placed where the
 *    action is shown. Every control names its form with the HTML `form` attribute.
 *  - part "form": the <form> itself with the hidden fields and the token, rendered by the caller
 *    *after* the ticket form has been closed.
 *
 * Without a part, both are rendered together - for a page where the action is not inside another
 * form.
 *
 * @var array $displayData  ['action' => TicketAction, 'part' => 'controls'|'form'|null]
 */

// No direct access
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Jed\Component\Jed\Administrator\Ticket\TicketAction;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;

/** @var TicketAction $action */
$action = $displayData['action'];
$part   = $displayData['part'] ?? null;
$formId = 'ticket-action-' . md5($action->option . $action->task . serialize($action->hiddenFields));
$esc    = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

$confirm = $action->confirmMessage
    ? ' onclick="return confirm(\'' . $esc(addslashes(Text::_($action->confirmMessage))) . '\');"'
    : '';
?>
<?php if ($part !== 'form') : ?>
    <?php if ($action->fields === []) : ?>
        <button type="submit" form="<?php echo $formId; ?>" class="btn btn-primary btn-sm me-2 mb-2"<?php echo $confirm; ?>>
            <span class="icon-<?php echo $esc($action->icon); ?>" aria-hidden="true"></span>
            <?php echo Text::_($action->label); ?>
        </button>
    <?php else : ?>
        <?php // <details> opens the inputs without any script; the submit stays a deliberate second click. ?>
        <details class="jed-ticket-action mb-2">
            <summary class="btn btn-primary btn-sm">
                <span class="icon-<?php echo $esc($action->icon); ?>" aria-hidden="true"></span>
                <?php echo Text::_($action->label); ?>
            </summary>
            <div class="border rounded p-2 mt-2 bg-white">
                <?php foreach ($action->fields as $field) :
                    $inputId  = $formId . '-' . preg_replace('/[^a-z0-9_-]/i', '_', (string) $field['name']);
                    $required = !empty($field['required']) ? ' required' : '';
                    ?>
                    <div class="mb-2">
                        <label class="form-label small mb-1" for="<?php echo $inputId; ?>">
                            <?php echo Text::_((string) $field['label']); ?><?php echo $required ? ' *' : ''; ?>
                        </label>
                        <?php if (($field['type'] ?? 'text') === 'select') : ?>
                            <select class="form-select form-select-sm" id="<?php echo $inputId; ?>"
                                    name="<?php echo $esc($field['name']); ?>" form="<?php echo $formId; ?>"<?php echo $required; ?>>
                                <option value=""><?php echo Text::_('JSELECT'); ?></option>
                                <?php foreach ((array) ($field['options'] ?? []) as $value => $label) : ?>
                                    <option value="<?php echo $esc($value); ?>"><?php echo $esc($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php elseif (($field['type'] ?? 'text') === 'textarea') : ?>
                            <textarea class="form-control form-control-sm" id="<?php echo $inputId; ?>" rows="4"
                                      name="<?php echo $esc($field['name']); ?>" form="<?php echo $formId; ?>"<?php echo $required; ?>></textarea>
                        <?php else : ?>
                            <input type="text" class="form-control form-control-sm" id="<?php echo $inputId; ?>"
                                   name="<?php echo $esc($field['name']); ?>" form="<?php echo $formId; ?>"<?php echo $required; ?>>
                        <?php endif; ?>
                        <?php if (!empty($field['hint'])) : ?>
                            <div class="form-text small"><?php echo Text::_((string) $field['hint']); ?></div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                <button type="submit" form="<?php echo $formId; ?>" class="btn btn-danger btn-sm"<?php echo $confirm; ?>>
                    <?php echo Text::_($action->label); ?>
                </button>
            </div>
        </details>
    <?php endif; ?>
<?php endif; ?>
<?php if ($part !== 'controls') : ?>
    <form action="index.php" method="post" id="<?php echo $formId; ?>" class="d-none">
        <input type="hidden" name="option" value="<?php echo $esc($action->option); ?>"/>
        <input type="hidden" name="task" value="<?php echo $esc($action->task); ?>"/>
        <?php foreach ($action->hiddenFields as $name => $value) : ?>
            <input type="hidden" name="<?php echo $esc($name); ?>" value="<?php echo $esc($value); ?>"/>
        <?php endforeach; ?>
        <?php if (!isset($action->hiddenFields['return'])) : ?>
            <?php // Controllers that honour it (ExtensionController) bring the team member back to this ticket. ?>
            <input type="hidden" name="return" value="<?php echo $esc(base64_encode(Uri::getInstance()->toString())); ?>"/>
        <?php endif; ?>
        <?php echo HTMLHelper::_('form.token'); ?>
    </form>
<?php endif; ?>
