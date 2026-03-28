<?php

namespace WSms\Integration;

use WSms\Support\UserMeta;

defined('ABSPATH') || exit;

class PayloadSchemas
{
    /** @return array<string, array> Customer sub-properties */
    public static function wooCustomer(): array
    {
        return [
            'email' => [
                'type' => 'string',
                'label' => __('Email', 'wp-sms'),
                'example' => 'customer@example.com',
            ],
            'phone' => [
                'type' => 'string',
                'label' => __('Phone', 'wp-sms'),
                'example' => '+1234567890',
            ],
            'name' => [
                'type' => 'string',
                'label' => __('Name', 'wp-sms'),
                'example' => 'John Doe',
            ],
            'first_name' => [
                'type' => 'string',
                'label' => __('First Name', 'wp-sms'),
                'example' => 'John',
            ],
            'last_name' => [
                'type' => 'string',
                'label' => __('Last Name', 'wp-sms'),
                'example' => 'Doe',
            ],
        ];
    }

    /** @return array<string, array> Order sub-properties */
    public static function wooOrder(): array
    {
        return [
            'id' => [
                'type' => 'integer',
                'label' => __('ID', 'wp-sms'),
                'example' => 1001,
            ],
            'total' => [
                'type' => 'string',
                'label' => __('Total', 'wp-sms'),
                'example' => '59.99',
            ],
            'status' => [
                'type' => 'string',
                'label' => __('Status', 'wp-sms'),
                'example' => 'pending',
                'enum' => ['pending', 'processing', 'on-hold', 'completed', 'cancelled', 'refunded', 'failed'],
                'enumLabels' => [
                    'pending'    => __('Pending payment', 'wp-sms'),
                    'processing' => __('Processing', 'wp-sms'),
                    'on-hold'    => __('On hold', 'wp-sms'),
                    'completed'  => __('Completed', 'wp-sms'),
                    'cancelled'  => __('Cancelled', 'wp-sms'),
                    'refunded'   => __('Refunded', 'wp-sms'),
                    'failed'     => __('Failed', 'wp-sms'),
                ],
            ],
            'currency' => [
                'type' => 'string',
                'label' => __('Currency', 'wp-sms'),
                'example' => 'USD',
            ],
            'payment_method' => [
                'type' => 'string',
                'label' => __('Payment Method', 'wp-sms'),
                'example' => 'Credit Card',
            ],
            'date_created' => [
                'type' => 'string',
                'label' => __('Date Created', 'wp-sms'),
                'example' => '2026-03-18 14:30:00',
            ],
            'items_count' => [
                'type' => 'integer',
                'label' => __('Items Count', 'wp-sms'),
                'example' => 3,
            ],
        ];
    }

    /**
     * Extract order payload data from a WC_Order object.
     *
     * @param \WC_Order $order
     * @return array<string, mixed>
     */
    public static function extractWooOrder($order): array
    {
        $dateCreated = $order->get_date_created();

        return [
            'id'             => $order->get_id(),
            'total'          => $order->get_total(),
            'status'         => $order->get_status(),
            'currency'       => $order->get_currency(),
            'payment_method' => $order->get_payment_method_title(),
            'date_created'   => $dateCreated ? $dateCreated->format('Y-m-d H:i:s') : '',
            'items_count'    => $order->get_item_count(),
        ];
    }

    /**
     * Extract customer payload data from a WC_Order object.
     *
     * @param \WC_Order $order
     * @return array<string, mixed>
     */
    public static function extractWooCustomer($order): array
    {
        return [
            'email'      => $order->get_billing_email(),
            'phone'      => $order->get_billing_phone(),
            'name'       => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
            'first_name' => $order->get_billing_first_name(),
            'last_name'  => $order->get_billing_last_name(),
        ];
    }

    /** @return array<string, array> WSMS Contact sub-properties */
    public static function wsmsContact(): array
    {
        return [
            'email' => [
                'type' => 'string',
                'label' => __('Email', 'wp-sms'),
                'example' => 'contact@example.com',
            ],
            'phone' => [
                'type' => 'string',
                'label' => __('Phone', 'wp-sms'),
                'example' => '+1234567890',
            ],
            'first_name' => [
                'type' => 'string',
                'label' => __('First Name', 'wp-sms'),
                'example' => 'John',
            ],
            'last_name' => [
                'type' => 'string',
                'label' => __('Last Name', 'wp-sms'),
                'example' => 'Doe',
            ],
            'status' => [
                'type' => 'string',
                'label' => __('Status', 'wp-sms'),
                'example' => 'subscribed',
                'enum' => ['subscribed', 'bounced', 'complained'],
                'enumLabels' => [
                    'subscribed' => __('Subscribed', 'wp-sms'),
                    'bounced'    => __('Bounced', 'wp-sms'),
                    'complained' => __('Complained', 'wp-sms'),
                ],
            ],
            'source' => [
                'type' => 'string',
                'label' => __('Source', 'wp-sms'),
                'example' => 'form',
            ],
        ];
    }

    /**
     * Extract contact payload data from a contact array.
     *
     * @param array $contact Contact data as returned by ContactRepositoryInterface::find()
     * @return array<string, mixed>
     */
    public static function extractContact(array $contact): array
    {
        return [
            'email'      => $contact['email'] ?? '',
            'phone'      => $contact['phone'] ?? '',
            'first_name' => $contact['first_name'] ?? '',
            'last_name'  => $contact['last_name'] ?? '',
            'status'     => $contact['status'] ?? '',
            'source'     => $contact['source'] ?? '',
        ];
    }

    /**
     * @param string[] $fields Fields to include (email, phone, login, display_name, first_name, last_name, roles)
     * @return array<string, array>
     */
    public static function wpUser(array $fields = ['email', 'phone', 'login', 'display_name', 'first_name', 'last_name', 'roles']): array
    {
        $all = [
            'email' => [
                'type' => 'string',
                'label' => __('Email', 'wp-sms'),
                'example' => 'user@example.com',
            ],
            'phone' => [
                'type' => 'string',
                'label' => __('Phone', 'wp-sms'),
                'example' => '+1234567890',
            ],
            'login' => [
                'type' => 'string',
                'label' => __('Login', 'wp-sms'),
                'example' => 'johndoe',
            ],
            'display_name' => [
                'type' => 'string',
                'label' => __('Display Name', 'wp-sms'),
                'example' => 'John Doe',
            ],
            'first_name' => [
                'type' => 'string',
                'label' => __('First Name', 'wp-sms'),
                'example' => 'John',
            ],
            'last_name' => [
                'type' => 'string',
                'label' => __('Last Name', 'wp-sms'),
                'example' => 'Doe',
            ],
            'roles' => [
                'type' => 'array',
                'label' => __('Roles', 'wp-sms'),
                'example' => ['subscriber'],
            ],
        ];

        return array_intersect_key($all, array_flip($fields));
    }

    /**
     * Extract user payload data from a WP_User object.
     *
     * @param \WP_User $user
     * @param string[] $fields Fields to include (must match wpUser() schema keys)
     * @return array<string, mixed>
     */
    public static function extractWpUser(\WP_User $user, array $fields = ['email', 'phone', 'login', 'display_name', 'first_name', 'last_name', 'roles']): array
    {
        $all = [
            'email'        => $user->user_email,
            'phone'        => get_user_meta($user->ID, UserMeta::PHONE, true) ?: '',
            'login'        => $user->user_login,
            'display_name' => $user->display_name,
            'first_name'   => $user->first_name,
            'last_name'    => $user->last_name,
            'roles'        => $user->roles,
        ];

        return array_intersect_key($all, array_flip($fields));
    }

    /** @return array<string, array> Telegram user sub-properties: id, first_name, last_name, username */
    public static function telegramUser(): array
    {
        return [
            'id' => [
                'type' => 'integer',
                'label' => __('User ID', 'wp-sms'),
                'example' => 123456789,
            ],
            'first_name' => [
                'type' => 'string',
                'label' => __('First Name', 'wp-sms'),
                'example' => 'John',
            ],
            'last_name' => [
                'type' => 'string',
                'label' => __('Last Name', 'wp-sms'),
                'example' => 'Doe',
            ],
            'username' => [
                'type' => 'string',
                'label' => __('Username', 'wp-sms'),
                'example' => 'johndoe',
            ],
        ];
    }

    /** @return array<string, array> Telegram chat sub-properties: id, type, title */
    public static function telegramChat(): array
    {
        return [
            'id' => [
                'type' => 'integer',
                'label' => __('Chat ID', 'wp-sms'),
                'example' => -1001234567890,
            ],
            'type' => [
                'type' => 'string',
                'label' => __('Type', 'wp-sms'),
                'example' => 'private',
            ],
            'title' => [
                'type' => 'string',
                'label' => __('Title', 'wp-sms'),
                'example' => 'My Group',
            ],
        ];
    }

    /** Extract Telegram user data from a raw update user object. */
    public static function extractTelegramUser(array $from): array
    {
        return [
            'id'         => $from['id'] ?? 0,
            'first_name' => $from['first_name'] ?? '',
            'last_name'  => $from['last_name'] ?? '',
            'username'   => $from['username'] ?? '',
        ];
    }

    /** Extract Telegram chat data from a raw update chat object. */
    public static function extractTelegramChat(array $chat): array
    {
        return [
            'id'    => $chat['id'] ?? 0,
            'type'  => $chat['type'] ?? '',
            'title' => $chat['title'] ?? '',
        ];
    }

    /** Detect media type from a Telegram message/post array. Returns empty string if no media. */
    public static function detectTelegramMediaType(array $message): string
    {
        $types = ['photo', 'video', 'audio', 'voice', 'document', 'animation', 'sticker', 'video_note'];

        foreach ($types as $type) {
            if (!empty($message[$type])) {
                return $type;
            }
        }

        return '';
    }

    /** @return array<string, array> Line user sub-properties: userId, displayName, pictureUrl, language */
    public static function lineUser(): array
    {
        return [
            'userId' => [
                'type' => 'string',
                'label' => __('User ID', 'wp-sms'),
                'example' => 'U1234567890abcdef1234567890abcdef',
            ],
            'displayName' => [
                'type' => 'string',
                'label' => __('Display Name', 'wp-sms'),
                'example' => 'Taro Yamada',
            ],
            'pictureUrl' => [
                'type' => 'string',
                'label' => __('Picture URL', 'wp-sms'),
                'example' => 'https://profile.line-scdn.net/...',
            ],
            'language' => [
                'type' => 'string',
                'label' => __('Language', 'wp-sms'),
                'example' => 'ja',
            ],
        ];
    }

    /** @return array<string, array> Line source sub-properties: type, userId, groupId, roomId */
    public static function lineSource(): array
    {
        return [
            'type' => [
                'type' => 'string',
                'label' => __('Source Type', 'wp-sms'),
                'example' => 'user',
            ],
            'userId' => [
                'type' => 'string',
                'label' => __('User ID', 'wp-sms'),
                'example' => 'U1234567890abcdef1234567890abcdef',
            ],
            'groupId' => [
                'type' => 'string',
                'label' => __('Group ID', 'wp-sms'),
                'example' => 'C1234567890abcdef1234567890abcdef',
            ],
        ];
    }

    /** Extract Line source data from a raw webhook event source object. */
    public static function extractLineSource(array $source): array
    {
        return [
            'type'    => $source['type'] ?? '',
            'userId'  => $source['userId'] ?? '',
            'groupId' => $source['groupId'] ?? '',
        ];
    }

    /**
     * Extract post payload data from a WP_Post object.
     *
     * @return array<string, mixed>
     */
    public static function extractPost(\WP_Post $post): array
    {
        return [
            'title'   => $post->post_title,
            'url'     => get_permalink($post->ID),
            'excerpt' => $post->post_excerpt,
            'type'    => $post->post_type,
            'status'  => $post->post_status,
            'date'    => $post->post_date,
        ];
    }
}
