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

    /**
     * Config schema with nested structure: { shared: {...}, channels: { sms: {...} } }
     *
     * @return array{shared?: array<string, array>, channels?: array<string, array<string, array>>}
     */
    public function getConfigSchema(): array;

    public function validateConfig(array $config): bool;

    public function isConfigured(): bool;

    /** Check if this gateway is configured for a specific channel. */
    public function isConfiguredForChannel(string $channel): bool;

    /**
     * Provider metadata: description, website, icon URL, supported regions.
     *
     * @return array{description?: string, website?: string, icon?: string, regions?: string[]}
     */
    public function getMetadata(): array;

    /**
     * Feature flags: bulk_send, mms, flash_sms, delivery_receipt, incoming, unicode, scheduled_send.
     *
     * @return array<string, bool>
     */
    public function getFeatures(): array;

    /** Account credit/balance, or null if not supported. */
    public function getCredit(): ?string;
}
