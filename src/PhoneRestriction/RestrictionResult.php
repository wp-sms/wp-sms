<?php

namespace WSms\PhoneRestriction;

defined('ABSPATH') || exit;

class RestrictionResult
{
    private function __construct(
        public readonly bool $allowed,
        public readonly ?string $reason,
        public readonly ?string $message,
        public readonly ?string $country,
        public readonly ?string $numberType,
    ) {
    }

    public static function allowed(?string $country = null, ?string $numberType = null): self
    {
        return new self(true, null, null, $country, $numberType);
    }

    public static function blocked(string $reason, string $message, ?string $country = null, ?string $numberType = null): self
    {
        return new self(false, $reason, $message, $country, $numberType);
    }
}
