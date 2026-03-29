<?php

namespace WSms\Integration\Line\Triggers;

use WSms\Flow\Contracts\AbstractTrigger;
use WSms\Integration\PayloadSchemas;

defined('ABSPATH') || exit;

class MemberJoinedTrigger extends AbstractTrigger
{
    public function getId(): string
    {
        return 'line.member_joined';
    }

    public function getName(): string
    {
        return __('Member Joined', 'wp-sms');
    }

    public function getDescription(): string
    {
        return __('Fires when a user joins a group or room where the bot is present', 'wp-sms');
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
            'replyToken' => [
                'type'    => 'string',
                'label'   => __('Reply Token', 'wp-sms'),
                'example' => 'nHuyWiB7yP5Zw52FIkcQobQuGDXCTA',
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
        add_action('wsms_line_member_joined', function (array $event) use ($callback) {
            $source = $event['source'] ?? [];
            $joined = $event['joined'] ?? [];
            $members = $joined['members'] ?? [];

            // Fire once per joined member.
            foreach ($members as $member) {
                $callback([
                    'userId'     => $member['userId'] ?? '',
                    'groupId'    => $source['groupId'] ?? $source['roomId'] ?? '',
                    'replyToken' => $event['replyToken'] ?? '',
                    'timestamp'  => (int) ($event['timestamp'] ?? 0),
                ]);
            }
        });
    }
}
