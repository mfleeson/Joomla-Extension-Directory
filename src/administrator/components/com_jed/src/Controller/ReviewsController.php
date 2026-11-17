<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Administrator\Controller;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Exception;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\AdminController;
use Joomla\CMS\Uri\Uri;
use Joomla\Utilities\ArrayHelper;

/**
 * Reviews list controller class.
 *
 * @since 4.0.0
 */
class ReviewsController extends AdminController
{
    /**
     * Method to clone existing Reviews
     *
     * @return void
     *
     * @throws Exception
     *
     * @since 4.0.0
     */
    public function duplicate()
    {
        // Check for request forgeries
        $this->checkToken();

        // Get id(s)
        $pks = $this->input->post->get('cid', [], 'array');

        try {
            if (empty($pks)) {
                throw new Exception(Text::_('COM_JED_NO_ELEMENT_SELECTED'));
            }

            ArrayHelper::toInteger($pks);
            $model = $this->getModel();
            $model->duplicate($pks);
            $this->setMessage(Text::_('COM_JED_ITEMS_SUCCESS_DUPLICATED'));
        } catch (Exception $e) {
            Factory::getApplication()->enqueueMessage($e->getMessage(), 'warning');
        }

        $this->setRedirect('index.php?option=com_jed&view=reviews');
    }

    /**
     * Approves the pending developer response(s) on the selected reviews.
     *
     * @return void
     *
     * @throws Exception
     *
     * @since 4.0.0
     */
    public function publishResponse(): void
    {
        $this->checkToken();

        if (!Factory::getApplication()->getIdentity()->authorise('core.edit.state', 'com_jed')) {
            throw new Exception(Text::_('JLIB_APPLICATION_ERROR_EDITSTATE_NOT_PERMITTED'), 403);
        }

        $pks = $this->input->post->get('cid', [], 'array');
        ArrayHelper::toInteger($pks);

        if (empty($pks)) {
            Factory::getApplication()->enqueueMessage(Text::_('COM_JED_NO_ELEMENT_SELECTED'), 'warning');
        } else {
            $this->getModel()->publishResponse($pks);
            $this->setMessage(Text::_('COM_JED_ITEMS_SUCCESS_PUBLISHED'));
        }

        $this->setRedirect($this->returnUrl('index.php?option=com_jed&view=reviews'));
    }

    /**
     * Rejects/deletes the developer response(s) on the selected reviews.
     *
     * @return void
     *
     * @throws Exception
     *
     * @since 4.0.0
     */
    public function deleteResponse(): void
    {
        $this->checkToken();

        if (!Factory::getApplication()->getIdentity()->authorise('core.edit.state', 'com_jed')) {
            throw new Exception(Text::_('JLIB_APPLICATION_ERROR_EDITSTATE_NOT_PERMITTED'), 403);
        }

        $pks = $this->input->post->get('cid', [], 'array');
        ArrayHelper::toInteger($pks);

        if (empty($pks)) {
            Factory::getApplication()->enqueueMessage(Text::_('COM_JED_NO_ELEMENT_SELECTED'), 'warning');
        } else {
            $this->getModel()->deleteResponse($pks);
            $this->setMessage(Text::_('COM_JED_ITEMS_SUCCESS_DELETED'));
        }

        $this->setRedirect($this->returnUrl('index.php?option=com_jed&view=reviews'));
    }

    /**
     * Publish, unpublish, archive or trash the selected reviews.
     *
     * The inherited task, plus one thing: when it was started from a ticket (the Review ticket's
     * Approve and Reject actions post a `return` - see layouts/ticket/action_button.php), the team
     * member goes back to that ticket instead of to the Reviews list.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function publish()
    {
        parent::publish();

        $this->redirectToReturn();
    }

    /**
     * Delete the selected reviews - the inherited task, returning to the ticket it was started from.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function delete()
    {
        parent::delete();

        $this->redirectToReturn();
    }

    /**
     * Replace the redirect with the posted `return` when there is a usable one.
     *
     * The message the inherited task set is kept: setRedirect() only replaces it when given one.
     *
     * @return void
     *
     * @since 4.0.0
     */
    private function redirectToReturn(): void
    {
        $return = $this->returnUrl(null);

        if ($return !== null) {
            $this->setRedirect($return);
        }
    }

    /**
     * The internal address posted as `return` (base64), or the fallback.
     *
     * Only internal addresses are followed, so the field cannot be used to send a team member
     * off-site after an action.
     *
     * @param string|null $fallback Where to go without a usable `return`.
     *
     * @return string|null
     *
     * @since 4.0.0
     */
    private function returnUrl(?string $fallback): ?string
    {
        $return = (string) $this->input->getBase64('return', '');

        if ($return !== '') {
            $url = base64_decode($return);

            if ($url !== false && $url !== '' && Uri::isInternal($url)) {
                return $url;
            }
        }

        return $fallback;
    }

    /**
     * Proxy for getModel.
     *
     * @param string $name   Optional. Model name
     * @param string $prefix Optional. Class prefix
     * @param array  $config Optional. Configuration array for model
     *
     * @return object  The Model
     *
     * @since 4.0.0
     */
    public function getModel($name = 'Review', $prefix = 'Administrator', $config = []): object
    {
        return parent::getModel($name, $prefix, ['ignore_request' => true]);
    }



    /**
     * Method to save the submitted ordering values for records via AJAX.
     *
     * @return void
     *
     * @since 4.0.0
     *
     * @throws Exception
     */
    public function saveOrderAjax(): void
    {
        // Same check as core's saveOrderAjax(); the draggable list sends the token with its request.
        $this->checkToken();

        // Get the input
        $input = Factory::getApplication()->input;
        $pks   = $input->post->get('cid', [], 'array');
        $order = $input->post->get('order', [], 'array');

        // Sanitize the input
        ArrayHelper::toInteger($pks);
        ArrayHelper::toInteger($order);

        // Get the model
        $model = $this->getModel();

        // Save the ordering
        $return = $model->saveorder($pks, $order);

        if ($return) {
            echo "1";
        }

        // Close the application
        Factory::getApplication()->close();
    }
}
