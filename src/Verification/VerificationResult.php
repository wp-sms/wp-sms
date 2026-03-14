<?php

namespace WSms\Verification;

defined('ABSPATH') || exit;

class VerificationResult
{
    private function __construct(
        public readonly bool $success,
        public readonly string $message,
        public readonly ?string $error = null,
        public readonly ?string $sessionToken = null,
        public readonly ?string $maskedIdentifier = null,
        public readonly ?int $expiresIn = null,
        public readonly ?int $retryAfter = null,
    ) {
    }

    public static function codeSent(string $token, string $masked, int $expiresIn): self
    {
        return new self(
            success: true,
            message: 'Verification code sent.',
            sessionToken: $token,
            maskedIdentifier: $masked,
            expiresIn: $expiresIn,
        );
    }

    public static function verified(string $token): self
    {
        return new self(
            success: true,
            message: 'Verification successful.',
            sessionToken: $token,
        );
    }

    public static function failed(string $error, string $message): self
    {
        return new self(
            success: false,
            message: $message,
            error: $error,
        );
    }

    public static function cooldown(int $retryAfter): self
    {
        return new self(
            success: false,
            message: 'Please wait before requesting a new code.',
            error: 'cooldown',
            retryAfter: $retryAfter,
        );
    }

    public static function rateLimited(int $retryAfter): self
    {
        return new self(
            success: false,
            message: 'Too many requests. Please try again later.',
            error: 'rate_limited',
            retryAfter: $retryAfter,
        );
    }

    public function toArray(): array
    {
        $data = [
            'success' => $this->success,
            'message' => $this->message,
        ];

        if ($this->error !== null) {
            $data['error'] = $this->error;
        }

        if ($this->sessionToken !== null) {
            $data['session_token'] = $this->sessionToken;
        }

        if ($this->maskedIdentifier !== null) {
            $data['masked_identifier'] = $this->maskedIdentifier;
        }

        if ($this->expiresIn !== null) {
            $data['expires_in'] = $this->expiresIn;
        }

        if ($this->retryAfter !== null) {
            $data['retry_after'] = $this->retryAfter;
        }

        return $data;
    }
}
