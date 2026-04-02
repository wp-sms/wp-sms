<?php

namespace WSms\Mfa\Channels;

use WSms\Enums\ChannelStatus;
use WSms\Enums\EventType;
use WSms\Enums\TemplateType;
use WSms\Audit\AuditLogger;
use WSms\Auth\UserInfo;
use WSms\Messaging\MessageDispatcher;
use WSms\Messaging\Template\TemplateManager;
use WSms\Mfa\Contracts\SupportsTokenVerification;
use WSms\Verification\OtpGenerator;
use WSms\Mfa\Support\EmailMasker;
use WSms\Mfa\ValueObjects\ChallengeResult;
use WSms\Mfa\ValueObjects\EnrollmentResult;
use WSms\Verification\OtpService;
use WSms\Verification\VerificationRepository;

defined('ABSPATH') || exit;

class EmailChannel extends AbstractOtpChannel implements SupportsTokenVerification
{
    private MagicLinkChannel $magicLink;
    private TemplateManager $templateManager;

    public function __construct(
        OtpGenerator $otpGenerator,
        AuditLogger $auditLogger,
        MessageDispatcher $messageDispatcher,
        MagicLinkChannel $magicLink,
        VerificationRepository $verificationRepo,
        TemplateManager $templateManager,
        ?OtpService $otpService = null,
    ) {
        parent::__construct($otpGenerator, $auditLogger, $messageDispatcher, $verificationRepo, $otpService);
        $this->magicLink = $magicLink;
        $this->templateManager = $templateManager;
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

    public function getIconSvg(): string
    {
        return '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>';
    }

    public function getDescription(): string
    {
        return __('Receive a verification code via email', 'wp-sms');
    }

    public function getConfigSchema(): array
    {
        return [];
    }

    public function supportsAutoEnrollment(): bool
    {
        return true;
    }

    public function isAvailableForUser(int $userId): bool
    {
        $email = $this->getIdentifier($userId);

        return $email !== null && !UserInfo::isPlaceholderEmail($email);
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
        $templateType = TemplateType::forCombinedDelivery($otpCode, $magicLinkUrl);

        $variables = ['expiry_minutes' => (string) (int) ($expiry / 60)];
        if ($otpCode !== null) {
            $variables['otp_code'] = $otpCode;
        }
        if ($magicLinkUrl !== null) {
            $variables['magic_link_url'] = $magicLinkUrl;
        }

        $message = $this->templateManager->renderToMessage($templateType->value, 'email', $identifier, $variables);
        $result = $this->messageDispatcher->sendImmediate($message, $this->resolveOtpGatewayId());

        return $result->success;
    }
}
