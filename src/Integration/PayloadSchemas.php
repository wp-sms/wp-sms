<?php

namespace WSms\Integration;

use WSms\Support\UserMeta;

defined('ABSPATH') || exit;

class PayloadSchemas
{
    /** @return array<string, array> Customer sub-properties: email, phone, name */
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
        ];
    }

    /** @return array<string, array> Order sub-properties: id, total, and optionally status */
    public static function wooOrder(bool $includeStatus = true): array
    {
        $props = [
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
        ];

        if ($includeStatus) {
            $props['status'] = [
                'type' => 'string',
                'label' => __('Status', 'wp-sms'),
                'example' => 'pending',
            ];
        }

        return $props;
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
