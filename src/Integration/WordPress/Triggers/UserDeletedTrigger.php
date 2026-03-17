<?php

namespace WSms\Integration\WordPress\Triggers;

use WSms\Flow\Contracts\AbstractTrigger;
use WSms\Integration\PayloadSchemas;

defined('ABSPATH') || exit;

class UserDeletedTrigger extends AbstractTrigger
{
    public function getId(): string
    {
        return 'wordpress.user_deleted';
    }

    public function getName(): string
    {
        return __('User Deleted', 'wp-sms');
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
                'description' => __('The WordPress user ID being deleted', 'wp-sms'),
                'example' => 42,
            ],
            'user' => [
                'type' => 'object',
                'label' => __('User Data', 'wp-sms'),
                'description' => __('User data captured before deletion', 'wp-sms'),
                'properties' => PayloadSchemas::wpUser(['email', 'login']),
                'example' => [
                    'email' => 'user@example.com',
                    'login' => 'johndoe',
                ],
            ],
            'reassign_to' => [
                'type' => 'integer',
                'label' => __('Reassign To', 'wp-sms'),
                'description' => __('User ID that content is reassigned to, or null', 'wp-sms'),
                'example' => 1,
            ],
        ];
    }

    public function subscribe(callable $callback): void
    {
        add_action('delete_user', function (int $userId, ?int $reassign) use ($callback) {
            $user = get_userdata($userId);
            $callback([
                'user_id' => $userId,
                'user' => $user ? [
                    'email' => $user->user_email,
                    'login' => $user->user_login,
                ] : [],
                'reassign_to' => $reassign,
            ]);
        }, 10, 2);
    }
}
