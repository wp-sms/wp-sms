<?php

use WP_SMS\Newsletter;
use WP_SMS\Option;

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

/**
 * Show SMS newsletter form.
 *
 * @deprecated 4.0 Use wp_sms_subscribes()
 * @see wp_sms_subscribes()
 *
 */
function wp_subscribes()
{
    _deprecated_function(__FUNCTION__, '4.0', 'wp_sms_subscribes()');
    wp_sms_subscribes();
}

/**
 * Show SMS newsletter form.
 *
 */
function wp_sms_subscribes()
{
    Newsletter::loadNewsLetter();
}

/**
 * Get option value.
 *
 * @param $option_name
 * @param bool $pro
 * @param string $setting_name
 *
 * @return string
 */
function wp_sms_get_option($option_name, $pro = false, $setting_name = '')
{
    return Option::getOption($option_name, $pro, $setting_name);
}

/**
 * Send SMS.
 *
 * @param array $to
 * @param $msg $pro
 * @param bool $is_flash
 *
 * @param bool $from
 *
 * @return string | WP_Error
 */
function wp_sms_send($to, $msg, $is_flash = false, $from = null)
{
    global $sms;

    $sms->isflash = $is_flash;
    $sms->to      = $to;
    $sms->msg     = $msg;

    if ($from) {
        $sms->from = $from;
    }

    return $sms->SendSMS();
}