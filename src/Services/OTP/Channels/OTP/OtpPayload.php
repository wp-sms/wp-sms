<?php

namespace WP_SMS\Services\OTP\Channels\Otp;

class OtpPayload
{
    public function __construct(
        public readonly string $code,
        public readonly int $expiresAt,
        public readonly int $maxAttempts,
        public int $attempts = 0,
    ) {}

    public function isExpired(): bool
    {
        return time() > $this->expiresAt;
    }

    public function canRetry(): bool
    {
        return $this->attempts < $this->maxAttempts;
    }

    public function incrementAttempts(): void
    {
        $this->attempts++;
    }
}
