<?php

namespace WSms\Flow\Contracts;

defined('ABSPATH') || exit;

interface ActionInterface
{
    public function getId(): string;

    public function getName(): string;

    public function getDescription(): string;

    public function getGroup(): string;

    public function getConfigSchema(): array;

    public function getConfigOptions(string $fieldKey, array $context = []): array;

    public function getPlaceholders(string $triggerType): array;

    public function getOutputSchema(): array;

    public function execute(array $payload, array $config): ActionResult;
}
