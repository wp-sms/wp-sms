<?php

namespace WSms\Flow\Contracts;

defined('ABSPATH') || exit;

class ActionResult
{
    private function __construct(
        public readonly bool $success,
        public readonly array $output,
        public readonly ?string $error = null,
    ) {
    }

    public static function success(array $output = []): self
    {
        return new self(true, $output);
    }

    public static function failure(string $error, array $output = []): self
    {
        return new self(false, $output, $error);
    }
}
