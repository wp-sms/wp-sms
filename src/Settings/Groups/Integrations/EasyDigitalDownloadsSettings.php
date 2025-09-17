<?php

namespace WP_SMS\Settings\Groups\Integrations;

use WP_SMS\Settings\Abstracts\AbstractSettingGroup;
use WP_SMS\Settings\Field;
use WP_SMS\Settings\Section;

class EasyDigitalDownloadsSettings extends AbstractSettingGroup
{
    public function getName(): string
    {
        return 'easy_digital_downloads';
    }

    public function getLabel(): string
    {
        return __('Easy Digital Downloads', 'wp-sms');
    }


    public function getSections(): array
    {
        $isPluginActive = class_exists('Easy_Digital_Downloads');
        $sections = [];

        // Always show plugin status notice first when plugin is inactive
        if (!$isPluginActive) {
            $sections[] = new Section([
                'id' => 'edd_integration',
                'title' => __('Easy Digital Downloads Integration', 'wp-sms'),
                'subtitle' => __('Connect EDD to enable SMS options.', 'wp-sms'),
                'fields' => [
                    new Field([
                        'key' => 'edd_not_active_notice',
                        'label' => __('Not active', 'wp-sms'),
                        'type' => 'notice',
                        'description' => __('Easy Digital Downloads is not installed or active. Install and activate EDD to configure SMS notifications.', 'wp-sms')
                    ])
                ]
            ]);
        }

        $sections[] = new Section([
            'id' => 'checkout_fields',
            'title' => __('Checkout Fields', 'wp-sms'),
            'subtitle' => __('Add a phone number field to the EDD checkout form.', 'wp-sms'),
            'fields' => [
                new Field([
                    'key' => 'edd_mobile_field',
                    'label' => __('Phone field at checkout', 'wp-sms'),
                    'type' => 'checkbox',
                    'description' => __('Add a phone number field to the checkout page so customers can receive order updates.', 'wp-sms'),
                    'readonly' => !$isPluginActive
                ])
            ]
        ]);
        $sections[] = new Section([
            'id' => 'order_notifications',
            'title' => __('Order Notifications', 'wp-sms'),
            'subtitle' => __('Send SMS when a payment is marked Complete.', 'wp-sms'),
            'fields' => [
                new Field([
                    'key' => 'edd_notify_order_enable',
                    'label' => __('Enable admin SMS', 'wp-sms'),
                    'type' => 'checkbox',
                    'description' => __('Send an SMS to your team when a payment is marked Complete.', 'wp-sms'),
                    'readonly' => !$isPluginActive
                ]),
                new Field([
                    'key' => 'edd_notify_order_receiver',
                    'label' => __('Recipient numbers', 'wp-sms'),
                    'type' => 'text',
                    'description' => __('Enter one or more phone numbers in international format. Separate numbers with commas. Example: +49 1512345678, +98 9120000000', 'wp-sms'),
                    'readonly' => !$isPluginActive
                ]),
                new Field([
                    'key' => 'edd_notify_order_message',
                    'label' => __('Message', 'wp-sms'),
                    'type' => 'textarea',
                    'description' => __('Write the SMS message. Available tags: %edd_email%, %edd_first%, %edd_last%.', 'wp-sms') . '<br>' .
                            sprintf(
                                // translators: %1$s: Email, %2$s: First name, %3$s: Last name
                                __('Example — New EDD order completed: %1$s %2$s (%3$s).', 'wp-sms'),
                                '<code>%edd_first%</code>',
                                '<code>%edd_last%</code>',
                                '<code>%edd_email%</code>'
                            ),
                    'readonly' => !$isPluginActive
                ]),
                new Field([
                    'key' => 'edd_notify_customer_enable',
                    'label' => __('Enable customer SMS', 'wp-sms'),
                    'type' => 'checkbox',
                    'description' => __('Send an SMS to the customer when a payment is marked Complete.', 'wp-sms'),
                    'readonly' => !$isPluginActive
                ]),
                new Field([
                    'key' => 'edd_notify_customer_message',
                    'label' => __('Message', 'wp-sms'),
                    'type' => 'textarea',
                    'description' => __('Write the SMS message. Available tags: %edd_email%, %edd_first%, %edd_last%.', 'wp-sms') . '<br>' .
                            sprintf(
                                // translators: %1$s: First name, %2$s: Email
                                __('Example — Thank you %1$s! Your EDD order is complete. A receipt has been emailed to %2$s.', 'wp-sms'),
                                '<code>%edd_first%</code>',
                                '<code>%edd_email%</code>'
                            ),
                    'readonly' => !$isPluginActive
                ]),
            ]
        ]);

        return $sections;
    }

    public function getFields(): array
    {
        // Legacy method - return all fields from all sections for backward compatibility
        $allFields = [];
        foreach ($this->getSections() as $section) {
            $allFields = array_merge($allFields, $section->getFields());
        }
        return $allFields;
    }
} 