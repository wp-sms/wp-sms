<?php

namespace WSms\Integration\WordPress\Triggers;

use WSms\Flow\Contracts\AbstractTrigger;

defined('ABSPATH') || exit;

class UserRegisterTrigger extends AbstractTrigger
{
    public function getId(): string
    {
        return 'wordpress.user_register';
    }

    public function getName(): string
    {
        return __('User Registered', 'wp-sms');
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
            'user' => [
                'type' => 'object',
                'label' => __('User Data', 'wp-sms'),
                'description' => __('User profile data including email, login, display name, and roles', 'wp-sms'),
                'example' => [
                    'email' => 'user@example.com',
                    'login' => 'johndoe',
                    'display_name' => 'John Doe',
                    'roles' => ['subscriber'],
                ],
            ],
        ];
    }

    public function subscribe(callable $callback): void
    {
        add_action('user_register', function (int $userId) use ($callback) {
            $user = get_userdata($userId);
            if ($user) {
                $callback([
                    'user_id' => $userId,
                    'user' => [
                        'email' => $user->user_email,
                        'login' => $user->user_login,
                        'display_name' => $user->display_name,
                        'roles' => $user->roles,
                    ],
                ]);
            }
        }, 20);
    }
}
