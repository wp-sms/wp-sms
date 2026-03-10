<?php

/**
 * Legacy function shims.
 *
 * These functions exist solely to prevent fatal errors in old add-ons.
 * They are no-ops or return safe defaults.
 */

defined('ABSPATH') || exit;

if (!function_exists('WPSms')) {
    function WPSms()
    {
        return null;
    }
}

if (!function_exists('wp_sms_send')) {
    function wp_sms_send($to = '', $msg = '', $isFlash = false, $from = '', $mediaUrls = [])
    {
        return false;
    }
}

if (!function_exists('wp_sms_initial_gateway')) {
    function wp_sms_initial_gateway()
    {
        return null;
    }
}

if (!function_exists('wp_sms_get_option')) {
    function wp_sms_get_option($key = '', $default = false)
    {
        return $default;
    }
}

if (!function_exists('wp_sms_render_mobile_field')) {
    function wp_sms_render_mobile_field($args = [])
    {
        return '';
    }
}

if (!function_exists('wp_sms_countries')) {
    function wp_sms_countries()
    {
        return [];
    }
}

if (!function_exists('wp_sms_shorturl')) {
    function wp_sms_shorturl($url = '')
    {
        return $url;
    }
}
