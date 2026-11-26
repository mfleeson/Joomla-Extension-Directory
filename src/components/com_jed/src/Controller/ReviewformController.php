<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Site\Controller;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Exception;
use Jed\Component\Jed\Site\Helper\JedHelper;
use Jed\Component\Jed\Site\Model\ExtensionModel;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\FormController;
use Joomla\CMS\Router\Route;

/**
 * Review class.
 *
 * @since 4.0.0
 */
class ReviewformController extends FormController
{
    /**
     * edit
     *
     * Checks out the review for editing and redirects to the edit screen. If no
     * `id` is given (the "Write a review" link) but the current user already has a
     * review for the given `extension_id`, transparently edits that existing review
     * instead of starting a blank one - a user may only ever have one review per
     * extension.
     *
     * @param $key
     * @param $urlVar
     *
     * @since  1.0
     * @throws Exception
     */
    public function edit($key = null, $urlVar = null): void
    {
        /* @var $app \Joomla\CMS\Application\SiteApplication */
        $app = Factory::getApplication();

        // Get the previous edit id (if any) and the current edit id.
        $previousId  = (int) $app->getUserState('com_jed.edit.review.id');
        $editId      = $app->getInput()->getInt('id', 0);
        $extensionId = $app->getInput()->getInt('extension_id', 0);

        if (!$editId && $extensionId && JedHelper::isLoggedIn()) {
            $extensionModel = new ExtensionModel();
            $userId         = (int) Factory::getApplication()->getIdentity()->id;
            $existingId     = $extensionModel->getUserReviewId($extensionId, $userId);

            if ($existingId !== null) {
                $editId = $existingId;
            }
        }

        // Only the author (or a moderator) edits a review. Anything else is treated as "write a new
        // review": holding somebody else's id here checked their review out to this user - any
        // logged-in user could lock any review against editing by its author and by moderators.
        if ($editId > 0) {
            $user  = $app->getIdentity();
            $table = $this->getModel('Reviewform', 'Site')->getTable();

            if (
                !JedHelper::isLoggedIn()
                || !$table->load($editId)
                || ((int) $table->created_by !== (int) $user->id && !$user->authorise('core.edit', 'com_jed'))
            ) {
                $editId = 0;
            }
        }

        // Set the user id for the user to edit in the session.
        $app->setUserState('com_jed.edit.review.id', $editId);

        // extension_id doesn't survive the redirect below as a query param, so stash it in
        // session state the same way - Reviewform's HtmlView falls back to this when adding
        // a brand new review (an existing review's own extension_id comes from the loaded row).
        if ($extensionId) {
            $app->setUserState('com_jed.edit.review.extension_id', $extensionId);
        }

        // Get the model.
        $model = $this->getModel('Reviewform', 'Site');

        // Check out the item
        if ($editId) {
            $model->checkout($editId);
        }

        // Check in the previous user.
        if ($previousId) {
            $model->checkin($previousId);
        }

        // Redirect to the edit screen.
        $this->setRedirect(Route::_('index.php?option=com_jed&view=reviewform&layout=edit', false));
    }

    /**
     * Method to save data.
     *
     * @param null $key
     * @param null $urlVar
     *
     * @return void
     *
     * @since  4.0.0
     * @throws Exception
     */
    public function save($key = null, $urlVar = null): void
    {
        //phpinfo();
        //echo "A";print_r($_POST);echo "B";exit();
        // Check for request forgeries.
        $this->checkToken();

        // Initialise variables.
        $app   = Factory::getApplication();
        $model = $this->getModel('Reviewform', 'Site');

        // Get the user data.
        $data = $app->getInput()->get('jform', [], 'array');

        // The five ratings arrive in the hidden *_num inputs the star widget fills. They were
        // copied across unchecked, so a hand-made POST could store any number the decimal(2,1)
        // column accepts (-9.9 to 9.9) on a 0-5 scale and drag a listing's average wherever it
        // liked; and the overall score divided by five even when an optional rating was left
        // unscored (the widget sends -1), punishing the listing for a question not answered.
        $ratings = [
            'functionality'   => 'func_num',
            'ease_of_use'     => 'ease_num',
            'support'         => 'support_num',
            'documentation'   => 'doc_num',
            'value_for_money' => 'value_num',
        ];
        $scored = [];

        foreach ($ratings as $column => $input) {
            $data[$column] = self::rating($data[$input] ?? null);

            if ($data[$column] !== null) {
                $scored[] = $data[$column];
            }
        }

        $data['overall_score'] = $scored === [] ? null : round(array_sum($scored) / \count($scored), 1);
        // Validate the posted data.
        $form = $model->getForm();

        // Validate the posted data.
        $data = $model->validate($form, $data);
        // Check for errors.
        if ($data === false) {
            $this->app->enqueueMessage(Text::_('COM_JED_ERROR_SAVING_DATA'), 'warning');

            $jform = $this->input->get('jform', [], 'ARRAY');

            // Save the data in the session.
            $app->setUserState('com_jed.edit.review.data', $jform);

            // Redirect back to the edit screen.
            $id = (int) $app->getUserState('com_jed.edit.review.id');
            $this->setRedirect(Route::_('index.php?option=com_jed&view=reviewform&layout=edit&id=' . $id, false));

            $this->redirect();
        }

        // Attempt to save the data. The model refuses reviews the rules do not allow (own
        // listing, second review, a listing that is not public, a ban) with an exception whose
        // message is meant for the reviewer.
        try {
            $return = $model->save($data);
        } catch (Exception $e) {
            $app->setUserState('com_jed.edit.review.data', $data);
            $this->setMessage($e->getMessage(), 'warning');
            $this->setRedirect(Route::_('index.php?option=com_jed&view=reviews', false));

            return;
        }

        // Check for errors.
        if ($return === false) {
            // Save the data in the session.
            $app->setUserState('com_jed.edit.review.data', $data);

            // Redirect back to the edit screen.
            $id = (int) $app->getUserState('com_jed.edit.review.id');
            $this->setMessage(Text::_('JLIB_APPLICATION_ERROR_SAVE_FAILED'), 'warning');
            $this->setRedirect(Route::_('index.php?option=com_jed&view=reviewform&id=' . $id, false));

            return;
        }

        // Clear the profile id from the session.
        $app->setUserState('com_jed.edit.review.id', null);

        // Redirect to the list screen.
        $this->setMessage(Text::_('COM_JED_GENERAL_ITEM_SAVED_SUCCESSFULLY_LABEL'));
        $menu = Factory::getApplication()->getMenu();
        $item = $menu->getActive();
        $url  = (empty($item->link) ? 'index.php?option=com_jed&view=reviews' : $item->link);
        $this->setRedirect(Route::_($url, false));

        // Flush the data from the session.
        $app->setUserState('com_jed.edit.review.data', null);
    }

    /**
     * Method to abort current operation
     *
     * @param null $key
     *
     * @return void
     *
     * @since  4.0.0
     * @throws Exception
     */
    public function cancel($key = null): void
    {
        /* @var $app \Joomla\CMS\Application\SiteApplication */
        $app = Factory::getApplication();

        // Get the current edit id.
        $editId = (int) $app->getUserState('com_jed.edit.review.id');

        // Get the model.
        $model = $this->getModel('Reviewform', 'Site');

        // Check in the item
        if ($editId) {
            $model->checkin($editId);
        }

        $menu = Factory::getApplication()->getMenu();
        $item = $menu->getActive();
        $url  = (empty($item->link) ? 'index.php?option=com_jed&view=reviews' : $item->link);
        $this->setRedirect(Route::_($url, false));
    }

    /**
     * A star rating as stored: 0.5 to 5 in half steps, or null for "not scored".
     *
     * @param mixed $value The posted value.
     *
     * @return float|null
     *
     * @since 4.0.0
     */
    private static function rating(mixed $value): ?float
    {
        if (!is_numeric($value)) {
            return null;
        }

        $value = round((float) $value * 2) / 2;

        return $value >= 0.5 && $value <= 5.0 ? $value : null;
    }
}
