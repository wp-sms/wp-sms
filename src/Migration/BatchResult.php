<?php

namespace WSms\Migration;

defined('ABSPATH') || exit;

final class BatchResult
{
    public function __construct(
        public readonly int $processed,
        public readonly int $migrated,
        public readonly int $skipped,
        public readonly int $failed,
        public readonly int $conflicts,
        public readonly ?string $nextCursor,
        public readonly bool $done,
        public readonly array $errors = [],
    ) {
    }

    public function toArray(): array
    {
        return [
            'processed'   => $this->processed,
            'migrated'    => $this->migrated,
            'skipped'     => $this->skipped,
            'failed'      => $this->failed,
            'conflicts'   => $this->conflicts,
            'next_cursor' => $this->nextCursor,
            'done'        => $this->done,
            'errors'      => $this->errors,
        ];
    }
}
