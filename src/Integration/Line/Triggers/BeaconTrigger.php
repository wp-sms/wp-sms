<?php

namespace WSms\Integration\Line\Triggers;

use WSms\Flow\Contracts\AbstractTrigger;

defined('ABSPATH') || exit;

class BeaconTrigger extends AbstractTrigger
{
    public function getId(): string
    {
        return 'line.beacon';
    }

    public function getName(): string
    {
        return __('Beacon Event', 'wp-sms');
    }

    public function getDescription(): string
    {
        return __('Fires when a user enters a LINE Beacon region', 'wp-sms');
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
            'hwid' => [
                'type'    => 'string',
                'label'   => __('Hardware ID', 'wp-sms'),
                'example' => 'd41d8cd98f',
            ],
            'beaconType' => [
                'type'    => 'string',
                'label'   => __('Beacon Type', 'wp-sms'),
                'example' => 'enter',
                'enum'    => ['enter', 'banner', 'stay'],
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

    public function getFilterSchema(): array
    {
        return [
            'beaconType' => [
                'type'        => 'string',
                'label'       => __('Beacon Type', 'wp-sms'),
                'description' => __('Only trigger for this beacon event type', 'wp-sms'),
                'enum'        => ['enter', 'banner', 'stay'],
            ],
        ];
    }

    public function subscribe(callable $callback): void
    {
        add_action('wsms_line_beacon', function (array $event) use ($callback) {
            $source = $event['source'] ?? [];
            $beacon = $event['beacon'] ?? [];

            $callback([
                'userId'     => $source['userId'] ?? '',
                'hwid'       => $beacon['hwid'] ?? '',
                'beaconType' => $beacon['type'] ?? '',
                'replyToken' => $event['replyToken'] ?? '',
                'timestamp'  => (int) ($event['timestamp'] ?? 0),
            ]);
        });
    }
}
