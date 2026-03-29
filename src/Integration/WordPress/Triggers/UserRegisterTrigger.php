<?php

namespace WSms\Integration\WordPress\Triggers;

use WSms\Flow\Contracts\AbstractTrigger;
use WSms\Integration\PayloadSchemas;
use WSms\Integration\WordPress\WordPressOptions;

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

    public function getDescription(): string
    {
        return __('Fires when a new user account is created', 'wp-sms');
    }

    public function getGroup(): string
    {
        return __('WordPress', 'wp-sms');
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
            'role' => [
                'type' => 'string',
                'label' => __('Role', 'wp-sms'),
                'description' => __('The primary role assigned to the user', 'wp-sms'),
                'example' => 'subscriber',
            ],
            'user' => [
                'type' => 'object',
                'label' => __('User Data', 'wp-sms'),
                'description' => __('User profile data including email, login, display name, and roles', 'wp-sms'),
                'properties' => PayloadSchemas::wpUser(),
                'example' => [
                    'email' => 'user@example.com',
                    'phone' => '+1234567890',
                    'login' => 'johndoe',
                    'display_name' => 'John Doe',
                    'first_name' => 'John',
                    'last_name' => 'Doe',
                    'roles' => ['subscriber'],
                ],
            ],
        ];
    }

    public function getFilterSchema(): array
    {
        return [
            'role' => [
                'type'        => 'string',
                'label'       => __('Role', 'wp-sms'),
                'description' => __('Only trigger when user is assigned this role', 'wp-sms'),
                'dynamic'     => true,
            ],
        ];
    }

    public function getFilterOptions(string $fieldKey): array
    {
        if ($fieldKey === 'role') {
            return WordPressOptions::roles();
        }

        return [];
    }

    public function getSamplePayload(): ?array
    {
        $users = get_users(['number' => 1, 'orderby' => 'registered', 'order' => 'DESC']);

        if (empty($users)) {
            return null;
        }

        $user = $users[0];

        return [
            'user_id' => $user->ID,
            'role'    => $user->roles[0] ?? '',
            'user'    => PayloadSchemas::extractWpUser($user),
        ];
    }

    public function subscribe(callable $callback): void
    {
        add_action('user_register', function (int $userId) use ($callback) {
            $user = get_userdata($userId);
            if ($user) {
                $callback([
                    'user_id' => $userId,
                    'role'    => $user->roles[0] ?? '',
                    'user'    => PayloadSchemas::extractWpUser($user),
                ]);
            }
        }, 20);
    }
}
