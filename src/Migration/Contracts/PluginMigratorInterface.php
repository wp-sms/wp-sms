<?php

namespace WSms\Migration\Contracts;

use WSms\Migration\BatchResult;
use WSms\Migration\DetectionResult;
use WSms\Migration\PreviewResult;
use WSms\Migration\RollbackResult;

defined('ABSPATH') || exit;

interface PluginMigratorInterface
{
    public function getId(): string;

    public function getName(): string;

    public function detect(): DetectionResult;

    /** @return MigrationStepInterface[] */
    public function getSteps(): array;

    public function preview(string $stepId, array $options = []): PreviewResult;

    public function migrateBatch(string $stepId, array $options, ?string $cursor = null): BatchResult;

    public function rollback(string $stepId): RollbackResult;
}
