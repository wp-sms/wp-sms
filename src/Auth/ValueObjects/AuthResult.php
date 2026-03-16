<?php

namespace WSms\Auth\ValueObjects;

readonly class AuthResult
{
    public function __construct(
        public bool $success,
        public string $status,
        public ?string $sessionToken = null,
        public ?int $userId = null,
        public ?array $user = null,
        public string $message = '',
        public string $error = '',
        public array $meta = [],
    ) {
    }

    public static function authenticated(int $userId, array $user, array $meta = []): self
    {
        return new self(
            success: true,
            status: 'authenticated',
            userId: $userId,
            user: $user,
            message: __('Login successful.', 'wp-sms'),
            meta: $meta,
        );
    }

    public static function mfaEnrollmentRequired(array $availableChannels): self
    {
        return new self(
            success: true,
            status: 'mfa_enrollment_required',
            message: __('MFA enrollment is required to continue.', 'wp-sms'),
            meta: ['available_channels' => $availableChannels],
        );
    }

    public static function mfaRequired(string $sessionToken, array $availableFactors): self
    {
        return new self(
            success: true,
            status: 'mfa_required',
            sessionToken: $sessionToken,
            message: __('MFA verification required.', 'wp-sms'),
            meta: ['available_factors' => $availableFactors],
        );
    }

    public static function challengeSent(string $sessionToken, array $meta = []): self
    {
        return new self(
            success: true,
            status: 'challenge_sent',
            sessionToken: $sessionToken,
            message: __('Verification challenge sent.', 'wp-sms'),
            meta: $meta,
        );
    }

    public static function verificationRequired(string $sessionToken, array $pendingVerifications): self
    {
        return new self(
            success: true,
            status: 'verification_required',
            sessionToken: $sessionToken,
            message: __('Account verification required.', 'wp-sms'),
            meta: ['pending_verifications' => $pendingVerifications],
        );
    }

    public static function failed(string $error, string $message, array $meta = []): self
    {
        return new self(
            success: false,
            status: 'failed',
            error: $error,
            message: $message,
            meta: $meta,
        );
    }

    public static function rateLimited(int $retryAfter): self
    {
        return new self(
            success: false,
            status: 'rate_limited',
            error: 'rate_limited',
            message: __('Too many requests. Please try again later.', 'wp-sms'),
            meta: ['retry_after' => $retryAfter],
        );
    }

    public static function expired(): self
    {
        return new self(
            success: false,
            status: 'expired',
            error: 'session_expired',
            message: __('Your session has expired. Please start over.', 'wp-sms'),
        );
    }

    public static function invalidToken(): self
    {
        return new self(
            success: false,
            status: 'invalid_token',
            error: 'invalid_token',
            message: __('Invalid or expired token.', 'wp-sms'),
        );
    }

    public function toHttpStatus(): int
    {
        return match ($this->status) {
            'authenticated', 'mfa_required', 'mfa_enrollment_required', 'challenge_sent', 'verification_required' => 200,
            'rate_limited' => 429,
            'expired', 'invalid_token' => 401,
            'failed' => match ($this->error) {
                'invalid_credentials' => 401,
                default => 400,
            },
            default => 400,
        };
    }

    public function toArray(): array
    {
        $data = [
            'success' => $this->success,
            'status'  => $this->status,
        ];

        if ($this->sessionToken !== null) {
            $data['session_token'] = $this->sessionToken;
        }

        if ($this->user !== null) {
            $data['user'] = $this->user;
        }

        if ($this->message !== '') {
            $data['message'] = $this->message;
        }

        if ($this->error !== '') {
            $data['error'] = $this->error;
        }

        if (!empty($this->meta)) {
            $data['meta'] = $this->meta;
        }

        return $data;
    }
}
