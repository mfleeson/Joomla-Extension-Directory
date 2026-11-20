<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Site\Model;

// No direct access.
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Exception;
use Jed\Component\Jed\Administrator\Access\JedAccessHelper;
use Jed\Component\Jed\Site\Helper\JedHelper;
use Jed\Component\Jed\Administrator\Enum\TicketType;
use Jed\Component\Jed\Administrator\Traits\TicketHandlingTrait;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\FormModel;
use Joomla\CMS\Table\Table;
use Joomla\Database\ParameterType;
use Joomla\Utilities\ArrayHelper;
use stdClass;

/**
 * Review Form model.
 *
 * @since 4.0.0
 */
class ReviewformModel extends FormModel
{
    use TicketHandlingTrait;
    /**
     * The item object
     *
     * @var   mixed
     * @since 4.0.0
     */
    private mixed $item = null;

    /**
     * Data Table
     *
     * @var   string
     * @since 4.0.0
     **/
    private string $dbtable = "#__jed_reviews";
    /**
     * Default ticket id
     *
     * @var   int
     * @since 4.0.0
     **/
    private int $id = -1;



    /**
     * Method to get the profile form.
     *
     * The base form is loaded from XML
     *
     * @param array $data     An optional array of data for the form to interogate.
     * @param bool  $loadData True if the form is to load its own data (default case), false if not.
     *
     * @return Form    A Form object on success, false on failure
     *
     * @since  4.0.0
     * @throws Exception
     */
    public function getForm($data = [], $loadData = true, $formname = 'jform'): Form
    {
        // Get the form.
        $form = $this->loadForm(
            'com_jed.review',
            'reviewform',
            [
                'control'   => $formname,
                'load_data' => $loadData,
            ]
        );

        if (!is_object($form)) {
            throw new Exception(Text::_('JERROR_LOADFILE_FAILED'), 500);
        }

        return $form;
    }

    /**
     * Method to get the table
     *
     * @param string $name
     * @param string $prefix  Optional prefix for the table class name
     * @param array  $options
     *
     * @return Table|bool Table if found, bool false on failure
     * @since  4.0.0
     * @throws Exception
     */
    public function getTable($name = 'Review', $prefix = 'Administrator', $options = []): Table|bool
    {

        return parent::getTable($name, $prefix, $options);
    }

    /**
     * Method to get the data that should be injected in the form.
     *
     * @return mixed The default data is an empty array.
     * @since  4.0.0
     * @throws Exception
     */
    protected function loadFormData(): mixed
    {
        $data = Factory::getApplication()->getUserState('com_jed.edit.review.data', []);

        if (empty($data)) {
            $data = $this->getItem();
        }

        if ($data) {
            return $data;
        }

        return [];
    }

    /**
     * Method to autopopulate the model state.
     *
     * Note. Calling getState in this method will result in recursion.
     *
     * @return void
     *
     * @since 4.0.0
     *
     * @throws Exception
     */
    protected function populateState(): void
    {
        /* @var $app \Joomla\CMS\Application\SiteApplication */
        $app = Factory::getApplication();

        // Load state from the request userState on edit or from the passed variable on default
        if (Factory::getApplication()->input->get('layout') == 'edit') {
            $id = Factory::getApplication()->getUserState('com_jed.edit.extension.id');
        } else {
            $id = Factory::getApplication()->input->get('id');
            Factory::getApplication()->setUserState('com_jed.edit.extension.id', $id);
        }

        $this->setState('extension.id', $id);

        // Load the parameters.
        $params       = $app->getParams();
        $params_array = $params->toArray();

        if (isset($params_array['item_id'])) {
            $this->setState('extension.id', $params_array['item_id']);
        }

        $this->setState('params', $params);
    }

    /**
     * Method to get an object.
     *
     * @param int|null $id The id of the object to get.
     *
     * @return object|bool Object on success, false on failure.
     *
     * @since  4.0.0
     * @throws Exception
     */
    public function getItem(?int $id = null)
    {
        if ($this->item === null) {
            $this->item = false;

            if (empty($id)) {
                $id = $this->getState('extension.id');
            }

            $extensionId = $id;

            $user  = $this->getCurrentUser();
            $db    = $this->getDatabase();
            $query = $db->getQuery(true);
            $query->select('id')->from($db->quoteName('#__jed_reviews'))->where($db->quoteName('extension_id') . ' = ' . $db->quote($id))
            ->where($db->quoteName('created_by') . ' = ' . $user->id)
            ->where($db->quoteName('state') . ' != -2');
            $db->setQuery($query);
            $existingReviewId = $db->loadResult();

            // Get a level row instance.
            $table      = $this->getTable();
            $this->item = ArrayHelper::toObject(ArrayHelper::fromObject($table), stdClass::class);

            if ($table !== false && $table->load($existingReviewId) && !empty($table->id)) {
                $user = Factory::getApplication()->getIdentity();
                if (empty($table->id) || JedHelper::isAdminOrSuperUser() || $table->created_by == $user->id) {
                    // Convert the Table to a clean stdClass. extension_id comes from the loaded
                    // row itself (the user is editing their own existing review).
                    $this->item = ArrayHelper::toObject(ArrayHelper::fromObject($table), stdClass::class);

                    if (isset($this->item->category_id) && is_object($this->item->category_id)) {
                        $this->item->category_id = ArrayHelper::fromObject($this->item->category_id);
                    }
                } else {
                    throw new Exception(Text::_("JERROR_ALERTNOAUTHOR"), 401);
                }
            } else {
                // No existing review for this extension/user - defaults for a brand-new review,
                // pinned to the extension the reviewform was opened for (from the "id" URL
                // param/session state), not left at the table's own empty/zero default.
                $this->item->extension_id = $extensionId;
            }
        }

        return $this->item;
    }

    /**
     * Returns Review ID
     *
     * @return int
     *
     * @since 4.0.0
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Method to save the form data.
     *
     * @param array $data The form data
     *
     * @return bool
     *
     * @since  4.0.0
     * @throws Exception
     */
    public function save(array $data): bool
    {
        // An existing review is identified by a review id and by nothing else. This used to fall
        // back to getState('extension.id') - an *extension* id used as a *review* id, which would
        // have made the edit branch load an unrelated review on any request that populates that
        // state (layout=edit, or the item_id menu parameter). Latent rather than exploited,
        // because the ordinary save flow leaves extension.id null, but wrong either way.
        $id         = (int) ($data['id'] ?? 0);
        $isLoggedIn = JedHelper::isLoggedIn();

        // The per-user gate (P1-05), asked before anything is written. Two questions in one: may
        // this person review at all, and are they barred from this developer or this category in
        // particular? The second is the point of targeted bans - somebody barred from reviewing
        // one developer can still review everybody else.
        if ($isLoggedIn) {
            JedAccessHelper::assertMayReview(
                (int) Factory::getApplication()->getIdentity()->id,
                (int) ($data['extension_id'] ?? 0)
            );
        }

        // Moderation's fields, not the reviewer's. reviewform.xml drops them with filter="unset"
        // before the model is reached; this is the line that actually enforces it, and it holds
        // for any caller - a future API endpoint included - not just for posts through that form.
        // A review is published by moderation (P1-02) and flagged by staff; the address is
        // recorded server-side, never taken from the request.
        $data['state']      = 0;
        $data['ip_address'] = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        unset($data['flagged']);

        if ($id && $isLoggedIn) {
            /* Editing an existing review - a user may only ever edit their own. */
            $table = $this->getTable();

            if (!$table->load($id)) {
                throw new Exception(Text::_('COM_JED_ITEM_DOESNT_EXIST'), 404);
            }

            if ((int) $table->created_by !== (int) Factory::getApplication()->getIdentity()->id && !JedHelper::isAdminOrSuperUser()) {
                throw new Exception(Text::_("JERROR_ALERTNOAUTHOR"), 401);
            }

            $data['id'] = $id;

            // A review stays attached to the listing it was written for. extension_id is a form
            // field, and posting another value moved the review - with its ratings - onto a
            // different listing, past every check the new-review path makes (self-review, one
            // review per listing, a listing the user can actually see). The ban check above was
            // made against the posted id, so it is repeated for the real one.
            $data['extension_id'] = (int) $table->extension_id;

            JedAccessHelper::assertMayReview((int) Factory::getApplication()->getIdentity()->id, (int) $table->extension_id);

            if ($table->save($data) === true) {
                $this->id = $table->id;

                return $table->id;
            }
            return false;
        }

        if (!$id && $isLoggedIn) {
            /* Any logged-in user can make a new review - of a listing they can see, that is not
               their own, and that they have not reviewed already. */
            $this->assertMayWriteNewReview((int) ($data['extension_id'] ?? 0), (int) Factory::getApplication()->getIdentity()->id);

            $table = $this->getTable();

            if ($table->save($data) === true) {
                $this->id = $table->id;

                $this->triggerTicket(
                    TicketType::Review,
                    $table->id,
                    Text::sprintf('COM_JED_TICKET_NEW_REVIEW_EVENT', $data['title'] ?? $table->id)
                );

                return $table->id;
            }
            return false;
        }
        throw new Exception(Text::_("JERROR_ALERTNOAUTHOR"), 401);
    }

    /**
     * Refuse a new review the rules do not allow.
     *
     * - The listing must exist and be visible to the reviewer (published, approved, not blocked,
     *   not deleted). Otherwise reviews could be attached to listings awaiting approval, blocked
     *   or deleted ones - and they feed the listing's score.
     * - Not one's own listing: "Developers must not review their own extensions"
     *   (helpdocs/developers/responding-to-reviews.md). Owners and accepted maintainers alike.
     * - One review per listing and person. The edit link already opens the existing review; this
     *   makes the rule hold for a direct POST as well, so nobody can stack ratings.
     *
     * @param int $extensionId The listing.
     * @param int $userId      The reviewer.
     *
     * @return void
     *
     * @throws Exception
     *
     * @since 4.0.0
     */
    private function assertMayWriteNewReview(int $extensionId, int $userId): void
    {
        if ($extensionId <= 0) {
            throw new Exception(Text::_('COM_JED_ITEM_DOESNT_EXIST'), 404);
        }

        $db      = $this->getDatabase();
        $listing = $db->setQuery(
            $db->getQuery(true)
                ->select($db->quoteName(['id', 'approved', 'state', 'blocked', 'deleted']))
                ->from($db->quoteName('#__jed_extensions'))
                ->where($db->quoteName('id') . ' = :eid')
                ->bind(':eid', $extensionId, ParameterType::INTEGER)
        )->loadObject();

        if (
            $listing === null
            || (int) $listing->approved !== 1
            || (int) $listing->state !== 1
            || (int) $listing->blocked !== 0
            || (int) $listing->deleted !== 0
        ) {
            throw new Exception(Text::_('COM_JED_ITEM_DOESNT_EXIST'), 404);
        }

        if (JedHelper::isOwnerOrMaintainer($extensionId)) {
            throw new Exception(Text::_('COM_JED_REVIEW_ERROR_OWN_EXTENSION'), 403);
        }

        $existing = $db->setQuery(
            $db->getQuery(true)
                ->select('1')
                ->from($db->quoteName('#__jed_reviews'))
                ->where($db->quoteName('extension_id') . ' = :eid')
                ->where($db->quoteName('created_by') . ' = :uid')
                ->where($db->quoteName('state') . ' <> -2')
                ->bind(':eid', $extensionId, ParameterType::INTEGER)
                ->bind(':uid', $userId, ParameterType::INTEGER),
            0,
            1
        )->loadResult();

        if ($existing) {
            throw new Exception(Text::_('COM_JED_REVIEW_ERROR_ALREADY_REVIEWED'), 409);
        }
    }
}
