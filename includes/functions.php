<?php

use WP_SMS\Gateway;
use WP_SMS\Option;

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

/**
 * @return mixed
 */
function wp_sms_initial_gateway()
{
    require_once WP_SMS_DIR . 'includes/class-wpsms-option.php';

    return Gateway::initial();
}

/**
 * @param $array_or_string
 * @return mixed|string
 */
function wp_sms_sanitize_array($array_or_string)
{
    if (is_string($array_or_string)) {
        $array_or_string = sanitize_text_field($array_or_string);
    } elseif (is_array($array_or_string)) {
        foreach ($array_or_string as $key => &$value) {
            if (is_array($value)) {
                $value = sanitize_text_or_array_field($value);
            } else {
                $value = sanitize_text_field($value);
            }
        }
    }

    return $array_or_string;
}

/**
 * Get Add-Ons
 *
 * @return array
 */
function wp_sms_get_addons()
{
    return apply_filters('wp_sms_addons', array());
}

/**
 * Generate constant license by plugin slug.
 *
 * @param $plugin_slug
 * @return mixed
 * @example wp-sms-pro > WP_SMS_PRO_LICENSE
 */
function wp_sms_generate_constant_license($plugin_slug)
{
    $generateConstant = strtoupper(str_replace('-', '_', $plugin_slug)) . '_LICENSE';

    if (defined($generateConstant)) {
        return constant($generateConstant);
    }
}

/**
 * Get stored license key
 *
 * @param $addOnKey
 * @return mixed|string
 */
function wp_sms_get_license_key($addOnKey)
{
    $constantLicenseKey = wp_sms_generate_constant_license($addOnKey);

    return $constantLicenseKey ? $constantLicenseKey : Option::getOption("license_{$addOnKey}_key");
}

/**
 * @param $media
 * @return string|void
 */
function wp_sms_render_media_list($media)
{
    $allMedia = unserialize($media);

    if (!is_array($allMedia)) {
        return;
    }

    $htmlMedia = [];
    foreach ($allMedia as $media) {
        $htmlMedia[] = "<img width='80' src='{$media}'/>";
    }

    return implode(' ', $htmlMedia);
}