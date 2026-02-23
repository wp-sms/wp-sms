<?php

if (!defined('ABSPATH')) exit; // Exit if accessed directly

use WP_SMS\Admin\NoticeHandler\Notice;
use WP_SMS\Utils\OptionUtil;

$installationTime = get_option('wp_sms_installation_time');

if (
    current_user_can('manage_options') &&
    !OptionUtil::get('share_anonymous_data') &&
    !in_array('share_anonymous_data', get_option('wp_sms_dismissed_notices', [])) &&
    (time() > $installationTime + 7 * DAY_IN_SECONDS)
) {
    $notice = [
        'title'   => __('Help Us Improve WSMS!', 'wp-sms'),
        'content' => __('Help us improve by sharing anonymous usage data. No personal or sensitive information is collected.', 'wp-sms'),
        'links'   => [
            'learn_more'     => [
                'text' => __('Learn More', 'wp-sms'),
                'url'  => 'https://wsms.io/docs/share-anonymous-data/?utm_source=wp-sms&utm_medium=link&utm_campaign=doc',
            ],
            'primary_button' => [
                'text'       => __('Enable Share Anonymous Data', 'wp-sms'),
                'url'        => '#',
                'attributes' => [
                    'data-option' => 'share_anonymous_data',
                    'data-value'  => '1',
                ],
                'class'      => 'wpsms-option__updater notice--enable-usage',
            ]
        ]
    ];
    Notice::renderNotice($notice, 'share_anonymous_data', 'setting', true, 'action');
}
?>
