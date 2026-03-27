<?php

namespace WSms\Integration\Line\Triggers;

use WSms\Flow\Contracts\AbstractTrigger;
use WSms\Integration\PayloadSchemas;

defined('ABSPATH') || exit;

class FollowTrigger extends AbstractTrigger
{
    public function getId(): string
    {
        return 'line.follow';
    }

    public function getName(): string
    {
        return __('User Followed', 'wp-sms');
    }

    public function getDescription(): string
    {
        return __('Fires when a user adds the LINE bot as a friend', 'wp-sms');
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
        add_action('wsms_line_follow', function (array $event) use ($callback) {
            $source = $event['source'] ?? [];

            $callback([
                'userId'     => $source['userId'] ?? '',
                'replyToken' => $event['replyToken'] ?? '',
                'timestamp'  => (int) ($event['timestamp'] ?? 0),
            ]);
        });
    }
}
