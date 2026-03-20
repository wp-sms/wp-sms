<?php

namespace WSms\Integration\WpSms\Triggers;

use WSms\Flow\Contracts\AbstractTrigger;

defined('ABSPATH') || exit;

class ManualTrigger extends AbstractTrigger
{
    public function getId(): string
    {
        return 'manual.button';
    }

    public function getName(): string
    {
        return __('Manual Trigger', 'wp-sms');
    }

    public function getDescription(): string
    {
        return __('Run this flow manually via the "Run Now" button or REST API', 'wp-sms');
    }

    public function getGroup(): string
    {
        return 'WSMS';
    }

    public function getPayloadSchema(): array
    {
        return [
            'triggered_by' => [
                'type'        => 'integer',
                'label'       => __('Triggered By', 'wp-sms'),
                'description' => __('The WordPress user ID who triggered the flow', 'wp-sms'),
                'example'     => 1,
            ],
        ];
    }

    public function subscribe(callable $callback): void
    {
        // Manual triggers don't subscribe to WP hooks.
        // They are fired via FlowRunner::onTriggerFired() from the REST endpoint.
    }
}
