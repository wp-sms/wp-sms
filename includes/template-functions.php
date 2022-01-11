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
 * @param array $mediaUrls
 *
 * @param bool $from
 *
 * @return string | WP_Error
 */
function wp_sms_send($to, $msg, $is_flash = false, $from = null, $mediaUrls = [])
{
    global $sms;

    $sms->isflash = $is_flash;
    $sms->to      = $to;
    $sms->msg     = $msg;
    $sms->media   = $mediaUrls;

    if ($from) {
        $sms->from = $from;
    }

    return $sms->SendSMS();
}

/**
 * Short URL generator
 *
 * @param string $longUrl
 * @return string
 */
function wp_sms_shorturl($longUrl = '')
{
    $short_url_status    = wp_sms_get_option('short_url_status');
    $short_url_api_token = wp_sms_get_option('short_url_api_token');

    if ($short_url_status == '1' && !empty($short_url_api_token)) {
        $response = wp_remote_post('https://api-ssl.bitly.com/v4/shorten', [
            'headers' => [
                'Authorization' => 'Bearer ' . $short_url_api_token,
                'Content-Type'  => 'application/json',
            ],
            'body'    => json_encode([
                'long_url'   => $longUrl,
                'domain'     => 'bit.ly',
            ])
        ]);

        $response_code = wp_remote_retrieve_response_code($response);
        if (in_array($response_code, ['200', '201'])) {
            $result = json_decode($response['body'], true);
            if (!empty($result['link'])) {
                return $result['link'];
            }
        }
    }

    return $longUrl;
}