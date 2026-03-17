<?php

namespace WSms\Flow\Contracts;

defined('ABSPATH') || exit;

interface TriggerInterface
{
    public function getId(): string;

    public function getName(): string;

    public function getGroup(): string;

    public function getPayloadSchema(): array;

    public function getFilterSchema(): array;

    public function getFilterOptions(string $fieldKey): array;

    public function subscribe(callable $callback): void;

    public function getSamplePayload(): ?array;
}
