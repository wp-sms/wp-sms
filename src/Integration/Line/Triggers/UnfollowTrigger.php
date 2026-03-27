<?php

namespace WSms\Integration\Line\Triggers;

use WSms\Flow\Contracts\AbstractTrigger;

defined('ABSPATH') || exit;

class UnfollowTrigger extends AbstractTrigger
{
    public function getId(): string
    {
        return 'line.unfollow';
    }

    public function getName(): string
    {
        return __('User Unfollowed', 'wp-sms');
    }

    public function getDescription(): string
    {
        return __('Fires when a user blocks or unfriends the LINE bot', 'wp-sms');
    }

    public function getGroup(): string
    {
        return 'LINE';
    }

    public function getPayloadSchema(): array
    {
        return [
            'userId' => [
                'type'    => 'string',
                'label'   => __('User ID', 'wp-sms'),
                'example' => 'U1234567890abcdef1234567890abcdef',
            ],
            'timestamp' => [
                'type'    => 'integer',
                'label'   => __('Timestamp', 'wp-sms'),
                'example' => 1700000000000,
            ],
        ];
    }

    public function subscribe(callable $callback): void
    {
        add_action('wsms_line_unfollow', function (array $event) use ($callback) {
            $source = $event['source'] ?? [];

            $callback([
                'userId'    => $source['userId'] ?? '',
                'timestamp' => (int) ($event['timestamp'] ?? 0),
            ]);
        });
    }
}
