<?php

namespace WSms\Auth\ValueObjects;

use WSms\Enums\AuthErrorCode;

class AuthResult
{
    public function __construct(
        public readonly bool $success,
        public readonly string $status,
        public readonly ?string $sessionToken = null,
        public readonly ?int $userId = null,
        public readonly ?array $user = null,
        public readonly string $message = '',
        public readonly string $error = '',
        public readonly array $meta = [],
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

    public static function failed(AuthErrorCode|string $error, string $message, array $meta = []): self
    {
        return new self(
            success: false,
            status: 'failed',
            error: $error instanceof AuthErrorCode ? $error->value : $error,
            message: $message,
            meta: $meta,
        );
    }

    /**
     * Wrap a challenge delivery failure with a generic user-facing message.
     *
     * The raw provider reason is stored in meta for logging/debugging.
     * The REST layer strips debug fields before sending to the client.
     */
    public static function challengeFailed(string $internalReason): self
    {
        return new self(
            success: false,
            status: 'failed',
            error: AuthErrorCode::ChallengeFailed->value,
            message: __('Could not send verification code. Please try again later.', 'wp-sms'),
            meta: ['debug_reason' => $internalReason],
        );
    }

    public static function rateLimited(int $retryAfter): self
    {
        return new self(
            success: false,
            status: 'rate_limited',
            error: AuthErrorCode::RateLimited->value,
            message: __('Too many requests. Please try again later.', 'wp-sms'),
            meta: ['retry_after' => $retryAfter],
        );
    }

    public static function expired(): self
    {
        return new self(
            success: false,
            status: 'expired',
            error: AuthErrorCode::SessionExpired->value,
            message: __('Your session has expired. Please start over.', 'wp-sms'),
        );
    }

    public static function invalidToken(): self
    {
        return new self(
            success: false,
            status: 'invalid_token',
            error: AuthErrorCode::InvalidToken->value,
            message: __('Invalid or expired token.', 'wp-sms'),
        );
    }

    private function errorEnum(): ?AuthErrorCode
    {
        return $this->error !== '' ? AuthErrorCode::tryFrom($this->error) : null;
    }

    public function toHttpStatus(): int
    {
        if (in_array($this->status, ['authenticated', 'mfa_required', 'mfa_enrollment_required', 'challenge_sent', 'verification_required'], true)) {
            return 200;
        }

        return $this->errorEnum()?->httpStatus() ?? 400;
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

            $action = $this->errorEnum()?->recoveryAction();
            if ($action !== null) {
                $data['recovery_action'] = $action;
            }
        }

        if (!empty($this->meta)) {
            $data['meta'] = $this->meta;
        }

        return $data;
    }
}
