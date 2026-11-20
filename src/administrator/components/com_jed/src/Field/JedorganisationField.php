<?php

/**
 * @package JED
 *
 * @subpackage Organisations
 *
 * @copyright (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Administrator\Field;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Jed\Component\Jed\Administrator\Organisation\MembershipState;
use Jed\Component\Jed\Administrator\Organisation\OrganisationService;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;

/**
 * Pick an organisation (PRD-09).
 *
 * `scope` decides which organisations are offered:
 *
 * - `mine` (default) - the active organisations the current user is an active member of: where a
 *   developer may submit a listing. Never a list of everybody, which would invite picking
 *   somebody else's - the model refuses that anyway.
 * - `accepting` - organisations taking membership requests (registration, "request to join").
 * - `all` - every organisation (backend, JED team).
 *
 * With `readonly="true"` the field shows the current organisation as text: on an existing
 * listing, changing the organisation is a transfer, not an edit.
 *
 * @since 4.1.0
 */
class JedorganisationField extends ListField
{
    /**
     * @var string
     *
     * @since 4.1.0
     */
    protected $type = 'Jedorganisation';

    /**
     * @return string
     *
     * @since 4.1.0
     */
    protected function getInput()
    {
        if ($this->readonly) {
            $organisation = (new OrganisationService(Factory::getContainer()->get(DatabaseInterface::class)))->find((int) $this->value);

            return '<div class="form-control-plaintext">'
                . ($organisation !== null
                    ? htmlspecialchars((string) $organisation->title . ' (' . $organisation->uid . ')', ENT_QUOTES, 'UTF-8')
                    : Text::_('COM_JED_ORGANISATION_NONE'))
                . '</div>';
        }

        return parent::getInput();
    }

    /**
     * @return array
     *
     * @since 4.1.0
     */
    protected function getOptions()
    {
        $service = new OrganisationService(Factory::getContainer()->get(DatabaseInterface::class));
        $scope   = (string) ($this->element['scope'] ?? 'mine');
        $options = [];

        switch ($scope) {
            case 'all':
                $db   = Factory::getContainer()->get(DatabaseInterface::class);
                $rows = $db->setQuery(
                    $db->getQuery(true)
                        ->select($db->quoteName(['id', 'uid', 'title']))
                        ->from($db->quoteName('#__jed_organisations'))
                        ->order($db->quoteName('title') . ' ASC')
                )->loadObjectList() ?: [];
                break;

            case 'accepting':
                $rows = $service->acceptingRequests();
                break;

            default:
                $userId = (int) (Factory::getApplication()->getIdentity()->id ?? 0);
                $rows   = array_values(array_filter(
                    $service->organisationsOf($userId, [MembershipState::ACTIVE]),
                    static fn (object $o): bool => (int) $o->state === 1
                ));

                if ($rows === []) {
                    // Somebody with no organisation yet: their personal one is created on submit.
                    $options[] = HTMLHelper::_('select.option', '0', Text::_('COM_JED_ORGANISATION_CREATE_PERSONAL_ON_SUBMIT'));
                }
        }

        foreach ($rows as $row) {
            $options[] = HTMLHelper::_('select.option', (string) (int) $row->id, (string) $row->title . ' (' . (string) $row->uid . ')');
        }

        return array_merge(parent::getOptions(), $options);
    }
}
