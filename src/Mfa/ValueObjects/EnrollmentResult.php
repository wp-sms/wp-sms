<?php

namespace WSms\Mfa\ValueObjects;

class EnrollmentResult
{
    public function __construct(
        public readonly bool $success,
        public readonly string $message = '',
        public readonly array $data = [],
    ) {
    }
}
