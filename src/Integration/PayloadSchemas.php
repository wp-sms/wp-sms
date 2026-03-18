<?php

namespace WSms\Integration;

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
            'phone'        => get_user_meta($user->ID, 'wsms_phone', true) ?: '',
            'login'        => $user->user_login,
            'display_name' => $user->display_name,
            'first_name'   => $user->first_name,
            'last_name'    => $user->last_name,
            'roles'        => $user->roles,
        ];

        return array_intersect_key($all, array_flip($fields));
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
