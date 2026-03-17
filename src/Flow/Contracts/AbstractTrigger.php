<?php

namespace WSms\Flow\Contracts;

defined('ABSPATH') || exit;

abstract class AbstractTrigger implements TriggerInterface
{
    abstract public function getId(): string;

    abstract public function getName(): string;

    abstract public function getGroup(): string;

    abstract public function getPayloadSchema(): array;

    abstract public function subscribe(callable $callback): void;

    public function getFilterSchema(): array
    {
        return [];
    }

    public function getFilterOptions(string $fieldKey): array
    {
        return [];
    }

    /**
     * Try to fetch a real sample payload (e.g. latest order, latest user).
     * Returns null if no real data is available; the controller falls back to schema examples.
     */
    public function getSamplePayload(): ?array
    {
        return null;
    }
}
