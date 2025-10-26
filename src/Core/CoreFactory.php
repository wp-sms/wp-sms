<?php

namespace WP_SMS\Core;

class CoreFactory
{
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