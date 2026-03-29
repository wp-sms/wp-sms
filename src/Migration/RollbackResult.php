<?php

namespace WSms\Migration;

defined('ABSPATH') || exit;

final class RollbackResult
{
    public function __construct(
        public readonly int $rolledBack,
        public readonly int $failed,
        public readonly array $errors = [],
    ) {
    }

    public function toArray(): array
    {
        return [
            'rolled_back' => $this->rolledBack,
            'failed'      => $this->failed,
            'errors'      => $this->errors,
        ];
    }
}
