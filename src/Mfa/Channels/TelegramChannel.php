<?php

namespace WSms\Mfa\Channels;

use WSms\Audit\AuditLogger;
use WSms\Enums\ChannelStatus;
use WSms\Enums\EventType;
use WSms\Messaging\MessageDispatcher;
use WSms\Messaging\Message\TelegramMessage;
use WSms\Mfa\OtpGenerator;
use WSms\Mfa\ValueObjects\EnrollmentResult;
use WSms\Verification\OtpService;
use WSms\Verification\VerificationRepository;

defined('ABSPATH') || exit;

class TelegramChannel extends AbstractOtpChannel
{
    private const LINKING_TOKEN_PREFIX = 'wsms_tg_link_';
    private const LINKING_TOKEN_TTL = 300; // 5 minutes

    public function __construct(
        OtpGenerator $otpGenerator,
        AuditLogger $auditLogger,
        MessageDispatcher $messageDispatcher,
        VerificationRepository $verificationRepo,
        ?OtpService $otpService = null,
    ) {
        parent::__construct($otpGenerator, $auditLogger, $messageDispatcher, $verificationRepo, $otpService);
    }

    public function getId(): string
    {
        return 'telegram';
    }

    public function getName(): string
    {
        return 'Telegram';
    }

    public function supportsPrimaryAuth(): bool
    {
        return false;
    }

    public function supportsMfa(): bool
    {
        return true;
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
            return new EnrollmentResult(false, __('Already enrolled in Telegram MFA.', 'wp-sms'));
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

        $botInfo = $this->getConfigValue('bot_username', '');

        $deepLink = $botInfo
            ? "https://t.me/{$botInfo}?start={$token}"
            : '';

        return new EnrollmentResult(true, __('Open Telegram to complete enrollment.', 'wp-sms'), [
            'requires_confirmation' => true,
            'deep_link'             => $deepLink,
            'linking_token'         => $token,
        ]);
    }

    /**
     * Auto-enroll a user from Telegram social login (no user action needed).
     */
    public function autoEnroll(int $userId, int $chatId, ?string $username = null): void
    {
        $existing = $this->getFactor($userId);

        $meta = [
            'chat_id'  => $chatId,
            'username' => $username,
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
     * Complete enrollment from webhook /start command.
     * Resolves the linking token to a user and activates the factor.
     */
    public function completeLinking(string $token, int $chatId, ?string $username = null): bool
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
            'chat_id'  => $chatId,
            'username' => $username,
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
        $expiryMinutes = (int) ($this->getConfigValue('expiry', 300) / 60);

        $body = '<b>' . sprintf(__('Your verification code is: %s', 'wp-sms'), $code) . '</b>'
            . "\n" . sprintf(__('It expires in %d minutes.', 'wp-sms'), $expiryMinutes);

        $result = $this->messageDispatcher->sendImmediate(new TelegramMessage($identifier, $body));

        return $result->success;
    }

    /** {@inheritDoc} */
    protected function getIdentifier(int $userId): ?string
    {
        $factor = $this->getFactor($userId);

        if ($factor && !empty($factor->meta['chat_id'])) {
            return (string) $factor->meta['chat_id'];
        }

        return null;
    }

    /** {@inheritDoc} */
    protected function maskIdentifier(string $identifier): string
    {
        if (strlen($identifier) > 4) {
            $visible = substr($identifier, -4);

            return "Telegram (***{$visible})";
        }

        return "Telegram ({$identifier})";
    }

    /** {@inheritDoc} */
    protected function getConfigPrefix(): string
    {
        return 'telegram';
    }

    public function getEnabledSettingKey(): string
    {
        return 'enabled';
    }
}
