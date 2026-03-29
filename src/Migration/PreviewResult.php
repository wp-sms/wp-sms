<?php

namespace WSms\Migration;

defined('ABSPATH') || exit;

final class PreviewResult
{
    public function __construct(
        public readonly int $totalItems,
        public readonly int $readyToMigrate,
        public readonly int $conflicts,
        public readonly int $skippable,
        public readonly int $invalidData,
        public readonly array $sampleConflicts = [],
        public readonly array $sampleErrors = [],
        public readonly array $warnings = [],
    ) {
    }

    public function toArray(): array
    {
        return [
            'total_items'      => $this->totalItems,
            'ready_to_migrate' => $this->readyToMigrate,
            'conflicts'        => $this->conflicts,
            'skippable'        => $this->skippable,
            'invalid_data'     => $this->invalidData,
            'sample_conflicts' => $this->sampleConflicts,
            'sample_errors'    => $this->sampleErrors,
            'warnings'         => $this->warnings,
        ];
    }
}
