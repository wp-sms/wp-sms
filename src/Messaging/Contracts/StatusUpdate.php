<?php

namespace WSms\Messaging\Contracts;

defined('ABSPATH') || exit;

class StatusUpdate
{
    public function __construct(
        public readonly string $providerId,
        public readonly string $status,
        public readonly ?string $errorCode = null,
        public readonly ?string $errorMessage = null,
        public readonly bool $permanent = false,
        public readonly bool $complaint = false,
        public readonly bool $unsubscribe = false,
    ) {
    }

    public function getDisplayError(): ?string
    {
        return $this->errorMessage ?? $this->errorCode;
    }
}
