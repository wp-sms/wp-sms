<?php

namespace WSms\Flow\Contracts;

defined('ABSPATH') || exit;

abstract class AbstractAction implements ActionInterface
{
    abstract public function getId(): string;

    abstract public function getName(): string;

    abstract public function getGroup(): string;

    abstract public function getConfigSchema(): array;

    abstract public function execute(array $payload, array $config): ActionResult;

    public function getConfigOptions(string $fieldKey): array
    {
        return [];
    }
}
