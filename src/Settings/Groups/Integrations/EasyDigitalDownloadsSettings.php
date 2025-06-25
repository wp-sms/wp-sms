<?php

namespace WP_SMS\Settings\Groups\Integrations;

use WP_SMS\Settings\Abstracts\AbstractSettingGroup;
use WP_SMS\Settings\Field;

class EasyDigitalDownloadsSettings extends AbstractSettingGroup
{
    public function getName(): string
    {
        return 'edd';
    }

    public function getLabel(): string
    {
        return 'Easy Digital Downloads Integration Settings';
    }

    public function isAvailable(): bool
    {
        return class_exists('Easy_Digital_Downloads');
    }

    public function getFields(): array
    {
        if (! $this->isAvailable()) {
            return [
                new Field([
                    'key'         => 'edd_fields',
                    'type'        => 'notice',
                    'label'       => 'Not active',
                    'description' => 'Easy Digital Downloads plugin should be installed to show the options.',
                    'group_label' => 'EDD',
                ])
            ];
        }

        return [
            new Field([
                'key'         => 'edd_fields',
                'type'        => 'header',
                'label'       => 'Fields',
                'group_label' => 'EDD',
            ]),
            new Field([
                'key'         => 'edd_mobile_field',
                'type'        => 'checkbox',
                'label'       => 'Mobile field',
                'description' => 'Adds a mobile number field to the EDD checkout page',
                'group_label' => 'EDD',
            ]),

            new Field([
                'key'         => 'edd_notify_order',
                'type'        => 'header',
                'label'       => 'Notify for new order',
                'group_label' => 'EDD',
            ]),
            new Field([
                'key'         => 'edd_notify_order_enable',
                'type'        => 'checkbox',
                'label'       => 'Send SMS',
                'description' => 'Sends SMS when payment is marked complete',
                'group_label' => 'EDD',
            ]),
            new Field([
                'key'         => 'edd_notify_order_receiver',
                'type'        => 'text',
                'label'       => 'SMS receiver',
                'description' => 'Enter one or more mobile numbers (comma-separated)',
                'group_label' => 'EDD',
            ]),
            new Field([
                'key'         => 'edd_notify_order_message',
                'type'        => 'textarea',
                'label'       => 'Message body',
                'description' => 'Template for admin SMS. Placeholders: <code>%edd_email%</code>, <code>%edd_first%</code>, <code>%edd_last%</code>',
                'group_label' => 'EDD',
            ]),

            new Field([
                'key'         => 'edd_notify_customer',
                'type'        => 'header',
                'label'       => 'Notify to customer order',
                'group_label' => 'EDD',
            ]),
            new Field([
                'key'         => 'edd_notify_customer_enable',
                'type'        => 'checkbox',
                'label'       => 'Send SMS',
                'description' => 'Sends SMS to customer when payment is completed',
                'group_label' => 'EDD',
            ]),
            new Field([
                'key'         => 'edd_notify_customer_message',
                'type'        => 'textarea',
                'label'       => 'Message body',
                'description' => 'Template for customer SMS. Placeholders: <code>%edd_email%</code>, <code>%edd_first%</code>, <code>%edd_last%</code>',
                'group_label' => 'EDD',
            ]),
        ];
    }
}
