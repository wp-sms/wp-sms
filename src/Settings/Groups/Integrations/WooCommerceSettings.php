<?php

namespace WP_SMS\Settings\Groups\Integrations;

use WP_SMS\Notification\NotificationFactory;
use WP_SMS\Settings\Field;
use WP_SMS\Settings\Abstracts\AbstractSettingGroup;

class WooCommerceSettings extends AbstractSettingGroup
{
    public function getName(): string
    {
        return 'woocommerce';
    }

    public function getLabel(): string
    {
        return 'WooCommerce Integration Settings';
    }

    public function isAvailable(): bool
    {
        return class_exists('WooCommerce');
    }

    public function getFields(): array
    {
        if (!$this->isAvailable()) {
            return [
                new Field([
                    'key'         => 'wc_fields',
                    'type'        => 'notice',
                    'label'       => 'Not active',
                    'description' => 'WooCommerce plugin should be installed to show the options.',
                    'group_label' => 'WooCommerce',
                ])
            ];
        }

        return [
            new Field([
                'key'         => 'wc_meta_box',
                'type'        => 'header',
                'label'       => 'Order Meta Box',
                'group_label' => 'WooCommerce',
            ]),
            new Field([
                'key'         => 'wc_meta_box_enable',
                'type'        => 'checkbox',
                'label'       => 'Status',
                'description' => 'Enables the meta box for sending SMS in order details (requires mobile field)',
                'group_label' => 'WooCommerce',
            ]),

            new Field([
                'key'         => 'wc_notify_product',
                'type'        => 'header',
                'label'       => 'Notify for new product',
                'group_label' => 'WooCommerce',
            ]),
            new Field([
                'key'         => 'wc_notify_product_enable',
                'type'        => 'checkbox',
                'label'       => 'Send SMS',
                'description' => 'Enables SMS alerts for new products',
                'group_label' => 'WooCommerce',
            ]),
            new Field([
                'key'         => 'wc_notify_product_receiver',
                'type'        => 'select',
                'label'       => 'SMS receiver',
                'description' => 'Choose to send to subscribers or user roles',
                'options'     => [
                    'subscriber' => 'Subscribers',
                    'users'      => 'Users'
                ],
                'group_label' => 'WooCommerce',
            ]),
            new Field([
                'key'         => 'wc_notify_product_cat',
                'type'        => 'select',
                'label'       => 'Subscribe group',
                'description' => 'Choose group if sending to subscribers',
                'show_if'     => ['wc_notify_product_receiver' => 'subscriber'],
                'group_label' => 'WooCommerce',
            ]),
            new Field([
                'key'         => 'wc_notify_product_roles',
                'type'        => 'multiselect',
                'label'       => 'Specific roles',
                'description' => 'Choose WordPress roles if sending to users',
                'show_if'     => ['wc_notify_product_receiver' => 'users'],
                'group_label' => 'WooCommerce',
            ]),
            new Field([
                'key'         => 'wc_notify_product_message',
                'type'        => 'textarea',
                'label'       => 'Message body',
                'description' => 'Message body for product alerts. Supports WooCommerce product variables.<br>' . NotificationFactory::getWooCommerceProduct()->printVariables(),
                'group_label' => 'WooCommerce',
            ]),

            new Field([
                'key'         => 'wc_notify_order',
                'type'        => 'header',
                'label'       => 'Notify for new order',
                'group_label' => 'WooCommerce',
            ]),
            new Field([
                'key'         => 'wc_notify_order_enable',
                'type'        => 'checkbox',
                'label'       => 'Send SMS',
                'description' => 'Enables SMS on new order',
                'group_label' => 'WooCommerce',
            ]),
            new Field([
                'key'         => 'wc_notify_order_receiver',
                'type'        => 'text',
                'label'       => 'SMS receiver',
                'description' => 'Enter mobile number(s), comma-separated',
                'group_label' => 'WooCommerce',
            ]),
            new Field([
                'key'         => 'wc_notify_order_message',
                'type'        => 'textarea',
                'label'       => 'Message body',
                'description' => 'Message body for new order alert.<br>' . NotificationFactory::getWooCommerceOrder()->printVariables(),
                'group_label' => 'WooCommerce',
            ]),

            // ... Remaining fields skipped for brevity — continue as per structure above ...
        ];
    }
}
