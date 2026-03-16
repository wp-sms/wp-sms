<?php

namespace WSms\Mfa\Channels;

use WSms\Audit\AuditLogger;
use WSms\Enums\ChannelStatus;
use WSms\Enums\EventType;
use WSms\Enums\VerificationType;
use WSms\Messaging\MessageDispatcher;
use WSms\Messaging\Message\EmailMessage;
use WSms\Mfa\Contracts\ChannelInterface;
use WSms\Verification\OtpGenerator;
use WSms\Mfa\Support\EmailMasker;
use WSms\Mfa\ValueObjects\ChallengeResult;
use WSms\Mfa\ValueObjects\EnrollmentResult;
use WSms\Verification\OtpService;
use WSms\Verification\VerificationRepository;

defined('ABSPATH') || exit;

/**
 * Internal magic link implementation — used as a delegate by EmailChannel and PhoneChannel.
 * Not registered as a standalone channel in MfaManager.
 */
class MagicLinkChannel implements ChannelInterface
{
    use HasUserFactor;

    public function __construct(
        private OtpGenerator $otpGenerator,
        private AuditLogger $auditLogger,
        private MessageDispatcher $messageDispatcher,
        private VerificationRepository $verificationRepo,
        private ?OtpService $otpService = null,
    ) {
    }

    public function getId(): string
    {
        return 'magic_link';
    }

    public function getName(): string
    {
        return 'Magic Link';
    }

    public function supportsPrimaryAuth(): bool
    {
        return true;
    }

    public function supportsMfa(): bool
    {
        return false;
    }

    protected function getConfigPrefix(): string
    {
        return 'magic_link';
    }

    /** {@inheritDoc} */
    public function enroll(int $userId, array $data): EnrollmentResult
    {
        $user = get_userdata($userId);

        if (!$user || empty($user->user_email)) {
            return new EnrollmentResult(false, __('No email address found for user.', 'wp-sms'));
        }

        $existing = $this->getFactor($userId);

        if ($existing && $existing->status === ChannelStatus::Active) {
            return new EnrollmentResult(false, __('Already enrolled in Magic Link.', 'wp-sms'));
        }

        if ($existing) {
            $this->updateFactor($existing->id, [
                'status' => ChannelStatus::Active->value,
                'meta'   => wp_json_encode(['email' => $user->user_email]),
            ]);
        } else {
            $this->createFactor($userId, ChannelStatus::Active, ['email' => $user->user_email]);
        }

        $this->auditLogger->log(EventType::MfaEnrolled, 'success', $userId, [
            'channel' => $this->getId(),
        ]);

        return new EnrollmentResult(true, __('Magic Link enrollment complete.', 'wp-sms'), [
            'masked_email' => EmailMasker::mask($user->user_email),
        ]);
    }

    /** {@inheritDoc} */
    public function sendChallenge(int $userId, array $context = []): ChallengeResult
    {
        if (!$this->isEnrolled($userId)) {
            return new ChallengeResult(false, __('User is not enrolled in Magic Link.', 'wp-sms'));
        }

        $factor = $this->getFactor($userId);
        $email = $factor->meta['email'] ?? null;

        if ($email === null) {
            return new ChallengeResult(false, __('No email address found for user.', 'wp-sms'));
        }

        // Check cooldown.
        $cooldown = (int) ($this->getConfigValue('cooldown', 60) ?: 60);

        if ($this->verificationRepo->hasPendingWithinCooldown([
            'user_id'    => $userId,
            'channel_id' => $this->getId(),
        ], $cooldown)) {
            return new ChallengeResult(false, __('Please wait before requesting a new magic link.', 'wp-sms'));
        }

        // Invalidate existing pending.
        $this->verificationRepo->invalidatePending([
            'user_id'    => $userId,
            'channel_id' => $this->getId(),
        ]);

        $expiry = (int) ($this->getConfigValue('expiry', 600) ?: 600);
        $token = $this->otpService->createToken($userId, VerificationType::MagicLink->value, $email, $expiry, $this->getId());
        do_action('wsms_magic_link_generated', $userId, $token);

        $url = get_site_url() . '/account/verify-magic-link?token=' . $token;

        $siteName = get_bloginfo('name');
        $expiryMinutes = (int) ($expiry / 60);

        $subject = sprintf(__('[%s] Your login link', 'wp-sms'), $siteName);

        $body = '<p>' . __('Click the link below to log in:', 'wp-sms') . '</p>'
            . '<p><a href="' . esc_url($url) . '">'
            . sprintf(__('Log in to %s', 'wp-sms'), esc_html($siteName))
            . '</a></p>'
            . '<p>' . sprintf(__('This link expires in %d minutes.', 'wp-sms'), $expiryMinutes) . '</p>'
            . '<p>' . __('If you did not request this, please ignore this email.', 'wp-sms') . '</p>';

        $headers = ['Content-Type: text/html; charset=UTF-8'];
        $result = $this->messageDispatcher->sendImmediate(
            new EmailMessage($email, $body, $subject, $headers)
        );

        if (!$result->success) {
            $this->auditLogger->log(EventType::MagicLinkSent, 'failure', $userId, [
                'channel' => $this->getId(),
            ]);

            return new ChallengeResult(false, __('Failed to send magic link email.', 'wp-sms'));
        }

        $this->auditLogger->log(EventType::MagicLinkSent, 'success', $userId, [
            'channel' => $this->getId(),
        ]);

        return new ChallengeResult(true, __('Magic link sent to your email.', 'wp-sms'), [
            'masked_email' => EmailMasker::mask($email),
            'expires_in'   => $expiry,
        ]);
    }

    /**
     * Generate a magic link token and store it, returning the full URL.
     * Used by parent channels (EmailChannel, PhoneChannel) for combined messages.
     */
    public function generateToken(int $userId, string $identifier, int $expiry): string
    {
        // Invalidate existing pending magic links for this user.
        $this->verificationRepo->invalidatePending([
            'user_id'    => $userId,
            'channel_id' => $this->getId(),
            'type'       => VerificationType::MagicLink->value,
        ]);

        $token = $this->otpService->createToken($userId, VerificationType::MagicLink->value, $identifier, $expiry, $this->getId());
        do_action('wsms_magic_link_generated', $userId, $token);

        return get_site_url() . '/account/verify-magic-link?token=' . $token;
    }

    /**
     * Verify a magic link token and resolve the user ID.
     */
    public function verifyTokenAndResolveUser(string $token): ?int
    {
        $hashedToken = $this->otpGenerator->hash($token);

        $verification = $this->verificationRepo->findByCodeAndChannel(
            $hashedToken,
            VerificationType::MagicLink->value,
            $this->getId(),
        );

        if (!$verification) {
            return null;
        }

        if (strtotime($verification->expires_at) < time()) {
            return null;
        }

        // Mark as used.
        $this->verificationRepo->markUsed((int) $verification->id);

        $userId = (int) $verification->user_id;

        $this->auditLogger->log(EventType::MagicLinkVerified, 'success', $userId, [
            'channel' => $this->getId(),
        ]);

        return $userId;
    }

    /** {@inheritDoc} */
    public function verify(int $userId, string $code, array $context = []): bool
    {
        // Token is consumed by verifyTokenAndResolveUser() regardless of userId match.
        // This is intentional: prevents cross-user token reuse if a token leaks.
        return $this->verifyTokenAndResolveUser($code) === $userId;
    }

    public function supportsAutoEnrollment(): bool
    {
        return false;
    }

    public function isAvailableForUser(int $userId): bool
    {
        return $this->isEnrolled($userId);
    }

    /** {@inheritDoc} */
    public function getEnrollmentInfo(int $userId): array
    {
        $factor = $this->getFactor($userId);

        if ($factor === null) {
            return ['enrolled' => false];
        }

        $email = $factor->meta['email'] ?? null;

        return [
            'enrolled'   => $factor->status === ChannelStatus::Active,
            'status'     => $factor->status->value,
            'channel'    => $this->getId(),
            'identifier' => $email ? EmailMasker::mask($email) : null,
            'created_at' => $factor->createdAt,
        ];
    }
}
