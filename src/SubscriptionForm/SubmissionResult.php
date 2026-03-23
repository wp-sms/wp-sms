<?php

namespace WSms\SubscriptionForm;

defined('ABSPATH') || exit;

class SubmissionResult
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

    public static function success(string $message = ''): self
    {
        return new self(
            success: true,
            message: $message ?: __('You have been subscribed successfully.', 'wp-sms'),
        );
    }

    public static function pendingVerification(string $sessionToken, string $maskedIdentifier, int $expiresIn): self
    {
        return new self(
            success: true,
            message: __('A verification code has been sent. Please check and enter the code.', 'wp-sms'),
            sessionToken: $sessionToken,
            maskedIdentifier: $maskedIdentifier,
            expiresIn: $expiresIn,
        );
    }

    public static function verified(): self
    {
        return new self(
            success: true,
            message: __('Verification successful. You are now subscribed.', 'wp-sms'),
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

    public static function rateLimited(int $retryAfter): self
    {
        return new self(
            success: false,
            message: __('Too many requests. Please try again later.', 'wp-sms'),
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
