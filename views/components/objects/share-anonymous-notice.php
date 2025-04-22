<?php

use WP_SMS\Admin\NoticeHandler\Notice;

if (!\WP_SMS\Utils\OptionUtil::get('share_anonymous_data') && !in_array('share_anonymous_data', get_option('wp_sms_dismissed_notices', []))) {
    $notice = [
        'title'   => __('Help Us Improve WP SMS!', 'wp-sms'),
        'content' => __('We’ve added a new Usage Tracking option to help us understand how WP SMS is used and identify areas for improvement. By enabling this feature, you’ll help us make the plugin better for everyone. No personal or sensitive data is collected.', 'wp-sms'),
        'links'   => [
            'learn_more'      => [
                'text' => __('Learn More', 'wp-sms'),
                'url'  => 'https://wp-sms.com/resources/sharing-your-data-with-us/?utm_source=wp-sms&utm_medium=link&utm_campaign=doc',
            ],
            'primary_button' => [
                'text'       => __('Enable Share Anonymous Data', 'wp-sms'),
                'url'        => '#',
                'attributes' => [
                    'data-option' => 'share_anonymous_data',
                    'data-value'  => 'true',
                ],
                'class'      => 'wps-option__updater notice--enable-usage',
            ]
        ]
    ];
    Notice::renderNotice($notice, 'share_anonymous_data', 'setting', true, 'action');
}
?>
