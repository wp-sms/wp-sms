<?php

namespace WSms\Integration\Contracts;

defined('ABSPATH') || exit;

interface IntegrationInterface
{
    public function getId(): string;

    public function getName(): string;

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
}
