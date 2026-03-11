<?php

namespace WSms\Mfa\Channels;

use WSms\Audit\AuditLogger;
use WSms\Enums\ChannelStatus;
use WSms\Enums\EventType;
use WSms\Mfa\Contracts\ChannelInterface;
use WSms\Mfa\OtpGenerator;
use WSms\Mfa\ValueObjects\ChallengeResult;
use WSms\Mfa\ValueObjects\EnrollmentResult;

defined('ABSPATH') || exit;

class BackupCodesChannel implements ChannelInterface
{
    use HasUserFactor;

    private const DEFAULT_CODE_COUNT = 10;
    private const CODE_HALF_LENGTH = 5;

    public function __construct(
        private OtpGenerator $otpGenerator,
        private AuditLogger $auditLogger,
    ) {
    }

    public function getId(): string
    {
        return 'backup_codes';
    }

    public function getName(): string
    {
        return 'Backup Codes';
    }

    public function supportsPrimaryAuth(): bool
    {
        return false;
    }

    public function supportsMfa(): bool
    {
        return true;
    }

    protected function getConfigPrefix(): string
    {
        return 'backup_codes';
    }

    /** {@inheritDoc} */
    public function enroll(int $userId, array $data): EnrollmentResult
    {
        $existing = $this->getFactor($userId);

        if ($existing && $existing->status === ChannelStatus::Active) {
            return new EnrollmentResult(false, 'Already enrolled in Backup Codes. Use regenerate to get new codes.');
        }

        $count = (int) $this->getConfigValue('count', self::DEFAULT_CODE_COUNT);
        $codes = [];
        $hashes = [];

        for ($i = 0; $i < $count; $i++) {
            $code = $this->generateCode(self::CODE_HALF_LENGTH);
            $codes[] = $code;
            // Codes are stored without hyphens for format-insensitive matching.
            $hashes[] = $this->otpGenerator->hash(str_replace('-', '', $code));
        }

        if ($existing) {
            $this->updateFactor($existing->id, [
                'status' => ChannelStatus::Active->value,
                'meta'   => wp_json_encode(['codes' => $hashes]),
            ]);
        } else {
            $this->createFactor($userId, ChannelStatus::Active, ['codes' => $hashes]);
        }

        $this->auditLogger->log(EventType::MfaEnrolled, 'success', $userId, [
            'channel' => $this->getId(),
            'count'   => $count,
        ]);

        return new EnrollmentResult(true, 'Backup codes generated. Save them securely.', [
            'codes' => $codes,
            'count' => $count,
        ]);
    }

    /** {@inheritDoc} */
    public function sendChallenge(int $userId, array $context = []): ChallengeResult
    {
        $factor = $this->getFactor($userId);

        if ($factor === null || $factor->status !== ChannelStatus::Active) {
            return new ChallengeResult(false, 'Backup codes not enrolled.');
        }

        $remaining = count($factor->meta['codes'] ?? []);

        return new ChallengeResult(true, 'Enter one of your backup codes.', [
            'remaining' => $remaining,
        ]);
    }

    /** {@inheritDoc} */
    public function verify(int $userId, string $code, array $context = []): bool
    {
        $factor = $this->getFactor($userId);

        if ($factor === null || $factor->status !== ChannelStatus::Active) {
            return false;
        }

        $storedHashes = $factor->meta['codes'] ?? [];

        if (empty($storedHashes)) {
            return false;
        }

        $normalized = str_replace('-', '', $code);

        // Iterate all hashes unconditionally to avoid timing side-channel.
        $matchIndex = null;

        foreach ($storedHashes as $index => $storedHash) {
            if ($this->otpGenerator->verify($normalized, $storedHash)) {
                $matchIndex = $index;
            }
        }

        if ($matchIndex === null) {
            return false;
        }

        // Remove used code.
        array_splice($storedHashes, $matchIndex, 1);

        $this->updateFactor($factor->id, [
            'meta' => wp_json_encode(['codes' => array_values($storedHashes)]),
        ]);

        $this->auditLogger->log(EventType::BackupCodeUsed, 'success', $userId, [
            'remaining' => count($storedHashes),
        ]);

        return true;
    }

    /** {@inheritDoc} */
    public function unenroll(int $userId): bool
    {
        $factor = $this->getFactor($userId);

        if ($factor === null) {
            return false;
        }

        global $wpdb;

        $table = $wpdb->prefix . 'wsms_user_factors';

        $wpdb->update(
            $table,
            [
                'status'     => ChannelStatus::Disabled->value,
                'meta'       => wp_json_encode([]),
                'updated_at' => current_time('mysql', true),
            ],
            ['id' => $factor->id],
        );

        $this->factorCache = [];

        $this->auditLogger->log(EventType::MfaUnenrolled, 'success', $userId, [
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

        return [
            'enrolled'   => $factor->status === ChannelStatus::Active,
            'status'     => $factor->status->value,
            'channel'    => $this->getId(),
            'remaining'  => count($factor->meta['codes'] ?? []),
            'created_at' => $factor->createdAt,
        ];
    }

    /**
     * Regenerate backup codes (unenroll + re-enroll).
     */
    public function regenerate(int $userId): EnrollmentResult
    {
        $this->unenroll($userId);
        $result = $this->enroll($userId, []);

        if ($result->success) {
            $this->auditLogger->log(EventType::BackupCodesRegenerated, 'success', $userId);
        }

        return $result;
    }

    /**
     * Generate a single backup code in XXXXX-XXXXX format.
     */
    private function generateCode(int $halfLength): string
    {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // No I/O/0/1 for readability
        $max = strlen($chars) - 1;
        $code = '';

        for ($i = 0; $i < $halfLength * 2; $i++) {
            $code .= $chars[random_int(0, $max)];
        }

        return substr($code, 0, $halfLength) . '-' . substr($code, $halfLength);
    }
}
