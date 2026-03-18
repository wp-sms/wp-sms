<?php

namespace WSms\Integration\WpSms;

use WSms\Flow\Action\HttpRequestAction;
use WSms\Flow\Action\SendMessageAction;
use WSms\Integration\Contracts\IntegrationInterface;
use WSms\Messaging\Gateway\GatewayRegistry;
use WSms\Messaging\MessageDispatcher;

defined('ABSPATH') || exit;

class WpSmsIntegration implements IntegrationInterface
{
    public function __construct(
        private readonly MessageDispatcher $messageDispatcher,
        private readonly GatewayRegistry $gatewayRegistry,
    ) {
    }

    public function getId(): string
    {
        return 'wp_sms';
    }

    public function getName(): string
    {
        return 'WSMS';
    }

    public function getCategory(): string
    {
        return 'messaging';
    }

    public function getIcon(): string
    {
        return 'message-square';
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function getAuthType(): string
    {
        return 'none';
    }

    public function getAuthSchema(): array
    {
        return [];
    }

    public function getTriggers(): array
    {
        return [];
    }

    public function getActions(): array
    {
        return [
            new SendMessageAction($this->messageDispatcher, $this->gatewayRegistry),
            new HttpRequestAction(),
        ];
    }

    public function boot(): void
    {
    }
}
