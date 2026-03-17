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
     * @param string[] $fields Fields to include (email, login, display_name, roles)
     * @return array<string, array>
     */
    public static function wpUser(array $fields = ['email', 'login', 'display_name', 'roles']): array
    {
        $all = [
            'email' => [
                'type' => 'string',
                'label' => __('Email', 'wp-sms'),
                'example' => 'user@example.com',
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
            'roles' => [
                'type' => 'array',
                'label' => __('Roles', 'wp-sms'),
                'example' => ['subscriber'],
            ],
        ];

        return array_intersect_key($all, array_flip($fields));
    }
}
