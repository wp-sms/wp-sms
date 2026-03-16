<?php

namespace WSms\Audit;

use WSms\Enums\EventType;

defined('ABSPATH') || exit;

class ReportAggregator
{
    private const LOGIN_SUCCESS_EVENTS = [
        EventType::LoginSuccess,
        EventType::MagicLinkVerified,
        EventType::SocialLoginSuccess,
    ];

    private const LOGIN_FAILURE_EVENTS = [
        EventType::LoginFailure,
        EventType::SocialLoginFailure,
    ];

    private const OTP_FAILURE_EVENTS = [
        EventType::OtpFailed,
        EventType::TotpFailed,
        EventType::OtpExpired,
    ];

    /**
     * Get aggregated report data for the given time range.
     *
     * @return array{range: int, auth_activity: array, user_security: array, channel_usage: array, security_alerts: array}
     */
    public function getReport(int $rangeDays): array
    {
        $cacheKey = "wsms_reports_{$rangeDays}d";
        $cached = get_transient($cacheKey);

        if ($cached !== false) {
            return $cached;
        }

        $report = [
            'range'           => $rangeDays,
            'auth_activity'   => $this->getAuthActivity($rangeDays),
            'user_security'   => $this->getUserSecurity(),
            'channel_usage'   => $this->getChannelUsage($rangeDays),
            'security_alerts' => $this->getSecurityAlerts($rangeDays),
        ];

        set_transient($cacheKey, $report, 5 * MINUTE_IN_SECONDS);

        return $report;
    }

    private function getAuthActivity(int $rangeDays): array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'wsms_auth_logs';
        $successIn = self::eventIn(self::LOGIN_SUCCESS_EVENTS);
        $failureIn = self::eventIn(self::LOGIN_FAILURE_EVENTS);

        // Timeline: logins/failures from logs
        $logTimeline = $wpdb->get_results($wpdb->prepare(
            "SELECT
                DATE(created_at) AS date,
                SUM(CASE WHEN event IN ({$successIn}) THEN 1 ELSE 0 END) AS logins,
                SUM(CASE WHEN event IN ({$failureIn}) THEN 1 ELSE 0 END) AS failures
            FROM {$table}
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
            GROUP BY DATE(created_at)",
            $rangeDays,
        ));

        // Registrations from wp_users (survives log cleanup)
        $regTimeline = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(user_registered) AS date, COUNT(*) AS registrations
            FROM {$wpdb->users}
            WHERE user_registered >= DATE_SUB(NOW(), INTERVAL %d DAY)
            GROUP BY DATE(user_registered)",
            $rangeDays,
        ));

        // Password resets (scalar, no per-day breakdown needed)
        $passwordResets = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE event = %s AND created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)",
            EventType::PasswordResetComplete->value,
            $rangeDays,
        ));

        // Merge both timelines by date
        $dates = [];
        foreach ($logTimeline as $row) {
            $dates[$row->date] = [
                'date'          => $row->date,
                'logins'        => (int) $row->logins,
                'failures'      => (int) $row->failures,
                'registrations' => 0,
            ];
        }
        foreach ($regTimeline as $row) {
            if (isset($dates[$row->date])) {
                $dates[$row->date]['registrations'] = (int) $row->registrations;
            } else {
                $dates[$row->date] = [
                    'date'          => $row->date,
                    'logins'        => 0,
                    'failures'      => 0,
                    'registrations' => (int) $row->registrations,
                ];
            }
        }
        ksort($dates);
        $timelineData = array_values($dates);

        // Derive scalar counts from timeline instead of a separate query
        $successful = array_sum(array_column($timelineData, 'logins'));
        $failed = array_sum(array_column($timelineData, 'failures'));
        $totalLogins = $successful + $failed;
        $totalRegistrations = array_sum(array_column($timelineData, 'registrations'));

        return [
            'total_logins'        => $totalLogins,
            'successful_logins'   => $successful,
            'failed_logins'       => $failed,
            'login_success_rate'  => $totalLogins > 0 ? round(($successful / $totalLogins) * 100, 1) : 0,
            'total_registrations' => $totalRegistrations,
            'password_resets'     => $passwordResets,
            'timeline'            => $timelineData,
        ];
    }

    private function getUserSecurity(): array
    {
        global $wpdb;

        $factorsTable = $wpdb->prefix . 'wsms_user_factors';

        $totalUsers = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->users}");

        $mfaEnrolled = (int) $wpdb->get_var(
            "SELECT COUNT(DISTINCT f.user_id)
            FROM {$factorsTable} f
            INNER JOIN {$wpdb->users} u ON u.ID = f.user_id
            WHERE f.status = 'active' AND f.channel_id != 'backup_codes'",
        );

        $emailVerified = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value = '1'",
            'wsms_email_verified',
        ));

        $phoneVerified = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value = '1'",
            'wsms_phone_verified',
        ));

        $suspendedUsers = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key = %s",
            'wsms_suspended',
        ));

        return [
            'total_users'              => $totalUsers,
            'mfa_enrolled'             => $mfaEnrolled,
            'mfa_adoption_rate'        => $totalUsers > 0 ? round(($mfaEnrolled / $totalUsers) * 100, 1) : 0,
            'email_verified'           => $emailVerified,
            'email_verification_rate'  => $totalUsers > 0 ? round(($emailVerified / $totalUsers) * 100, 1) : 0,
            'phone_verified'           => $phoneVerified,
            'phone_verification_rate'  => $totalUsers > 0 ? round(($phoneVerified / $totalUsers) * 100, 1) : 0,
            'suspended_users'          => $suspendedUsers,
        ];
    }

    private function getChannelUsage(int $rangeDays): array
    {
        global $wpdb;

        $logsTable = $wpdb->prefix . 'wsms_auth_logs';
        $factorsTable = $wpdb->prefix . 'wsms_user_factors';
        $successIn = self::eventIn(self::LOGIN_SUCCESS_EVENTS);

        // Login methods
        $socialEvent = EventType::SocialLoginSuccess->value;
        $magicEvent = EventType::MagicLinkVerified->value;
        $loginRows = $wpdb->get_results($wpdb->prepare(
            "SELECT
                CASE
                    WHEN event = '{$socialEvent}' THEN 'social'
                    WHEN event = '{$magicEvent}' THEN 'magic_link'
                    WHEN channel_id IS NOT NULL AND channel_id != '' THEN channel_id
                    ELSE 'password'
                END AS method,
                COUNT(*) AS count
            FROM {$logsTable}
            WHERE event IN ({$successIn})
                AND created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
            GROUP BY method
            ORDER BY count DESC",
            $rangeDays,
        ));

        $loginMethods = [];
        foreach ($loginRows as $row) {
            $loginMethods[] = ['method' => $row->method, 'count' => (int) $row->count];
        }

        // MFA methods (only for existing users)
        $mfaRows = $wpdb->get_results(
            "SELECT f.channel_id AS method, COUNT(*) AS count
            FROM {$factorsTable} f
            INNER JOIN {$wpdb->users} u ON u.ID = f.user_id
            WHERE f.status = 'active' AND f.channel_id != 'backup_codes'
            GROUP BY f.channel_id
            ORDER BY count DESC",
        );

        $mfaMethods = [];
        foreach ($mfaRows as $row) {
            $mfaMethods[] = ['method' => $row->method, 'count' => (int) $row->count];
        }

        // Social providers
        $socialRows = $wpdb->get_results($wpdb->prepare(
            "SELECT
                COALESCE(channel_id, 'unknown') AS provider,
                COUNT(*) AS count
            FROM {$logsTable}
            WHERE event = %s
                AND created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
            GROUP BY provider
            ORDER BY count DESC",
            EventType::SocialLoginSuccess->value,
            $rangeDays,
        ));

        $socialProviders = [];
        foreach ($socialRows as $row) {
            $socialProviders[] = ['provider' => $row->provider, 'count' => (int) $row->count];
        }

        return [
            'login_methods'    => $loginMethods,
            'mfa_methods'      => $mfaMethods,
            'social_providers' => $socialProviders,
        ];
    }

    private function getSecurityAlerts(int $rangeDays): array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'wsms_auth_logs';
        $failureIn = self::eventIn(self::LOGIN_FAILURE_EVENTS);
        $otpFailureIn = self::eventIn(self::OTP_FAILURE_EVENTS);

        // Summary counts in a single query
        $summary = $wpdb->get_row($wpdb->prepare(
            "SELECT
                SUM(CASE WHEN event IN ({$failureIn}) THEN 1 ELSE 0 END) AS failed_logins,
                SUM(CASE WHEN event = %s THEN 1 ELSE 0 END) AS accounts_locked,
                SUM(CASE WHEN event = %s THEN 1 ELSE 0 END) AS accounts_suspended,
                SUM(CASE WHEN event IN ({$otpFailureIn}) THEN 1 ELSE 0 END) AS otp_failures
            FROM {$table}
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)",
            EventType::AccountLocked->value,
            EventType::AccountSuspended->value,
            $rangeDays,
        ));

        // Top failed IPs
        $topIps = $wpdb->get_results($wpdb->prepare(
            "SELECT ip_address AS ip, COUNT(*) AS count
            FROM {$table}
            WHERE event IN ({$failureIn})
                AND ip_address IS NOT NULL
                AND ip_address != ''
                AND created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
            GROUP BY ip_address
            ORDER BY count DESC
            LIMIT 10",
            $rangeDays,
        ));

        $topFailedIps = [];
        foreach ($topIps as $row) {
            $topFailedIps[] = ['ip' => $row->ip, 'count' => (int) $row->count];
        }

        $recentLockouts = $this->getRecentUserEvents($table, EventType::AccountLocked, 'locked_at', $rangeDays);
        $recentSuspensions = $this->getRecentUserEvents($table, EventType::AccountSuspended, 'suspended_at', $rangeDays);

        return [
            'failed_login_attempts' => (int) ($summary->failed_logins ?? 0),
            'accounts_locked'       => (int) ($summary->accounts_locked ?? 0),
            'accounts_suspended'    => (int) ($summary->accounts_suspended ?? 0),
            'otp_failures'          => (int) ($summary->otp_failures ?? 0),
            'top_failed_ips'        => $topFailedIps,
            'recent_lockouts'       => $recentLockouts,
            'recent_suspensions'    => $recentSuspensions,
        ];
    }

    private function getRecentUserEvents(string $table, EventType $event, string $dateKey, int $rangeDays, int $limit = 10): array
    {
        global $wpdb;

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT l.user_id, l.ip_address AS ip, l.created_at AS event_at
            FROM {$table} l
            WHERE l.event = %s
                AND l.created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
            ORDER BY l.created_at DESC
            LIMIT %d",
            $event->value,
            $rangeDays,
            $limit,
        ));

        $userIds = array_unique(array_filter(array_column($rows, 'user_id')));

        if (!empty($userIds)) {
            cache_users(array_map('intval', $userIds));
        }

        $result = [];
        foreach ($rows as $row) {
            $user = get_userdata((int) $row->user_id);
            $result[] = [
                'user_id'      => (int) $row->user_id,
                'display_name' => $user ? $user->display_name : 'Unknown',
                $dateKey        => $row->event_at,
                'ip'           => $row->ip ?? '',
            ];
        }

        return $result;
    }

    /**
     * Build a quoted, comma-separated event value list for IN() clauses.
     */
    private static function eventIn(array $events): string
    {
        return implode(',', array_map(
            static fn(EventType $e) => "'" . $e->value . "'",
            $events,
        ));
    }
}
