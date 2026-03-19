<?php

namespace WSms\Messaging\Contracts;

defined('ABSPATH') || exit;

class TestConnectionResult
{
    private function __construct(
        public readonly bool $success,
        public readonly string $message,
        public readonly array $details = [],
    ) {
    }

    public static function ok(string $message = 'Connection successful', array $details = []): self
    {
        return new self(true, $message, $details);
    }

    public static function error(string $message, array $details = []): self
    {
        return new self(false, $message, $details);
    }
}
