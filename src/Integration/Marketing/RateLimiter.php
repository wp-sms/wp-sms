<?php

namespace WSms\Integration\Marketing;

defined('ABSPATH') || exit;

class RateLimiter
{
    private float $tokens;
    private float $lastRefill;
    private ?int $retryAfterUntil = null;

    public function __construct(
        private readonly int $tokensPerSecond,
        private readonly int $burstCapacity,
    ) {
        $this->tokens = (float) $burstCapacity;
        $this->lastRefill = microtime(true);
    }

    public function acquire(): void
    {
        // Honor provider Retry-After if active
        if ($this->retryAfterUntil !== null) {
            $wait = $this->retryAfterUntil - time();
            if ($wait > 0) {
                usleep($wait * 1_000_000);
            }
            $this->retryAfterUntil = null;
        }

        $this->refill();

        if ($this->tokens < 1) {
            $waitSeconds = (1 - $this->tokens) / $this->tokensPerSecond;
            usleep((int) ceil($waitSeconds * 1_000_000));
            $this->refill();
        }

        $this->tokens -= 1;
    }

    public function remaining(): int
    {
        $this->refill();

        return (int) floor($this->tokens);
    }

    public function handleRetryAfter(int $seconds): void
    {
        $this->retryAfterUntil = time() + $seconds;
        $this->tokens = 0;
    }

    private function refill(): void
    {
        $now = microtime(true);
        $elapsed = $now - $this->lastRefill;
        $this->tokens = min($this->burstCapacity, $this->tokens + $elapsed * $this->tokensPerSecond);
        $this->lastRefill = $now;
    }
}
