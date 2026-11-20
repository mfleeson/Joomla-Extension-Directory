<?php

/**
 * @package JED
 *
 * @subpackage Organisations
 *
 * @copyright (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Administrator\Organisation;

// No direct access.
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * The public code of an organisation: `ORG-XXXX-XXXX`.
 *
 * People use it to ask to join an organisation and to address an ownership transfer, so it is
 * handed around in mails and chat. Three properties follow from that (PRD-09 S7):
 *
 * - **random**, not the row id - a sequence would let anybody walk every organisation;
 * - **immutable** - a code that changes breaks every place it was pasted;
 * - **forgiving to type** - Crockford base-32 has no I, L, O or U, and reading normalises the
 *   look-alikes (`o` -> 0, `i`/`l` -> 1), case and missing dashes.
 *
 * 8 symbols of 5 bits is 40 bits: far more codes than organisations will ever exist, and not
 * guessable in bulk.
 *
 * @since 4.1.0
 */
final class OrganisationUid
{
    private const ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    private const PATTERN = '/^ORG-[0-9A-HJKMNP-TV-Z]{4}-[0-9A-HJKMNP-TV-Z]{4}$/';

    /**
     * A new random code. Uniqueness is the caller's to check against the table.
     *
     * @return string
     *
     * @since 4.1.0
     */
    public static function generate(): string
    {
        $symbols = '';

        for ($i = 0; $i < 8; $i++) {
            $symbols .= self::ALPHABET[random_int(0, 31)];
        }

        return 'ORG-' . substr($symbols, 0, 4) . '-' . substr($symbols, 4, 4);
    }

    /**
     * The canonical form of what somebody typed, or '' when it cannot be a code.
     *
     * @param string $input What was typed or pasted.
     *
     * @return string
     *
     * @since 4.1.0
     */
    public static function normalise(string $input): string
    {
        $raw = strtoupper(preg_replace('/[\s\-_]+/', '', trim($input)) ?? '');

        if (str_starts_with($raw, 'ORG')) {
            $raw = substr($raw, 3);
        }

        $raw = strtr($raw, ['O' => '0', 'I' => '1', 'L' => '1']);

        if (\strlen($raw) !== 8) {
            return '';
        }

        $uid = 'ORG-' . substr($raw, 0, 4) . '-' . substr($raw, 4, 4);

        return self::isValid($uid) ? $uid : '';
    }

    /**
     * @param string $uid A code.
     *
     * @return bool  Whether it is a well-formed code (not whether it exists).
     *
     * @since 4.1.0
     */
    public static function isValid(string $uid): bool
    {
        return preg_match(self::PATTERN, $uid) === 1;
    }
}
