<?php

use WP_SMS\BackgroundProcess\SmsDispatcher;
use WP_SMS\Gateway;
use WP_SMS\Option;
use WP_SMS\Components\Countries;

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

/**
 * @return mixed
 */
function wp_sms_initial_gateway()
{
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
                $value = wp_sms_sanitize_array($value);
            } else {
                $value = htmlspecialchars($value);
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
 * Check the license with server
 *
 * @param $addOnKey
 * @param $licenseKey
 * @return bool|void
 */
function wp_sms_check_remote_license($addOnKey, $licenseKey)
{
    $buildUrl = add_query_arg(array(
        'plugin-name' => $addOnKey,
        'license_key' => $licenseKey,
        'website'     => get_bloginfo('url')
    ), WP_SMS_SITE . '/wp-json/plugins/v1/validate');

    $response = wp_remote_get($buildUrl, [
        'timeout' => 25
    ]);

    if (is_wp_error($response)) {
        return;
    }

    $response = json_decode($response['body']);

    if (isset($response->status) and $response->status == 200) {
        // To clear the download transient and sync with download status
        delete_transient($addOnKey . '_download_info');

        return true;
    }
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

/**
 * Returns countries component.
 *
 * @return  Countries
 */
function wp_sms_countries()
{
    return Countries::getInstance();
}

/**
 * Show SMS newsletter form
 *
 * @deprecated 4.0 Use wp_sms_subscriber_form()
 * @see wp_sms_subscriber_form()
 *
 */
function wp_subscribes()
{
    _deprecated_function(__FUNCTION__, '4.0', 'wp_sms_subscriber_form');
    wp_sms_subscriber_form();
}

/**
 * Show SMS newsletter form
 *
 * @deprecated 4.0 Use wp_sms_subscriber_form()
 * @see wp_sms_subscriber_form()
 *
 */
function wp_sms_subscribes()
{
    _deprecated_function(__FUNCTION__, '5.7', 'wp_sms_subscriber_form');
    wp_sms_subscriber_form();
}

/**
 * Show SMS newsletter form
 *
 * @param array $attributes
 *
 * @return false|string|null
 */
function wp_sms_subscriber_form($attributes = array())
{
    return \WP_SMS\Helper::loadTemplate('subscribe-form.php', [
            'attributes'                           => $attributes,
            'international_mobile'                 => wp_sms_get_option('international_mobile'),
            'gdpr_compliance'                      => wp_sms_get_option('gdpr_compliance'),
            'subscribe_form_gdpr_confirm_checkbox' => wp_sms_get_option('newsletter_form_gdpr_confirm_checkbox'),
            'subscribe_form_gdpr_text'             => wp_sms_get_option('newsletter_form_gdpr_text'),
            'get_group_result'                     => isset($attributes['groups']) ? $attributes['groups'] : \WP_SMS\Newsletter::getGroups(wp_sms_get_option('newsletter_form_specified_groups')),
        ]
    );
}

function wp_sms_send_sms_form($attributes = array())
{
    $block_visibility = apply_filters('wp_sms_send_sms_block_visibility', __return_false());
    $current_user     = wp_get_current_user();

    if (!$attributes['onlyLoggedUsers'] || ($attributes['onlyLoggedUsers'] && $current_user->ID !== 0 && ($attributes['userRole'] == 'all' || in_array($attributes['userRole'], $current_user->roles)))) {
        return \WP_SMS\Helper::loadTemplate('send-sms-form.php', [
            'attributes' => $attributes,
            'visibility' => $block_visibility
        ]);
    }
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
 * Send an SMS message.
 *
 * @param string $to The recipient phone number.
 * @param string $msg The message content.
 * @param bool $is_flash (optional) Whether the message should be sent as a flash message. Defaults to false.
 * @param string|null $from (optional) The sender phone number. Defaults to null.
 * @param array $mediaUrls (optional) An array of media URLs to be sent along with the message. Defaults to an empty array.
 *
 * @return bool Whether the SMS message was successfully sent.
 */
function wp_sms_send($to, $msg, $is_flash = false, $from = null, $mediaUrls = [])
{
    $smsDispatcher = new SmsDispatcher($to, $msg, $is_flash, $from, $mediaUrls);
    return $smsDispatcher->dispatch();
}

/**
 * Short URL generator
 *
 * @param string $longUrl
 * @return string
 */
if (!function_exists('wp_sms_shorturl')) {
    function wp_sms_shorturl($longUrl = '')
    {
        return apply_filters('wp_sms_shorturl', $longUrl);
    }
}

/**
 * @return void
 */
function wp_sms_render_mobile_field($args)
{
    $placeHolder = wp_sms_get_option('mobile_terms_field_place_holder');
    $defaults    = array(
        'type'        => 'tel',
        'placeholder' => $placeHolder ? $placeHolder : esc_html__('Phone Number', 'wp-sms'),
        'min'         => '',
        'max'         => '',
        'required'    => false,
        'id'          => 'wpsms-mobile',
        'value'       => '',
        'name'        => '',
        'class'       => array(),
        'attributes'  => array(),
    );

    $args = wp_parse_args($args, $defaults);

    if (wp_sms_get_option('international_mobile')) {
        $args['class'] = array_merge(['wp-sms-input-mobile'], $args['class']);
    } else {
        $args['min'] = wp_sms_get_option('mobile_terms_minimum');
        $args['max'] = wp_sms_get_option('mobile_terms_maximum');
    }

    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    echo sprintf(
        '<input id="%s" type="%s" name="%s" placeholder="%s" class="%s" value="%s" required="%s" minlength="%s" maxlength="%s" %s/>',
        esc_attr($args['id']),
        esc_attr($args['type']),
        esc_attr($args['name']),
        esc_attr($args['placeholder']),
        esc_attr(implode(' ', $args['class'])),
        esc_attr($args['value']),
        esc_attr($args['required']),
        esc_attr($args['min']),
        esc_attr($args['max']),
        esc_attr(implode(' ', $args['attributes']))
    );
}

/**
 * @param $number
 * @param $group_id
 * @return string
 */
function wp_sms_render_quick_reply($number, $group_id = false)
{
    add_thickbox();
    wp_enqueue_script('wpsms-quick-reply');

    $numbers          = explode(',', $number);
    $result           = '';
    $quick_reply_icon = plugins_url('wp-sms/assets/images/quick-reply-icon.svg');

    if (count($numbers) > 1) {
        foreach ($numbers as $item) {
            $result .= sprintf('<a href="#TB_inline?&width=500&height=500&inlineId=wpsms-quick-reply" class="number thickbox js-replyModalToggle" name="Quick Reply" style="display: block" data-number="%1$s"><img class="quick-reply-icon" src="%2$s" alt="quick-reply-icon"> %1$s</a>', esc_html($item), $quick_reply_icon);
        }
    } else {
        $result = sprintf('<a href="#TB_inline?&width=500&height=500&inlineId=wpsms-quick-reply" class="number thickbox js-replyModalToggle" name="Quick Reply" style="display: block" data-number="%1$s" data-group-id="%2$s"><img class="quick-reply-icon" src="%3$s" alt=""> %1$s</a>', esc_html($number), $group_id, $quick_reply_icon);
    }

    return $result;
}

if (!function_exists('array_key_last')) {
    function array_key_last(array $array)
    {
        return key(array_slice($array, -1, 1, true));
    }
}