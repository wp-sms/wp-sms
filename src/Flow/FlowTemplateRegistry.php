<?php

namespace WSms\Flow;

defined('ABSPATH') || exit;

class FlowTemplateRegistry
{
    private static ?array $cache = null;

    /** @return array<string, array> */
    public static function all(): array
    {
        return self::$cache ??= [
            'welcome_sms' => [
                'id'          => 'welcome_sms',
                'name'        => __('Welcome SMS to New Subscribers', 'wp-sms'),
                'description' => __('Send a welcome message when a new user registers on your site.', 'wp-sms'),
                'category'    => 'Welcome & Onboarding',
                'trigger_type'   => 'wordpress.user_register',
                'trigger_config' => [],
                'steps' => [
                    [
                        'id'     => 'welcome_action',
                        'type'   => 'action',
                        'action' => 'send_message',
                        'config' => [
                            'channel' => 'sms',
                            'to'      => '{{user.phone}}',
                            'body'    => 'Welcome {{user.display_name}}! Thanks for joining us.',
                        ],
                    ],
                ],
            ],

            'order_confirmation' => [
                'id'          => 'order_confirmation',
                'name'        => __('Order Confirmation SMS', 'wp-sms'),
                'description' => __('Notify customers via SMS when they place a new order.', 'wp-sms'),
                'category'    => 'WooCommerce',
                'trigger_type'   => 'woocommerce.order_created',
                'trigger_config' => [],
                'steps' => [
                    [
                        'id'     => 'confirm_action',
                        'type'   => 'action',
                        'action' => 'send_message',
                        'config' => [
                            'channel' => 'sms',
                            'to'      => '{{customer.phone}}',
                            'body'    => 'Hi {{customer.name}}, your order #{{order_id}} has been received! Total: ${{order.total}}',
                        ],
                    ],
                ],
            ],

            'order_completed' => [
                'id'          => 'order_completed',
                'name'        => __('Order Completed Notification', 'wp-sms'),
                'description' => __('Let customers know when their order is completed and ready.', 'wp-sms'),
                'category'    => 'WooCommerce',
                'trigger_type'   => 'woocommerce.order_completed',
                'trigger_config' => [],
                'steps' => [
                    [
                        'id'     => 'completed_action',
                        'type'   => 'action',
                        'action' => 'send_message',
                        'config' => [
                            'channel' => 'sms',
                            'to'      => '{{customer.phone}}',
                            'body'    => 'Great news {{customer.name}}! Your order #{{order_id}} is now complete.',
                        ],
                    ],
                ],
            ],

            'payment_failed_alert' => [
                'id'          => 'payment_failed_alert',
                'name'        => __('Payment Failed Alert to Admin', 'wp-sms'),
                'description' => __('Immediately notify the admin when a payment fails.', 'wp-sms'),
                'category'    => 'WooCommerce',
                'trigger_type'   => 'woocommerce.payment_failed',
                'trigger_config' => [],
                'steps' => [
                    [
                        'id'     => 'failed_alert_action',
                        'type'   => 'action',
                        'action' => 'send_message',
                        'config' => [
                            'channel' => 'sms',
                            'to'      => '',
                            'body'    => 'Payment failed for order #{{order_id}} from {{customer.name}} ({{customer.email}}). Total: ${{order.total}}',
                        ],
                    ],
                ],
            ],

            'new_user_notification' => [
                'id'          => 'new_user_notification',
                'name'        => __('New User Registration Alert', 'wp-sms'),
                'description' => __('Get notified when a new user registers on your site.', 'wp-sms'),
                'category'    => 'Notifications',
                'trigger_type'   => 'wordpress.user_register',
                'trigger_config' => [],
                'steps' => [
                    [
                        'id'     => 'new_user_action',
                        'type'   => 'action',
                        'action' => 'send_message',
                        'config' => [
                            'channel' => 'sms',
                            'to'      => '',
                            'body'    => 'New user registered: {{user.display_name}} ({{user.email}}) with role: {{role}}',
                        ],
                    ],
                ],
            ],

            'role_change_notification' => [
                'id'          => 'role_change_notification',
                'name'        => __('User Role Change Notification', 'wp-sms'),
                'description' => __('Alert when a user\'s role is changed.', 'wp-sms'),
                'category'    => 'Notifications',
                'trigger_type'   => 'wordpress.user_role_changed',
                'trigger_config' => [],
                'steps' => [
                    [
                        'id'     => 'role_change_action',
                        'type'   => 'action',
                        'action' => 'send_message',
                        'config' => [
                            'channel' => 'sms',
                            'to'      => '',
                            'body'    => 'User {{user.display_name}} role changed from {{old_role}} to {{new_role}}.',
                        ],
                    ],
                ],
            ],

            'new_comment_notification' => [
                'id'          => 'new_comment_notification',
                'name'        => __('New Comment Notification', 'wp-sms'),
                'description' => __('Get notified when a new comment is posted on your site.', 'wp-sms'),
                'category'    => 'Notifications',
                'trigger_type'   => 'wordpress.comment_posted',
                'trigger_config' => [],
                'steps' => [
                    [
                        'id'     => 'comment_action',
                        'type'   => 'action',
                        'action' => 'send_message',
                        'config' => [
                            'channel' => 'sms',
                            'to'      => '',
                            'body'    => 'New comment on "{{post.title}}" by {{comment.author}}: {{comment.content}}',
                        ],
                    ],
                ],
            ],

            'post_published' => [
                'id'          => 'post_published',
                'name'        => __('Post Published Announcement', 'wp-sms'),
                'description' => __('Announce when a new post is published on your site.', 'wp-sms'),
                'category'    => 'Engagement',
                'trigger_type'   => 'wordpress.post_published',
                'trigger_config' => [],
                'steps' => [
                    [
                        'id'     => 'post_action',
                        'type'   => 'action',
                        'action' => 'send_message',
                        'config' => [
                            'channel' => 'sms',
                            'to'      => '',
                            'body'    => 'New post published: "{{post.title}}" by {{post.author}}. Read it at {{post.url}}',
                        ],
                    ],
                ],
            ],

            'order_status_webhook' => [
                'id'          => 'order_status_webhook',
                'name'        => __('Order Status Change Webhook', 'wp-sms'),
                'description' => __('Fire a webhook when an order status changes in WooCommerce.', 'wp-sms'),
                'category'    => 'WooCommerce',
                'trigger_type'   => 'woocommerce.order_status_changed',
                'trigger_config' => [],
                'steps' => [
                    [
                        'id'     => 'webhook_action',
                        'type'   => 'action',
                        'action' => 'send_webhook',
                        'config' => [
                            'url'    => '',
                            'method' => 'POST',
                        ],
                    ],
                ],
            ],

            'abandoned_cart_reminder' => [
                'id'          => 'abandoned_cart_reminder',
                'name'        => __('Abandoned Cart Follow-up', 'wp-sms'),
                'description' => __('Send a reminder if an order is still pending after 1 hour.', 'wp-sms'),
                'category'    => 'Engagement',
                'trigger_type'   => 'woocommerce.order_created',
                'trigger_config' => [],
                'steps' => [
                    [
                        'id'       => 'delay_1hr',
                        'type'     => 'delay',
                        'duration' => 3600,
                        'then' => [
                            [
                                'id'         => 'check_pending',
                                'type'       => 'condition',
                                'expression' => 'order["status"] == "pending"',
                                'rules' => [
                                    ['field' => 'order.status', 'operator' => 'equals', 'value' => 'pending'],
                                ],
                                'then' => [
                                    [
                                        'id'     => 'reminder_action',
                                        'type'   => 'action',
                                        'action' => 'send_message',
                                        'config' => [
                                            'channel' => 'sms',
                                            'to'      => '{{customer.phone}}',
                                            'body'    => 'Hi {{customer.name}}, you have an incomplete order #{{order_id}} for ${{order.total}}. Complete your purchase today!',
                                        ],
                                    ],
                                ],
                                'else' => [],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /** @return array|null */
    public static function find(string $id): ?array
    {
        return self::all()[$id] ?? null;
    }
}
