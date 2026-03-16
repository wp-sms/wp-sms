<?php

namespace WSms\Messaging\Contracts;

defined('ABSPATH') || exit;

class DeliveryResult
{
    private function __construct(
        public readonly bool $success,
        public readonly string $status,
        public readonly ?string $providerId = null,
        public readonly ?string $error = null,
        public readonly ?float $cost = null,
        public readonly array $meta = [],
    ) {
    }

    public static function sent(?string $providerId = null, ?float $cost = null, array $meta = []): self
    {
        return new self(true, 'sent', $providerId, null, $cost, $meta);
    }

    public static function failed(string $error, array $meta = []): self
    {
        return new self(false, 'failed', null, $error, null, $meta);
    }

    public static function queued(?string $providerId = null): self
    {
        return new self(true, 'queued', $providerId);
    }
}
