<?php

namespace WSms\Integration\Line\Triggers;

use WSms\Flow\Contracts\AbstractTrigger;

defined('ABSPATH') || exit;

class MemberLeftTrigger extends AbstractTrigger
{
    public function getId(): string
    {
        return 'line.member_left';
    }

    public function getName(): string
    {
        return __('Member Left', 'wp-sms');
    }

    public function getDescription(): string
    {
        return __('Fires when a user leaves a group or room where the bot is present', 'wp-sms');
    }

    public function getGroup(): string
    {
        return __('LINE', 'wp-sms');
    }

    public function getPayloadSchema(): array
    {
        return [
            'userId' => [
                'type'    => 'string',
                'label'   => __('User ID', 'wp-sms'),
                'example' => 'U1234567890abcdef1234567890abcdef',
            ],
            'groupId' => [
                'type'    => 'string',
                'label'   => __('Group ID', 'wp-sms'),
                'example' => 'C1234567890abcdef1234567890abcdef',
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
        add_action('wsms_line_member_left', function (array $event) use ($callback) {
            $source = $event['source'] ?? [];
            $left = $event['left'] ?? [];
            $members = $left['members'] ?? [];

            foreach ($members as $member) {
                $callback([
                    'userId'    => $member['userId'] ?? '',
                    'groupId'   => $source['groupId'] ?? $source['roomId'] ?? '',
                    'timestamp' => (int) ($event['timestamp'] ?? 0),
                ]);
            }
        });
    }
}
