<?php

namespace WP_SMS\Controller;

use WP_SMS\Helper;
use WP_SMS\Option;
use WP_SMS\Components\NumberParser;
use WP_SMS\Components\DateTime;

if (!defined('ABSPATH')) exit;

class NumberMigrationAjax extends AjaxControllerAbstract
{
    protected $action = 'wp_sms_number_migration';
    public $requiredFields = ['sub_action'];

    const BACKUP_OPTION_KEY = 'wpsms_number_migration_backup';
    const STATUS_OPTION_KEY = 'wpsms_number_migration_status';
    const LOCK_TRANSIENT    = 'wpsms_migration_lock';
    const LOCK_TTL_SECONDS  = 300; // 5 minutes
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
            case 'clear_backup':
                $this->clearBackup();
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
            [
                'key'         => 'outbox',
                'label'       => __('Outbox', 'wp-sms'),
                'table'       => "{$wpdb->prefix}sms_send",
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
            // Tell the frontend which copy variant to show for the country step. When
            // international_mobile is enabled we still need a CC for legacy local-format
            // data, but we must not imply the user has to change their input mode.
            $mode = Option::getOption('international_mobile') ? 'international_input' : 'default';
            wp_send_json_error([
                'code'    => $countryCode->get_error_code(),
                'message' => $countryCode->get_error_message(),
                'mode'    => $mode,
            ], 400);
            return;
        }

        $sources     = $this->getPhoneSources();
        $scanResults = [];
        $totalNeedFix    = 0;
        $totalAlreadyOk  = 0;
        $samples         = [];

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

            // Pull up to 3 example "before" values from the first source with need_fix > 0
            // so the UI can show concrete patterns ("What kind of numbers need fixing?")
            // without an extra round-trip.
            if (empty($samples) && $needFix > 0 && $source['type'] === 'single') {
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $sampleRows = $wpdb->get_col("SELECT `{$source['column']}` FROM `{$source['table']}` WHERE {$whereBase} AND `{$source['column']}` NOT LIKE '+%' LIMIT 3");
                if (!empty($sampleRows)) {
                    $samples = array_values(array_filter(array_map('strval', $sampleRows)));
                }
            }
        }

        $backup             = get_option(self::BACKUP_OPTION_KEY);
        $backupExists       = !empty($backup);
        $backupTimestamp    = null;
        $backupTimestampIso = null;
        if ($backupExists && !empty($backup['timestamp'])) {
            $backupTimestamp    = $this->formatLocalizedTimestamp($backup['timestamp']);
            $backupTimestampIso = $this->formatIsoTimestamp($backup['timestamp']);
        }

        // Detect whether the admin added/removed data sources since the last run.
        // Drives a "we're checking N new sources" banner on the Review step.
        $previousStatus      = get_option(self::STATUS_OPTION_KEY, []);
        $previousCounts      = isset($previousStatus['counts']) && is_array($previousStatus['counts'])
            ? $previousStatus['counts']
            : [];
        $currentSourceKeys   = array_keys($scanResults);
        $previousSourceKeys  = array_keys($previousCounts);
        $newSourcesSinceLast = $previousCounts
            ? array_values(array_diff($currentSourceKeys, $previousSourceKeys))
            : [];

        // Detect whether the plugin default country code changed since the last run.
        // If it did, we force the user to re-scan before applying changes.
        $ccChanged = false;
        if (!empty($previousStatus['country_code']) && $previousStatus['country_code'] !== $countryCode) {
            $ccChanged = true;
        }

        $lastRunHadErrors = !empty($previousStatus['errors']);

        wp_send_json_success([
            'country_code'              => $countryCode,
            'sources'                   => $scanResults,
            'total_need_fix'            => $totalNeedFix,
            'total_already_intl'        => $totalAlreadyOk,
            'total_records'             => $totalNeedFix + $totalAlreadyOk,
            'samples'                   => $samples,
            'backup_exists'             => $backupExists,
            'backup_timestamp'          => $backupTimestamp,
            'backup_timestamp_iso'      => $backupTimestampIso,
            'previous_run_sources'      => $previousSourceKeys,
            'new_sources_since_last'    => $newSourcesSinceLast,
            'cc_changed_since_last_run' => $ccChanged,
            'last_run_had_errors'       => $lastRunHadErrors,
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
        $allRows = [];

        foreach ($sources as $source) {
            $whereBase = isset($source['where_extra']) ? $source['where_extra'] : "{$source['column']} != ''";

            if ($source['type'] === 'single') {
                $nameSelect = $source['name_column'] ? ", {$source['name_column']} AS display_name" : ", '' AS display_name";

                // For usermeta, join with users table for name
                if ($source['key'] === 'usermeta') {
                    $rows = $wpdb->get_results(
                        "SELECT t.{$source['pk']} AS pk_val, t.{$source['column']} AS phone, u.display_name
                         FROM {$source['table']} t
                         LEFT JOIN {$wpdb->users} u ON t.user_id = u.ID
                         WHERE {$whereBase} AND t.{$source['column']} NOT LIKE '+%'
                         ORDER BY t.{$source['pk']} ASC"
                    );
                } else {
                    $rows = $wpdb->get_results(
                        "SELECT {$source['pk']} AS pk_val, {$source['column']} AS phone {$nameSelect}
                         FROM {$source['table']}
                         WHERE {$whereBase} AND {$source['column']} NOT LIKE '+%'
                         ORDER BY {$source['pk']} ASC"
                    );
                }

                foreach ($rows as $row) {
                    $migrated = $this->migrateNumber($row->phone, $countryCode);
                    $allRows[] = [
                        'source'   => $source['key'],
                        'label'    => $source['label'],
                        'id'       => (int) $row->pk_val,
                        'name'     => $row->display_name ?: '',
                        'original' => $row->phone,
                        'migrated' => $migrated,
                        'changed'  => $row->phone !== $migrated,
                    ];
                }
            } else {
                // CSV type
                $rows = $wpdb->get_results(
                    "SELECT {$source['pk']} AS pk_val, {$source['column']} AS phone
                     FROM {$source['table']}
                     WHERE {$source['column']} IS NOT NULL AND {$source['column']} != ''
                     ORDER BY {$source['pk']} ASC"
                );

                foreach ($rows as $row) {
                    $numbers  = array_map('trim', explode(',', $row->phone));
                    $migrated = array_map(function ($n) use ($countryCode) {
                        return !empty($n) ? $this->migrateNumber($n, $countryCode) : $n;
                    }, $numbers);
                    $migratedStr = implode(',', $migrated);

                    if ($row->phone !== $migratedStr) {
                        $allRows[] = [
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
            }
        }

        // Paginate the combined results
        $preview = array_slice($allRows, $offset, $perPage);

        wp_send_json_success([
            'preview'      => $preview,
            'page'         => $page,
            'per_page'     => $perPage,
            'total'        => count($allRows),
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

        // Concurrency guard: backups now span tables, options, and post_meta. A double-execute
        // would interleave writes against the same backup option and could corrupt revert state.
        if (get_transient(self::LOCK_TRANSIENT)) {
            wp_send_json_error([
                'code'    => 'migration_in_progress',
                'message' => __('Another migration is already running. Please wait for it to finish before retrying.', 'wp-sms'),
            ], 409);
        }
        set_transient(self::LOCK_TRANSIENT, time(), self::LOCK_TTL_SECONDS);

        $sources = $this->getPhoneSources();
        $backup  = [
            'timestamp'    => current_time('mysql'),
            'country_code' => $countryCode,
            'tables'       => [],
            'options'      => [],
            'postmeta'     => [],
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
                // Refresh the lock TTL per batch so a legitimately long-running migration
                // on a huge dataset can't let another admin acquire the lock mid-flight.
                set_transient(self::LOCK_TRANSIENT, time(), self::LOCK_TTL_SECONDS);

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

        // Sweep storage locations real users hit that aren't in $sources: the admin's own
        // notification number (single value in wpsms_settings) and the per-post scheduled
        // recipient lists (CSV string + serialized array post_meta).
        $optionsResult = $this->migrateAdminMobileNumberOption($countryCode);
        if ($optionsResult['changed']) {
            $backup['options'][$optionsResult['backup_key']] = $optionsResult['backup_entry'];
            $migrationCounts['admin_mobile_number'] = 1;
            $totalMigrated++;
        } else {
            $migrationCounts['admin_mobile_number'] = 0;
        }

        $postMetaResult = $this->migrateScheduledPostMeta($countryCode, $errors);
        $migrationCounts['scheduled_send_to']    = $postMetaResult['scheduled_send_to_count'];
        $migrationCounts['scheduled_receivers']  = $postMetaResult['scheduled_receivers_count'];
        $totalMigrated                          += $postMetaResult['scheduled_send_to_count'];
        $totalMigrated                          += $postMetaResult['scheduled_receivers_count'];

        foreach ($postMetaResult['backup_entries'] as $backupKey => $backupEntry) {
            $backup['postmeta'][$backupKey] = $backupEntry;
        }

        // Save backup and invalidate notice cache
        update_option(self::BACKUP_OPTION_KEY, $backup, false);
        delete_transient('wpsms_local_number_count');

        // Persist the country code to plugin settings so future numbers use it
        Option::updateOption('mobile_county_code', $countryCode);

        // Save migration status
        $completedAt = current_time('mysql');
        update_option(self::STATUS_OPTION_KEY, [
            'status'          => 'completed',
            'timestamp'       => $completedAt,
            'country_code'    => $countryCode,
            'counts'          => $migrationCounts,
            'total_migrated'  => $totalMigrated,
            'errors'          => $errors,
        ], false);

        delete_transient(self::LOCK_TRANSIENT);

        // Count the number of sources that actually migrated rows — surfaced on the
        // Done step ("We updated X numbers across Y sources").
        $sourcesTouched = 0;
        foreach ($migrationCounts as $count) {
            if ($count > 0) {
                $sourcesTouched++;
            }
        }

        wp_send_json_success([
            'counts'               => $migrationCounts,
            'total_migrated'       => $totalMigrated,
            'sources_touched'      => $sourcesTouched,
            'errors'               => $errors,
            'backup_created'       => true,
            'timestamp'            => $this->formatLocalizedTimestamp($completedAt),
            'backup_timestamp'     => $this->formatLocalizedTimestamp($completedAt),
            'backup_timestamp_iso' => $this->formatIsoTimestamp($completedAt),
        ]);
    }

    /**
     * Migrate the admin's notification mobile number stored in wpsms_settings.
     *
     * Backup key namespace: option:wpsms_settings:admin_mobile_number
     *
     * @param string $countryCode
     * @return array{changed: bool, backup_key?: string, backup_entry?: array}
     */
    private function migrateAdminMobileNumberOption($countryCode)
    {
        $current = Option::getOption('admin_mobile_number');

        if (empty($current) || strpos((string) $current, '+') === 0) {
            return ['changed' => false];
        }

        $migrated = $this->migrateNumber((string) $current, $countryCode);

        if ($migrated === $current) {
            return ['changed' => false];
        }

        Option::updateOption('admin_mobile_number', $migrated);

        return [
            'changed'      => true,
            'backup_key'   => 'option:wpsms_settings:admin_mobile_number',
            'backup_entry' => [
                'option'    => 'wpsms_settings',
                'json_path' => 'admin_mobile_number',
                'original'  => $current,
                'migrated'  => $migrated,
            ],
        ];
    }

    /**
     * Migrate scheduled post meta — wpsms_scheduled_send_to (CSV) and wpsms_scheduled_receivers
     * (serialized array). Skips orphaned post_meta rows whose parent post no longer exists.
     *
     * Backup key namespaces:
     *   postmeta:<post_id>:wpsms_scheduled_send_to
     *   postmeta:<post_id>:wpsms_scheduled_receivers
     *
     * @param string $countryCode
     * @param array  $errors  Errors collected during the sweep (passed by reference)
     * @return array{scheduled_send_to_count: int, scheduled_receivers_count: int, backup_entries: array}
     */
    private function migrateScheduledPostMeta($countryCode, array &$errors)
    {
        global $wpdb;

        $sendToCount    = 0;
        $receiversCount = 0;
        $backupEntries  = [];

        // wpsms_scheduled_send_to — CSV string of recipients. INNER JOIN against wp_posts
        // both filters out orphans (deleted parent posts) and avoids an N+1 get_post() call.
        $sendToRows = $wpdb->get_results($wpdb->prepare(
            "SELECT pm.post_id, pm.meta_value
             FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
             WHERE pm.meta_key = %s AND pm.meta_value != ''",
            'wpsms_scheduled_send_to'
        ));

        foreach ($sendToRows as $row) {
            $numbers  = array_map('trim', explode(',', (string) $row->meta_value));
            $migrated = array_map(function ($n) use ($countryCode) {
                if ($n === '') {
                    return $n;
                }
                if (strpos($n, '+') === 0) {
                    return $n;
                }
                return $this->migrateNumber($n, $countryCode);
            }, $numbers);

            $migratedStr = implode(',', $migrated);
            if ($migratedStr === $row->meta_value) {
                continue;
            }

            $updateResult = update_post_meta($row->post_id, 'wpsms_scheduled_send_to', $migratedStr, $row->meta_value);
            if ($updateResult === false) {
                $errors[] = sprintf('post_meta wpsms_scheduled_send_to for post #%d: update failed', $row->post_id);
                continue;
            }

            $key                 = 'postmeta:' . $row->post_id . ':wpsms_scheduled_send_to';
            $backupEntries[$key] = [
                'post_id'  => (int) $row->post_id,
                'meta_key' => 'wpsms_scheduled_send_to',
                'original' => $row->meta_value,
                'migrated' => $migratedStr,
            ];
            $sendToCount++;
        }

        // wpsms_scheduled_receivers — serialized array of recipients. Same INNER JOIN trick.
        $receiversRows = $wpdb->get_results($wpdb->prepare(
            "SELECT pm.post_id, pm.meta_value
             FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
             WHERE pm.meta_key = %s AND pm.meta_value != ''",
            'wpsms_scheduled_receivers'
        ));

        foreach ($receiversRows as $row) {
            // Guarded unserialize: skip corrupted/non-array values rather than crashing.
            $original = $row->meta_value;
            $decoded  = @unserialize($original);
            if ($decoded === false && $original !== 'b:0;') {
                continue;
            }
            if (!is_array($decoded)) {
                continue;
            }

            $migrated = [];
            foreach ($decoded as $k => $v) {
                if (!is_string($v) || $v === '') {
                    $migrated[$k] = $v;
                    continue;
                }
                if (strpos($v, '+') === 0) {
                    $migrated[$k] = $v;
                    continue;
                }
                $migrated[$k] = $this->migrateNumber($v, $countryCode);
            }

            if ($migrated === $decoded) {
                continue;
            }

            $updateResult = update_post_meta($row->post_id, 'wpsms_scheduled_receivers', $migrated, $decoded);
            if ($updateResult === false) {
                $errors[] = sprintf('post_meta wpsms_scheduled_receivers for post #%d: update failed', $row->post_id);
                continue;
            }

            $key                 = 'postmeta:' . $row->post_id . ':wpsms_scheduled_receivers';
            $backupEntries[$key] = [
                'post_id'  => (int) $row->post_id,
                'meta_key' => 'wpsms_scheduled_receivers',
                'original' => $original,
                'migrated' => $migrated,
            ];
            $receiversCount++;
        }

        return [
            'scheduled_send_to_count'   => $sendToCount,
            'scheduled_receivers_count' => $receiversCount,
            'backup_entries'            => $backupEntries,
        ];
    }

    /**
     * Get migration status.
     */
    private function getStatus()
    {
        $status = get_option(self::STATUS_OPTION_KEY, [
            'status' => 'not_started',
        ]);

        $backup             = get_option(self::BACKUP_OPTION_KEY);
        $backupTimestamp    = null;
        $backupTimestampIso = null;
        if (!empty($backup) && !empty($backup['timestamp'])) {
            $backupTimestamp    = $this->formatLocalizedTimestamp($backup['timestamp']);
            $backupTimestampIso = $this->formatIsoTimestamp($backup['timestamp']);
        }
        $status['backup_exists']        = !empty($backup);
        $status['backup_timestamp']     = $backupTimestamp;
        $status['backup_timestamp_iso'] = $backupTimestampIso;

        // `running` flag lets the frontend collide-detect another admin running execute
        // right now, so it can auto-poll until the lock clears and jump to Done.
        $status['running'] = (bool) get_transient(self::LOCK_TRANSIENT);

        $status['last_run_had_errors'] = !empty($status['errors']);

        wp_send_json_success($status);
    }

    /**
     * Clear the migration backup without running revert.
     *
     * Used by the "Clear old backup" affordance when an admin wants to drop a stale
     * backup before running a fresh migration.
     */
    private function clearBackup()
    {
        if (get_transient(self::LOCK_TRANSIENT)) {
            wp_send_json_error([
                'code'    => 'migration_in_progress',
                'message' => __('Another migration is already running. Please wait for it to finish before retrying.', 'wp-sms'),
            ], 409);
        }

        delete_option(self::BACKUP_OPTION_KEY);

        wp_send_json_success([
            'cleared' => true,
        ]);
    }

    /**
     * Format a mysql timestamp (site time) into a human-readable string with the
     * site's timezone abbreviation appended — surfaced on the wizard's Done step
     * so admins can confirm the backup time without needing to compute it.
     *
     * @param string $mysqlTimestamp
     * @return string
     */
    private function formatLocalizedTimestamp($mysqlTimestamp)
    {
        if (empty($mysqlTimestamp)) {
            return '';
        }
        try {
            return sprintf(
                '%s (%s)',
                DateTime::format($mysqlTimestamp, ['include_time' => true]),
                wp_timezone_string()
            );
        } catch (\Exception $e) {
            return (string) $mysqlTimestamp;
        }
    }

    /**
     * Format a mysql timestamp as ISO 8601 — used by the revert dialog so the JS
     * side can pass it to Date constructors without parsing site-format strings.
     *
     * @param string $mysqlTimestamp
     * @return string
     */
    private function formatIsoTimestamp($mysqlTimestamp)
    {
        if (empty($mysqlTimestamp)) {
            return '';
        }
        $unix = strtotime($mysqlTimestamp);
        return $unix === false ? (string) $mysqlTimestamp : gmdate('c', $unix);
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

        // Backup key namespace: option:<option_name>:<json_path>. json_path is currently
        // always a top-level key inside wpsms_settings.
        if (!empty($backup['options'])) {
            foreach ($backup['options'] as $backupKey => $entry) {
                $optionName = isset($entry['option']) ? $entry['option'] : '';
                $jsonPath   = isset($entry['json_path']) ? $entry['json_path'] : '';

                if ($optionName === 'wpsms_settings' && $jsonPath !== '') {
                    Option::updateOption($jsonPath, $entry['original']);
                    $totalReverted++;
                } elseif ($optionName !== '') {
                    update_option($optionName, $entry['original']);
                    $totalReverted++;
                } else {
                    $errors[] = sprintf('option backup entry %s: malformed', $backupKey);
                }
            }
        }

        // Backup key namespace: postmeta:<post_id>:<meta_key>.
        if (!empty($backup['postmeta'])) {
            foreach ($backup['postmeta'] as $backupKey => $entry) {
                $postId  = isset($entry['post_id']) ? (int) $entry['post_id'] : 0;
                $metaKey = isset($entry['meta_key']) ? $entry['meta_key'] : '';

                if ($postId === 0 || $metaKey === '') {
                    $errors[] = sprintf('postmeta backup entry %s: malformed', $backupKey);
                    continue;
                }

                // For wpsms_scheduled_receivers we stored the original as a serialized string.
                // update_post_meta will re-serialize an array, so unserialize first to round-trip
                // back to the exact pre-migration form.
                $value = $entry['original'];
                if ($metaKey === 'wpsms_scheduled_receivers' && is_string($value)) {
                    $unserialized = @unserialize($value);
                    if (is_array($unserialized)) {
                        $value = $unserialized;
                    }
                }

                $result = update_post_meta($postId, $metaKey, $value);
                if ($result !== false) {
                    $totalReverted++;
                } else {
                    $errors[] = sprintf('postmeta %s for post #%d: revert failed', $metaKey, $postId);
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
