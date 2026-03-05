<?php

namespace WP_SMS\Admin\ModalHandler;

use WP_SMS\Utils\OptionUtil as Option;

if (!defined('ABSPATH')) exit;

class Modal
{
    const MODAL_OPTION_KEY = 'user_modals';

    /**
     * Check if a modal has been seen by the current user.
     *
     * @param string $modalId The modal ID to check.
     *
     * @return bool
     */
    public static function hasBeenSeen($modalId)
    {
        return !empty(self::getState($modalId));
    }

    /**
     * Updates the state of a modal.
     *
     * @param string $modalId The name of the modal.
     *
     * @return void
     */
    public static function updateState($modalId)
    {
        $modals           = self::getStates();
        $modals[$modalId] = self::generateStateObject($modalId);

        Option::saveOptionGroup(get_current_user_id(), $modals, self::MODAL_OPTION_KEY);
    }

    /**
     * Retrieves the state of the modals.
     *
     * @return array The state of all modals.
     */
    private static function getStates()
    {
        return Option::getOptionGroup(self::MODAL_OPTION_KEY, get_current_user_id(), []);
    }

    /**
     * Retrieves the state of a modal.
     *
     * @param string $modal .
     *
     * @return array|false The state of the modal, or false if the modal has not been opened before.
     */
    private static function getState($modal)
    {
        $modals = self::getStates();
        return isset($modals[$modal]) ? $modals[$modal] : false;
    }

    /**
     * Generates a new state object for a given modalId.
     *
     * @param string $modalId
     *
     * @return array The state object.
     */
    private static function generateStateObject($modalId)
    {
        $modal = self::getState($modalId);

        if (!is_array($modal)) {
            $modal = [];
        }

        $state = [
            'times_opened' => (is_array($modal) && isset($modal['times_opened'])) ? $modal['times_opened'] + 1 : 1,
            'last_opened'  => gmdate('Y-m-d H:i:s')
        ];

        return $state;
    }
}