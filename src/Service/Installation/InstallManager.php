<?php

namespace WSms\Service\Installation;

use WSms\Database\CleanupScheduler;
use WSms\Database\Migrator;

defined('ABSPATH') || exit;

class InstallManager
{
    /**
     * Run on plugin activation.
     *
     * @param bool $networkWide Whether the plugin is being activated network-wide.
     */
    public static function activate(bool $networkWide): void
    {
        Migrator::createTables();

        set_transient('wsms_flush_rewrite', '1');

        if (!wp_next_scheduled(CleanupScheduler::HOOK_NAME)) {
            wp_schedule_event(time(), 'daily', CleanupScheduler::HOOK_NAME);
        }

        add_option('wsms_auth_settings', [
            'primary_methods'        => ['password'],
            'mfa_factors'            => [],
            'mfa_required_roles'     => [],
            'enrollment_timing'      => 'voluntary',
            'grace_period_days'      => 7,
            'auto_create_users'      => false,
            'auth_base_url'          => '/account',
            'otp_sms_length'         => 6,
            'otp_sms_expiry'         => 300,
            'otp_sms_max_attempts'   => 5,
            'otp_sms_cooldown'       => 60,
            'otp_email_length'       => 6,
            'otp_email_expiry'       => 600,
            'otp_email_max_attempts' => 5,
            'otp_email_cooldown'     => 60,
            'magic_link_expiry'      => 600,
            'backup_codes_count'     => 10,
            'backup_codes_length'    => 10,
            'log_verbosity'          => 'standard',
            'log_retention_days'     => 90,
            'registration_fields'    => ['email', 'password'],
        ]);
    }

    /**
     * Run on plugin deactivation.
     */
    public static function deactivate(): void
    {
        wp_clear_scheduled_hook(CleanupScheduler::HOOK_NAME);
    }
}
