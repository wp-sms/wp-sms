<?php

namespace WSms\Integration\Contracts;

defined('ABSPATH') || exit;

class SyncResult
{
    private function __construct(
        public readonly bool $success,
        public readonly string $status,
        public readonly ?string $providerContactId = null,
        public readonly ?string $error = null,
        public readonly ?string $reason = null,
        public readonly bool $retryable = false,
        public readonly array $details = [],
        public readonly int $pushed = 0,
        public readonly int $failed = 0,
        public readonly int $skipped = 0,
        public readonly array $errors = [],
    ) {
    }

    public static function success(?string $providerContactId = null, array $details = []): self
    {
        return new self(true, 'success', $providerContactId, details: $details);
    }

    public static function failure(string $error, array $details = [], bool $retryable = false): self
    {
        return new self(false, 'failure', error: $error, retryable: $retryable, details: $details);
    }

    public static function skipped(string $reason): self
    {
        return new self(true, 'skipped', reason: $reason);
    }

    public static function batch(int $pushed, int $failed, int $skipped, array $errors = []): self
    {
        return new self(
            success: $failed === 0,
            status: $failed === 0 ? 'success' : 'partial',
            pushed: $pushed,
            failed: $failed,
            skipped: $skipped,
            errors: $errors,
        );
    }
}
