<?php

namespace WSms\Mfa\Contracts;

use WSms\Mfa\ValueObjects\EnrollmentResult;
use WSms\Mfa\ValueObjects\ChallengeResult;

interface ChannelInterface
{
    /**
     * Unique channel identifier (e.g., 'phone', 'email', 'backup_codes').
     */
    public function getId(): string;

    /**
     * Human-readable channel name.
     */
    public function getName(): string;

    /**
     * Whether this channel can be used as a primary login method.
     */
    public function supportsPrimaryAuth(): bool;

    /**
     * Whether this channel can be used as an MFA second factor.
     */
    public function supportsMfa(): bool;

    /**
     * Enroll a user in this channel.
     *
     * @param int   $userId User ID.
     * @param array $data   Channel-specific enrollment data (e.g., phone number).
     */
    public function enroll(int $userId, array $data): EnrollmentResult;

    /**
     * Send a challenge to the user (OTP code, magic link email, etc.).
     *
     * @param int   $userId  User ID.
     * @param array $context Additional context (e.g., IP address).
     */
    public function sendChallenge(int $userId, array $context = []): ChallengeResult;

    /**
     * Verify a user's response to a challenge.
     *
     * @param int    $userId  User ID.
     * @param string $code    The code or token submitted by the user.
     * @param array  $context Additional context.
     */
    public function verify(int $userId, string $code, array $context = []): bool;

    /**
     * Remove a user's enrollment in this channel.
     */
    public function unenroll(int $userId): bool;

    /**
     * Check if a user is enrolled in this channel.
     */
    public function isEnrolled(int $userId): bool;

    /**
     * Get enrollment display data for the frontend (masked phone, email, etc.).
     */
    public function getEnrollmentInfo(int $userId): array;
}
