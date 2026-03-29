<?php

namespace WSms\Migration;

use WSms\Database\Connection;
use WSms\Migration\RollbackResult;
use WSms\Support\UserMeta;

defined('ABSPATH') || exit;

/**
 * Handles backup and rollback for Digits migration data.
 *
 * Uses WordPress user meta and options for all backup storage — no new tables.
 */
class MigrationBackupService
{
    private const PHONE_BACKUP_META = 'wsms_pre_migration_phone';
    private const MFA_ENABLED_BACKUP_META = 'wsms_pre_migration_mfa_enabled';
    private const SETTINGS_BACKUP_OPTION = 'wsms_auth_settings_pre_migration';
    private const MIGRATION_TAG = 'digits';

    public function __construct(
        private readonly Connection $db,
    ) {
    }

    public function backupPhone(int $userId): void
    {
        $existing = get_user_meta($userId, UserMeta::PHONE, true);
        // get_user_meta returns '' when key doesn't exist — sentinel for rollback to delete.
        update_user_meta($userId, self::PHONE_BACKUP_META, $existing);
    }

    public function rollbackPhones(): RollbackResult
    {
        $table = $this->db->table('usermeta');
        $batchSize = 500;
        $rolledBack = 0;
        $failed = 0;
        $errors = [];

        // Process in batches to avoid loading all backup rows into memory at once.
        do {
            $backups = $this->db->getResults(
                "SELECT user_id, meta_value FROM {$table} WHERE meta_key = %s LIMIT %d",
                self::PHONE_BACKUP_META,
                $batchSize,
            );

            foreach ($backups as $row) {
                $userId = (int) $row['user_id'];
                $original = $row['meta_value'];

                try {
                    if ($original === '') {
                        delete_user_meta($userId, UserMeta::PHONE);
                    } else {
                        update_user_meta($userId, UserMeta::PHONE, $original);
                    }
                    delete_user_meta($userId, UserMeta::PHONE_VERIFIED);
                    delete_user_meta($userId, self::PHONE_BACKUP_META);
                    $rolledBack++;
                } catch (\Throwable $e) {
                    $failed++;
                    $errors[] = ['user_id' => $userId, 'reason' => $e->getMessage()];
                }
            }
        } while (count($backups) === $batchSize);

        return new RollbackResult($rolledBack, $failed, $errors);
    }

    public function backupMfaEnabled(int $userId): void
    {
        $current = get_user_meta($userId, UserMeta::MFA_ENABLED, true);
        if ($current !== '') {
            update_user_meta($userId, self::MFA_ENABLED_BACKUP_META, $current);
        }
    }

    public function rollbackTotp(): RollbackResult
    {
        $factorsTable = $this->db->table(Connection::TABLE_USER_FACTORS);

        // Delete all migrated TOTP factors (tagged with migration: digits in meta).
        $deleted = $this->db->query(
            "DELETE FROM {$factorsTable} WHERE JSON_EXTRACT(meta, '$.migration') = %s",
            self::MIGRATION_TAG,
        );

        // Restore MFA enabled state from backup.
        $metaTable = $this->db->table('usermeta');
        $backups = $this->db->getResults(
            "SELECT user_id, meta_value FROM {$metaTable} WHERE meta_key = %s",
            self::MFA_ENABLED_BACKUP_META,
        );

        $rolledBack = $deleted;
        $failed = 0;
        $errors = [];

        foreach ($backups as $row) {
            $userId = (int) $row['user_id'];
            try {
                $original = $row['meta_value'];
                if ($original === '' || $original === '0') {
                    delete_user_meta($userId, UserMeta::MFA_ENABLED);
                } else {
                    update_user_meta($userId, UserMeta::MFA_ENABLED, $original);
                }
                delete_user_meta($userId, self::MFA_ENABLED_BACKUP_META);
            } catch (\Throwable $e) {
                $failed++;
                $errors[] = ['user_id' => $userId, 'reason' => $e->getMessage()];
            }
        }

        return new RollbackResult($rolledBack, $failed, $errors);
    }

    public function backupSettings(): void
    {
        $current = get_option('wsms_auth_settings', []);
        update_option(self::SETTINGS_BACKUP_OPTION, $current, false);
    }

    public function rollbackSettings(): RollbackResult
    {
        $backup = get_option(self::SETTINGS_BACKUP_OPTION);
        if ($backup === false) {
            return new RollbackResult(0, 0, [['reason' => __('No settings backup found.', 'wp-sms')]]);
        }

        update_option('wsms_auth_settings', $backup, false);
        delete_option(self::SETTINGS_BACKUP_OPTION);

        return new RollbackResult(1, 0);
    }

    public function rollbackAll(): RollbackResult
    {
        $phoneResult = $this->rollbackPhones();
        $totpResult = $this->rollbackTotp();
        $settingsResult = $this->rollbackSettings();

        return new RollbackResult(
            rolledBack: $phoneResult->rolledBack + $totpResult->rolledBack + $settingsResult->rolledBack,
            failed: $phoneResult->failed + $totpResult->failed + $settingsResult->failed,
            errors: array_merge($phoneResult->errors, $totpResult->errors, $settingsResult->errors),
        );
    }

    public function getMigrationTag(): string
    {
        return self::MIGRATION_TAG;
    }
}
