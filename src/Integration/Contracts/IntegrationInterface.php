<?php

namespace WSms\Integration\Contracts;

defined('ABSPATH') || exit;

interface IntegrationInterface
{
    public function getId(): string;

    public function getName(): string;

    public function getDescription(): string;

    public function getCategory(): string;

    public function getIcon(): string;

    public function isAvailable(): bool;

    public function getAuthType(): string;

    public function getAuthSchema(): array;

    /** @return \WSms\Flow\Contracts\TriggerInterface[] */
    public function getTriggers(): array;

    /** @return \WSms\Flow\Contracts\ActionInterface[] */
    public function getActions(): array;

    public function boot(): void;

    /** Validate credentials and set up external resources (webhooks, etc). Throw \RuntimeException on failure. */
    public function connect(array $credentials): array;

    /** Clean up on disconnect (delete webhooks, revoke tokens, etc). */
    public function disconnect(): void;

    /** Whether the integration has valid credentials / is connected. */
    public function isConnected(): bool;
}
