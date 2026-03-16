<?php

namespace WSms\Mfa\Channels;

use WSms\Audit\AuditLogger;
use WSms\Enums\EventType;
use WSms\Enums\VerificationType;
use WSms\Messaging\MessageDispatcher;
use WSms\Mfa\Contracts\ChannelInterface;
use WSms\Verification\OtpGenerator;
use WSms\Mfa\ValueObjects\ChallengeResult;
use WSms\Mfa\ValueObjects\EnrollmentResult;
use WSms\Verification\OtpService;
use WSms\Verification\VerificationRepository;

defined('ABSPATH') || exit;

abstract class AbstractOtpChannel implements ChannelInterface
{
    use HasUserFactor;

    public function __construct(
        protected OtpGenerator $otpGenerator,
        protected AuditLogger $auditLogger,
        protected MessageDispatcher $messageDispatcher,
        protected VerificationRepository $verificationRepo,
        protected ?OtpService $otpService = null,
    ) {
    }

    abstract public function getId(): string;

    abstract public function getName(): string;

    abstract public function supportsPrimaryAuth(): bool;

    abstract public function supportsMfa(): bool;

    /**
     * Deliver the OTP to the user via the channel-specific transport.
     */
    abstract protected function deliver(int $userId, string $code, string $identifier): bool;

    /**
     * Get the destination identifier (phone number or email) for the user.
     */
    abstract protected function getIdentifier(int $userId): ?string;

    /**
     * Mask the identifier for display.
     */
    abstract protected function maskIdentifier(string $identifier): string;

    /**
     * Validate common prerequisites before sending a challenge.
     * Returns a success ChallengeResult with 'identifier' in meta, or a failure ChallengeResult.
     */
    protected function validateChallengePrerequisites(int $userId): ChallengeResult
    {
        if (!$this->isEnrolled($userId)) {
            return new ChallengeResult(false, __('User is not enrolled in this channel.', 'wp-sms'));
        }

        $identifier = $this->getIdentifier($userId);

        if ($identifier === null) {
            return new ChallengeResult(false, __('No identifier found for user.', 'wp-sms'));
        }

        $cooldown = (int) $this->getConfigValue('cooldown', 60);

        if ($this->hasCooldownActive($userId, $cooldown)) {
            return new ChallengeResult(false, __('Please wait before requesting a new code.', 'wp-sms'));
        }

        return new ChallengeResult(true, '', ['identifier' => $identifier]);
    }

    /** {@inheritDoc} */
    public function sendChallenge(int $userId, array $context = []): ChallengeResult
    {
        $prereq = $this->validateChallengePrerequisites($userId);

        if (!$prereq->success) {
            return $prereq;
        }

        $identifier = $prereq->meta['identifier'];
        $expiry = (int) $this->getConfigValue('expiry', 300);

        $delivered = $this->createAndDeliverOtp($userId, $identifier, $expiry);

        if (!$delivered) {
            $this->auditLogger->log(EventType::OtpSent, 'failure', $userId, [
                'channel' => $this->getId(),
            ]);

            return new ChallengeResult(false, __('Failed to deliver the verification code.', 'wp-sms'));
        }

        $this->auditLogger->log(EventType::OtpSent, 'success', $userId, [
            'channel' => $this->getId(),
        ]);

        return new ChallengeResult(true, __('Verification code sent.', 'wp-sms'), [
            'masked_identifier' => $this->maskIdentifier($identifier),
            'expires_in'        => $expiry,
        ]);
    }

    /** {@inheritDoc} */
    public function verify(int $userId, string $code, array $context = []): bool
    {
        $code = preg_replace('/\s+/', '', $code);

        $verification = $this->verificationRepo->findLatestPending([
            'user_id'    => $userId,
            'channel_id' => $this->getId(),
            'type'       => VerificationType::Otp->value,
        ]);

        if (!$verification) {
            return false;
        }

        if (strtotime($verification->expires_at) < time()) {
            $this->verificationRepo->delete((int) $verification->id);

            $this->auditLogger->log(EventType::OtpExpired, 'failure', $userId, [
                'channel' => $this->getId(),
            ]);

            return false;
        }

        // Atomic attempt increment — fails if max attempts reached.
        if (!$this->verificationRepo->incrementAttempts((int) $verification->id)) {
            $this->auditLogger->log(EventType::OtpFailed, 'failure', $userId, [
                'channel' => $this->getId(),
                'reason'  => 'max_attempts_exceeded',
            ]);

            return false;
        }

        if (!$this->otpGenerator->verify($code, $verification->code)) {
            $this->auditLogger->log(EventType::OtpFailed, 'failure', $userId, [
                'channel' => $this->getId(),
            ]);

            return false;
        }

        // Mark as used.
        $this->verificationRepo->markUsed((int) $verification->id);

        $this->auditLogger->log(EventType::OtpVerified, 'success', $userId, [
            'channel' => $this->getId(),
        ]);

        return true;
    }

    /** {@inheritDoc} */
    public function getEnrollmentInfo(int $userId): array
    {
        $factor = $this->getFactor($userId);

        if ($factor === null) {
            return ['enrolled' => false];
        }

        $identifier = $this->getIdentifier($userId);

        return [
            'enrolled'   => $factor->status === \WSms\Enums\ChannelStatus::Active,
            'status'     => $factor->status->value,
            'channel'    => $this->getId(),
            'identifier' => $identifier ? $this->maskIdentifier($identifier) : null,
            'created_at' => $factor->createdAt,
        ];
    }

    /**
     * Generate, store, and deliver an OTP. Used by both sendChallenge() and
     * enrollment flows.
     */
    protected function createAndDeliverOtp(int $userId, string $identifier, int $expiry): bool
    {
        $code = $this->generateAndStoreOtp($userId, $identifier, $expiry);

        return $this->deliver($userId, $code, $identifier);
    }

    /**
     * Generate an OTP, store it in the verifications table, and return the
     * plain-text code. Does NOT deliver — callers handle delivery.
     */
    protected function generateAndStoreOtp(int $userId, string $identifier, int $expiry): string
    {
        // Invalidate existing pending verifications.
        $this->verificationRepo->invalidatePending([
            'user_id'    => $userId,
            'channel_id' => $this->getId(),
        ]);

        return $this->otpService->createOtp(
            $userId,
            VerificationType::Otp->value,
            $identifier,
            (int) $this->getConfigValue('code_length', 6),
            $expiry,
            (int) $this->getConfigValue('max_attempts', 3),
            $this->getId(),
        );
    }

    public function supportsAutoEnrollment(): bool
    {
        return false;
    }

    public function isAvailableForUser(int $userId): bool
    {
        return $this->getIdentifier($userId) !== null;
    }

    /**
     * Check if a cooldown is active for this user/channel.
     */
    protected function hasCooldownActive(int $userId, int $cooldown): bool
    {
        return $this->verificationRepo->hasPendingWithinCooldown([
            'user_id'    => $userId,
            'channel_id' => $this->getId(),
        ], $cooldown);
    }
}
