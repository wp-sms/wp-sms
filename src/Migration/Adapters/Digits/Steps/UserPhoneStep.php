<?php

namespace WSms\Migration\Adapters\Digits\Steps;

use WSms\Database\Connection;
use WSms\Migration\BatchResult;
use WSms\Migration\ConflictResolution;
use WSms\Migration\MigrationBackupService;
use WSms\Migration\Adapters\Digits\DigitsDetector;
use WSms\Migration\Contracts\MigrationStepInterface;
use WSms\Migration\PreviewResult;
use WSms\Support\UserMeta;

defined('ABSPATH') || exit;

class UserPhoneStep implements MigrationStepInterface
{
    public function __construct(
        private readonly Connection $db,
        private readonly MigrationBackupService $backup,
        private readonly DigitsDetector $detector,
    ) {
    }

    public function getId(): string
    {
        return 'user_phones';
    }

    public function getLabel(): string
    {
        return __('Phone Numbers', 'wp-sms');
    }

    public function getDescription(): string
    {
        return __('Import phone numbers and verification status from Digits users.', 'wp-sms');
    }

    public function getPriority(): int
    {
        return 10;
    }

    public function getTotalCount(): int
    {
        return $this->detector->countDigitsUsers();
    }

    public function isApplicable(): bool
    {
        return $this->getTotalCount() > 0;
    }

    public function preview(array $options = []): PreviewResult
    {
        $table = $this->db->table('usermeta');

        $totalUsers = $this->getTotalCount();

        // Conflicts: users who already have wsms_phone.
        $conflicts = (int) $this->db->getVar(
            "SELECT COUNT(DISTINCT d.user_id) FROM {$table} d
             JOIN {$table} w ON d.user_id = w.user_id AND w.meta_key = %s
             WHERE d.meta_key = 'digits_phone'",
            UserMeta::PHONE,
        );

        // Sample phones for quality check.
        $sample = $this->db->getResults(
            "SELECT d.user_id, d.meta_value AS phone, c.meta_value AS countrycode
             FROM {$table} d
             LEFT JOIN {$table} c ON d.user_id = c.user_id AND c.meta_key = 'digt_countrycode'
             WHERE d.meta_key = 'digits_phone'
             ORDER BY d.user_id LIMIT 1000",
        );

        $defaultCountry = $options['default_country'] ?? null;
        $invalidCount = 0;
        $sampleErrors = [];

        foreach ($sample as $row) {
            $normalized = $this->normalizePhone($row['phone'], $row['countrycode'], $defaultCountry);
            if ($normalized === null) {
                $invalidCount++;
                if (count($sampleErrors) < 10) {
                    $sampleErrors[] = [
                        'user_id' => (int) $row['user_id'],
                        'phone'   => $row['phone'],
                        'reason'  => __('Could not be converted to international format.', 'wp-sms'),
                    ];
                }
            }
        }

        // Extrapolate invalid count for full dataset.
        $estimatedInvalid = $totalUsers > 1000
            ? (int) round(($invalidCount / count($sample)) * $totalUsers)
            : $invalidCount;

        $readyToMigrate = max(0, $totalUsers - $conflicts - $estimatedInvalid);

        $warnings = [];
        if ($estimatedInvalid > 0) {
            $warnings[] = sprintf(
                __('%s phone numbers could not be converted to international format (missing country code or invalid format). These will be skipped.', 'wp-sms'),
                number_format_i18n($estimatedInvalid),
            );
        }

        // Check for phone-only users (no password) among invalid phones.
        if ($estimatedInvalid > 0) {
            $noPasswordCount = $this->countNoPasswordUsers();
            if ($noPasswordCount > 0) {
                $warnings[] = sprintf(
                    __('%s users have no password set — they registered through Digits using only their phone. After switching, they won\'t be able to log in unless you fix their phone number or set a temporary password.', 'wp-sms'),
                    number_format_i18n($noPasswordCount),
                );
            }
        }

        return new PreviewResult(
            totalItems: $totalUsers,
            readyToMigrate: $readyToMigrate,
            conflicts: $conflicts,
            skippable: $conflicts,
            invalidData: $estimatedInvalid,
            sampleErrors: $sampleErrors,
            warnings: $warnings,
        );
    }

    public function migrateBatch(array $options, ?string $cursor = null): BatchResult
    {
        $table = $this->db->table('usermeta');
        $batchSize = $options['batch_size'] ?? 500;
        $conflictResolution = ConflictResolution::tryFrom($options['conflict_resolution'] ?? 'skip') ?? ConflictResolution::Skip;
        $defaultCountry = $options['default_country'] ?? null;
        $dryRun = !empty($options['dry_run']);
        $wcSync = !empty($options['wc_sync']);

        $cursorClause = $cursor !== null
            ? $this->db->prepare('AND d.user_id > %d', (int) $cursor)
            : '';

        $rows = $this->db->getResults(
            "SELECT d.user_id, d.meta_value AS phone, c.meta_value AS countrycode
             FROM {$table} d
             LEFT JOIN {$table} c ON d.user_id = c.user_id AND c.meta_key = 'digt_countrycode'
             WHERE d.meta_key = 'digits_phone' {$cursorClause}
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

            // Normalize phone.
            $normalized = $this->normalizePhone($row['phone'], $row['countrycode'], $defaultCountry);
            if ($normalized === null) {
                $failed++;
                $errors[] = [
                    'user_id' => $userId,
                    'phone'   => $row['phone'],
                    'reason'  => __('Could not be converted to international format.', 'wp-sms'),
                ];
                continue;
            }

            // Check for duplicate phone across existing WP-SMS users.
            $existingOwner = $this->findPhoneOwner($normalized);
            if ($existingOwner !== null && $existingOwner !== $userId) {
                $skipped++;
                $conflicts++;
                continue;
            }

            // Check for conflict with existing wsms_phone on same user.
            $existingPhone = get_user_meta($userId, UserMeta::PHONE, true);
            if ($existingPhone !== '' && $existingPhone !== false) {
                $conflicts++;

                if ($conflictResolution === ConflictResolution::Skip || $conflictResolution === ConflictResolution::KeepExisting) {
                    $skipped++;
                    continue;
                }

            }

            if ($dryRun) {
                $migrated++;
                continue;
            }

            // Backup before writing — enables rollback for both new writes and overwrites.
            $this->backup->backupPhone($userId);
            update_user_meta($userId, UserMeta::PHONE, $normalized);
            update_user_meta($userId, UserMeta::PHONE_VERIFIED, '1');

            if ($wcSync && class_exists('WooCommerce')) {
                update_user_meta($userId, 'billing_phone', $normalized);
            }

            $migrated++;
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
     * Normalize a phone number to E.164 format.
     *
     * Pipeline:
     * 1. Strip non-digit chars except leading +
     * 2. Handle 00 international prefix
     * 3. Normalize country code
     * 4. Combine phone + country code
     * 5. Strip trunk prefix (leading 0 in national number)
     * 6. Validate E.164 pattern
     */
    public function normalizePhone(string $phone, ?string $countryCode, ?string $defaultCountry): ?string
    {
        // Step 1: Strip all non-digit chars except leading +.
        $phone = trim($phone);
        $hasPlus = str_starts_with($phone, '+');
        $phone = preg_replace('/[^\d]/', '', $phone);

        if ($phone === '') {
            return null;
        }

        if ($hasPlus) {
            $phone = '+' . $phone;
        }

        // Step 2: Handle 00 international prefix.
        if (str_starts_with($phone, '00')) {
            $phone = '+' . substr($phone, 2);
        }

        // If phone already starts with +, validate directly.
        if (str_starts_with($phone, '+')) {
            return $this->validateE164($phone);
        }

        // Step 3: Normalize country code.
        $cc = $this->normalizeCountryCode($countryCode ?? $defaultCountry);
        if ($cc === null) {
            return null;
        }

        // Step 4: Combine — strip trunk prefix (leading 0) from national number.
        $national = ltrim($phone, '0');
        if ($national === '') {
            return null;
        }

        // If phone already starts with the country code digits, don't prepend.
        $ccDigits = ltrim($cc, '+');
        if (str_starts_with($phone, $ccDigits)) {
            $candidate = '+' . $phone;
        } else {
            $candidate = $cc . $national;
        }

        return $this->validateE164($candidate);
    }

    private function normalizeCountryCode(?string $code): ?string
    {
        if ($code === null || trim($code) === '') {
            return null;
        }

        $code = trim($code);

        // Strip + prefix, strip leading 00.
        if (str_starts_with($code, '+')) {
            $code = substr($code, 1);
        } elseif (str_starts_with($code, '00')) {
            $code = substr($code, 2);
        }

        $code = preg_replace('/[^\d]/', '', $code);

        return $code !== '' ? '+' . $code : null;
    }

    private function validateE164(string $phone): ?string
    {
        // Strip trunk prefix after country code: find where CC ends and national begins.
        // E.164: + followed by 1-3 digit CC then 6-14 digit subscriber.
        return preg_match('/^\+[1-9]\d{6,14}$/', $phone) ? $phone : null;
    }

    private function findPhoneOwner(string $phone): ?int
    {
        $table = $this->db->table('usermeta');
        $result = $this->db->getVar(
            "SELECT user_id FROM {$table} WHERE meta_key = %s AND meta_value = %s LIMIT 1",
            UserMeta::PHONE,
            $phone,
        );

        return $result !== null ? (int) $result : null;
    }

    private function countNoPasswordUsers(): int
    {
        $table = $this->db->table('usermeta');
        // Users with digits_phone who have wsms_has_usable_password = '0' or empty password hash.
        return (int) $this->db->getVar(
            "SELECT COUNT(DISTINCT d.user_id) FROM {$table} d
             JOIN {$this->db->table('users')} u ON d.user_id = u.ID
             WHERE d.meta_key = 'digits_phone'
             AND (u.user_pass = '' OR u.user_pass IS NULL)",
        );
    }
}
