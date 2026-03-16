<?php

namespace WSms\Messaging\Contracts;

defined('ABSPATH') || exit;

interface GatewayInterface
{
    public function getId(): string;

    public function getName(): string;

    /** @return string[] Channels this gateway supports: 'sms', 'email', 'webhook', etc. */
    public function getSupportedChannels(): array;

    public function send(MessageInterface $message): DeliveryResult;

    public function getConfigSchema(): array;

    public function validateConfig(array $config): bool;

    public function isConfigured(): bool;
}
