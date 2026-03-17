<?php

namespace WSms\Integration\WordPress\Triggers;

use WSms\Flow\Contracts\AbstractTrigger;

defined('ABSPATH') || exit;

class UserRoleChangedTrigger extends AbstractTrigger
{
    public function getId(): string
    {
        return 'wordpress.user_role_changed';
    }

    public function getName(): string
    {
        return __('User Role Changed', 'wp-sms');
    }

    public function getGroup(): string
    {
        return 'WordPress';
    }

    public function getPayloadSchema(): array
    {
        return [
            'user_id' => [
                'type' => 'integer',
                'label' => __('User ID', 'wp-sms'),
                'description' => __('The WordPress user ID', 'wp-sms'),
                'example' => 42,
            ],
            'new_role' => [
                'type' => 'string',
                'label' => __('New Role', 'wp-sms'),
                'description' => __('The role assigned to the user', 'wp-sms'),
                'example' => 'editor',
            ],
            'old_role' => [
                'type' => 'string',
                'label' => __('Old Role', 'wp-sms'),
                'description' => __('The previous primary role of the user', 'wp-sms'),
                'example' => 'subscriber',
            ],
            'user' => [
                'type' => 'object',
                'label' => __('User Data', 'wp-sms'),
                'description' => __('User profile data', 'wp-sms'),
                'example' => [
                    'email' => 'user@example.com',
                    'login' => 'johndoe',
                    'display_name' => 'John Doe',
                ],
            ],
        ];
    }

    public function subscribe(callable $callback): void
    {
        add_action('set_user_role', function (int $userId, string $role, array $oldRoles) use ($callback) {
            $user = get_userdata($userId);
            $callback([
                'user_id' => $userId,
                'new_role' => $role,
                'old_role' => $oldRoles[0] ?? '',
                'user' => $user ? [
                    'email' => $user->user_email,
                    'login' => $user->user_login,
                    'display_name' => $user->display_name,
                ] : [],
            ]);
        }, 10, 3);
    }
}
