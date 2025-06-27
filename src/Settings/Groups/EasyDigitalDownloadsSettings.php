<?php

namespace WP_SMS\Settings\Groups;

use WP_SMS\Settings\Abstracts\AbstractSettingGroup;
use WP_SMS\Settings\Field;
use WP_SMS\Settings\Section;
use WP_SMS\Settings\LucideIcons;

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

    public function getIcon(): string
    {
        return LucideIcons::DOWNLOAD;
    }

    public function getSections(): array
    {
        if (!class_exists('Easy_Digital_Downloads')) {
            return [
                new Section([
                    'id' => 'edd_not_active',
                    'title' => __('Not active', 'wp-sms'),
                    'subtitle' => __('Easy Digital Downloads plugin should be installed to show the options.', 'wp-sms'),
                    'fields' => []
                ])
            ];
        }

        return [
            new Section([
                'id' => 'checkout_fields',
                'title' => __('Fields', 'wp-sms'),
                'subtitle' => __('Configure checkout form fields for Easy Digital Downloads', 'wp-sms'),
                'fields' => [
                    new Field([
                        'key' => 'edd_mobile_field',
                        'label' => __('Mobile field', 'wp-sms'),
                        'type' => 'checkbox',
                        'description' => __('Add mobile field to checkout page', 'wp-sms')
                    ]),
                ]
            ]),
            new Section([
                'id' => 'new_order_notification',
                'title' => __('Notify for new order', 'wp-sms'),
                'subtitle' => __('Configure SMS notifications for new EDD order completions', 'wp-sms'),
                'fields' => [
                    new Field([
                        'key' => 'edd_notify_order_enable',
                        'label' => __('Send SMS', 'wp-sms'),
                        'type' => 'checkbox',
                        'description' => __('Send SMS to number when a payment is marked as complete.', 'wp-sms')
                    ]),
                    new Field([
                        'key' => 'edd_notify_order_receiver',
                        'label' => __('SMS receiver', 'wp-sms'),
                        'type' => 'text',
                        'description' => __('Please enter mobile number for get sms. You can separate the numbers with the Latin comma.', 'wp-sms')
                    ]),
                    new Field([
                        'key' => 'edd_notify_order_message',
                        'label' => __('Message body', 'wp-sms'),
                        'type' => 'textarea',
                        'description' => __('Enter the contents of the SMS message.', 'wp-sms') . '<br>' .
                            sprintf(
                                // translators: %1$s: Email, %2$s: First name, %3$s: Last name
                                __('Email: %1$s, First name: %2$s, Last name: %3$s', 'wp-sms'),
                                '<code>%edd_email%</code>',
                                '<code>%edd_first%</code>',
                                '<code>%edd_last%</code>'
                            )
                    ]),
                ]
            ]),
            new Section([
                'id' => 'customer_order_notification',
                'title' => __('Notify to customer order', 'wp-sms'),
                'subtitle' => __('Configure SMS notifications sent to customers for their EDD orders', 'wp-sms'),
                'fields' => [
                    new Field([
                        'key' => 'edd_notify_customer_enable',
                        'label' => __('Send SMS', 'wp-sms'),
                        'type' => 'checkbox',
                        'description' => __('Send SMS to customer when a payment is marked as complete.', 'wp-sms')
                    ]),
                    new Field([
                        'key' => 'edd_notify_customer_message',
                        'label' => __('Message body', 'wp-sms'),
                        'type' => 'textarea',
                        'description' => __('Enter the contents of the SMS message.', 'wp-sms') . '<br>' .
                            sprintf(
                                // translators: %1$s: Email, %2$s: First name, %3$s: Last name
                                __('Email: %1$s, First name: %2$s, Last name: %3$s', 'wp-sms'),
                                '<code>%edd_email%</code>',
                                '<code>%edd_first%</code>',
                                '<code>%edd_last%</code>'
                            )
                    ]),
                ]
            ]),
        ];
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