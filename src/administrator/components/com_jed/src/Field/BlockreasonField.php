<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Administrator\Field;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Jed\Component\Jed\Administrator\Listing\BlockReasons;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;

/**
 * The enabled block reasons from #__jed_block_reasons.
 *
 * Read from the table rather than declared in the form XML because the codes are data (4.8):
 * the JED team adds or retires a reason without a release, and the same vocabulary keys the
 * knowledge base articles and the ticket mail templates.
 *
 * @since 4.0.0
 */
class BlockreasonField extends ListField
{
    /**
     * The form field type.
     *
     * @var string
     *
     * @since 4.0.0
     */
    protected $type = 'Blockreason';

    /**
     * Build the option list.
     *
     * @return array
     *
     * @since 4.0.0
     */
    protected function getOptions(): array
    {
        $options = [HTMLHelper::_('select.option', '', Text::_('COM_JED_BLOCK_REASON_SELECT'))];

        foreach (BlockReasons::options(Factory::getContainer()->get(DatabaseInterface::class)) as $code => $label) {
            $options[] = HTMLHelper::_('select.option', $code, $label);
        }

        return array_merge($options, parent::getOptions());
    }
}
