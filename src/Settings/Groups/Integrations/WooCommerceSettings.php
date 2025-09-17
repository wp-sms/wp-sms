<?php

namespace WP_SMS\Settings\Groups\Integrations;

use WP_SMS\Settings\Abstracts\AbstractSettingGroup;
use WP_SMS\Settings\Field;
use WP_SMS\Settings\Section;
use WP_SMS\Notification\NotificationFactory;

class WooCommerceSettings extends AbstractSettingGroup
{
    public function getName(): string
    {
        return 'woocommerce';
    }

    public function getLabel(): string
    {
        return __('WooCommerce', 'wp-sms');
    }

    public function getSections(): array
    {
        $isPluginActive = class_exists('WooCommerce');
        $sections = [];

        // Always show plugin status notice first when plugin is inactive
        if (!$isPluginActive) {
            $sections[] = new Section([
                'id' => 'woocommerce_integration',
                'title' => __('WooCommerce Integration', 'wp-sms'),
                'subtitle' => __('Configure SMS notifications for WooCommerce activities', 'wp-sms'),
                'fields' => [
                    new Field([
                        'key' => 'woocommerce_not_active_notice',
                        'label' => __('Not active', 'wp-sms'),
                        'type' => 'notice',
                        'description' => __('Install and activate the WooCommerce plugin to access these options.', 'wp-sms')
                    ])
                ]
            ]);
        }

        $sections[] = new Section([
            'id' => 'order_meta_box',
            'title' => __('Order SMS Box', 'wp-sms'),
            'subtitle' => __('Show a "Send SMS" box on the WooCommerce order screen', 'wp-sms'),
            'fields' => [
                new Field([
                    'key' => 'wc_meta_box_enable',
                    'label' => __('Enable', 'wp-sms'),
                    'type' => 'checkbox',
                    'description' => __('Show a Send SMS box on order pages to message the customer or any number. The customer mobile field must be set. If it is not set, this box will be hidden.', 'wp-sms'),
                    'readonly' => !$isPluginActive
                ]),
            ]
        ]);
        $sections[] = new Section([
            'id' => 'new_product_notification',
            'title' => __('New Product Published', 'wp-sms'),
            'subtitle' => __('Send an SMS when a product is published', 'wp-sms'),
            'help_url' => WP_SMS_SITE . '/resources/woocommerce-sms-variables-and-order-meta/',
            'fields' => [
                new Field([
                    'key' => 'wc_notify_product_enable',
                    'label' => __('Send SMS', 'wp-sms'),
                    'type' => 'checkbox',
                    'description' => __('Send an SMS when a new product is published.', 'wp-sms'),
                    'readonly' => !$isPluginActive
                ]),
                new Field([
                    'key' => 'wc_notify_product_receiver',
                    'label' => __('Recipients', 'wp-sms'),
                    'type' => 'select',
                    'options' => [
                        'subscriber' => __('Subscribers', 'wp-sms'),
                        'users' => __('WordPress users', 'wp-sms')
                    ],
                    'description' => __('Choose who should receive this SMS.', 'wp-sms'),
                    'readonly' => !$isPluginActive
                ]),
                new Field([
                    'key' => 'wc_notify_product_cat',
                    'label' => __('Subscriber group', 'wp-sms'),
                    'type' => 'select',
                    'options' => $this->getSubscribeGroups(),
                    'description' => __('If you selected Subscribers, choose the group to send to.', 'wp-sms'),
                    'show_if' => ['wc_notify_product_receiver' => 'subscriber'],
                    'readonly' => !$isPluginActive
                ]),
                new Field([
                    'key' => 'wc_notify_product_roles',
                    'label' => __('User roles', 'wp-sms'),
                    'type' => 'multiselect',
                    'options' => $this->getRoles(),
                    'description' => __('Select which user roles will receive the SMS.', 'wp-sms'),
                    'show_if' => ['wc_notify_product_receiver' => 'users'],
                    'readonly' => !$isPluginActive
                ]),
                new Field([
                    'key' => 'wc_notify_product_message',
                    'label' => __('Message', 'wp-sms'),
                    'type' => 'textarea',
                    'description' => __('Write your SMS. Variables are available below.', 'wp-sms') . '<br>' . NotificationFactory::getWooCommerceProduct()->printVariables(),
                    'readonly' => !$isPluginActive
                ]),
            ]
        ]);
        $sections[] = new Section([
            'id' => 'new_order_notification',
            'title' => __('New Order Alert (Admin)', 'wp-sms'),
            'subtitle' => __('Send an SMS to your team when a new order is placed', 'wp-sms'),
            'help_url' => WP_SMS_SITE . '/resources/woocommerce-sms-variables-and-order-meta/',
            'fields' => [
                new Field([
                    'key' => 'wc_notify_order_enable',
                    'label' => __('Send SMS', 'wp-sms'),
                    'type' => 'checkbox',
                    'description' => __('Send an SMS when a new order is submitted.', 'wp-sms'),
                    'readonly' => !$isPluginActive
                ]),
                new Field([
                    'key' => 'wc_notify_order_receiver',
                    'label' => __('Phone numbers', 'wp-sms'),
                    'type' => 'text',
                    'description' => __('Enter one or more numbers. Separate numbers with commas. Use international format when possible.', 'wp-sms'),
                    'readonly' => !$isPluginActive
                ]),
                new Field([
                    'key' => 'wc_notify_order_message',
                    'label' => __('Message', 'wp-sms'),
                    'type' => 'textarea',
                    'description' => __('Write your SMS. Variables are available below.', 'wp-sms') . '<br>' . NotificationFactory::getWooCommerceOrder()->printVariables(),
                    'readonly' => !$isPluginActive
                ]),
            ]
        ]);
        $sections[] = new Section([
            'id' => 'customer_order_notification',
            'title' => __('Order Placed (Customer)', 'wp-sms'),
            'subtitle' => __('Send a confirmation SMS to customers after checkout', 'wp-sms'),
            'help_url' => WP_SMS_SITE . '/resources/woocommerce-sms-variables-and-order-meta/',
            'fields' => [
                new Field([
                    'key' => 'wc_notify_customer_enable',
                    'label' => __('Send SMS', 'wp-sms'),
                    'type' => 'checkbox',
                    'description' => __('Send an SMS to the customer when an order is placed.', 'wp-sms'),
                    'readonly' => !$isPluginActive
                ]),
                new Field([
                    'key' => 'wc_notify_customer_message',
                    'label' => __('Message', 'wp-sms'),
                    'type' => 'textarea',
                    'description' => __('Write your SMS. Variables are available below.', 'wp-sms') . '<br>' . NotificationFactory::getWooCommerceOrder()->printVariables(),
                    'readonly' => !$isPluginActive
                ]),
            ]
        ]);
        $sections[] = new Section([
            'id' => 'stock_notification',
            'title' => __('Low Stock Alert (Admin)', 'wp-sms'),
            'subtitle' => __('Notify your team when stock is low', 'wp-sms'),
            'help_url' => WP_SMS_SITE . '/resources/woocommerce-sms-variables-and-order-meta/',
            'fields' => [
                new Field([
                    'key' => 'wc_notify_stock_enable',
                    'label' => __('Send SMS', 'wp-sms'),
                    'type' => 'checkbox',
                    'description' => __('Send an SMS when stock reaches the low stock threshold.', 'wp-sms'),
                    'readonly' => !$isPluginActive
                ]),
                new Field([
                    'key' => 'wc_notify_stock_receiver',
                    'label' => __('Phone numbers', 'wp-sms'),
                    'type' => 'text',
                    'description' => __('Enter one or more numbers. Separate with commas.', 'wp-sms'),
                    'readonly' => !$isPluginActive
                ]),
                new Field([
                    'key' => 'wc_notify_stock_message',
                    'label' => __('Message', 'wp-sms'),
                    'type' => 'textarea',
                    'description' => __('Write your SMS. Variables are available below.', 'wp-sms') . '<br>' . NotificationFactory::getWooCommerceProduct()->printVariables(),
                    'readonly' => !$isPluginActive
                ]),
            ]
        ]);
        $sections[] = new Section([
            'id' => 'checkout_confirmation_checkbox',
            'title' => __('Checkout Opt-in Checkbox', 'wp-sms'),
            'subtitle' => __('Show a consent checkbox on checkout', 'wp-sms'),
            'fields' => [
                new Field([
                    'key' => 'wc_checkout_confirmation_checkbox_enabled',
                    'label' => __('Enable', 'wp-sms'),
                    'type' => 'checkbox',
                    'description' => __('Show a checkbox at checkout so customers can confirm they want to receive SMS updates.', 'wp-sms'),
                    'readonly' => !$isPluginActive
                ]),
            ]
        ]);
        $sections[] = new Section([
            'id' => 'order_status_notification',
            'title' => __('Order Status Updates', 'wp-sms'),
            'subtitle' => __('Send SMS updates when the order status changes', 'wp-sms'),
            'help_url' => WP_SMS_SITE . '/resources/woocommerce-sms-variables-and-order-meta/',
            'fields' => [
                new Field([
                    'key' => 'wc_notify_status_enable',
                    'label' => __('Send SMS', 'wp-sms'),
                    'type' => 'checkbox',
                    'description' => __('Send an SMS to the customer when the order status changes.', 'wp-sms'),
                    'readonly' => !$isPluginActive
                ]),
                new Field([
                    'key' => 'wc_notify_status_message',
                    'label' => __('Fallback message', 'wp-sms'),
                    'type' => 'textarea',
                    'description' => __('Used if no per-status message is defined. Variables are available below.', 'wp-sms') . '<br>' . NotificationFactory::getWooCommerceOrder()->printVariables(),
                    'readonly' => !$isPluginActive
                ]),
                new Field([
                    'key' => 'wc_notify_by_status_content',
                    'label' => __('Order status messages', 'wp-sms'),
                    'type' => 'repeater',
                    'description' => __('Add messages for specific order statuses. These override the fallback message.', 'wp-sms'),
                    'options' => [
                        'template' => 'admin/fields/field-wc-status-repeater.php',
                        'order_statuses' => function_exists('wc_get_order_statuses') ? wc_get_order_statuses() : [
                            'pending' => __('Pending payment', 'wp-sms'),
                            'processing' => __('Processing', 'wp-sms'),
                            'on-hold' => __('On hold', 'wp-sms'),
                            'completed' => __('Completed', 'wp-sms'),
                            'cancelled' => __('Cancelled', 'wp-sms'),
                            'refunded' => __('Refunded', 'wp-sms'),
                            'failed' => __('Failed', 'wp-sms')
                        ],
                        'variables' => NotificationFactory::getWooCommerceOrder()->printVariables()
                    ],
                    'repeatable' => true,
                    'readonly' => !$isPluginActive,
                    'field_groups' => [
                        new \WP_SMS\Settings\FieldGroup([
                            'key' => 'order_status_message_group',
                            'label' => __('Order Status Message Group', 'wp-sms'),
                            'fields' => [
                                new Field([
                                    'key' => 'order_status',
                                    'label' => __('Order Status', 'wp-sms'),
                                    'type' => 'select',
                                    'options' => function_exists('wc_get_order_statuses') ? wc_get_order_statuses() : [
                                        'pending' => __('Pending payment', 'wp-sms'),
                                        'processing' => __('Processing', 'wp-sms'),
                                        'on-hold' => __('On hold', 'wp-sms'),
                                        'completed' => __('Completed', 'wp-sms'),
                                        'cancelled' => __('Cancelled', 'wp-sms'),
                                        'refunded' => __('Refunded', 'wp-sms'),
                                        'failed' => __('Failed', 'wp-sms')
                                    ],
                                    'description' => __('Choose an order status.', 'wp-sms'),
                                ]),
                                new Field([
                                    'key' => 'notify_status',
                                    'label' => __('Notify', 'wp-sms'),
                                    'type' => 'select',
                                    'options' => [
                                        '1' => __('Enable', 'wp-sms'),
                                        '2' => __('Disable', 'wp-sms'),
                                    ],
                                    'description' => __('Enable or disable this status notification.', 'wp-sms'),
                                ]),
                                new Field([
                                    'key' => 'message',
                                    'label' => __('Message', 'wp-sms'),
                                    'type' => 'textarea',
                                    'description' => __('Write your SMS. Variables are available below.', 'wp-sms') . '<br>' . NotificationFactory::getWooCommerceOrder()->printVariables(),
                                    'rows' => 3,
                                ]),
                            ]
                        ])
                    ]
                ]),
            ]
        ]);

        return $sections;
    }

    private function getSubscribeGroups(): array
    {
        global $wpdb;
        
        $groups = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}sms_subscribes_group");
        $options = [];
        
        if ($groups) {
            foreach ($groups as $group) {
                $options[$group->ID] = $group->name;
            }
        }
        
        return $options;
    }

    public function getRoles(): array
    {
        $roles = wp_roles()->get_names();
        return $roles;
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