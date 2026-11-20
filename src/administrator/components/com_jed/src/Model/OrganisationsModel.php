<?php

/**
 * @package JED
 *
 * @subpackage Organisations
 *
 * @copyright (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Administrator\Model;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Jed\Component\Jed\Administrator\Organisation\MembershipState;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Database\ParameterType;
use Joomla\Database\QueryInterface;

/**
 * The JED team's list of organisations (PRD-09 R13).
 *
 * @since 4.1.0
 */
class OrganisationsModel extends ListModel
{
    /**
     * @param array $config Configuration.
     *
     * @since 4.1.0
     */
    public function __construct($config = [])
    {
        if (empty($config['filter_fields'])) {
            $config['filter_fields'] = [
                'id', 'a.id',
                'title', 'a.title',
                'created', 'a.created',
                'state', 'a.state',
                'personal', 'pending',
            ];
        }

        parent::__construct($config);
    }

    /**
     * @param string $ordering  Default ordering.
     * @param string $direction Default direction.
     *
     * @return void
     *
     * @since 4.1.0
     */
    protected function populateState($ordering = 'a.title', $direction = 'ASC'): void
    {
        $this->setState('filter.search', $this->getUserStateFromRequest($this->context . '.filter.search', 'filter_search', ''));
        $this->setState('filter.state', $this->getUserStateFromRequest($this->context . '.filter.state', 'filter_state', ''));
        $this->setState('filter.personal', $this->getUserStateFromRequest($this->context . '.filter.personal', 'filter_personal', ''));
        $this->setState('filter.pending', $this->getUserStateFromRequest($this->context . '.filter.pending', 'filter_pending', ''));

        parent::populateState($ordering, $direction);
    }

    /**
     * @param string $id Prefix.
     *
     * @return string
     *
     * @since 4.1.0
     */
    protected function getStoreId($id = ''): string
    {
        foreach (['search', 'state', 'personal', 'pending'] as $filter) {
            $id .= ':' . $this->getState('filter.' . $filter);
        }

        return parent::getStoreId($id);
    }

    /**
     * @return QueryInterface
     *
     * @since 4.1.0
     */
    protected function getListQuery(): QueryInterface
    {
        $db        = $this->getDatabase();
        $active    = MembershipState::ACTIVE->value;
        $requested = MembershipState::REQUESTED->value;

        $query = $db->getQuery(true)
            ->select($db->quoteName(['a.id', 'a.uid', 'a.title', 'a.contact_email', 'a.state', 'a.is_personal', 'a.accept_requests', 'a.primary_user_id', 'a.created', 'a.checked_out', 'a.checked_out_time']))
            ->select($db->quoteName('p.name', 'primary_name'))
            ->select('(SELECT COUNT(*) FROM ' . $db->quoteName('#__jed_organisation_members', 'm1')
                . ' WHERE ' . $db->quoteName('m1.organisation_id') . ' = ' . $db->quoteName('a.id')
                . ' AND ' . $db->quoteName('m1.state') . ' = ' . $active . ') AS ' . $db->quoteName('member_count'))
            ->select('(SELECT COUNT(*) FROM ' . $db->quoteName('#__jed_organisation_members', 'm2')
                . ' WHERE ' . $db->quoteName('m2.organisation_id') . ' = ' . $db->quoteName('a.id')
                . ' AND ' . $db->quoteName('m2.state') . ' = ' . $requested . ') AS ' . $db->quoteName('request_count'))
            ->select('(SELECT COUNT(*) FROM ' . $db->quoteName('#__jed_extensions', 'e')
                . ' WHERE ' . $db->quoteName('e.organisation_id') . ' = ' . $db->quoteName('a.id')
                . ' AND ' . $db->quoteName('e.deleted') . ' = 0) AS ' . $db->quoteName('listing_count'))
            ->from($db->quoteName('#__jed_organisations', 'a'))
            ->leftJoin($db->quoteName('#__users', 'p'), $db->quoteName('p.id') . ' = ' . $db->quoteName('a.primary_user_id'));

        $search = trim((string) $this->getState('filter.search'));

        if ($search !== '') {
            if (stripos($search, 'id:') === 0) {
                $id = (int) substr($search, 3);
                $query->where($db->quoteName('a.id') . ' = :id')->bind(':id', $id, ParameterType::INTEGER);
            } else {
                $like = '%' . str_replace(' ', '%', $search) . '%';
                $query->where('(' . $db->quoteName('a.title') . ' LIKE :s1 OR ' . $db->quoteName('a.uid') . ' LIKE :s2 OR '
                    . $db->quoteName('a.contact_email') . ' LIKE :s3 OR ' . $db->quoteName('p.name') . ' LIKE :s4)')
                    ->bind(':s1', $like)
                    ->bind(':s2', $like)
                    ->bind(':s3', $like)
                    ->bind(':s4', $like);
            }
        }

        $state = (string) $this->getState('filter.state');

        if ($state === '0' || $state === '1') {
            $stateValue = (int) $state;
            $query->where($db->quoteName('a.state') . ' = :state')->bind(':state', $stateValue, ParameterType::INTEGER);
        }

        $personal = (string) $this->getState('filter.personal');

        if ($personal === '0' || $personal === '1') {
            $personalValue = (int) $personal;
            $query->where($db->quoteName('a.is_personal') . ' = :personal')->bind(':personal', $personalValue, ParameterType::INTEGER);
        }

        if ((string) $this->getState('filter.pending') === '1') {
            $query->where('EXISTS (SELECT 1 FROM ' . $db->quoteName('#__jed_organisation_members', 'm3')
                . ' WHERE ' . $db->quoteName('m3.organisation_id') . ' = ' . $db->quoteName('a.id')
                . ' AND ' . $db->quoteName('m3.state') . ' = ' . $requested . ')');
        }

        $ordering  = (string) $this->getState('list.ordering', 'a.title');
        $direction = strtoupper((string) $this->getState('list.direction', 'ASC')) === 'DESC' ? 'DESC' : 'ASC';

        if (!\in_array($ordering, ['a.id', 'a.title', 'a.created', 'a.state'], true)) {
            $ordering = 'a.title';
        }

        $query->order($db->quoteName($ordering) . ' ' . $direction);

        return $query;
    }
}
