<?php

namespace WSms\Integration\Line\Triggers;

use WSms\Flow\Contracts\AbstractTrigger;
use WSms\Integration\PayloadSchemas;

defined('ABSPATH') || exit;

class PostbackTrigger extends AbstractTrigger
{
    public function getId(): string
    {
        return 'line.postback';
    }

    public function getName(): string
    {
        return __('Postback Received', 'wp-sms');
    }

    public function getDescription(): string
    {
        return __('Fires when a user taps a postback button in a template or rich menu', 'wp-sms');
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
            'data' => [
                'type'        => 'string',
                'label'       => __('Postback Data', 'wp-sms'),
                'description' => __('The data string attached to the postback action', 'wp-sms'),
                'example'     => 'action=buy&itemid=123',
            ],
            'params' => [
                'type'        => 'object',
                'label'       => __('Params', 'wp-sms'),
                'description' => __('Date/time picker params if applicable', 'wp-sms'),
                'example'     => ['date' => '2026-01-15'],
            ],
            'replyToken' => [
                'type'    => 'string',
                'label'   => __('Reply Token', 'wp-sms'),
                'example' => 'nHuyWiB7yP5Zw52FIkcQobQuGDXCTA',
            ],
            'source' => [
                'type'       => 'object',
                'label'      => __('Source', 'wp-sms'),
                'properties' => PayloadSchemas::lineSource(),
                'example'    => ['type' => 'user', 'userId' => 'U1234...'],
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
            'data' => [
                'type'        => 'string',
                'label'       => __('Postback Data', 'wp-sms'),
                'description' => __('Only trigger for this postback data value', 'wp-sms'),
            ],
        ];
    }

    public function subscribe(callable $callback): void
    {
        add_action('wsms_line_postback', function (array $event) use ($callback) {
            $source = $event['source'] ?? [];
            $postback = $event['postback'] ?? [];

            $callback([
                'userId'     => $source['userId'] ?? '',
                'data'       => $postback['data'] ?? '',
                'params'     => $postback['params'] ?? [],
                'replyToken' => $event['replyToken'] ?? '',
                'source'     => PayloadSchemas::extractLineSource($source),
                'timestamp'  => (int) ($event['timestamp'] ?? 0),
            ]);
        });
    }
}
