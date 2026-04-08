<?php

namespace WSms\System;

use WSms\Auth\SettingsRepository;
use WSms\Database\Connection;
use WSms\Exception\NotFoundException;
use WSms\Flow\Trigger\TriggerRegistry;
use WSms\Messaging\Gateway\GatewayRegistry;
use WSms\PhoneRestriction\RestrictionSettings;
use WSms\Support\IpResolver;
use WSms\Webhook\WebhookRepository;

defined('ABSPATH') || exit;

class SystemHealthService
{
    private const CACHE_KEY = 'wsms_system_health';
    private const CACHE_TTL = 30;

    private const RECURRING_TASKS = [
        'wsms_check_scheduled_campaigns' => ['label' => 'Campaign Scheduler', 'interval' => 60,     'tolerance' => 3],
        'wsms_daily_cleanup'             => ['label' => 'Daily Cleanup',      'interval' => 86400,  'tolerance' => 1.5],
        'wsms_phone_db_update'           => ['label' => 'Phone DB Update',    'interval' => 604800, 'tolerance' => 1.5],
        'wsms_suppression_poll'          => ['label' => 'Suppression Sync',   'interval' => 3600,   'tolerance' => 2],
    ];

    /**
     * Translate task labels (cannot use __() inside class constants).
     */
    private static function getTranslatedTaskLabels(): array
    {
        return [
            'Campaign Scheduler' => __('Campaign Scheduler', 'wp-sms'),
            'Daily Cleanup'      => __('Daily Cleanup', 'wp-sms'),
            'Phone DB Update'    => __('Phone DB Update', 'wp-sms'),
            'Suppression Sync'   => __('Suppression Sync', 'wp-sms'),
        ];
    }

    private const HIGH_SEVERITY_TYPES = ['send_message', 'execute_flow_step', 'evaluate_trigger'];

    /** All plugin tables that should exist. */
    private const EXPECTED_TABLES = [
        Connection::TABLE_USER_FACTORS,
        Connection::TABLE_VERIFICATIONS,
        Connection::TABLE_AUTH_LOGS,
        Connection::TABLE_FLOWS,
        Connection::TABLE_FLOW_EXECUTIONS,
        Connection::TABLE_MESSAGE_LOGS,
        Connection::TABLE_CONTACTS,
        Connection::TABLE_TAGS,
        Connection::TABLE_CONTACT_TAG,
        Connection::TABLE_LISTS,
        Connection::TABLE_CAMPAIGNS,
    ];

    public function __construct(
        private readonly Connection $db,
        private readonly GatewayRegistry $gatewayRegistry,
        private readonly SettingsRepository $authSettings,
        private readonly WebhookRepository $webhookRepo,
        private readonly RestrictionSettings $restrictionSettings,
        private readonly TriggerRegistry $triggerRegistry,
    ) {
    }

    public function getHealthData(): array
    {
        $cached = get_transient(self::CACHE_KEY);

        if ($cached !== false) {
            return $cached;
        }

        $asAvailable = class_exists(\ActionScheduler::class) && \ActionScheduler::is_initialized();

        $checks = $this->buildChecks($asAvailable);

        if (!$asAvailable) {
            $data = [
                'overall_status'   => $this->computeOverallStatus($checks),
                'checks'           => $checks,
                'cron_health'      => ['wp_cron_disabled' => defined('DISABLE_WP_CRON') && DISABLE_WP_CRON, 'as_runner_active' => false, 'last_as_run' => null, 'status' => 'unknown'],
                'heartbeat'        => [],
                'queue'            => ['totals' => [], 'by_type' => [], 'stuck_jobs' => []],
                'failed_jobs'      => ['items' => [], 'total' => 0, 'error_groups' => []],
                'recent_activity'  => [],
                'active_campaigns' => $this->buildActiveCampaigns(),
                'generated_at'     => gmdate('c'),
            ];
        } else {
            $data = [
                'overall_status'   => $this->computeOverallStatus($checks),
                'checks'           => $checks,
                'cron_health'      => $this->detectCronHealth(),
                'heartbeat'        => $this->buildHeartbeat(),
                'queue'            => $this->buildQueueOverview(),
                'failed_jobs'      => $this->buildFailedJobs(50),
                'recent_activity'  => $this->buildRecentActivity(30),
                'active_campaigns' => $this->buildActiveCampaigns(),
                'generated_at'     => gmdate('c'),
            ];
        }

        set_transient(self::CACHE_KEY, $data, self::CACHE_TTL);

        return $data;
    }

    public function retryFailedJob(int $actionId): void
    {
        $this->requireActionScheduler();

        $action = $this->getFailedAction($actionId);

        as_enqueue_async_action($action->get_hook(), $action->get_args(), 'wsms');
        \ActionScheduler::store()->delete_action($actionId);

        delete_transient(self::CACHE_KEY);
    }

    public function dismissFailedJob(int $actionId): void
    {
        $this->requireActionScheduler();

        $this->getFailedAction($actionId);

        \ActionScheduler::store()->delete_action($actionId);

        delete_transient(self::CACHE_KEY);
    }

    // ── Lazy endpoints ───────────────────────────────────────────────

    public function getGatewayCredits(): array
    {
        $result = [];
        foreach ($this->gatewayRegistry->getConfigured() as $gateway) {
            try {
                $credit = $gateway->getCredit();
                $result[$gateway->getId()] = [
                    'gateway_id' => $gateway->getId(),
                    'name'       => $gateway->getName(),
                    'credit'     => $credit,
                ];
            } catch (\Throwable $e) {
                $result[$gateway->getId()] = [
                    'gateway_id' => $gateway->getId(),
                    'name'       => $gateway->getName(),
                    'credit'     => null,
                    'error'      => $e->getMessage(),
                ];
            }
        }
        return $result;
    }

    public function getTableSizes(): array
    {
        $prefix = $this->db->wpdb()->prefix;

        $rows = $this->db->getResults(
            "SELECT TABLE_NAME AS table_name,
                    TABLE_ROWS AS row_count,
                    ROUND(DATA_LENGTH / 1048576, 2) AS data_size_mb,
                    ROUND(INDEX_LENGTH / 1048576, 2) AS index_size_mb
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME LIKE %s
             ORDER BY DATA_LENGTH DESC",
            $prefix . 'wsms\_%',
        );

        $result = [];
        foreach ($rows as $row) {
            $result[] = [
                'table'         => $row['table_name'],
                'rows'          => (int) $row['row_count'],
                'data_size_mb'  => (float) $row['data_size_mb'],
                'index_size_mb' => (float) $row['index_size_mb'],
            ];
        }

        return $result;
    }

    // ── Health check builders ────────────────────────────────────────

    private function buildChecks(bool $asAvailable): array
    {
        $activeFlows = $this->fetchActiveFlows();

        return [
            'messaging'     => $this->buildMessagingChecks($activeFlows),
            'auth'          => $this->buildAuthChecks(),
            'background'    => $this->buildBackgroundChecks($asAvailable),
            'configuration' => $this->buildConfigChecks($activeFlows),
            'data_security' => $this->buildDataSecurityChecks(),
        ];
    }

    /**
     * Fetch active flows once for shared use across messaging and config checks.
     * Each flow's published_steps is pre-decoded from JSON.
     */
    private function fetchActiveFlows(): array
    {
        $flowTable = $this->db->table(Connection::TABLE_FLOWS);
        $rows = $this->db->getResults(
            "SELECT id, name, trigger_type, published_steps FROM {$flowTable} WHERE status = 'active' AND published_steps IS NOT NULL",
        );

        return array_map(fn(array $row) => [
            ...$row,
            'parsed_steps' => json_decode($row['published_steps'] ?? '[]', true) ?: [],
        ], $rows);
    }

    private function buildMessagingChecks(array $activeFlows): array
    {
        $checks = [];
        $configuredChannels = $this->gatewayRegistry->getConfiguredChannels();
        $campaignTable = $this->db->table(Connection::TABLE_CAMPAIGNS);
        $msgTable = $this->db->table(Connection::TABLE_MESSAGE_LOGS);

        // msg_channel_gateway — Default gateway per in-use channel
        // Only check channels actually in use: configured gateways, active campaigns, active flows, auth verification
        $inUseChannels = $configuredChannels;

        // Channels needed by auth verification settings
        $settings = $this->authSettings->all();
        foreach (['phone' => 'sms', 'email' => 'email'] as $authChannel => $gwChannel) {
            $chSettings = $settings[$authChannel] ?? [];
            if (!empty($chSettings['verify_at_signup']) || !empty($chSettings['verify_at_login'])) {
                $inUseChannels[] = $gwChannel;
            }
        }

        // Channels needed by active campaigns
        $campaignChannels = $this->db->getCol(
            "SELECT DISTINCT channel FROM {$campaignTable} WHERE status IN ('sending', 'scheduled', 'paused')",
        );
        $inUseChannels = array_merge($inUseChannels, $campaignChannels);

        // Channels needed by active flows
        foreach ($activeFlows as $flow) {
            foreach ($flow['parsed_steps'] as $step) {
                if (($step['type'] ?? '') === 'action' && ($step['action'] ?? '') === 'send_message') {
                    $channel = $step['config']['channel'] ?? null;
                    if ($channel) {
                        $inUseChannels[] = $channel;
                    }
                }
            }
        }

        $inUseChannels = array_unique($inUseChannels);

        if (empty($inUseChannels)) {
            $checks[] = $this->makeCheck('msg_channel_gateway', __('Channel gateways configured', 'wp-sms'), 'skip', __('No messaging channels configured yet.', 'wp-sms'));
        } else {
            $channelDetails = [];
            $missingChannels = [];
            foreach ($inUseChannels as $channel) {
                $default = $this->gatewayRegistry->getDefault($channel);
                if ($default) {
                    $channelDetails[$channel] = $default->getName();
                } else {
                    $channelDetails[$channel] = __('configured but no default', 'wp-sms');
                    $missingChannels[] = $channel;
                }
            }

            if (empty($missingChannels)) {
                $checks[] = $this->makeCheck('msg_channel_gateway', __('Channel gateways configured', 'wp-sms'), 'pass', __('All channels have default gateways assigned.', 'wp-sms'), details: ['channels' => $channelDetails]);
            } else {
                $checks[] = $this->makeCheck('msg_channel_gateway', __('Channel gateways configured', 'wp-sms'), 'warn',
                    sprintf(__('%d channel(s) missing a default gateway.', 'wp-sms'), count($missingChannels)),
                    sprintf(__('Go to Gateway settings and configure a gateway for %s.', 'wp-sms'), implode(', ', $missingChannels)),
                    $this->settingsLink('gateways'),
                    ['channels' => $channelDetails],
                );
            }
        }

        // msg_gateway_credentials — Configured gateways have valid credentials
        $configuredGateways = $this->gatewayRegistry->getConfigured();

        if (empty($configuredGateways)) {
            $checks[] = $this->makeCheck('msg_gateway_credentials', __('Gateway credentials valid', 'wp-sms'), 'skip', __('No gateways configured yet.', 'wp-sms'));
        } else {
            $incompleteGateways = [];
            foreach ($configuredGateways as $gateway) {
                if (!$gateway->isConfigured()) {
                    $incompleteGateways[] = $gateway->getName();
                }
            }

            if (empty($incompleteGateways)) {
                $checks[] = $this->makeCheck('msg_gateway_credentials', __('Gateway credentials valid', 'wp-sms'), 'pass', __('All configured gateways have valid credentials.', 'wp-sms'));
            } else {
                $checks[] = $this->makeCheck('msg_gateway_credentials', __('Gateway credentials valid', 'wp-sms'), 'fail',
                    sprintf(__('Incomplete credentials: %s.', 'wp-sms'), implode(', ', $incompleteGateways)),
                    __('Update gateway credentials in Gateway settings.', 'wp-sms'),
                    $this->settingsLink('gateways'),
                );
            }
        }

        // msg_delivery_rate_24h — 24-hour delivery rate (includes 7-day trend in details)
        $checks[] = $this->buildDeliveryRateCheck('24h', '24 HOUR');

        // msg_queue_age — Messages stuck in pending
        $queueData = $this->db->getRow(
            "SELECT MIN(created_at) AS oldest_queued, COUNT(*) AS queued_count
             FROM {$msgTable}
             WHERE status = 'queued'",
        );

        $queuedCount = (int) ($queueData['queued_count'] ?? 0);
        if ($queuedCount === 0) {
            $checks[] = $this->makeCheck('msg_queue_age', __('Message queue age', 'wp-sms'), 'pass', __('No messages stuck in queue.', 'wp-sms'));
        } else {
            $oldestMinutes = $queueData['oldest_queued']
                ? (int) ((time() - strtotime($queueData['oldest_queued'])) / 60)
                : 0;

            if ($oldestMinutes > 120) {
                $checks[] = $this->makeCheck('msg_queue_age', __('Message queue age', 'wp-sms'), 'fail',
                    sprintf(__('%d messages queued, oldest %d minutes ago.', 'wp-sms'), $queuedCount, $oldestMinutes),
                    __('Messages are stuck in queue. Background processing may be stalled.', 'wp-sms'),
                    details: ['queued_count' => $queuedCount, 'oldest_minutes' => $oldestMinutes],
                );
            } elseif ($oldestMinutes > 30) {
                $checks[] = $this->makeCheck('msg_queue_age', __('Message queue age', 'wp-sms'), 'warn',
                    sprintf(__('%d messages queued, oldest %d minutes ago.', 'wp-sms'), $queuedCount, $oldestMinutes),
                    __('Messages are taking longer than expected. Check background processing.', 'wp-sms'),
                    details: ['queued_count' => $queuedCount, 'oldest_minutes' => $oldestMinutes],
                );
            } else {
                $checks[] = $this->makeCheck('msg_queue_age', __('Message queue age', 'wp-sms'), 'pass',
                    sprintf(__('%d messages in queue, processing normally.', 'wp-sms'), $queuedCount),
                );
            }
        }

        // msg_campaign_stuck — Campaigns stuck in sending
        $stuckCampaigns = $this->db->getResults(
            "SELECT id, name, sent_count, delivered_count, failed_count, started_at
             FROM {$campaignTable}
             WHERE status = 'sending'
               AND TIMESTAMPDIFF(MINUTE, started_at, NOW()) > 30
               AND (sent_count + delivered_count + failed_count) = 0",
        );

        if (empty($stuckCampaigns)) {
            $checks[] = $this->makeCheck('msg_campaign_stuck', __('Campaign progress', 'wp-sms'), 'pass', __('No stuck campaigns detected.', 'wp-sms'));
        } else {
            $names = array_map(fn($c) => $c['name'], $stuckCampaigns);
            $isStuckLong = false;
            foreach ($stuckCampaigns as $c) {
                $elapsed = (int) ((time() - strtotime($c['started_at'])) / 60);
                if ($elapsed > 60) {
                    $isStuckLong = true;
                    break;
                }
            }
            $checks[] = $this->makeCheck('msg_campaign_stuck', __('Campaign progress', 'wp-sms'), $isStuckLong ? 'fail' : 'warn',
                sprintf(__('%d campaign(s) stuck: %s.', 'wp-sms'), count($stuckCampaigns), implode(', ', $names)),
                __('Try pausing and resuming the stuck campaign, or check Failed Jobs.', 'wp-sms'),
                $this->settingsLink('campaigns'),
                ['stuck_campaigns' => $names],
            );
        }

        // msg_suppression_rate — Suppression rate
        $suppData = $this->db->getRow(
            "SELECT COUNT(*) AS total,
                    SUM(CASE WHEN status IN ('bounced', 'suppressed') THEN 1 ELSE 0 END) AS suppressed
             FROM {$msgTable}
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)",
        );

        $totalMsgs = (int) ($suppData['total'] ?? 0);
        $suppressed = (int) ($suppData['suppressed'] ?? 0);

        if ($totalMsgs === 0) {
            $checks[] = $this->makeCheck('msg_suppression_rate', __('Suppression rate', 'wp-sms'), 'skip', __('No messages sent in the last 24 hours.', 'wp-sms'));
        } else {
            $rate = ($suppressed / $totalMsgs) * 100;
            if ($rate > 20) {
                $checks[] = $this->makeCheck('msg_suppression_rate', __('Suppression rate', 'wp-sms'), 'fail',
                    sprintf(__('%.1f%% suppression rate (%d of %d messages).', 'wp-sms'), $rate, $suppressed, $totalMsgs),
                    __('High suppression rate may indicate a data quality issue. Review your contact list.', 'wp-sms'),
                );
            } elseif ($rate > 5) {
                $checks[] = $this->makeCheck('msg_suppression_rate', __('Suppression rate', 'wp-sms'), 'warn',
                    sprintf(__('%.1f%% suppression rate (%d of %d messages).', 'wp-sms'), $rate, $suppressed, $totalMsgs),
                    __('Review your contact list for bounced addresses and check suppression settings.', 'wp-sms'),
                );
            } else {
                $checks[] = $this->makeCheck('msg_suppression_rate', __('Suppression rate', 'wp-sms'), 'pass',
                    sprintf(__('%.1f%% suppression rate — within normal range.', 'wp-sms'), $rate),
                );
            }
        }

        // msg_gateway_credit — Lazy check (not fetched here)
        $checks[] = $this->makeCheck('msg_gateway_credit', __('Gateway credit/balance', 'wp-sms'), 'skip', __('Click "Check balance" to query gateway balances.', 'wp-sms'), lazy: true);

        return $checks;
    }

    private function buildAuthChecks(): array
    {
        $checks = [];
        $settings = $this->authSettings->all();

        if (empty($settings['auth_enabled'])) {
            return [$this->makeCheck(
                'auth_disabled',
                __('Authentication', 'wp-sms'),
                'skip',
                __('Authentication is disabled. Enable it in Authentication → Channels.', 'wp-sms'),
            )];
        }

        // auth_primary_methods — At least one login method available
        $passwordEnabled = !empty($settings['password']['enabled']);
        $phoneLogin = !empty($settings['phone']['enabled']) && ($settings['phone']['usage'] ?? '') === 'login';
        $emailLogin = !empty($settings['email']['enabled']) && ($settings['email']['usage'] ?? '') === 'login';
        $methodDetails = [];
        if ($passwordEnabled) $methodDetails['password'] = __('enabled', 'wp-sms');
        if ($phoneLogin) $methodDetails['phone'] = __('enabled', 'wp-sms');
        if ($emailLogin) $methodDetails['email'] = __('enabled', 'wp-sms');

        // Check social providers
        $socialProviders = $settings['social'] ?? [];
        foreach ($socialProviders as $provider => $config) {
            if (!empty($config['enabled'])) {
                $methodDetails[$provider] = __('enabled', 'wp-sms');
            }
        }

        if (empty($methodDetails)) {
            $checks[] = $this->makeCheck('auth_primary_methods', __('Login methods available', 'wp-sms'), 'fail',
                __('No sign-in methods are available. Users cannot log in.', 'wp-sms'),
                __('Enable at least one method in Authentication settings.', 'wp-sms'),
                $this->settingsLink('channels'),
                ['methods' => $methodDetails],
            );
        } else {
            $checks[] = $this->makeCheck('auth_primary_methods', __('Login methods available', 'wp-sms'), 'pass',
                sprintf(__('%d login method(s) available.', 'wp-sms'), count($methodDetails)),
                details: ['methods' => $methodDetails],
            );
        }

        // auth_mfa_gateway — MFA channels have delivery gateways
        $mfaSettings = $settings['mfa'] ?? [];
        $mfaEnabled = !empty($mfaSettings['enabled']);
        $configuredChannels = $this->gatewayRegistry->getConfiguredChannels();

        if ($mfaEnabled) {
            $mfaChannelStatus = [];
            $missingGateways = [];

            // Phone MFA needs SMS/voice gateway
            $phoneMfa = !empty($settings['phone']['enabled']) && ($settings['phone']['usage'] ?? '') === 'mfa';
            if ($phoneMfa) {
                $hasGw = in_array('sms', $configuredChannels) || in_array('voice', $configuredChannels);
                $mfaChannelStatus['phone'] = $hasGw ? 'ok' : 'missing';
                if (!$hasGw) $missingGateways[] = 'phone';
            }

            // Email MFA needs email gateway
            $emailMfa = !empty($settings['email']['enabled']) && ($settings['email']['usage'] ?? '') === 'mfa';
            if ($emailMfa) {
                $hasGw = in_array('email', $configuredChannels);
                $mfaChannelStatus['email'] = $hasGw ? 'ok' : 'missing';
                if (!$hasGw) $missingGateways[] = 'email';
            }

            $requiredRoles = $mfaSettings['required_roles'] ?? [];
            if (!empty($missingGateways) && !empty($requiredRoles)) {
                $checks[] = $this->makeCheck('auth_mfa_gateway', __('MFA delivery gateways', 'wp-sms'), 'fail',
                    sprintf(__('MFA is required but no gateway for: %s.', 'wp-sms'), implode(', ', $missingGateways)),
                    __('Configure a gateway or set an OTP gateway in Auth settings.', 'wp-sms'),
                    $this->settingsLink('channels'),
                    ['channels' => $mfaChannelStatus],
                );
            } elseif (!empty($missingGateways)) {
                $checks[] = $this->makeCheck('auth_mfa_gateway', __('MFA delivery gateways', 'wp-sms'), 'warn',
                    sprintf(__('MFA channel(s) without gateway: %s.', 'wp-sms'), implode(', ', $missingGateways)),
                    __('Configure a gateway for full MFA support.', 'wp-sms'),
                    $this->settingsLink('gateways'),
                    ['channels' => $mfaChannelStatus],
                );
            } else {
                $checks[] = $this->makeCheck('auth_mfa_gateway', __('MFA delivery gateways', 'wp-sms'), 'pass',
                    __('All MFA channels have configured gateways.', 'wp-sms'),
                    details: ['channels' => $mfaChannelStatus],
                );
            }
        } else {
            $checks[] = $this->makeCheck('auth_mfa_gateway', __('MFA delivery gateways', 'wp-sms'), 'skip',
                __('MFA is not enabled.', 'wp-sms'),
            );
        }

        // auth_mfa_policy — MFA policy coherence
        if ($mfaEnabled) {
            $enrollmentTiming = $mfaSettings['enrollment_timing'] ?? 'voluntary';
            $requiredRoles = $mfaSettings['required_roles'] ?? [];
            $graceDays = (int) ($mfaSettings['grace_period_days'] ?? 14);

            if ($enrollmentTiming === 'on_registration' && empty($requiredRoles)) {
                $checks[] = $this->makeCheck('auth_mfa_policy', __('MFA policy coherent', 'wp-sms'), 'warn',
                    __('MFA policy is active but no roles are selected — MFA will not apply to anyone.', 'wp-sms'),
                    __('Configure target roles in Security settings.', 'wp-sms'),
                    $this->settingsLink('channels'),
                );
            } elseif ($enrollmentTiming === 'grace_period' && $graceDays <= 0) {
                $checks[] = $this->makeCheck('auth_mfa_policy', __('MFA policy coherent', 'wp-sms'), 'warn',
                    __('Grace period is 0 days — users will be locked out immediately.', 'wp-sms'),
                    __('Set a grace period > 0 in Security settings.', 'wp-sms'),
                    $this->settingsLink('channels'),
                );
            } else {
                $checks[] = $this->makeCheck('auth_mfa_policy', __('MFA policy coherent', 'wp-sms'), 'pass',
                    __('MFA policy is valid.', 'wp-sms'),
                );
            }
        } else {
            $checks[] = $this->makeCheck('auth_mfa_policy', __('MFA policy coherent', 'wp-sms'), 'skip',
                __('MFA is not enabled.', 'wp-sms'),
            );
        }

        // auth_verify_gateway — Verification channels have gateways
        $verifyIssues = [];
        foreach (['phone', 'email'] as $channel) {
            $chSettings = $settings[$channel] ?? [];
            $verifyAtSignup = !empty($chSettings['verify_at_signup']);
            $verifyAtLogin = !empty($chSettings['verify_at_login']);

            if ($verifyAtSignup || $verifyAtLogin) {
                $gwChannel = $channel === 'phone' ? 'sms' : 'email';
                if (!in_array($gwChannel, $configuredChannels)) {
                    $verifyIssues[] = $channel;
                }
            }
        }

        if (empty($verifyIssues)) {
            $checks[] = $this->makeCheck('auth_verify_gateway', __('Verification gateways', 'wp-sms'), 'pass',
                __('All verification channels have gateways.', 'wp-sms'),
            );
        } else {
            $checks[] = $this->makeCheck('auth_verify_gateway', __('Verification gateways', 'wp-sms'), 'fail',
                sprintf(__('Verification enabled for %s but no gateway configured.', 'wp-sms'), implode(', ', $verifyIssues)),
                __('Disable verification or configure a gateway.', 'wp-sms'),
                $this->settingsLink('gateways'),
            );
        }

        // auth_captcha — CAPTCHA configuration
        $captcha = $settings['captcha'] ?? [];
        if (empty($captcha['enabled'])) {
            $checks[] = $this->makeCheck('auth_captcha', __('CAPTCHA configuration', 'wp-sms'), 'pass',
                __('CAPTCHA is disabled.', 'wp-sms'),
            );
        } elseif (empty($captcha['site_key']) || empty($captcha['secret_key'])) {
            $checks[] = $this->makeCheck('auth_captcha', __('CAPTCHA configuration', 'wp-sms'), 'fail',
                __('CAPTCHA is enabled but API keys are missing.', 'wp-sms'),
                sprintf(__('Enter your %s keys in Auth settings.', 'wp-sms'), $captcha['provider'] ?? 'CAPTCHA'),
                $this->settingsLink('channels'),
            );
        } elseif (!empty($captcha['fail_open'])) {
            $checks[] = $this->makeCheck('auth_captcha', __('CAPTCHA configuration', 'wp-sms'), 'warn',
                __('CAPTCHA is set to fail-open. Protection degrades on network errors.', 'wp-sms'),
            );
        } else {
            $checks[] = $this->makeCheck('auth_captcha', __('CAPTCHA configuration', 'wp-sms'), 'pass',
                __('CAPTCHA is properly configured.', 'wp-sms'),
            );
        }

        // auth_social_providers — Social auth providers
        $socialProviders = $settings['social'] ?? [];
        $brokenProviders = [];
        foreach ($socialProviders as $provider => $config) {
            if (!empty($config['enabled']) && (empty($config['client_id']) || empty($config['client_secret']))) {
                $brokenProviders[] = $provider;
            }
        }

        if (!empty($brokenProviders)) {
            $checks[] = $this->makeCheck('auth_social_providers', __('Social auth providers', 'wp-sms'), 'fail',
                sprintf(__('Social login for %s is enabled but credentials are missing.', 'wp-sms'), implode(', ', $brokenProviders)),
                __('Configure credentials in Auth settings.', 'wp-sms'),
                $this->settingsLink('channels'),
            );
        } else {
            $checks[] = $this->makeCheck('auth_social_providers', __('Social auth providers', 'wp-sms'), 'pass',
                __('All enabled social providers have valid credentials.', 'wp-sms'),
            );
        }

        // auth_signing_key — Session signing key strength
        if (!defined('AUTH_KEY') || AUTH_KEY === '') {
            $checks[] = $this->makeCheck('auth_signing_key', __('Session signing key', 'wp-sms'), 'fail',
                __('WordPress AUTH_KEY is not defined. Auth sessions use a predictable fallback key.', 'wp-sms'),
                __('Define AUTH_KEY in wp-config.php for secure session tokens.', 'wp-sms'),
            );
        } else {
            $checks[] = $this->makeCheck('auth_signing_key', __('Session signing key', 'wp-sms'), 'pass',
                __('Session signing key is configured.', 'wp-sms'),
            );
        }

        // auth_lockout_accounts — Accounts under lockout
        $usermeta = $this->db->wpdb()->usermeta;
        $lockedCount = (int) $this->db->getVar(
            "SELECT COUNT(DISTINCT user_id)
             FROM {$usermeta}
             WHERE meta_key = 'wsms_lockout_until'
               AND CAST(meta_value AS UNSIGNED) > UNIX_TIMESTAMP()",
        );
        $maxAttempts = (int) ($this->db->getVar(
            "SELECT MAX(CAST(meta_value AS UNSIGNED))
             FROM {$usermeta}
             WHERE meta_key = 'wsms_failed_attempts'",
        ) ?? 0);

        if ($lockedCount === 0) {
            $checks[] = $this->makeCheck('auth_lockout_accounts', __('Account lockouts', 'wp-sms'), 'pass',
                __('No accounts are currently locked out.', 'wp-sms'),
            );
        } elseif ($lockedCount <= 5 && $maxAttempts <= 50) {
            $checks[] = $this->makeCheck('auth_lockout_accounts', __('Account lockouts', 'wp-sms'), 'warn',
                sprintf(__('%d account(s) locked out.', 'wp-sms'), $lockedCount),
                __('Review Auth Logs for suspicious activity.', 'wp-sms'),
                details: ['locked_count' => $lockedCount, 'max_attempts' => $maxAttempts],
            );
        } else {
            $checks[] = $this->makeCheck('auth_lockout_accounts', __('Account lockouts', 'wp-sms'), 'fail',
                sprintf(__('%d account(s) locked out (max %d failed attempts).', 'wp-sms'), $lockedCount, $maxAttempts),
                __('Multiple lockouts may indicate a brute-force attack. Review Auth Logs.', 'wp-sms'),
                details: ['locked_count' => $lockedCount, 'max_attempts' => $maxAttempts],
            );
        }

        return $checks;
    }

    private function buildBackgroundChecks(bool $asAvailable): array
    {
        $checks = [];

        // bg_action_scheduler — AS is installed and initialized
        if (!$asAvailable) {
            $checks[] = $this->makeCheck('bg_action_scheduler', __('Action Scheduler', 'wp-sms'), 'fail',
                __('Action Scheduler is not initialized. All async operations will fail.', 'wp-sms'),
                __('Deactivate and reactivate the plugin to reinitialize.', 'wp-sms'),
            );
            return $checks;
        }

        $checks[] = $this->makeCheck('bg_action_scheduler', __('Action Scheduler', 'wp-sms'), 'pass',
            __('Action Scheduler is initialized and running.', 'wp-sms'),
        );

        // bg_wp_cron — WP-Cron or external cron
        $wpCronDisabled = defined('DISABLE_WP_CRON') && DISABLE_WP_CRON;
        $cronHealth = $this->detectCronHealth();

        if (!$wpCronDisabled) {
            $checks[] = $this->makeCheck('bg_wp_cron', __('WP-Cron active', 'wp-sms'), 'pass',
                __('WP-Cron is enabled and processing tasks.', 'wp-sms'),
            );
        } elseif ($cronHealth['as_runner_active']) {
            $checks[] = $this->makeCheck('bg_wp_cron', __('WP-Cron active', 'wp-sms'), 'pass',
                __('WP-Cron is disabled but external cron is active.', 'wp-sms'),
            );
        } else {
            $checks[] = $this->makeCheck('bg_wp_cron', __('WP-Cron active', 'wp-sms'), 'fail',
                __('WP-Cron is disabled and no external cron appears active.', 'wp-sms'),
                sprintf(__('Set up an external cron job: */1 * * * * wget -q -O /dev/null %s/wp-cron.php', 'wp-sms'), site_url()),
            );
        }

        // bg_runner_active — AS runner has executed recently
        $lastRun = $cronHealth['last_as_run'];
        if ($lastRun === null) {
            $checks[] = $this->makeCheck('bg_runner_active', __('Background runner active', 'wp-sms'), 'fail',
                __('No background jobs have ever completed.', 'wp-sms'),
                __('Check server error logs for PHP fatal errors or memory exhaustion.', 'wp-sms'),
            );
        } else {
            $elapsed = time() - strtotime($lastRun . ' UTC');
            if ($elapsed < 600) {
                $checks[] = $this->makeCheck('bg_runner_active', __('Background runner active', 'wp-sms'), 'pass',
                    __('Background runner executed recently.', 'wp-sms'),
                );
            } elseif ($elapsed < 1800) {
                $checks[] = $this->makeCheck('bg_runner_active', __('Background runner active', 'wp-sms'), 'warn',
                    sprintf(__('Last background job completed %d minutes ago.', 'wp-sms'), (int) ($elapsed / 60)),
                    __('Background processing may be slowing down.', 'wp-sms'),
                );
            } else {
                $checks[] = $this->makeCheck('bg_runner_active', __('Background runner active', 'wp-sms'), 'fail',
                    sprintf(__('Last background job completed %d minutes ago.', 'wp-sms'), (int) ($elapsed / 60)),
                    __('Check server error logs for PHP fatal errors or memory exhaustion.', 'wp-sms'),
                );
            }
        }

        return $checks;
    }

    private function buildConfigChecks(array $activeFlows): array
    {
        $checks = [];

        // cfg_https
        if (is_ssl()) {
            $checks[] = $this->makeCheck('cfg_https', __('HTTPS enabled', 'wp-sms'), 'pass', __('Site is using HTTPS.', 'wp-sms'));
        } else {
            $checks[] = $this->makeCheck('cfg_https', __('HTTPS enabled', 'wp-sms'), 'fail',
                __('Site is not using HTTPS. Auth cookies and webhook signatures are insecure.', 'wp-sms'),
                __('Update WordPress URL to use https://.', 'wp-sms'),
            );
        }

        // cfg_timezone — Recommend city-based timezone for DST support
        $tz = wp_timezone_string();
        if ($tz === 'UTC' || str_contains($tz, '/')) {
            $checks[] = $this->makeCheck('cfg_timezone', __('Timezone', 'wp-sms'), 'pass',
                sprintf(__('Site timezone: %s.', 'wp-sms'), $tz),
            );
        } else {
            $checks[] = $this->makeCheck('cfg_timezone', __('Timezone', 'wp-sms'), 'info',
                sprintf(__('Site timezone is set to a manual UTC offset (%s). Selecting a city-based timezone (e.g. America/New_York) enables automatic daylight saving time adjustments for scheduled campaigns and quiet hours.', 'wp-sms'), $tz),
                __('Go to Settings → General and select a city-based timezone.', 'wp-sms'),
                admin_url('options-general.php'),
            );
        }

        // cfg_php_extensions
        $required = ['json', 'mbstring', 'openssl', 'curl'];
        $recommended = ['intl'];
        $missingRequired = array_filter($required, fn($ext) => !extension_loaded($ext));
        $missingRecommended = array_filter($recommended, fn($ext) => !extension_loaded($ext));

        if (!empty($missingRequired)) {
            $checks[] = $this->makeCheck('cfg_php_extensions', __('PHP extensions', 'wp-sms'), 'fail',
                sprintf(__('Missing required PHP extension(s): %s.', 'wp-sms'), implode(', ', $missingRequired)),
                __('Contact your hosting provider to enable the missing extension(s).', 'wp-sms'),
            );
        } elseif (!empty($missingRecommended)) {
            $checks[] = $this->makeCheck('cfg_php_extensions', __('PHP extensions', 'wp-sms'), 'warn',
                sprintf(__('Missing recommended PHP extension(s): %s.', 'wp-sms'), implode(', ', $missingRecommended)),
                __('The intl extension improves phone number parsing.', 'wp-sms'),
            );
        } else {
            $checks[] = $this->makeCheck('cfg_php_extensions', __('PHP extensions', 'wp-sms'), 'pass', __('All required PHP extensions are loaded.', 'wp-sms'));
        }

        // cfg_php_version
        $phpVersion = PHP_VERSION;
        if (version_compare($phpVersion, '8.1', '<')) {
            $checks[] = $this->makeCheck('cfg_php_version', __('PHP version', 'wp-sms'), 'fail',
                sprintf(__('PHP %s is below the minimum requirement (8.1).', 'wp-sms'), $phpVersion),
                __('Upgrade to PHP 8.2+.', 'wp-sms'),
                details: ['version' => $phpVersion],
            );
        } elseif (version_compare($phpVersion, '8.2', '<')) {
            $checks[] = $this->makeCheck('cfg_php_version', __('PHP version', 'wp-sms'), 'warn',
                sprintf(__('PHP %s — approaching end of life.', 'wp-sms'), $phpVersion),
                __('Consider upgrading to PHP 8.2+.', 'wp-sms'),
                details: ['version' => $phpVersion],
            );
        } else {
            $checks[] = $this->makeCheck('cfg_php_version', __('PHP version', 'wp-sms'), 'pass',
                sprintf(__('PHP %s.', 'wp-sms'), $phpVersion),
                details: ['version' => $phpVersion],
            );
        }

        // cfg_mysql_version
        $dbVersion = $this->db->wpdb()->db_version();
        $isMariaDb = str_contains(($this->db->wpdb()->db_server_info() ?? ''), 'MariaDB');
        $minVersion = $isMariaDb ? '10.2' : '5.7';
        $dbLabel = $isMariaDb ? 'MariaDB' : 'MySQL';

        if (version_compare($dbVersion, $minVersion, '<')) {
            $checks[] = $this->makeCheck('cfg_mysql_version', __('Database version', 'wp-sms'), 'fail',
                sprintf(__('%s %s does not support JSON columns.', 'wp-sms'), $dbLabel, $dbVersion),
                sprintf(__('Upgrade %s to %s+.', 'wp-sms'), $dbLabel, $minVersion),
                details: ['engine' => $dbLabel, 'version' => $dbVersion],
            );
        } else {
            $checks[] = $this->makeCheck('cfg_mysql_version', __('Database version', 'wp-sms'), 'pass',
                sprintf('%s %s.', $dbLabel, $dbVersion),
                details: ['engine' => $dbLabel, 'version' => $dbVersion],
            );
        }

        // cfg_flow_channels — Active flows reference available channels
        $configuredChannels = $this->gatewayRegistry->getConfiguredChannels();
        $flowChannelIssues = [];
        $flowTriggerIssues = [];

        foreach ($activeFlows as $flow) {
            foreach ($flow['parsed_steps'] as $step) {
                $type = $step['type'] ?? '';
                if ($type === 'action' && ($step['action'] ?? '') === 'send_message') {
                    $channel = $step['config']['channel'] ?? null;
                    if ($channel && !in_array($channel, $configuredChannels)) {
                        $flowChannelIssues[] = sprintf('%s (%s)', $flow['name'], $channel);
                    }
                } elseif ($type === 'trigger') {
                    $triggerType = $step['trigger_type'] ?? $step['config']['trigger_type'] ?? null;
                    if ($triggerType && $this->triggerRegistry->get($triggerType) === null) {
                        $flowTriggerIssues[] = sprintf('%s (%s)', $flow['name'], $triggerType);
                    }
                }
            }

            // Also check the flow-level trigger_type
            if ($flow['trigger_type'] && $this->triggerRegistry->get($flow['trigger_type']) === null) {
                $flowTriggerIssues[] = sprintf('%s (%s)', $flow['name'], $flow['trigger_type']);
            }
        }

        $flowTriggerIssues = array_unique($flowTriggerIssues);

        if (empty($flowTriggerIssues)) {
            $checks[] = $this->makeCheck('cfg_flow_triggers', __('Flow trigger availability', 'wp-sms'), 'pass', __('All active flow triggers are registered.', 'wp-sms'));
        } else {
            $checks[] = $this->makeCheck('cfg_flow_triggers', __('Flow trigger availability', 'wp-sms'), 'fail',
                sprintf(__('Flows with unregistered triggers: %s.', 'wp-sms'), implode(', ', $flowTriggerIssues)),
                __('Activate the required plugin or edit the flow.', 'wp-sms'),
                $this->settingsLink('flows'),
            );
        }

        // cfg_webhooks — Active webhooks tested
        $webhooks = $this->webhookRepo->findAll();
        $untestedWebhooks = [];
        foreach ($webhooks as $webhook) {
            if (($webhook['status'] ?? '') === 'active' && empty($webhook['last_test']['success'])) {
                $untestedWebhooks[] = $webhook['name'] ?? $webhook['id'] ?? 'unknown';
            }
        }

        if (empty($untestedWebhooks)) {
            $checks[] = $this->makeCheck('cfg_webhooks', __('Webhook connectivity', 'wp-sms'), 'pass', __('All active webhooks have been tested.', 'wp-sms'));
        } else {
            $checks[] = $this->makeCheck('cfg_webhooks', __('Webhook connectivity', 'wp-sms'), 'warn',
                sprintf(__('Untested webhooks: %s.', 'wp-sms'), implode(', ', $untestedWebhooks)),
                __('Use the Test button in Webhook settings to verify connectivity.', 'wp-sms'),
                $this->settingsLink('webhooks'),
            );
        }

        // cfg_phone_restrictions — Not blocking everything
        $allSettings = $this->restrictionSettings->all();
        foreach (['auth', 'messaging'] as $context) {
            $ctx = $allSettings[$context] ?? [];
            if (!empty($ctx['enabled']) && ($ctx['mode'] ?? 'allow') === 'allow' && empty($ctx['allowed_countries'])) {
                $checks[] = $this->makeCheck('cfg_phone_restrictions', __('Phone restrictions', 'wp-sms'), 'fail',
                    sprintf(__('Phone restrictions (%s) set to allow-list with no countries. All phone operations are blocked.', 'wp-sms'), $context),
                    __('Add countries to the allow-list or disable phone restrictions.', 'wp-sms'),
                    $this->settingsLink('gateways'),
                );
                // Found issue — skip adding a pass
                $foundRestrictionIssue = true;
                break;
            }
        }

        if (!isset($foundRestrictionIssue)) {
            $checks[] = $this->makeCheck('cfg_phone_restrictions', __('Phone restrictions', 'wp-sms'), 'pass', __('Phone restrictions are properly configured.', 'wp-sms'));
        }

        // cfg_environment — Reference info (always 'info')
        $checks[] = $this->makeCheck('cfg_environment', __('Environment', 'wp-sms'), 'info',
            __('Server environment information.', 'wp-sms'),
            details: [
                'php_version'       => PHP_VERSION,
                'wp_version'        => get_bloginfo('version'),
                'db_engine'         => $dbLabel,
                'db_version'        => $dbVersion,
                'as_version'        => defined('ACTION_SCHEDULER_VERSION') ? ACTION_SCHEDULER_VERSION : 'N/A',
                'memory_limit'      => ini_get('memory_limit'),
                'max_execution_time' => ini_get('max_execution_time'),
                'plugin_version'    => defined('WSMS_VERSION') ? WSMS_VERSION : 'dev',
                'wp_cron_disabled'  => defined('DISABLE_WP_CRON') && DISABLE_WP_CRON,
            ],
        );

        return $checks;
    }

    private function buildDataSecurityChecks(): array
    {
        $checks = [];
        $prefix = $this->db->wpdb()->prefix;

        // data_tables_exist
        $existingTables = $this->db->getCol(
            "SHOW TABLES LIKE %s",
            $prefix . 'wsms\_%',
        );

        $missingTables = [];
        foreach (self::EXPECTED_TABLES as $table) {
            $fullName = $prefix . $table;
            if (!in_array($fullName, $existingTables)) {
                $missingTables[] = $table;
            }
        }

        if (empty($missingTables)) {
            $checks[] = $this->makeCheck('data_tables_exist', __('Database tables', 'wp-sms'), 'pass',
                sprintf(__('All %d plugin tables exist.', 'wp-sms'), count(self::EXPECTED_TABLES)),
            );
        } else {
            $checks[] = $this->makeCheck('data_tables_exist', __('Database tables', 'wp-sms'), 'fail',
                sprintf(__('Missing table(s): %s.', 'wp-sms'), implode(', ', $missingTables)),
                __('Deactivate and reactivate the plugin to recreate database tables.', 'wp-sms'),
            );
        }

        // data_table_sizes — Lazy
        $checks[] = $this->makeCheck('data_table_sizes', __('Table sizes', 'wp-sms'), 'skip',
            __('Click "Check table sizes" to query database table sizes.', 'wp-sms'),
            lazy: true,
        );

        // data_cleanup_running
        $heartbeat = $this->isActionSchedulerAvailable() ? $this->buildHeartbeat() : [];
        $cleanupStatus = 'stopped';
        foreach ($heartbeat as $task) {
            if ($task['hook'] === 'wsms_daily_cleanup') {
                $cleanupStatus = $task['status'];
                break;
            }
        }

        if ($cleanupStatus === 'healthy') {
            $checks[] = $this->makeCheck('data_cleanup_running', __('Cleanup scheduler', 'wp-sms'), 'pass', __('Daily cleanup is running on schedule.', 'wp-sms'));
        } elseif ($cleanupStatus === 'late') {
            $checks[] = $this->makeCheck('data_cleanup_running', __('Cleanup scheduler', 'wp-sms'), 'warn',
                __('Daily cleanup is behind schedule.', 'wp-sms'),
                __('Check the Background Processing section.', 'wp-sms'),
            );
        } else {
            $checks[] = $this->makeCheck('data_cleanup_running', __('Cleanup scheduler', 'wp-sms'), 'fail',
                __('Cleanup is not running. Data will accumulate unbounded.', 'wp-sms'),
                __('Check the Background Processing section.', 'wp-sms'),
            );
        }

        // data_log_retention
        $messagingSettings = get_option('wsms_messaging_settings', []);
        $msgRetention = (int) ($messagingSettings['message_log_retention_days'] ?? 90);
        $logRetention = (int) ($this->authSettings->get('log_retention_days', 30));

        if ($msgRetention > 0 && $logRetention > 0) {
            if ($msgRetention > 365 || $logRetention > 365) {
                $checks[] = $this->makeCheck('data_log_retention', __('Log retention', 'wp-sms'), 'warn',
                    __('Log retention is set to over 365 days. Tables may grow very large.', 'wp-sms'),
                    __('Consider reducing retention in Settings > General.', 'wp-sms'),
                    $this->settingsLink('s-general'),
                );
            } else {
                $checks[] = $this->makeCheck('data_log_retention', __('Log retention', 'wp-sms'), 'pass',
                    sprintf(__('Message logs: %d days, Auth logs: %d days.', 'wp-sms'), $msgRetention, $logRetention),
                );
            }
        } else {
            $checks[] = $this->makeCheck('data_log_retention', __('Log retention', 'wp-sms'), 'fail',
                __('Log retention is not configured. Logs will grow forever.', 'wp-sms'),
                __('Configure log retention in Settings > General.', 'wp-sms'),
                $this->settingsLink('s-general'),
            );
        }

        // data_orphaned_verifications
        $verTable = $this->db->table(Connection::TABLE_VERIFICATIONS);
        $expiredCount = (int) $this->db->getVar(
            "SELECT COUNT(*) FROM {$verTable}
             WHERE used_at IS NULL AND expires_at < NOW()",
        );

        if ($cleanupStatus === 'healthy' && $expiredCount < 10000) {
            $checks[] = $this->makeCheck('data_orphaned_verifications', __('Stale verifications', 'wp-sms'), 'pass',
                sprintf(__('%d expired verification records.', 'wp-sms'), $expiredCount),
            );
        } elseif ($expiredCount >= 50000 && $cleanupStatus !== 'healthy') {
            $checks[] = $this->makeCheck('data_orphaned_verifications', __('Stale verifications', 'wp-sms'), 'fail',
                sprintf(__('%d expired verification records accumulating.', 'wp-sms'), $expiredCount),
                __('Ensure the cleanup scheduler is running.', 'wp-sms'),
            );
        } elseif ($expiredCount >= 10000) {
            $checks[] = $this->makeCheck('data_orphaned_verifications', __('Stale verifications', 'wp-sms'), 'warn',
                sprintf(__('%d expired verification records.', 'wp-sms'), $expiredCount),
                __('Cleanup may not be running effectively.', 'wp-sms'),
            );
        } else {
            $checks[] = $this->makeCheck('data_orphaned_verifications', __('Stale verifications', 'wp-sms'), 'pass',
                sprintf(__('%d expired verification records.', 'wp-sms'), $expiredCount),
            );
        }

        // sec_failed_logins
        $usermeta = $this->db->wpdb()->usermeta;
        $highAttempts = $this->db->getResults(
            "SELECT user_id, CAST(meta_value AS UNSIGNED) AS attempts
             FROM {$usermeta}
             WHERE meta_key = 'wsms_failed_attempts'
               AND CAST(meta_value AS UNSIGNED) > 15
             ORDER BY CAST(meta_value AS UNSIGNED) DESC
             LIMIT 5",
        );

        if (empty($highAttempts)) {
            $checks[] = $this->makeCheck('sec_failed_logins', __('Failed login attempts', 'wp-sms'), 'pass', __('No accounts with high failed login attempts.', 'wp-sms'));
        } else {
            $maxAttempts = (int) $highAttempts[0]['attempts'];
            $affectedUsers = count($highAttempts);
            if ($maxAttempts > 50) {
                $checks[] = $this->makeCheck('sec_failed_logins', __('Failed login attempts', 'wp-sms'), 'fail',
                    sprintf(__('%d account(s) with high failed attempts (max %d). Possible brute-force attack.', 'wp-sms'), $affectedUsers, $maxAttempts),
                    __('Review Auth Logs and consider suspending affected accounts.', 'wp-sms'),
                    details: ['accounts' => array_map(fn($r) => ['user_id' => (int) $r['user_id'], 'attempts' => (int) $r['attempts']], $highAttempts)],
                );
            } else {
                $checks[] = $this->makeCheck('sec_failed_logins', __('Failed login attempts', 'wp-sms'), 'warn',
                    sprintf(__('%d account(s) with elevated failed attempts (max %d).', 'wp-sms'), $affectedUsers, $maxAttempts),
                    __('Review Auth Logs for suspicious activity.', 'wp-sms'),
                    details: ['accounts' => array_map(fn($r) => ['user_id' => (int) $r['user_id'], 'attempts' => (int) $r['attempts']], $highAttempts)],
                );
            }
        }

        // sec_ip_resolution
        $clientIp = IpResolver::resolve();
        if ($clientIp === '') {
            $checks[] = $this->makeCheck('sec_ip_resolution', __('IP address resolution', 'wp-sms'), 'fail',
                __('IP resolution is failing. Rate limiting and audit logging are degraded.', 'wp-sms'),
                __('Check reverse proxy configuration (X-Forwarded-For, X-Real-IP headers).', 'wp-sms'),
            );
        } else {
            $checks[] = $this->makeCheck('sec_ip_resolution', __('IP address resolution', 'wp-sms'), 'pass',
                __('IP address resolution is working.', 'wp-sms'),
            );
        }

        return $checks;
    }

    // ── Existing section builders ────────────────────────────────────

    private function detectCronHealth(): array
    {
        $wpCronDisabled = defined('DISABLE_WP_CRON') && DISABLE_WP_CRON;
        $actionsTable   = $this->asTable('actions');
        $groupsTable    = $this->asTable('groups');
        $complete       = \ActionScheduler_Store::STATUS_COMPLETE;

        $lastAsRun = $this->db->getVar(
            "SELECT MAX(a.last_attempt_gmt)
             FROM {$actionsTable} a
             INNER JOIN {$groupsTable} g ON a.group_id = g.group_id
             WHERE g.slug = 'wsms' AND a.status = '{$complete}'",
        );

        $asRunnerActive = false;
        if ($lastAsRun) {
            $elapsed        = time() - strtotime($lastAsRun . ' UTC');
            $asRunnerActive = $elapsed < 600; // 10 minutes
        }

        if ($asRunnerActive) {
            $status = 'healthy';
        } elseif ($wpCronDisabled) {
            $status = 'error';
        } elseif ($lastAsRun === null) {
            $status = 'unknown';
        } else {
            $status = 'warning';
        }

        return [
            'wp_cron_disabled' => $wpCronDisabled,
            'as_runner_active' => $asRunnerActive,
            'last_as_run'      => $lastAsRun,
            'status'           => $status,
        ];
    }

    private function buildHeartbeat(): array
    {
        $actionsTable = $this->asTable('actions');
        $groupsTable  = $this->asTable('groups');
        $complete     = \ActionScheduler_Store::STATUS_COMPLETE;
        $pending      = \ActionScheduler_Store::STATUS_PENDING;

        $allHooks = array_keys(self::RECURRING_TASKS);
        $hookPlaceholders = implode(',', array_fill(0, count($allHooks), '%s'));

        $rows = $this->db->getResults(
            "SELECT a.hook,
                    MAX(CASE WHEN a.status = '{$complete}' THEN a.last_attempt_gmt END) AS last_run,
                    MIN(CASE WHEN a.status = '{$pending}' THEN a.scheduled_date_gmt END) AS next_run
             FROM {$actionsTable} a
             INNER JOIN {$groupsTable} g ON a.group_id = g.group_id
             WHERE g.slug = 'wsms'
               AND a.status IN ('{$complete}', '{$pending}')
               AND a.hook IN ({$hookPlaceholders})
             GROUP BY a.hook",
            ...$allHooks,
        );

        $lastRunMap = [];
        $nextRunMap = [];
        foreach ($rows as $row) {
            if ($row['last_run']) $lastRunMap[$row['hook']] = $row['last_run'];
            if ($row['next_run']) $nextRunMap[$row['hook']] = $row['next_run'];
        }

        $translatedLabels = self::getTranslatedTaskLabels();

        $heartbeat = [];
        foreach (self::RECURRING_TASKS as $hook => $meta) {
            $lastRun = $lastRunMap[$hook] ?? null;
            $nextRun = $nextRunMap[$hook] ?? null;

            $status = $this->resolveTaskStatus($lastRun, $nextRun, $meta['interval'], $meta['tolerance']);

            $heartbeat[] = [
                'hook'     => $hook,
                'label'    => $translatedLabels[$meta['label']] ?? $meta['label'],
                'status'   => $status,
                'last_run' => $lastRun,
                'next_run' => $nextRun,
                'interval' => $meta['interval'],
            ];
        }

        // Detect dynamic flow hooks.
        // last_run must only count completed runs — otherwise MAX() over
        // pending-only rows returns Action Scheduler's zero-date
        // ('0000-00-00 00:00:00'), which crashes downstream date formatters.
        // Mirror the recurring-tasks query above.
        $flowHooks = $this->db->getResults(
            "SELECT a.hook,
                    MAX(CASE WHEN a.status = '{$complete}' THEN a.last_attempt_gmt END) AS last_run,
                    MIN(CASE WHEN a.status = '{$pending}' THEN a.scheduled_date_gmt END) AS next_run
             FROM {$actionsTable} a
             INNER JOIN {$groupsTable} g ON a.group_id = g.group_id
             WHERE g.slug = 'wsms'
               AND a.hook LIKE 'wsms\\_schedule\\_flow\\_%'
               AND a.status IN ('{$complete}', '{$pending}')
             GROUP BY a.hook",
        );

        foreach ($flowHooks as $row) {
            $status = $row['next_run'] ? 'healthy' : ($row['last_run'] ? 'stopped' : 'unknown');

            $heartbeat[] = [
                'hook'     => $row['hook'],
                'label'    => 'Flow: ' . str_replace('wsms_schedule_flow_', '', $row['hook']),
                'status'   => $status,
                'last_run' => $row['last_run'],
                'next_run' => $row['next_run'],
                'interval' => 0,
            ];
        }

        return $heartbeat;
    }

    private function buildQueueOverview(): array
    {
        $actionsTable = $this->asTable('actions');
        $groupsTable  = $this->asTable('groups');
        $pending      = \ActionScheduler_Store::STATUS_PENDING;
        $running      = \ActionScheduler_Store::STATUS_RUNNING;

        // Totals by status
        $statusCounts = $this->db->getResults(
            "SELECT a.status, COUNT(*) AS count
             FROM {$actionsTable} a
             INNER JOIN {$groupsTable} g ON a.group_id = g.group_id
             WHERE g.slug = 'wsms' AND a.hook = 'wsms_process_job'
             GROUP BY a.status",
        );

        $totals = [];
        foreach ($statusCounts as $row) {
            $totals[$row['status']] = (int) $row['count'];
        }

        // By-type breakdown for pending/in-progress
        $byType = $this->db->getResults(
            "SELECT
                JSON_UNQUOTE(JSON_EXTRACT(a.args, '$[0].type')) AS job_type,
                SUM(CASE WHEN a.status = '{$pending}' THEN 1 ELSE 0 END) AS pending,
                SUM(CASE WHEN a.status = '{$running}' THEN 1 ELSE 0 END) AS in_progress
             FROM {$actionsTable} a
             INNER JOIN {$groupsTable} g ON a.group_id = g.group_id
             WHERE g.slug = 'wsms'
               AND a.hook = 'wsms_process_job'
               AND a.status IN ('{$pending}', '{$running}')
             GROUP BY job_type",
        );

        $byTypeResult = [];
        foreach ($byType as $row) {
            $byTypeResult[] = [
                'type'        => $row['job_type'] ?? 'unknown',
                'pending'     => (int) $row['pending'],
                'in_progress' => (int) $row['in_progress'],
            ];
        }

        // Stuck detection: in-progress for more than 5 minutes
        $stuckJobs = $this->db->getResults(
            "SELECT a.action_id, a.last_attempt_gmt AS stuck_since,
                    JSON_UNQUOTE(JSON_EXTRACT(a.args, '$[0].type')) AS job_type
             FROM {$actionsTable} a
             INNER JOIN {$groupsTable} g ON a.group_id = g.group_id
             WHERE g.slug = 'wsms'
               AND a.hook = 'wsms_process_job'
               AND a.status = '{$running}'
               AND a.last_attempt_gmt < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 5 MINUTE)",
        );

        $stuckResult = [];
        foreach ($stuckJobs as $row) {
            $stuckResult[] = [
                'action_id'   => (int) $row['action_id'],
                'job_type'    => $row['job_type'] ?? 'unknown',
                'stuck_since' => $row['stuck_since'],
            ];
        }

        return [
            'totals'     => $totals,
            'by_type'    => $byTypeResult,
            'stuck_jobs' => $stuckResult,
        ];
    }

    private function buildFailedJobs(int $limit): array
    {
        $actionsTable = $this->asTable('actions');
        $groupsTable  = $this->asTable('groups');
        $logsTable    = $this->asTable('logs');
        $failed       = \ActionScheduler_Store::STATUS_FAILED;

        // Failed actions with last log message
        $items = $this->db->getResults(
            "SELECT
                a.action_id,
                a.hook,
                a.args,
                a.last_attempt_gmt AS failed_at,
                a.attempts,
                (SELECT l.message
                 FROM {$logsTable} l
                 WHERE l.action_id = a.action_id
                 ORDER BY l.log_id DESC
                 LIMIT 1) AS error_message
             FROM {$actionsTable} a
             INNER JOIN {$groupsTable} g ON a.group_id = g.group_id
             WHERE g.slug = 'wsms' AND a.status = '{$failed}'
             ORDER BY a.last_attempt_gmt DESC
             LIMIT %d",
            $limit,
        );

        $total = (int) $this->db->getVar(
            "SELECT COUNT(*)
             FROM {$actionsTable} a
             INNER JOIN {$groupsTable} g ON a.group_id = g.group_id
             WHERE g.slug = 'wsms' AND a.status = '{$failed}'",
        );

        $result = [];
        foreach ($items as $row) {
            $jobType  = self::extractJobType($row['args'], $row['hook']);
            $severity = in_array($jobType, self::HIGH_SEVERITY_TYPES, true) ? 'high' : 'low';
            $args     = json_decode($row['args'], true) ?: [];

            $argsPreview = [];
            if (isset($args[0]) && is_array($args[0])) {
                foreach (['recipient', 'campaign_id', 'flow_id', 'contact_id'] as $key) {
                    if (isset($args[0][$key])) {
                        $argsPreview[$key] = (string) $args[0][$key];
                    }
                }
            }

            $result[] = [
                'action_id'     => (int) $row['action_id'],
                'job_type'      => $jobType,
                'severity'      => $severity,
                'error_message' => $row['error_message'] ?? '',
                'failed_at'     => $row['failed_at'],
                'attempts'      => (int) $row['attempts'],
                'args_preview'  => $argsPreview,
            ];
        }

        // Error grouping
        $errorGroups = $this->db->getResults(
            "SELECT
                (SELECT l2.message
                 FROM {$logsTable} l2
                 WHERE l2.action_id = a.action_id
                 ORDER BY l2.log_id DESC
                 LIMIT 1) AS message,
                COUNT(*) AS count
             FROM {$actionsTable} a
             INNER JOIN {$groupsTable} g ON a.group_id = g.group_id
             WHERE g.slug = 'wsms' AND a.status = '{$failed}'
             GROUP BY message
             ORDER BY count DESC
             LIMIT 10",
        );

        $groups = [];
        foreach ($errorGroups as $row) {
            if ($row['message']) {
                $groups[] = ['message' => $row['message'], 'count' => (int) $row['count']];
            }
        }

        return [
            'items'        => $result,
            'total'        => $total,
            'error_groups' => $groups,
        ];
    }

    private function buildRecentActivity(int $limit): array
    {
        $actionsTable = $this->asTable('actions');
        $groupsTable  = $this->asTable('groups');
        $complete     = \ActionScheduler_Store::STATUS_COMPLETE;
        $failed       = \ActionScheduler_Store::STATUS_FAILED;

        $rows = $this->db->getResults(
            "SELECT a.action_id, a.hook, a.args, a.status, a.last_attempt_gmt AS completed_at
             FROM {$actionsTable} a
             INNER JOIN {$groupsTable} g ON a.group_id = g.group_id
             WHERE g.slug = 'wsms' AND a.status IN ('{$complete}', '{$failed}')
             ORDER BY a.last_attempt_gmt DESC
             LIMIT %d",
            $limit,
        );

        $result = [];
        foreach ($rows as $row) {
            $result[] = [
                'action_id'    => (int) $row['action_id'],
                'hook'         => $row['hook'],
                'job_type'     => self::extractJobType($row['args'], $row['hook']),
                'status'       => $row['status'],
                'completed_at' => $row['completed_at'],
            ];
        }

        return $result;
    }

    private function buildActiveCampaigns(): array
    {
        $table = $this->db->table(Connection::TABLE_CAMPAIGNS);

        $rows = $this->db->getResults(
            "SELECT id, name, channel, status, total_recipients, sent_count, delivered_count, failed_count, started_at
             FROM {$table}
             WHERE status IN ('sending', 'paused')
             ORDER BY started_at DESC",
        );

        $result = [];
        foreach ($rows as $row) {
            $result[] = [
                'id'               => $row['id'],
                'name'             => $row['name'],
                'channel'          => $row['channel'],
                'status'           => $row['status'],
                'total_recipients' => (int) $row['total_recipients'],
                'sent_count'       => (int) $row['sent_count'],
                'delivered_count'  => (int) $row['delivered_count'],
                'failed_count'     => (int) $row['failed_count'],
                'started_at'       => $row['started_at'],
            ];
        }

        return $result;
    }

    // ── Helpers ───────────────────────────────────────────────────────

    private function buildDeliveryRateCheck(string $period, string $sqlInterval): array
    {
        $msgTable = $this->db->table(Connection::TABLE_MESSAGE_LOGS);

        // Single query fetches both 24h and 7d stats via conditional aggregation
        $rows = $this->db->getResults(
            "SELECT channel,
                    COUNT(*) AS total_7d,
                    SUM(CASE WHEN status IN ('sent','delivered') THEN 1 ELSE 0 END) AS success_7d,
                    SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL {$sqlInterval}) THEN 1 ELSE 0 END) AS total,
                    SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL {$sqlInterval}) AND status IN ('sent','delivered') THEN 1 ELSE 0 END) AS success,
                    SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL {$sqlInterval}) AND status = 'failed' THEN 1 ELSE 0 END) AS failed,
                    SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL {$sqlInterval}) AND status = 'bounced' THEN 1 ELSE 0 END) AS bounced
             FROM {$msgTable}
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
             GROUP BY channel",
        );

        // Filter to rows that have data in the requested period
        $periodRows = array_filter($rows, fn($row) => (int) $row['total'] > 0);

        if (empty($periodRows)) {
            return $this->makeCheck(
                "msg_delivery_rate_{$period}",
                sprintf(__('Delivery rate (%s)', 'wp-sms'), $period),
                'skip',
                sprintf(__('No messages sent in the last %s.', 'wp-sms'), $period),
            );
        }

        $channelStats = [];
        $worstStatus = 'pass';
        foreach ($periodRows as $row) {
            $total = (int) $row['total'];
            $success = (int) $row['success'];
            $rate = $total > 0 ? ($success / $total) * 100 : 100;

            $channelStats[$row['channel']] = [
                'total'   => $total,
                'success' => $success,
                'failed'  => (int) $row['failed'],
                'bounced' => (int) $row['bounced'],
                'rate'    => round($rate, 1),
            ];

            if ($rate < 70) {
                $worstStatus = 'fail';
            } elseif ($rate < 90 && $worstStatus !== 'fail') {
                $worstStatus = 'warn';
            }
        }

        $summaryParts = [];
        foreach ($channelStats as $channel => $stats) {
            $summaryParts[] = sprintf('%s: %s%%', $channel, $stats['rate']);
        }

        $resolution = $worstStatus !== 'pass'
            ? __('Check Message Logs for failed deliveries. Common causes: invalid credentials, exhausted credits, invalid recipients.', 'wp-sms')
            : null;

        $details = ['channels' => $channelStats];

        // Include 7-day trend data from the same query
        $trend7d = [];
        foreach ($rows as $row) {
            $total7d = (int) $row['total_7d'];
            $success7d = (int) $row['success_7d'];
            $trend7d[$row['channel']] = [
                'total'   => $total7d,
                'success' => $success7d,
                'rate'    => $total7d > 0 ? round(($success7d / $total7d) * 100, 1) : 100,
            ];
        }
        $details['trend_7d'] = $trend7d;

        return $this->makeCheck(
            "msg_delivery_rate_{$period}",
            sprintf(__('Delivery rate (%s)', 'wp-sms'), $period),
            $worstStatus,
            implode(' | ', $summaryParts),
            $resolution,
            details: $details,
        );
    }

    private function computeOverallStatus(array $checks): array
    {
        $sectionStatuses = [];
        $totalWarnings = 0;
        $totalCriticals = 0;

        foreach ($checks as $section => $sectionChecks) {
            $worstStatus = 'healthy';
            foreach ($sectionChecks as $check) {
                $status = $check['status'];
                if ($status === 'fail') {
                    $worstStatus = 'critical';
                    $totalCriticals++;
                } elseif ($status === 'warn' && $worstStatus !== 'critical') {
                    $worstStatus = 'warning';
                    $totalWarnings++;
                }
            }
            $sectionStatuses[$section] = $worstStatus;
        }

        $overallLevel = 'healthy';
        foreach ($sectionStatuses as $status) {
            if ($status === 'critical') {
                $overallLevel = 'critical';
                break;
            }
            if ($status === 'warning') {
                $overallLevel = 'warnings';
            }
        }

        if ($overallLevel === 'healthy') {
            $summary = __('All systems operational.', 'wp-sms');
        } elseif ($overallLevel === 'critical') {
            $total = $totalCriticals + $totalWarnings;
            $summary = sprintf(_n('%d critical issue needs attention.', '%d issues need attention.', $total, 'wp-sms'), $total);
        } else {
            $summary = sprintf(_n('%d issue needs attention.', '%d issues need attention.', $totalWarnings, 'wp-sms'), $totalWarnings);
        }

        return [
            'level'            => $overallLevel,
            'summary'          => $summary,
            'section_statuses' => $sectionStatuses,
        ];
    }

    private function makeCheck(
        string $id,
        string $label,
        string $status,
        string $message,
        ?string $resolution = null,
        ?string $link = null,
        ?array $details = null,
        bool $lazy = false,
    ): array {
        $check = [
            'id'      => $id,
            'label'   => $label,
            'status'  => $status,
            'message' => $message,
        ];

        if ($resolution !== null) {
            $check['resolution'] = $resolution;
        }
        if ($link !== null) {
            $check['link'] = $link;
        }
        if ($details !== null) {
            $check['details'] = $details;
        }
        if ($lazy) {
            $check['lazy'] = true;
        }

        return $check;
    }

    private function settingsLink(string $section): string
    {
        return admin_url("admin.php?page=wsms#{$section}");
    }

    private function resolveTaskStatus(?string $lastRun, ?string $nextRun, int $interval, float $tolerance): string
    {
        if ($lastRun === null) {
            return $nextRun ? 'unknown' : 'stopped';
        }

        if ($nextRun === null) {
            return 'stopped';
        }

        $elapsed = time() - strtotime($lastRun . ' UTC');

        if ($elapsed > $interval * $tolerance) {
            return 'late';
        }

        return 'healthy';
    }

    private function getFailedAction(int $actionId): \ActionScheduler_Action
    {
        $store  = \ActionScheduler::store();
        $action = $store->fetch_action($actionId);

        if ($action instanceof \ActionScheduler_NullAction) {
            throw NotFoundException::entity('Action', (string) $actionId);
        }

        if ($action->get_group() !== 'wsms') {
            throw NotFoundException::entity('Action', (string) $actionId);
        }

        if ($store->get_status($actionId) !== \ActionScheduler_Store::STATUS_FAILED) {
            throw NotFoundException::entity('Failed action', (string) $actionId);
        }

        return $action;
    }

    private static function extractJobType(string $argsJson, string $hook): string
    {
        $args = json_decode($argsJson, true) ?: [];

        return $args[0]['type'] ?? $hook;
    }

    private function requireActionScheduler(): void
    {
        if (!class_exists(\ActionScheduler::class) || !\ActionScheduler::is_initialized()) {
            throw new \RuntimeException('Action Scheduler is not available.');
        }
    }

    private function isActionSchedulerAvailable(): bool
    {
        return class_exists(\ActionScheduler::class) && \ActionScheduler::is_initialized();
    }

    private function asTable(string $name): string
    {
        return $this->db->wpdb()->prefix . 'actionscheduler_' . $name;
    }
}
