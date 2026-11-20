<?php

/**
 * @package JED
 *
 * @subpackage Organisations
 *
 * @copyright (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Site\Model;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Jed\Component\Jed\Administrator\Organisation\MembershipState;
use Jed\Component\Jed\Administrator\Organisation\OrganisationService;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;

/**
 * "My Organisations" (PRD-09): where a user sees the organisations they belong to, answers
 * invitations, follows their requests, and starts a new organisation or asks to join one.
 *
 * @since 4.1.0
 */
class OrganisationsModel extends BaseDatabaseModel
{
    /**
     * @param MembershipState[] $states The states.
     *
     * @return object[]
     *
     * @since 4.1.0
     */
    public function getMine(array $states): array
    {
        return $this->service()->organisationsOf((int) $this->getCurrentUser()->id, $states);
    }

    /**
     * Organisations taking requests, minus the ones the user already has a row in.
     *
     * @return object[]
     *
     * @since 4.1.0
     */
    public function getJoinable(): array
    {
        $taken = array_map(
            static fn (object $o): int => (int) $o->id,
            $this->getMine([MembershipState::ACTIVE, MembershipState::REQUESTED, MembershipState::INVITED])
        );

        return array_values(array_filter(
            $this->service()->acceptingRequests(),
            static fn (object $o): bool => !\in_array((int) $o->id, $taken, true)
        ));
    }

    /**
     * @return OrganisationService
     *
     * @since 4.1.0
     */
    private function service(): OrganisationService
    {
        return new OrganisationService($this->getDatabase());
    }
}
