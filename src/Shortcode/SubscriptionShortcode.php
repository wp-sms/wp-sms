<?php

namespace WP_SMS\Shortcode;

class SubscriptionShortcode extends ShortcodeAbstract
{
    public function __construct()
    {
        add_shortcode('wp_sms_subscription_form', array($this, 'run'));
    }

    public function run()
    {
        return wp_sms_subscription_form($args = array(
            'title'       => 'Test2',
            'description' => 'Test3',
            'fields'      => [
                'age' => [
                    'label'       => 'Age',
                    'type'        => 'number',
                    'description' => 'Your age',
                ],
                'zip_code' => [
                    'label'       => 'Zip Code',
                    'type'        => 'text',
                    'description' => 'Your ZIP code',
                ]
            ]
        ));
    }
}