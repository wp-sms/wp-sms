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
        if (!class_exists('WooCommerce')) {
            return [
                new Section([
                    'id' => 'woocommerce_not_active',
                    'title' => __('WooCommerce Integration', 'wp-sms'),
                    'subtitle' => __('Configure SMS notifications for WooCommerce activities', 'wp-sms'),
                    'fields' => [
                        new Field([
                            'key' => 'woocommerce_not_active_notice',
                            'label' => __('Not active', 'wp-sms'),
                            'type' => 'notice',
                            'description' => __('WooCommerce plugin should be installed to show the options.', 'wp-sms')
                        ])
                    ]
                ])
            ];
        }

        return [
            new Section([
                'id' => 'order_meta_box',
                'title' => __('Order Meta Box', 'wp-sms'),
                'subtitle' => __('Configure SMS meta box on WooCommerce orders', 'wp-sms'),
                'fields' => [
                    new Field([
                        'key' => 'wc_meta_box_enable',
                        'label' => __('Status', 'wp-sms'),
                        'type' => 'checkbox',
                        'description' => __('Enable send SMS meta box on Orders.<br>Note: You must choose the mobile field first if disable Meta Box will not appear too.', 'wp-sms')
                    ]),
                ]
            ]),
            new Section([
                'id' => 'new_product_notification',
                'title' => __('Notify for new product', 'wp-sms'),
                'subtitle' => __('Configure SMS notifications for new product publications', 'wp-sms'),
                'help_url' => WP_SMS_SITE . '/resources/woocommerce-sms-variables-and-order-meta/',
                'fields' => [
                    new Field([
                        'key' => 'wc_notify_product_enable',
                        'label' => __('Send SMS', 'wp-sms'),
                        'type' => 'checkbox',
                        'description' => __('Send SMS when publish new a product', 'wp-sms')
                    ]),
                    new Field([
                        'key' => 'wc_notify_product_receiver',
                        'label' => __('SMS receiver', 'wp-sms'),
                        'type' => 'select',
                        'options' => [
                            'subscriber' => __('Subscriber', 'wp-sms'),
                            'users' => __('Users', 'wp-sms')
                        ],
                        'description' => __('Please select the receiver of SMS', 'wp-sms')
                    ]),
                    new Field([
                        'key' => 'wc_notify_product_cat',
                        'label' => __('Subscribe group', 'wp-sms'),
                        'type' => 'select',
                        'options' => $this->getSubscribeGroups(),
                        'description' => __('If you select the Subscribe users, can select the group for send sms', 'wp-sms'),
                        'show_if' => ['wc_notify_product_receiver' => 'subscriber']
                    ]),
                    new Field([
                        'key' => 'wc_notify_product_roles',
                        'label' => __('Specific roles', 'wp-sms'),
                        'type' => 'multiselect',
                        'options' => $this->getRoles(),
                        'description' => __('Select the role of the user you want to receive the SMS.', 'wp-sms'),
                        'show_if' => ['wc_notify_product_receiver' => 'users']
                    ]),
                    new Field([
                        'key' => 'wc_notify_product_message',
                        'label' => __('Message body', 'wp-sms'),
                        'type' => 'textarea',
                        'description' => __('Enter the contents of the SMS message.', 'wp-sms') . '<br>' . NotificationFactory::getWooCommerceProduct()->printVariables()
                    ]),
                ]
            ]),
            new Section([
                'id' => 'new_order_notification',
                'title' => __('Notify for new order', 'wp-sms'),
                'subtitle' => __('Configure SMS notifications for new order submissions', 'wp-sms'),
                'help_url' => WP_SMS_SITE . '/resources/woocommerce-sms-variables-and-order-meta/',
                'fields' => [
                    new Field([
                        'key' => 'wc_notify_order_enable',
                        'label' => __('Send SMS', 'wp-sms'),
                        'type' => 'checkbox',
                        'description' => __('Send SMS when submit new order', 'wp-sms')
                    ]),
                    new Field([
                        'key' => 'wc_notify_order_receiver',
                        'label' => __('SMS receiver', 'wp-sms'),
                        'type' => 'text',
                        'description' => __('Please enter mobile number for get sms. You can separate the numbers with the Latin comma.', 'wp-sms')
                    ]),
                    new Field([
                        'key' => 'wc_notify_order_message',
                        'label' => __('Message body', 'wp-sms'),
                        'type' => 'textarea',
                        'description' => __('Enter the contents of the SMS message.', 'wp-sms') . '<br>' . NotificationFactory::getWooCommerceOrder()->printVariables()
                    ]),
                ]
            ]),
            new Section([
                'id' => 'customer_order_notification',
                'title' => __('Notify to customer order', 'wp-sms'),
                'subtitle' => __('Configure SMS notifications sent to customers for their orders', 'wp-sms'),
                'help_url' => WP_SMS_SITE . '/resources/woocommerce-sms-variables-and-order-meta/',
                'fields' => [
                    new Field([
                        'key' => 'wc_notify_customer_enable',
                        'label' => __('Send SMS', 'wp-sms'),
                        'type' => 'checkbox',
                        'description' => __('Send SMS to customer when submit the order', 'wp-sms')
                    ]),
                    new Field([
                        'key' => 'wc_notify_customer_message',
                        'label' => __('Message body', 'wp-sms'),
                        'type' => 'textarea',
                        'description' => __('Enter the contents of the SMS message.', 'wp-sms') . '<br>' . NotificationFactory::getWooCommerceOrder()->printVariables()
                    ]),
                ]
            ]),
            new Section([
                'id' => 'stock_notification',
                'title' => __('Notify of stock', 'wp-sms'),
                'subtitle' => __('Configure SMS notifications for low stock alerts', 'wp-sms'),
                'help_url' => WP_SMS_SITE . '/resources/woocommerce-sms-variables-and-order-meta/',
                'fields' => [
                    new Field([
                        'key' => 'wc_notify_stock_enable',
                        'label' => __('Send SMS', 'wp-sms'),
                        'type' => 'checkbox',
                        'description' => __('Send SMS when stock is low', 'wp-sms')
                    ]),
                    new Field([
                        'key' => 'wc_notify_stock_receiver',
                        'label' => __('SMS receiver', 'wp-sms'),
                        'type' => 'text',
                        'description' => __('Please enter mobile number for get sms. You can separate the numbers with the Latin comma.', 'wp-sms')
                    ]),
                    new Field([
                        'key' => 'wc_notify_stock_message',
                        'label' => __('Message body', 'wp-sms'),
                        'type' => 'textarea',
                        'description' => __('Enter the contents of the SMS message.', 'wp-sms') . '<br>' . NotificationFactory::getWooCommerceProduct()->printVariables()
                    ]),
                ]
            ]),
            new Section([
                'id' => 'checkout_confirmation_checkbox',
                'title' => __('Confirmation Checkbox', 'wp-sms'),
                'subtitle' => __('Configure SMS confirmation checkbox on checkout', 'wp-sms'),
                'fields' => [
                    new Field([
                        'key' => 'wc_checkout_confirmation_checkbox_enabled',
                        'label' => __('Status', 'wp-sms'),
                        'type' => 'checkbox',
                        'description' => __('Show the checkbox on the checkout for the customer to confirm receiving notification via SMS.', 'wp-sms')
                    ]),
                ]
            ]),
            new Section([
                'id' => 'order_status_notification',
                'title' => __('Notify of order status', 'wp-sms'),
                'subtitle' => __('Configure SMS notifications for order status changes', 'wp-sms'),
                'help_url' => WP_SMS_SITE . '/resources/woocommerce-sms-variables-and-order-meta/',
                'fields' => [
                    new Field([
                        'key' => 'wc_notify_status_enable',
                        'label' => __('Send SMS', 'wp-sms'),
                        'type' => 'checkbox',
                        'description' => __('Send SMS to customer when status is changed', 'wp-sms')
                    ]),
                    new Field([
                        'key' => 'wc_notify_status_message',
                        'label' => __('Message body', 'wp-sms'),
                        'type' => 'textarea',
                        'description' => __('Enter the contents of the SMS message.', 'wp-sms') . '<br>' . NotificationFactory::getWooCommerceOrder()->printVariables()
                    ]),
                ]
            ]),
            new Section([
                'id' => 'specific_order_status_notification',
                'title' => __('Notify of specific order status', 'wp-sms'),
                'subtitle' => __('Configure SMS notifications for specific order statuses', 'wp-sms'),
                'help_url' => WP_SMS_SITE . '/resources/woocommerce-sms-variables-and-order-meta/',
                'fields' => [
                    new Field([
                        'key' => 'wc_notify_by_status_enable',
                        'label' => __('Send SMS', 'wp-sms'),
                        'type' => 'checkbox',
                        'description' => __('Send SMS to customer by order status', 'wp-sms')
                    ]),
                    new Field([
                        'key' => 'wc_notify_by_status_content',
                        'label' => __('Order Status & Message', 'wp-sms'),
                        'type' => 'repeater',
                        'description' => __('Add Order Status & Write Message Body Per Order Status', 'wp-sms'),
                        'options' => [
                            'template' => 'admin/fields/field-wc-status-repeater.php',
                            'order_statuses' => wc_get_order_statuses(),
                            'variables' => NotificationFactory::getWooCommerceOrder()->printVariables()
                        ],
                        'repeatable' => true,
                        'field_groups' => [
                            new \WP_SMS\Settings\FieldGroup([
                                'key' => 'order_status_message_group',
                                'label' => __('Order Status Message Group', 'wp-sms'),
                                'fields' => [
                                    new Field([
                                        'key' => 'order_status',
                                        'label' => __('Order Status', 'wp-sms'),
                                        'type' => 'select',
                                        'options' => wc_get_order_statuses(),
                                        'description' => __('Please choose an order status', 'wp-sms'),
                                    ]),
                                    new Field([
                                        'key' => 'notify_status',
                                        'label' => __('Notify Status', 'wp-sms'),
                                        'type' => 'select',
                                        'options' => [
                                            '1' => __('Enable', 'wp-sms'),
                                            '2' => __('Disable', 'wp-sms'),
                                        ],
                                        'description' => __('Please select notify status', 'wp-sms'),
                                    ]),
                                    new Field([
                                        'key' => 'message',
                                        'label' => __('Message', 'wp-sms'),
                                        'type' => 'textarea',
                                        'description' => __('Enter the contents of the SMS message.', 'wp-sms') . '<br>' . NotificationFactory::getWooCommerceOrder()->printVariables(),
                                        'rows' => 3,
                                    ]),
                                ]
                            ])
                        ]
                    ]),
                ]
            ]),
        ];
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