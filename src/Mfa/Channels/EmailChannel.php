<?php

namespace WSms\Mfa\Channels;

use WSms\Enums\ChannelStatus;
use WSms\Enums\EventType;
use WSms\Audit\AuditLogger;
use WSms\Auth\AccountManager;
use WSms\Messaging\MessageDispatcher;
use WSms\Messaging\Message\EmailMessage;
use WSms\Mfa\Contracts\SupportsTokenVerification;
use WSms\Verification\OtpGenerator;
use WSms\Mfa\Support\EmailMasker;
use WSms\Mfa\ValueObjects\ChallengeResult;
use WSms\Mfa\ValueObjects\EnrollmentResult;
use WSms\Support\IpResolver;
use WSms\Verification\OtpService;
use WSms\Verification\VerificationRepository;

defined('ABSPATH') || exit;

class EmailChannel extends AbstractOtpChannel implements SupportsTokenVerification
{
    private MagicLinkChannel $magicLink;

    public function __construct(
        OtpGenerator $otpGenerator,
        AuditLogger $auditLogger,
        MessageDispatcher $messageDispatcher,
        MagicLinkChannel $magicLink,
        VerificationRepository $verificationRepo,
        ?OtpService $otpService = null,
    ) {
        parent::__construct($otpGenerator, $auditLogger, $messageDispatcher, $verificationRepo, $otpService);
        $this->magicLink = $magicLink;
    }

    public function getId(): string
    {
        return 'email';
    }

    public function getName(): string
    {
        return 'Email';
    }

    public function supportsPrimaryAuth(): bool
    {
        return true;
    }

    public function supportsMfa(): bool
    {
        return true;
    }

    public function supportsAutoEnrollment(): bool
    {
        return true;
    }

    public function isAvailableForUser(int $userId): bool
    {
        $email = $this->getIdentifier($userId);

        return $email !== null && !AccountManager::isPlaceholderEmail($email);
    }

    /** {@inheritDoc} */
    public function enroll(int $userId, array $data): EnrollmentResult
    {
        $email = $this->getIdentifier($userId);

        if ($email === null) {
            return new EnrollmentResult(false, __('No email address found for user.', 'wp-sms'));
        }

        $existing = $this->getFactor($userId);

        if ($existing && $existing->status === ChannelStatus::Active) {
            return new EnrollmentResult(false, __('Already enrolled in Email.', 'wp-sms'));
        }

        // Auto-enroll with Active status — WP email is already verified.
        if ($existing) {
            $this->updateFactor($existing->id, [
                'status' => ChannelStatus::Active->value,
                'meta'   => wp_json_encode(['email' => $email]),
            ]);
        } else {
            $this->createFactor($userId, ChannelStatus::Active, ['email' => $email]);
        }

        $this->auditLogger->log(EventType::MfaEnrolled, 'success', $userId, [
            'channel' => $this->getId(),
        ]);

        return new EnrollmentResult(true, __('Email enrollment complete.', 'wp-sms'), [
            'masked_email' => EmailMasker::mask($email),
        ]);
    }

    /**
     * Send challenge with OTP, magic link, or both based on verification_methods setting.
     */
    public function sendChallenge(int $userId, array $context = []): ChallengeResult
    {
        $verificationMethods = (array) $this->getConfigValue('verification_methods', ['otp']);
        $hasOtp = in_array('otp', $verificationMethods, true);
        $hasMagicLink = in_array('magic_link', $verificationMethods, true);

        if (!$hasOtp && !$hasMagicLink) {
            return new ChallengeResult(false, __('No verification methods enabled for email channel.', 'wp-sms'));
        }

        $prereq = $this->validateChallengePrerequisites($userId);

        if (!$prereq->success) {
            return $prereq;
        }

        $identifier = $prereq->meta['identifier'];
        $expiry = (int) $this->getConfigValue('expiry', 600);
        $otpCode = null;
        $magicLinkUrl = null;

        // Generate OTP if enabled.
        if ($hasOtp) {
            $otpCode = $this->generateAndStoreOtp($userId, $identifier, $expiry);
        }

        // Generate magic link if enabled.
        if ($hasMagicLink) {
            $magicLinkUrl = $this->magicLink->generateToken($userId, $identifier, $expiry);
        }

        // Send single combined email.
        $sent = $this->deliverCombined($userId, $identifier, $otpCode, $magicLinkUrl, $expiry);

        if (!$sent) {
            $this->auditLogger->log(EventType::OtpSent, 'failure', $userId, [
                'channel' => $this->getId(),
            ]);

            return new ChallengeResult(false, __('Failed to deliver the verification email.', 'wp-sms'));
        }

        $this->auditLogger->log(EventType::OtpSent, 'success', $userId, [
            'channel' => $this->getId(),
        ]);

        return new ChallengeResult(true, __('Verification email sent.', 'wp-sms'), [
            'masked_identifier' => $this->maskIdentifier($identifier),
            'expires_in'        => $expiry,
            'has_otp'           => $hasOtp,
            'has_magic_link'    => $hasMagicLink,
        ]);
    }

    /**
     * Verify a magic link token and resolve the user.
     */
    public function verifyTokenAndResolveUser(string $token): ?int
    {
        return $this->magicLink->verifyTokenAndResolveUser($token);
    }

    /** {@inheritDoc} */
    protected function deliver(int $userId, string $code, string $identifier): bool
    {
        // Used only when sendChallenge is called via parent (OTP-only path).
        return $this->deliverCombined($userId, $identifier, $code, null, (int) $this->getConfigValue('expiry', 600));
    }

    /** {@inheritDoc} */
    protected function getIdentifier(int $userId): ?string
    {
        $user = get_userdata($userId);

        if (!$user || empty($user->user_email)) {
            return null;
        }

        return $user->user_email;
    }

    /** {@inheritDoc} */
    protected function maskIdentifier(string $identifier): string
    {
        return EmailMasker::mask($identifier);
    }

    /** {@inheritDoc} */
    protected function getConfigPrefix(): string
    {
        return 'email';
    }

    /**
     * Send a single email containing OTP code, magic link, or both.
     */
    private function deliverCombined(int $userId, string $identifier, ?string $otpCode, ?string $magicLinkUrl, int $expiry): bool
    {
        $siteName = get_bloginfo('name');
        $expiryMinutes = (int) ($expiry / 60);
        $subject = sprintf(__('[%s] Your verification code', 'wp-sms'), $siteName);

        $bodyParts = [];

        if ($otpCode !== null) {
            $bodyParts[] = '<p>' . sprintf(__('Your verification code is: %s', 'wp-sms'), '<strong>' . $otpCode . '</strong>') . '</p>';
        }

        if ($magicLinkUrl !== null) {
            $bodyParts[] = '<p>' . __('Or click the link below to log in:', 'wp-sms') . '</p>'
                . '<p><a href="' . esc_url($magicLinkUrl) . '">'
                . sprintf(__('Log in to %s', 'wp-sms'), esc_html($siteName))
                . '</a></p>';
        }

        $bodyParts[] = '<p>' . sprintf(__('This expires in %d minutes.', 'wp-sms'), $expiryMinutes) . '</p>';

        $ipWarning = IpResolver::renderIpWarningHtml(__('This code was requested', 'wp-sms'));
        if ($ipWarning !== '') {
            $bodyParts[] = $ipWarning;
        } else {
            $bodyParts[] = '<p>' . __('If you did not request this, please ignore this email.', 'wp-sms') . '</p>';
        }

        $body = implode("\n", $bodyParts);
        $headers = ['Content-Type: text/html; charset=UTF-8'];

        $result = $this->messageDispatcher->sendImmediate(
            new EmailMessage($identifier, $body, $subject, $headers),
            $this->resolveOtpGatewayId(),
        );

        return $result->success;
    }
}
