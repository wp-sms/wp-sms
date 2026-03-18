<?php

namespace WSms\Integration\WordPress\Triggers;

use WSms\Flow\Contracts\AbstractTrigger;
use WSms\Integration\PayloadSchemas;

defined('ABSPATH') || exit;

class UserUpdatedTrigger extends AbstractTrigger
{
    public function getId(): string
    {
        return 'wordpress.user_updated';
    }

    public function getName(): string
    {
        return __('User Updated', 'wp-sms');
    }

    public function getDescription(): string
    {
        return __('Fires when a user profile is updated', 'wp-sms');
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
                'description' => __('Updated user profile data', 'wp-sms'),
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
            'changed_fields' => [
                'type' => 'array',
                'label' => __('Changed Fields', 'wp-sms'),
                'description' => __('List of user fields that were modified', 'wp-sms'),
                'example' => ['display_name', 'user_email'],
            ],
        ];
    }

    public function getFilterSchema(): array
    {
        return [
            'changed_fields' => [
                'type'        => 'string',
                'label'       => __('Changed Field', 'wp-sms'),
                'description' => __('Only trigger when this specific field was modified', 'wp-sms'),
                'enum'        => ['user_email', 'display_name', 'user_login', 'first_name', 'last_name'],
                'enumLabels'  => [
                    'user_email'   => __('Email', 'wp-sms'),
                    'display_name' => __('Display Name', 'wp-sms'),
                    'user_login'   => __('Login', 'wp-sms'),
                    'first_name'   => __('First Name', 'wp-sms'),
                    'last_name'    => __('Last Name', 'wp-sms'),
                ],
            ],
        ];
    }

    public function subscribe(callable $callback): void
    {
        add_action('profile_update', function (int $userId, $oldUserData) use ($callback) {
            $user = get_userdata($userId);
            if (!$user) {
                return;
            }

            $changedFields = [];
            if ($oldUserData && is_object($oldUserData)) {
                foreach (['user_email', 'display_name', 'user_login', 'first_name', 'last_name'] as $field) {
                    if (isset($oldUserData->$field) && $oldUserData->$field !== $user->$field) {
                        $changedFields[] = $field;
                    }
                }
            }

            $callback([
                'user_id'        => $userId,
                'user'           => PayloadSchemas::extractWpUser($user),
                'changed_fields' => $changedFields,
            ]);
        }, 10, 2);
    }
}
