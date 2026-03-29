<?php

namespace WSms\Integration\Line\Triggers;

use WSms\Flow\Contracts\AbstractTrigger;

defined('ABSPATH') || exit;

class AccountLinkTrigger extends AbstractTrigger
{
    public function getId(): string
    {
        return 'line.account_link';
    }

    public function getName(): string
    {
        return __('Account Linked', 'wp-sms');
    }

    public function getDescription(): string
    {
        return __('Fires when a user completes LINE account linking', 'wp-sms');
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
            'result' => [
                'type'    => 'string',
                'label'   => __('Result', 'wp-sms'),
                'example' => 'ok',
                'enum'    => ['ok', 'failed'],
            ],
            'nonce' => [
                'type'    => 'string',
                'label'   => __('Nonce', 'wp-sms'),
                'example' => 'abc123',
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
            'result' => [
                'type'        => 'string',
                'label'       => __('Result', 'wp-sms'),
                'description' => __('Only trigger for this result', 'wp-sms'),
                'enum'        => ['ok', 'failed'],
            ],
        ];
    }

    public function subscribe(callable $callback): void
    {
        add_action('wsms_line_account_link', function (array $event) use ($callback) {
            $source = $event['source'] ?? [];
            $link = $event['link'] ?? [];

            $callback([
                'userId'     => $source['userId'] ?? '',
                'result'     => $link['result'] ?? '',
                'nonce'      => $link['nonce'] ?? '',
                'replyToken' => $event['replyToken'] ?? '',
                'timestamp'  => (int) ($event['timestamp'] ?? 0),
            ]);
        });
    }
}
