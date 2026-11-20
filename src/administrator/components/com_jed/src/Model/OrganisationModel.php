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

use Jed\Component\Jed\Administrator\Organisation\OrganisationService;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\AdminModel;
use Joomla\CMS\Table\Table;
use RuntimeException;

/**
 * One organisation, for the JED team (PRD-09 R13).
 *
 * Saving goes through OrganisationService rather than straight into the table, for the same reason
 * every other write does: the UID and alias are generated there, a new organisation needs its first
 * administrator's membership row, and the details are validated in one place.
 *
 * @since 4.1.0
 */
class OrganisationModel extends AdminModel
{
    /**
     * @var string
     *
     * @since 4.1.0
     */
    protected $text_prefix = 'COM_JED_ORGANISATION';

    /**
     * @param string $name    The table name.
     * @param string $prefix  The class prefix.
     * @param array  $options Configuration.
     *
     * @return Table
     *
     * @since 4.1.0
     */
    public function getTable($name = 'Organisation', $prefix = 'Administrator', $options = []): Table
    {
        return parent::getTable($name, $prefix, $options);
    }

    /**
     * @param array $data     Data.
     * @param bool  $loadData Load the item.
     *
     * @return Form|false
     *
     * @since 4.1.0
     */
    public function getForm($data = [], $loadData = true)
    {
        $form = $this->loadForm('com_jed.organisation', 'organisation', ['control' => 'jform', 'load_data' => $loadData]);

        if (!$form) {
            return false;
        }

        $id = (int) ($data['id'] ?? $this->getState($this->getName() . '.id'));

        if ($id > 0) {
            // Who runs an existing organisation changes through the member actions, each with its
            // own rule (the primary contact must be an active administrator).
            $form->setFieldAttribute('primary_user_id', 'readonly', 'true');
            $form->setFieldAttribute('primary_user_id', 'required', 'false');
            $form->setFieldAttribute('primary_user_id', 'filter', 'unset');
        }

        return $form;
    }

    /**
     * @return mixed
     *
     * @since 4.1.0
     */
    protected function loadFormData()
    {
        $data = Factory::getApplication()->getUserState('com_jed.edit.organisation.data', []);

        return !empty($data) ? $data : ($this->getItem() ?: []);
    }

    /**
     * The organisation with all its membership rows.
     *
     * @param int|null $pk The id.
     *
     * @return object|false
     *
     * @since 4.1.0
     */
    public function getItem($pk = null)
    {
        $item = parent::getItem($pk);

        if ($item && !empty($item->id)) {
            $service             = new OrganisationService($this->getDatabase());
            $item->members       = $service->members((int) $item->id);
            $item->listing_count = $service->listingCount((int) $item->id);
        }

        return $item;
    }

    /**
     * @param array $data The validated form data.
     *
     * @return bool
     *
     * @since 4.1.0
     */
    public function save($data): bool
    {
        $service = new OrganisationService($this->getDatabase());
        $actorId = (int) $this->getCurrentUser()->id;
        $id      = (int) ($data['id'] ?? 0);

        try {
            if ($id > 0) {
                $service->update($id, $data, $actorId);
            } else {
                $id = $service->create($data, (int) ($data['primary_user_id'] ?? 0), false, $actorId);
            }

            if (isset($data['state'])) {
                $service->setState($id, (int) $data['state'] === 1, $actorId);
            }
        } catch (RuntimeException $e) {
            $this->setError($e->getMessage());

            return false;
        }

        $this->setState($this->getName() . '.id', $id);
        $this->setState($this->getName() . '.new', (int) ($data['id'] ?? 0) === 0);

        return true;
    }

    /**
     * An organisation that owns listings cannot be deleted: its listings would have no owner. The
     * foreign key refuses it too; this says why.
     *
     * @param object $record The organisation.
     *
     * @return bool
     *
     * @since 4.1.0
     */
    protected function canDelete($record): bool
    {
        if ((new OrganisationService($this->getDatabase()))->listingCount((int) $record->id, true) > 0) {
            Factory::getApplication()->enqueueMessage(Text::sprintf('COM_JED_ORGANISATION_ERROR_HAS_LISTINGS', $record->title), 'warning');

            return false;
        }

        return parent::canDelete($record);
    }
}
