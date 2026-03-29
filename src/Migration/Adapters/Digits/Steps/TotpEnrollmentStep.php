<?php

namespace WSms\Migration\Adapters\Digits\Steps;

use WSms\Database\Connection;
use WSms\Enums\ChannelStatus;
use WSms\Mfa\SecretEncryptor;
use WSms\Mfa\UserFactorRepository;
use WSms\Migration\BatchResult;
use WSms\Migration\MigrationBackupService;
use WSms\Migration\Adapters\Digits\DigitsDetector;
use WSms\Migration\Contracts\MigrationStepInterface;
use WSms\Migration\PreviewResult;
use WSms\Support\UserMeta;

defined('ABSPATH') || exit;

class TotpEnrollmentStep implements MigrationStepInterface
{
    public function __construct(
        private readonly Connection $db,
        private readonly UserFactorRepository $factorRepo,
        private readonly SecretEncryptor $encryptor,
        private readonly MigrationBackupService $backup,
        private readonly DigitsDetector $detector,
    ) {
    }

    public function getId(): string
    {
        return 'totp_enrollment';
    }

    public function getLabel(): string
    {
        return __('Authenticator Apps', 'wp-sms');
    }

    public function getDescription(): string
    {
        return __('Import TOTP (authenticator app) enrollments so users\' apps continue working.', 'wp-sms');
    }

    public function getPriority(): int
    {
        return 20;
    }

    public function getTotalCount(): int
    {
        return $this->detector->countTotpUsers();
    }

    public function isApplicable(): bool
    {
        return $this->getTotalCount() > 0;
    }

    public function hasEncryptionKey(): bool
    {
        return defined('WSMS_MFA_ENCRYPTION_KEY') && WSMS_MFA_ENCRYPTION_KEY !== '';
    }

    public function preview(array $options = []): PreviewResult
    {
        $total = $this->getTotalCount();

        $warnings = [];
        if (!$this->hasEncryptionKey()) {
            $warnings[] = __('Authenticator app migration requires an encryption key. Add define(\'WSMS_MFA_ENCRYPTION_KEY\', \'...\') to wp-config.php.', 'wp-sms');
        }

        // Check for conflicts: users who already have active TOTP in WP-SMS.
        $factorsTable = $this->db->table(Connection::TABLE_USER_FACTORS);
        $metaTable = $this->db->table('usermeta');

        $activeStatus = ChannelStatus::Active->value;
        $conflicts = (int) $this->db->getVar(
            "SELECT COUNT(DISTINCT d.user_id) FROM {$metaTable} d
             JOIN {$factorsTable} f ON d.user_id = f.user_id AND f.channel_id = 'totp' AND f.status = %s
             WHERE d.meta_key = 'digits_totp'",
            $activeStatus,
        );

        return new PreviewResult(
            totalItems: $total,
            readyToMigrate: max(0, $total - $conflicts),
            conflicts: $conflicts,
            skippable: $conflicts,
            invalidData: 0,
            warnings: $warnings,
        );
    }

    public function migrateBatch(array $options, ?string $cursor = null): BatchResult
    {
        $table = $this->db->table('usermeta');
        $batchSize = $options['batch_size'] ?? 500;
        $dryRun = !empty($options['dry_run']);
        $migrationTag = $this->backup->getMigrationTag();

        $cursorClause = $cursor !== null
            ? $this->db->prepare('AND d.user_id > %d', (int) $cursor)
            : '';

        $rows = $this->db->getResults(
            "SELECT d.user_id, d.meta_value AS totp_secret
             FROM {$table} d
             WHERE d.meta_key = 'digits_totp' {$cursorClause}
             ORDER BY d.user_id ASC
             LIMIT %d",
            $batchSize,
        );

        $processed = 0;
        $migrated = 0;
        $skipped = 0;
        $failed = 0;
        $conflicts = 0;
        $errors = [];
        $lastUserId = $cursor;

        foreach ($rows as $row) {
            $userId = (int) $row['user_id'];
            $lastUserId = (string) $userId;
            $processed++;

            $secret = $this->extractTotpSecret($row['totp_secret']);
            if ($secret === null) {
                $failed++;
                $errors[] = [
                    'user_id' => $userId,
                    'reason'  => __('Authenticator data was incomplete or in an unrecognized format.', 'wp-sms'),
                ];
                continue;
            }

            // Check if user already has active TOTP in WP-SMS.
            $existingFactor = $this->factorRepo->findLatest($userId, 'totp');
            if ($existingFactor !== null && $existingFactor->status === ChannelStatus::Active) {
                $skipped++;
                $conflicts++;
                continue;
            }

            if ($dryRun) {
                $migrated++;
                continue;
            }

            try {
                $encrypted = $this->encryptor->encrypt($secret, $userId);

                $this->backup->backupMfaEnabled($userId);

                $this->factorRepo->create($userId, 'totp', ChannelStatus::Active, [
                    'secret'    => $encrypted,
                    'migration' => $migrationTag,
                ]);

                update_user_meta($userId, UserMeta::MFA_ENABLED, '1');
                $migrated++;
            } catch (\Throwable $e) {
                $failed++;
                $errors[] = [
                    'user_id' => $userId,
                    'reason'  => $e->getMessage(),
                ];
            }
        }

        $done = count($rows) < $batchSize;

        return new BatchResult(
            processed: $processed,
            migrated: $migrated,
            skipped: $skipped,
            failed: $failed,
            conflicts: $conflicts,
            nextCursor: $done ? null : $lastUserId,
            done: $done,
            errors: $errors,
        );
    }

    /**
     * Extract a Base32 TOTP secret from various Digits storage formats.
     */
    private function extractTotpSecret(string $value): ?string
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        // Try otpauth:// URI format first.
        if (str_starts_with($value, 'otpauth://')) {
            $parsed = parse_url($value);
            if ($parsed === false) {
                return null;
            }
            parse_str($parsed['query'] ?? '', $params);
            $value = $params['secret'] ?? '';
            if ($value === '') {
                return null;
            }
        }

        // Validate Base32 format.
        if (preg_match('/^[A-Z2-7]+=*$/', strtoupper($value))) {
            return strtoupper($value);
        }

        // Try base64 decode.
        $decoded = base64_decode($value, true);
        if ($decoded !== false && strlen($decoded) >= 10) {
            // Re-encode as Base32 for TOTP compatibility.
            return self::base32Encode($decoded);
        }

        // Try hex decode.
        if (preg_match('/^[0-9a-fA-F]+$/', $value) && strlen($value) >= 20) {
            $decoded = hex2bin($value);
            if ($decoded !== false) {
                return self::base32Encode($decoded);
            }
        }

        return null;
    }

    private static function base32Encode(string $data): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $binary = '';
        foreach (str_split($data) as $char) {
            $binary .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }

        $encoded = '';
        foreach (str_split($binary, 5) as $chunk) {
            $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
            $encoded .= $alphabet[bindec($chunk)];
        }

        // Pad to multiple of 8.
        while (strlen($encoded) % 8 !== 0) {
            $encoded .= '=';
        }

        return $encoded;
    }
}
