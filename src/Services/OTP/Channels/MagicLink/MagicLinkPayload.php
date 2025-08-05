<?php

namespace WP_SMS\Services\Authentication\Channels\MagicLink;

class MagicLinkPayload
{
    public function __construct(
        public readonly string $flowId,
        public readonly int $userId,
        public readonly string $tokenHash,
        public readonly int $expiresAt,
        public readonly ?int $usedAt = null,
    ) {}

    public function isExpired(): bool
    {
        return time() > $this->expiresAt;
    }

    public function isUsed(): bool
    {
        return $this->usedAt !== null;
    }

    public function matchesToken(string $inputToken): bool
    {
        return hash_equals($this->tokenHash, hash('sha256', $inputToken));
    }
}
