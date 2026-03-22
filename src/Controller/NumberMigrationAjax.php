<?php

namespace WP_SMS\Controller;

use WP_SMS\Helper;
use WP_SMS\Option;
use WP_SMS\Components\NumberParser;

if (!defined('ABSPATH')) exit;

class NumberMigrationAjax extends AjaxControllerAbstract
{
    protected $action = 'wp_sms_number_migration';
    public $requiredFields = ['sub_action'];

    const BACKUP_OPTION_KEY = 'wpsms_number_migration_backup';
    const STATUS_OPTION_KEY = 'wpsms_number_migration_status';
    const BATCH_SIZE        = 500;

    protected function run()
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('You do not have permission to perform this action.', 'wp-sms'), 403);
        }

        $subAction = $this->get('sub_action');

        switch ($subAction) {
            case 'scan':
                $this->scan();
                break;
            case 'preview':
                $this->preview();
                break;
            case 'execute':
                $this->execute();
                break;
            case 'status':
                $this->getStatus();
                break;
            case 'revert':
                $this->revert();
                break;
            default:
                wp_send_json_error(__('Invalid sub-action.', 'wp-sms'), 400);
        }
    }

    /**
     * Returns the list of all phone sources to scan/migrate.
     *
     * Each source defines:
     *   - key:           Unique identifier for this source
     *   - label:         Human-readable label
     *   - table:         Table name (without prefix, or 'usermeta' for the WP table)
     *   - column:        Column containing the phone number
     *   - pk:            Primary key column
     *   - name_column:   Column for display name (optional)
     *   - type:          'single' for one number per row, 'csv' for comma-separated recipients
     *   - where_extra:   Extra WHERE clause (without leading AND)
     *
     * @return array
     */
    private function getPhoneSources()
    {
        global $wpdb;

        $mobileField = Helper::getUserMobileFieldName();

        $sources = [
            [
                'key'         => 'subscribers',
                'label'       => __('Subscribers', 'wp-sms'),
                'table'       => "{$wpdb->prefix}sms_subscribes",
                'column'      => 'mobile',
                'pk'          => 'ID',
                'name_column' => 'name',
                'type'        => 'single',
            ],
            [
                'key'         => 'usermeta',
                'label'       => __('User Mobile Numbers', 'wp-sms'),
                'table'       => $wpdb->usermeta,
                'column'      => 'meta_value',
                'pk'          => 'umeta_id',
                'name_column' => null,
                'type'        => 'single',
                'where_extra' => $wpdb->prepare("meta_key = %s AND meta_value != ''", $mobileField),
            ],
            [
                'key'         => 'otp',
                'label'       => __('OTP Records', 'wp-sms'),
                'table'       => "{$wpdb->prefix}sms_otp",
                'column'      => 'phone_number',
                'pk'          => 'ID',
                'name_column' => null,
                'type'        => 'single',
            ],
            [
                'key'         => 'otp_attempts',
                'label'       => __('OTP Attempts', 'wp-sms'),
                'table'       => "{$wpdb->prefix}sms_otp_attempts",
                'column'      => 'phone_number',
                'pk'          => 'ID',
                'name_column' => null,
                'type'        => 'single',
            ],
            [
                'key'         => 'campaign_targets',
                'label'       => __('Campaign Targets', 'wp-sms'),
                'table'       => "{$wpdb->prefix}sms_campaign_target_orders",
                'column'      => 'mobile_number',
                'pk'          => 'id',
                'name_column' => null,
                'type'        => 'single',
            ],
            [
                'key'         => 'scheduled',
                'label'       => __('Scheduled Messages', 'wp-sms'),
                'table'       => "{$wpdb->prefix}sms_scheduled",
                'column'      => 'recipient',
                'pk'          => 'ID',
                'name_column' => null,
                'type'        => 'csv',
            ],
            [
                'key'         => 'repeating',
                'label'       => __('Repeating Messages', 'wp-sms'),
                'table'       => "{$wpdb->prefix}sms_repeating",
                'column'      => 'recipient',
                'pk'          => 'ID',
                'name_column' => null,
                'type'        => 'csv',
            ],
        ];

        // Filter out tables that don't exist (cached for this request)
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }

        $cache = array_filter($sources, function ($source) use ($wpdb) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            return $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $source['table'])) === $source['table'];
        });

        return $cache;
    }

    /**
     * Scan all numbers and return counts of numbers needing migration.
     */
    private function scan()
    {
        global $wpdb;

        $countryCode = $this->getConfiguredCountryCode();
        if (is_wp_error($countryCode)) {
            wp_send_json_error([
                'code'    => $countryCode->get_error_code(),
                'message' => $countryCode->get_error_message(),
            ], 400);
            return;
        }

        $sources     = $this->getPhoneSources();
        $scanResults = [];
        $totalNeedFix    = 0;
        $totalAlreadyOk  = 0;

        foreach ($sources as $source) {
            $whereBase = isset($source['where_extra']) ? $source['where_extra'] : "`{$source['column']}` != ''";

            if ($source['type'] === 'single') {
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table/column names from hardcoded source registry
                $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$source['table']}` WHERE {$whereBase}");
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $needFix = (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$source['table']}` WHERE {$whereBase} AND `{$source['column']}` NOT LIKE '+%'");
                $alreadyOk = $total - $needFix;
            } else {
                // CSV type — count total rows and rows needing fix using SQL pattern matching
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$source['table']}` WHERE `{$source['column']}` IS NOT NULL AND `{$source['column']}` != ''");
                // A CSV row needs fixing if it contains a number not starting with + (i.e., the field itself doesn't start with + OR contains a comma followed by a non-+ char)
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $needFix = (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$source['table']}` WHERE `{$source['column']}` IS NOT NULL AND `{$source['column']}` != '' AND (`{$source['column']}` NOT LIKE '+%' OR `{$source['column']}` REGEXP ',[^+]')");
                $alreadyOk = $total - $needFix;
            }

            $scanResults[$source['key']] = [
                'label'      => $source['label'],
                'total'      => $total,
                'need_fix'   => $needFix,
                'already_intl' => $alreadyOk,
            ];

            $totalNeedFix   += $needFix;
            $totalAlreadyOk += $alreadyOk;
        }

        $backupExists = !empty(get_option(self::BACKUP_OPTION_KEY));

        wp_send_json_success([
            'country_code'       => $countryCode,
            'sources'            => $scanResults,
            'total_need_fix'     => $totalNeedFix,
            'total_already_intl' => $totalAlreadyOk,
            'backup_exists'      => $backupExists,
        ]);
    }

    /**
     * Preview the changes that would be made.
     */
    private function preview()
    {
        global $wpdb;

        $countryCode = $this->getConfiguredCountryCode();
        if (is_wp_error($countryCode)) {
            wp_send_json_error(['code' => $countryCode->get_error_code(), 'message' => $countryCode->get_error_message()], 400);
        }

        $page    = max(1, (int) $this->get('page', 1));
        $perPage = min(50, max(10, (int) $this->get('per_page', 20)));
        $offset  = ($page - 1) * $perPage;

        $sources = $this->getPhoneSources();
        $preview = [];
        $remaining = $perPage;

        foreach ($sources as $source) {
            if ($remaining <= 0) break;

            $whereBase = isset($source['where_extra']) ? $source['where_extra'] : "{$source['column']} != ''";

            if ($source['type'] === 'single') {
                $nameSelect = $source['name_column'] ? ", {$source['name_column']} AS display_name" : ", '' AS display_name";

                // For usermeta, join with users table for name
                if ($source['key'] === 'usermeta') {
                    $rows = $wpdb->get_results($wpdb->prepare(
                        "SELECT t.{$source['pk']} AS pk_val, t.{$source['column']} AS phone, u.display_name
                         FROM {$source['table']} t
                         LEFT JOIN {$wpdb->users} u ON t.user_id = u.ID
                         WHERE {$whereBase} AND t.{$source['column']} NOT LIKE '+%%'
                         ORDER BY t.{$source['pk']} ASC LIMIT %d OFFSET %d",
                        $remaining,
                        $offset
                    ));
                } else {
                    $rows = $wpdb->get_results($wpdb->prepare(
                        "SELECT {$source['pk']} AS pk_val, {$source['column']} AS phone {$nameSelect}
                         FROM {$source['table']}
                         WHERE {$whereBase} AND {$source['column']} NOT LIKE '+%%'
                         ORDER BY {$source['pk']} ASC LIMIT %d OFFSET %d",
                        $remaining,
                        $offset
                    ));
                }

                foreach ($rows as $row) {
                    $migrated = $this->migrateNumber($row->phone, $countryCode);
                    $preview[] = [
                        'source'   => $source['key'],
                        'label'    => $source['label'],
                        'id'       => (int) $row->pk_val,
                        'name'     => $row->display_name ?: '',
                        'original' => $row->phone,
                        'migrated' => $migrated,
                        'changed'  => $row->phone !== $migrated,
                    ];
                }

                $remaining -= count($rows);
            } else {
                // CSV type
                $rows = $wpdb->get_results($wpdb->prepare(
                    "SELECT {$source['pk']} AS pk_val, {$source['column']} AS phone
                     FROM {$source['table']}
                     WHERE {$source['column']} IS NOT NULL AND {$source['column']} != ''
                     ORDER BY {$source['pk']} ASC LIMIT %d OFFSET %d",
                    $remaining,
                    $offset
                ));

                foreach ($rows as $row) {
                    $numbers  = array_map('trim', explode(',', $row->phone));
                    $migrated = array_map(function ($n) use ($countryCode) {
                        return !empty($n) ? $this->migrateNumber($n, $countryCode) : $n;
                    }, $numbers);
                    $migratedStr = implode(',', $migrated);

                    if ($row->phone !== $migratedStr) {
                        $preview[] = [
                            'source'   => $source['key'],
                            'label'    => $source['label'],
                            'id'       => (int) $row->pk_val,
                            'name'     => '',
                            'original' => $row->phone,
                            'migrated' => $migratedStr,
                            'changed'  => true,
                        ];
                    }
                }

                $remaining -= count($rows);
            }
        }

        wp_send_json_success([
            'preview'      => $preview,
            'page'         => $page,
            'per_page'     => $perPage,
            'country_code' => $countryCode,
        ]);
    }

    /**
     * Execute the migration with backup.
     */
    private function execute()
    {
        global $wpdb;

        $countryCode = $this->getConfiguredCountryCode();
        if (is_wp_error($countryCode)) {
            wp_send_json_error(['code' => $countryCode->get_error_code(), 'message' => $countryCode->get_error_message()], 400);
        }

        $sources = $this->getPhoneSources();
        $backup  = [
            'timestamp'    => current_time('mysql'),
            'country_code' => $countryCode,
            'tables'       => [],
        ];

        $totalMigrated = 0;
        $migrationCounts = [];
        $errors = [];

        foreach ($sources as $source) {
            $whereBase  = isset($source['where_extra']) ? $source['where_extra'] : "{$source['column']} != ''";
            $count      = 0;
            $backupRows = [];
            $offset     = 0;

            while (true) {
                if ($source['type'] === 'single') {
                    $rows = $wpdb->get_results($wpdb->prepare(
                        "SELECT {$source['pk']} AS pk_val, {$source['column']} AS phone
                         FROM {$source['table']}
                         WHERE {$whereBase} AND {$source['column']} NOT LIKE '+%%'
                         ORDER BY {$source['pk']} ASC LIMIT %d OFFSET %d",
                        self::BATCH_SIZE,
                        $offset
                    ));
                } else {
                    // CSV type — get all rows with non-empty recipients
                    $rows = $wpdb->get_results($wpdb->prepare(
                        "SELECT {$source['pk']} AS pk_val, {$source['column']} AS phone
                         FROM {$source['table']}
                         WHERE {$source['column']} IS NOT NULL AND {$source['column']} != ''
                         ORDER BY {$source['pk']} ASC LIMIT %d OFFSET %d",
                        self::BATCH_SIZE,
                        $offset
                    ));
                }

                if (empty($rows)) break;

                foreach ($rows as $row) {
                    if ($source['type'] === 'csv') {
                        $numbers  = array_map('trim', explode(',', $row->phone));
                        $migrated = array_map(function ($n) use ($countryCode) {
                            return !empty($n) ? $this->migrateNumber($n, $countryCode) : $n;
                        }, $numbers);
                        $migratedStr = implode(',', $migrated);

                        if ($migratedStr === $row->phone) continue;

                        $backupRows[] = [
                            'pk'       => (int) $row->pk_val,
                            'original' => $row->phone,
                            'migrated' => $migratedStr,
                        ];

                        $result = $wpdb->update(
                            $source['table'],
                            [$source['column'] => $migratedStr],
                            [$source['pk'] => $row->pk_val],
                            ['%s'],
                            ['%d']
                        );
                    } else {
                        $migrated = $this->migrateNumber($row->phone, $countryCode);

                        if ($migrated === $row->phone) continue;

                        $backupRows[] = [
                            'pk'       => (int) $row->pk_val,
                            'original' => $row->phone,
                            'migrated' => $migrated,
                        ];

                        $result = $wpdb->update(
                            $source['table'],
                            [$source['column'] => $migrated],
                            [$source['pk'] => $row->pk_val],
                            ['%s'],
                            ['%d']
                        );
                    }

                    if ($result !== false) {
                        $count++;
                    } else {
                        $errors[] = sprintf('%s #%d: %s', $source['label'], $row->pk_val, $wpdb->last_error);
                    }
                }

                $offset += self::BATCH_SIZE;
            }

            if (!empty($backupRows)) {
                $backup['tables'][$source['key']] = [
                    'table'  => $source['table'],
                    'column' => $source['column'],
                    'pk'     => $source['pk'],
                    'rows'   => $backupRows,
                ];
            }

            $migrationCounts[$source['key']] = $count;
            $totalMigrated += $count;
        }

        // Save backup and invalidate notice cache
        update_option(self::BACKUP_OPTION_KEY, $backup, false);
        delete_transient('wpsms_local_number_count');

        // Persist the country code to plugin settings so future numbers use it
        Option::updateOption('mobile_county_code', $countryCode);

        // Save migration status
        update_option(self::STATUS_OPTION_KEY, [
            'status'          => 'completed',
            'timestamp'       => current_time('mysql'),
            'country_code'    => $countryCode,
            'counts'          => $migrationCounts,
            'total_migrated'  => $totalMigrated,
            'errors'          => $errors,
        ], false);

        wp_send_json_success([
            'counts'         => $migrationCounts,
            'total_migrated' => $totalMigrated,
            'errors'         => $errors,
            'backup_created' => true,
        ]);
    }

    /**
     * Get migration status.
     */
    private function getStatus()
    {
        $status = get_option(self::STATUS_OPTION_KEY, [
            'status' => 'not_started',
        ]);

        $backupExists = !empty(get_option(self::BACKUP_OPTION_KEY));
        $status['backup_exists'] = $backupExists;

        wp_send_json_success($status);
    }

    /**
     * Revert migration using backup data.
     */
    private function revert()
    {
        global $wpdb;

        $backup = get_option(self::BACKUP_OPTION_KEY);

        if (empty($backup)) {
            wp_send_json_error(__('No backup found. Cannot revert.', 'wp-sms'), 400);
        }

        $totalReverted = 0;
        $errors        = [];

        if (!empty($backup['tables'])) {
            foreach ($backup['tables'] as $sourceKey => $tableBackup) {
                foreach ($tableBackup['rows'] as $item) {
                    $result = $wpdb->update(
                        $tableBackup['table'],
                        [$tableBackup['column'] => $item['original']],
                        [$tableBackup['pk'] => $item['pk']],
                        ['%s'],
                        ['%d']
                    );

                    if ($result !== false) {
                        $totalReverted++;
                    } else {
                        $errors[] = sprintf('%s #%d: %s', $sourceKey, $item['pk'], $wpdb->last_error);
                    }
                }
            }
        }

        // Remove backup after successful revert and invalidate notice cache
        if (empty($errors)) {
            delete_option(self::BACKUP_OPTION_KEY);
        }
        delete_transient('wpsms_local_number_count');

        // Update status
        update_option(self::STATUS_OPTION_KEY, [
            'status'         => 'reverted',
            'timestamp'      => current_time('mysql'),
            'total_reverted' => $totalReverted,
            'errors'         => $errors,
        ], false);

        wp_send_json_success([
            'total_reverted' => $totalReverted,
            'errors'         => $errors,
        ]);
    }

    /**
     * Apply migration rules to convert a local number to E.164.
     *
     * @param string $number     The original number
     * @param string $countryCode The country code with + prefix (e.g., '+98')
     * @return string The migrated number in E.164 format
     */
    private function migrateNumber($number, $countryCode)
    {
        $number = trim($number);

        // Already in E.164
        if (strpos($number, '+') === 0) {
            return $number;
        }

        // Strip non-digit characters
        $clean = preg_replace('/[^\d]/', '', $number);

        if (empty($clean)) {
            return $number;
        }

        $ccDigits = ltrim($countryCode, '+');

        // Number starts with international prefix '00' + country code digits
        if (strpos($clean, '00' . $ccDigits) === 0) {
            return '+' . substr($clean, 2);
        }

        // Number already starts with country code digits (without + or 00)
        if (strpos($clean, $ccDigits) === 0 && strlen($clean) > strlen($ccDigits) + 4) {
            return '+' . $clean;
        }

        // Number starts with trunk prefix '0'
        if (strpos($clean, '0') === 0) {
            return $countryCode . substr($clean, 1);
        }

        // Plain local number without any prefix
        return $countryCode . $clean;
    }

    /**
     * Get the configured country code, checking both international mode and legacy setting.
     *
     * @return string|\WP_Error
     */
    private function getConfiguredCountryCode()
    {
        $countryCode = Option::getOption('mobile_county_code');

        if (!empty($countryCode) && $countryCode !== '0') {
            return $countryCode;
        }

        // Allow passing country_code from request when not configured in settings
        $requestCC = $this->get('country_code');
        if (!empty($requestCC)) {
            return sanitize_text_field($requestCC);
        }

        return new \WP_Error('missing_country_code', __('Please select a country code to continue.', 'wp-sms'));
    }
}
