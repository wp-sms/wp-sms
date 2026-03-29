<?php

namespace WSms\Migration\Contracts;

defined('ABSPATH') || exit;

interface MigrationStepInterface
{
    public function getId(): string;

    public function getLabel(): string;

    public function getDescription(): string;

    public function getPriority(): int;

    public function getTotalCount(): int;

    public function isApplicable(): bool;
}
