<?php

/**
 * @package        JED
 *
 * @copyright  (C) 2022 Open Source Matters, Inc.  <https://www.joomla.org>
 * @license        GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Administrator\Table;

// No direct access
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Exception;
use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseDriver;
use Jed\Component\Jed\Administrator\Helper\JedHelper;

/**
 * Extensionvarieddatum table
 *
 * @since 4.0.0
 */
class ExtensionvarieddatumTable extends Table
{
    /**
     * Constructor
     *
     * @param   DatabaseDriver  $db  A database connector object
     *
     * @since 4.0.0
     */
    public function __construct(DatabaseDriver $db)
    {
        $this->typeAlias = 'com_jed.extensionvarieddatum';
        parent::__construct('#__jed_extension_varied_data', 'id', $db);
        $this->setColumnAlias('published', 'state');
    }

    /**
     * Define a namespaced asset name for inclusion in the #__assets table
     *
     * @return string The asset name
     *
     * @see Table::_getAssetName
     *
     * @since 4.0.0
     */
    protected function _getAssetName(): string
    {
        $k = $this->_tbl_key;

        return $this->typeAlias . '.' . (int) $this->$k;
    }

    /**
     * Overloaded bind function to pre-process the params.
     *
     * @param   array  $src     Named array
     * @param   mixed  $ignore  Optional array or list of parameters to ignore
     *
     * @return  null|string  null is operation was satisfactory, otherwise returns an error
     *
     * @see     Table:bind
     * @throws Exception
     * @since   4.0.0
     */
    public function bind($src, $ignore = ''): ?string
    {
        // Support for multiple or not foreign key field: extension_id
        if (!empty($src['extension_id'])) {
            if (is_array($src['extension_id'])) {
                $src['extension_id'] = implode(',', $src['extension_id']);
            }
        } else {
            $src['extension_id'] = 0;
        }

        // Support for multiple or not foreign key field: supply_option_id
        if (!empty($src['supply_option_id'])) {
            if (is_array($src['supply_option_id'])) {
                $src['supply_option_id'] = implode(',', $src['supply_option_id']);
            }
        } else {
            $src['supply_option_id'] = 0;
        }

        if ($src['id'] == 0 && empty($src['created_by'])) {
            $src['created_by'] = JedHelper::getUser()->id;
        }

        // Support for multiple field: download_integration_type
        if (isset($src['download_integration_type'])) {
            if (is_array($src['download_integration_type'])) {
                $src['download_integration_type'] = implode(',', $src['download_integration_type']);
            }
        } else {
            $src['download_integration_type'] = '';
        }

        // Support for checkbox field: is_default_data
        if (!isset($src['is_default_data'])) {
            $src['is_default_data'] = 0;
        }

        return parent::bind($src, $ignore);
    }

    /**
     * Overloaded check function
     *
     * @return bool
     *
     * @since 4.0.0
     */
    public function check(): bool
    {
        // If there is an ordering column and this is a new row then get the next ordering value
        if (property_exists($this, 'ordering') && $this->id == 0) {
            $this->ordering = self::getNextOrder();
        }

        return parent::check();
    }

    /**
     * Delete a record by id
     *
     * @param   mixed  $pk  Primary key value to delete. Optional
     *
     * @return bool
     *
     * @since 4.0.0
     */
    public function delete($pk = null): bool
    {
        $this->load($pk);

        return parent::delete($pk);
    }

    /**
     * Get the type alias for the history table
     *
     * @return  string  The alias as described above
     *
     * @since   4.0.0
     */
    public function getTypeAlias(): string
    {
        return $this->typeAlias;
    }

    /**
     * Method to store a row in the database from the Table instance properties.
     *
     * If a primary key value is set the row with that primary key value will be updated with the instance property values.
     * If no primary key value is set a new row will be inserted into the database with the properties from the Table instance.
     *
     * @param   boolean  $updateNulls  True to update fields even if they are null.
     *
     * @return  boolean  True on success.
     *
     * @since   4.0.0
     */
    public function store($updateNulls = true): bool
    {
        return parent::store($updateNulls);
    }
}
