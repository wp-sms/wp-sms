<?php

namespace WP_SMS\Services\OTP\Security;

class RateLimiter
{
    protected int $defaultTtl = 300;
    protected int $defaultLimit = 5;

    public function isAllowed(string $key, int $maxAttempts = 15, int $ttl = 300): bool
    {
        $max = $maxAttempts ?? $this->defaultLimit;
        $ttl = $ttl ?? $this->defaultTtl;

        $attempts = (int) get_transient($this->formatKey($key));
        return $attempts < $max;
    }

    public function increment(string $key, int $ttl = 300): void
    {
        $ttl = $ttl ?? $this->defaultTtl;
        $key = $this->formatKey($key);
        $attempts = (int) get_transient($key);
        set_transient($key, $attempts + 1, $ttl);
    }

    public function reset(string $key): void
    {
        delete_transient($this->formatKey($key));
    }

    public function getAttempts(string $key): int
    {
        return (int) get_transient($this->formatKey($key));
    }

    protected function formatKey(string $key): string
    {
        return 'auth_rate_' . md5($key);
    }
}
