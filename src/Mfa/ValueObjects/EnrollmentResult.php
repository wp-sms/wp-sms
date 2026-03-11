<?php

namespace WSms\Mfa\ValueObjects;

readonly class EnrollmentResult
{
    public function __construct(
        public bool $success,
        public string $message = '',
        public array $data = [],
    ) {
    }
}
