<?php

namespace WSms\Integration\WpSms;

use WSms\Event\Contracts\EventDispatcherInterface;
use WSms\Flow\Action\HttpRequestAction;
use WSms\Flow\Action\SendMessageAction;
use WSms\Integration\Contracts\IntegrationInterface;
use WSms\Integration\WpSms\Triggers\ContactOptedOutTrigger;
use WSms\Integration\WpSms\Triggers\InboundSmsReceivedTrigger;
use WSms\Messaging\Gateway\GatewayRegistry;
use WSms\Messaging\MessageDispatcher;

defined('ABSPATH') || exit;

class WpSmsIntegration implements IntegrationInterface
{
    public function __construct(
        private readonly MessageDispatcher $messageDispatcher,
        private readonly GatewayRegistry $gatewayRegistry,
        private readonly EventDispatcherInterface $eventDispatcher,
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

    public function getDescription(): string
    {
        return 'Send messages and make HTTP requests from your automation flows.';
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
        return [
            new ContactOptedOutTrigger($this->eventDispatcher),
            new InboundSmsReceivedTrigger($this->eventDispatcher),
        ];
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

    public function connect(array $credentials): array
    {
        return $credentials;
    }

    public function disconnect(): void
    {
    }

    public function isConnected(): bool
    {
        return true;
    }
}
