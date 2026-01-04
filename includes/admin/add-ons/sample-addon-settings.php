<?php
/**
 * Sample Add-on Settings Registration
 *
 * This file demonstrates how add-ons can register their settings
 * to appear in the new React-based settings page.
 *
 * To test: Uncomment the add_filter line at the bottom of this file,
 * or copy this pattern to your add-on plugin.
 *
 * @package WP_SMS
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register sample WooCommerce add-on settings
 *
 * @param array $schemas Existing add-on schemas
 * @return array Modified schemas
 */
function wpsms_sample_woocommerce_addon_settings($schemas)
{
    $schemas['wpsms-woocommerce'] = [
        'name'    => 'WooCommerce Integration',
        'version' => '2.0.0',
        'sections' => [
            [
                'id'          => 'woo-orders',
                'title'       => 'WooCommerce Orders',
                'description' => 'Send SMS notifications for order status changes',
                'icon'        => 'ShoppingCart',
                'page'        => 'integrations',
                'priority'    => 5,
            ],
            [
                'id'          => 'woo-customers',
                'title'       => 'Customer Notifications',
                'description' => 'Automated SMS to customers',
                'icon'        => 'Users',
                'page'        => 'integrations',
                'priority'    => 10,
            ],
        ],
        'fields' => [
            // Admin order notification toggle
            [
                'id'          => 'woo_admin_order_notification',
                'type'        => 'switch',
                'label'       => 'Admin Order Alerts',
                'description' => 'Notify admin when new orders are placed',
                'default'     => false,
                'target'      => [
                    'page'     => 'integrations',
                    'section'  => 'woo-orders',
                    'priority' => 10,
                ],
                'isPro'       => true,
            ],
            // Order statuses multi-select
            [
                'id'          => 'woo_order_statuses',
                'type'        => 'multi-select',
                'label'       => 'Notify on Status',
                'description' => 'Select which order statuses trigger notifications',
                'default'     => ['processing', 'completed'],
                'options'     => [
                    ['value' => 'pending', 'label' => 'Pending Payment'],
                    ['value' => 'processing', 'label' => 'Processing'],
                    ['value' => 'on-hold', 'label' => 'On Hold'],
                    ['value' => 'completed', 'label' => 'Completed'],
                    ['value' => 'cancelled', 'label' => 'Cancelled'],
                    ['value' => 'refunded', 'label' => 'Refunded'],
                ],
                'target'      => [
                    'page'     => 'integrations',
                    'section'  => 'woo-orders',
                    'priority' => 20,
                ],
                'conditions'  => [
                    ['field' => 'woo_admin_order_notification', 'operator' => '==', 'value' => true],
                ],
                'isPro'       => true,
            ],
            // Admin message template
            [
                'id'          => 'woo_admin_order_template',
                'type'        => 'textarea',
                'label'       => 'Admin Message Template',
                'description' => 'Variables: %order_id%, %order_status%, %order_total%, %customer_name%',
                'default'     => 'New order #%order_id% - %order_total% from %customer_name%',
                'rows'        => 3,
                'target'      => [
                    'page'     => 'integrations',
                    'section'  => 'woo-orders',
                    'priority' => 30,
                ],
                'conditions'  => [
                    ['field' => 'woo_admin_order_notification', 'operator' => '==', 'value' => true],
                ],
                'validation'  => [
                    'maxLength' => 500,
                ],
                'isPro'       => true,
            ],
            // Customer notification toggle
            [
                'id'          => 'woo_customer_order_notification',
                'type'        => 'switch',
                'label'       => 'Customer Order Updates',
                'description' => 'Send SMS to customers when their order status changes',
                'default'     => true,
                'target'      => [
                    'page'     => 'integrations',
                    'section'  => 'woo-customers',
                    'priority' => 10,
                ],
                'isPro'       => true,
            ],
            // Customer statuses
            [
                'id'          => 'woo_customer_statuses',
                'type'        => 'multi-select',
                'label'       => 'Status Updates',
                'description' => 'Which status changes notify the customer',
                'default'     => ['processing', 'completed'],
                'options'     => [
                    ['value' => 'processing', 'label' => 'Processing'],
                    ['value' => 'completed', 'label' => 'Completed'],
                    ['value' => 'shipped', 'label' => 'Shipped'],
                    ['value' => 'delivered', 'label' => 'Delivered'],
                ],
                'target'      => [
                    'page'     => 'integrations',
                    'section'  => 'woo-customers',
                    'priority' => 20,
                ],
                'conditions'  => [
                    ['field' => 'woo_customer_order_notification', 'operator' => '==', 'value' => true],
                ],
                'isPro'       => true,
            ],
        ],
    ];

    return $schemas;
}

/**
 * Register sample Two-Way SMS add-on settings
 *
 * @param array $schemas Existing add-on schemas
 * @return array Modified schemas
 */
function wpsms_sample_twoway_addon_settings($schemas)
{
    $schemas['wpsms-two-way'] = [
        'name'    => 'Two-Way SMS',
        'version' => '1.5.0',
        'sections' => [
            [
                'id'          => 'two-way-config',
                'title'       => 'Two-Way SMS Configuration',
                'description' => 'Configure incoming SMS handling and auto-responses',
                'icon'        => 'MessageSquare',
                'page'        => 'advanced',
                'priority'    => 5,
            ],
        ],
        'fields' => [
            [
                'id'          => 'twoway_enable',
                'type'        => 'switch',
                'label'       => 'Enable Two-Way SMS',
                'description' => 'Allow receiving and processing incoming SMS messages',
                'default'     => false,
                'target'      => [
                    'page'     => 'advanced',
                    'section'  => 'two-way-config',
                    'priority' => 10,
                ],
                'isPro'       => true,
            ],
            [
                'id'          => 'twoway_webhook_url',
                'type'        => 'text',
                'label'       => 'Webhook URL',
                'description' => 'URL to receive incoming SMS from your gateway',
                'placeholder' => 'https://yoursite.com/wp-json/wpsms/v1/webhook/incoming',
                'target'      => [
                    'page'     => 'advanced',
                    'section'  => 'two-way-config',
                    'priority' => 20,
                ],
                'conditions'  => [
                    ['field' => 'twoway_enable', 'operator' => '==', 'value' => true],
                ],
                'isPro'       => true,
            ],
            [
                'id'          => 'twoway_auto_reply',
                'type'        => 'switch',
                'label'       => 'Auto-Reply',
                'description' => 'Automatically reply to incoming messages',
                'default'     => false,
                'target'      => [
                    'page'     => 'advanced',
                    'section'  => 'two-way-config',
                    'priority' => 30,
                ],
                'conditions'  => [
                    ['field' => 'twoway_enable', 'operator' => '==', 'value' => true],
                ],
                'isPro'       => true,
            ],
            [
                'id'          => 'twoway_auto_reply_message',
                'type'        => 'textarea',
                'label'       => 'Auto-Reply Message',
                'description' => 'Message to send when auto-reply is enabled',
                'default'     => 'Thank you for your message. We will respond shortly.',
                'rows'        => 2,
                'target'      => [
                    'page'     => 'advanced',
                    'section'  => 'two-way-config',
                    'priority' => 40,
                ],
                'conditions'  => [
                    ['field' => 'twoway_auto_reply', 'operator' => '==', 'value' => true],
                ],
                'validation'  => [
                    'maxLength' => 160,
                ],
                'isPro'       => true,
            ],
        ],
    ];

    return $schemas;
}

// Enable sample add-on settings for testing
// Comment these lines out or remove this file in production
// add_filter('wpsms_addon_settings_schema', 'wpsms_sample_woocommerce_addon_settings');
// add_filter('wpsms_addon_settings_schema', 'wpsms_sample_twoway_addon_settings');
