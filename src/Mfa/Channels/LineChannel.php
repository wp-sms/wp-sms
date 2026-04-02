<?php

namespace WSms\Mfa\Channels;

use WSms\Audit\AuditLogger;
use WSms\Enums\ChannelStatus;
use WSms\Enums\EventType;
use WSms\Enums\TemplateType;
use WSms\Messaging\MessageDispatcher;
use WSms\Messaging\Template\TemplateManager;
use WSms\Mfa\ValueObjects\EnrollmentResult;
use WSms\Verification\OtpGenerator;
use WSms\Verification\OtpService;
use WSms\Verification\VerificationRepository;

defined('ABSPATH') || exit;

class LineChannel extends AbstractOtpChannel
{
    private const LINKING_TOKEN_PREFIX = 'wsms_line_link_';
    private const LINKING_TOKEN_TTL = 300; // 5 minutes

    private TemplateManager $templateManager;

    public function __construct(
        OtpGenerator $otpGenerator,
        AuditLogger $auditLogger,
        MessageDispatcher $messageDispatcher,
        VerificationRepository $verificationRepo,
        TemplateManager $templateManager,
        ?OtpService $otpService = null,
    ) {
        parent::__construct($otpGenerator, $auditLogger, $messageDispatcher, $verificationRepo, $otpService);
        $this->templateManager = $templateManager;
    }

    public function getId(): string
    {
        return 'line';
    }

    public function getName(): string
    {
        return 'LINE';
    }

    public function supportsPrimaryAuth(): bool
    {
        return false;
    }

    public function supportsMfa(): bool
    {
        return true;
    }

    public function getIconSvg(): string
    {
        return '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"/></svg>';
    }

    public function getDescription(): string
    {
        return __('Receive a verification code via LINE', 'wp-sms');
    }

    public function getConfigSchema(): array
    {
        return [];
    }

    public function supportsAutoEnrollment(): bool
    {
        return true;
    }

    /** {@inheritDoc} */
    public function enroll(int $userId, array $data): EnrollmentResult
    {
        $existing = $this->getFactor($userId);

        if ($existing && $existing->status === ChannelStatus::Active) {
            return new EnrollmentResult(false, __('Already enrolled in LINE MFA.', 'wp-sms'));
        }

        // Delete old linking transient if exists.
        if ($existing && !empty($existing->meta['linking_token'])) {
            delete_transient(self::LINKING_TOKEN_PREFIX . $existing->meta['linking_token']);
        }

        // Generate a linking token for deep link enrollment.
        $token = bin2hex(random_bytes(16));
        set_transient(self::LINKING_TOKEN_PREFIX . $token, $userId, self::LINKING_TOKEN_TTL);

        // Create or update a pending factor.
        if ($existing) {
            $this->updateFactor($existing->id, [
                'status' => ChannelStatus::Pending->value,
                'meta'   => wp_json_encode(['linking_token' => $token]),
            ]);
        } else {
            $this->createFactor($userId, ChannelStatus::Pending, ['linking_token' => $token]);
        }

        $botBasicId = $this->getConfigValue('bot_basic_id', '');

        // Deep link: opens LINE chat with the bot, pre-filled with the linking token message.
        $deepLink = $botBasicId
            ? "https://line.me/R/oaMessage/{$botBasicId}/?link_{$token}"
            : '';

        return new EnrollmentResult(true, __('Open LINE to complete enrollment.', 'wp-sms'), [
            'requires_confirmation' => true,
            'deep_link'             => $deepLink,
            'linking_token'         => $token,
        ]);
    }

    /**
     * Auto-enroll a user from LINE social login (no user action needed).
     */
    public function autoEnroll(int $userId, string $lineUserId, ?string $displayName = null): void
    {
        $existing = $this->getFactor($userId);

        $meta = [
            'line_user_id'  => $lineUserId,
            'display_name'  => $displayName,
        ];

        if ($existing) {
            $this->updateFactor($existing->id, [
                'status' => ChannelStatus::Active->value,
                'meta'   => wp_json_encode($meta),
            ]);
        } else {
            $this->createFactor($userId, ChannelStatus::Active, $meta);
        }

        $this->auditLogger->log(EventType::MfaEnrolled, 'success', $userId, [
            'channel' => $this->getId(),
            'method'  => 'auto',
        ]);
    }

    /**
     * Complete enrollment from webhook when user sends "link_<token>" message.
     * Resolves the linking token to a user and activates the factor.
     */
    public function completeLinking(string $token, string $lineUserId, ?string $displayName = null): bool
    {
        $userId = get_transient(self::LINKING_TOKEN_PREFIX . $token);

        if ($userId === false) {
            return false;
        }

        delete_transient(self::LINKING_TOKEN_PREFIX . $token);

        $factor = $this->getFactor((int) $userId);

        if (!$factor || $factor->status !== ChannelStatus::Pending) {
            return false;
        }

        $meta = [
            'line_user_id' => $lineUserId,
            'display_name' => $displayName,
        ];

        $this->updateFactor($factor->id, [
            'status' => ChannelStatus::Active->value,
            'meta'   => wp_json_encode($meta),
        ]);

        $this->auditLogger->log(EventType::MfaEnrolled, 'success', (int) $userId, [
            'channel' => $this->getId(),
            'method'  => 'deep_link',
        ]);

        return true;
    }

    /** {@inheritDoc} */
    protected function deliver(int $userId, string $code, string $identifier): bool
    {
        $expiry = (int) $this->getConfigValue('expiry', 300);

        $message = $this->templateManager->renderToMessage(
            TemplateType::Otp->value,
            'line',
            $identifier,
            [
                'otp_code'       => $code,
                'expiry_minutes' => (string) (int) ($expiry / 60),
            ],
        );

        $result = $this->messageDispatcher->sendImmediate($message);

        return $result->success;
    }

    /** {@inheritDoc} */
    protected function getIdentifier(int $userId): ?string
    {
        $factor = $this->getFactor($userId);

        if ($factor && !empty($factor->meta['line_user_id'])) {
            return (string) $factor->meta['line_user_id'];
        }

        return null;
    }

    /** {@inheritDoc} */
    protected function maskIdentifier(string $identifier): string
    {
        if (strlen($identifier) > 4) {
            $visible = substr($identifier, -4);

            return "LINE (***{$visible})";
        }

        return "LINE ({$identifier})";
    }

    /** {@inheritDoc} */
    protected function getConfigPrefix(): string
    {
        return 'line';
    }

    public function getEnabledSettingKey(): string
    {
        return 'enabled';
    }
}
