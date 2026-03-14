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
            'phone' => [
                'enabled'              => false,
                'usage'                => 'login',
                'verification_methods' => ['otp'],
                'delivery_channel'     => 'sms',
                'required_at_signup'   => false,
                'verify_at_signup'     => false,
                'allow_sign_in'        => true,
                'code_length'          => 6,
                'expiry'               => 300,
                'max_attempts'         => 3,
                'cooldown'             => 60,
            ],
            'email' => [
                'enabled'              => true,
                'usage'                => 'login',
                'verification_methods' => ['otp'],
                'required_at_signup'   => true,
                'verify_at_signup'     => false,
                'allow_sign_in'        => true,
                'code_length'          => 6,
                'expiry'               => 600,
                'max_attempts'         => 3,
                'cooldown'             => 60,
            ],
            'password' => [
                'enabled'            => true,
                'required_at_signup' => true,
                'allow_sign_in'      => true,
            ],
            'backup_codes' => [
                'enabled' => false,
                'count'   => 10,
                'length'  => 10,
            ],
            'totp' => [
                'enabled' => false,
            ],
            'mfa_required_roles'  => [],
            'enrollment_timing'   => 'voluntary',
            'grace_period_days'   => 7,
            'auth_base_url'       => '/account',
            'redirect_login'      => false,
            'auto_create_users'   => false,
            'log_verbosity'       => 'standard',
            'log_retention_days'  => 90,
            'registration_fields' => ['email', 'password'],
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
