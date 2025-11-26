<?php

namespace WP_SMS\Components;

if (!defined('ABSPATH')) exit;

class Ajax
{
    public static function register($action, $callback, $public = true)
    {
        add_action('wp_ajax_wp_sms_' . $action, $callback);

        if ($public) {
            add_action('wp_ajax_nopriv_wp_sms_' . $action, $callback);
        }
    }
}