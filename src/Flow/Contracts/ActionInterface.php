<?php

namespace WSms\Flow\Contracts;

defined('ABSPATH') || exit;

interface ActionInterface
{
    public function getId(): string;

    public function getName(): string;

    public function getGroup(): string;

    public function getConfigSchema(): array;

    public function getConfigOptions(string $fieldKey): array;

    public function execute(array $payload, array $config): ActionResult;
}
