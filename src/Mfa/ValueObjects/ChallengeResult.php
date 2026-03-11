<?php

namespace WSms\Mfa\ValueObjects;

readonly class ChallengeResult
{
    public function __construct(
        public bool $success,
        public string $message = '',
        public array $meta = [],
    ) {
    }
}
