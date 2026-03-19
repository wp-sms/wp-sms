<?php

namespace WSms\Mfa\ValueObjects;

class ChallengeResult
{
    public function __construct(
        public readonly bool $success,
        public readonly string $message = '',
        public readonly array $meta = [],
    ) {
    }
}
