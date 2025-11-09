<?php

namespace WP_SMS\Core;

use WP_SMS\Core\Operations\Updater;

class CoreFactory
{
    /**
     * Create and return the updater service.
     *
     * @return Updater Updater service instance.
     */
    public static function updater()
    {
        return new Updater();
    }
    
    /**
     * Check whether the plugin is marked as a fresh install.
     *
     * @return bool True if the fresh-install flag is set, false otherwise.
     */
    public static function isFresh()
    {
        $isFresh = get_option('wp_sms_is_fresh', false);

        if ($isFresh) {
            return true;
        }

        return false;
    }
}