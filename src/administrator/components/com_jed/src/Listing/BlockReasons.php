<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Administrator\Listing;

// No direct access.
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;

/**
 * The shared reason vocabulary of `#__jed_block_reasons`, used for blocks and for rejections.
 *
 * One reader for every place that offers the codes - the Blocking tab's list field, the Reject
 * action on an extension ticket and the Reject panel on the compare page - so the three cannot
 * disagree about which reasons are on offer or how they are labelled.
 *
 * @since 4.0.0
 */
final class BlockReasons
{
    /**
     * The enabled reasons, in their configured order.
     *
     * @param DatabaseInterface $db The database.
     *
     * @return array<string, string>  code => "CODE - Title".
     *
     * @since 4.0.0
     */
    public static function options(DatabaseInterface $db): array
    {
        try {
            $rows = $db->setQuery(
                $db->getQuery(true)
                    ->select($db->quoteName(['code', 'title']))
                    ->from($db->quoteName('#__jed_block_reasons'))
                    ->where($db->quoteName('state') . ' = 1')
                    ->order($db->quoteName('ordering') . ' ASC')
            )->loadAssocList();
        } catch (\RuntimeException) {
            return [];
        }

        $options = [];

        foreach ((array) $rows as $row) {
            $options[(string) $row['code']] = $row['code'] . ' - ' . Text::_((string) $row['title']);
        }

        return $options;
    }
}
