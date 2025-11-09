<?php

namespace WP_SMS\Settings\Groups;

use WP_SMS\Settings\Abstracts\AbstractSettingGroup;
use WP_SMS\Settings\Field;
use WP_SMS\Settings\Section;
use WP_SMS\Settings\LucideIcons;
use WP_SMS\Settings\Tags;

class OTPSettings extends AbstractSettingGroup
{
    public function getName(): string
    {
        return 'otp-settings';
    }

    public function getLabel(): string
    {
        return __('OTP Settings', 'wp-sms');
    }

    public function getIcon(): string
    {
        return LucideIcons::SETTINGS;
    }

    public function getSections(): array
    {
        return [
            // IP Whitelist Section
            new Section([
                'id'       => 'ip_whitelist',
                'title'    => __('IP Whitelist', 'wp-sms'),
                'subtitle' => __('Configure trusted IP addresses to bypass security restrictions', 'wp-sms'),
                'order'    => 1,
                'fields'   => [
                    new Field([
                        'key'         => 'otp_ip_whitelist_enabled',
                        'label'       => __('Enable IP Whitelist', 'wp-sms'),
                        'type'        => 'checkbox',
                        'description' => __('Enable IP whitelisting to bypass rate limits and security checks for trusted IPs', 'wp-sms'),
                        'default'     => false,
                    ]),
                    new Field([
                        'key'         => 'otp_ip_whitelist_addresses',
                        'label'       => __('Whitelisted IP Addresses', 'wp-sms'),
                        'type'        => 'textarea',
                        'description' => __('Enter one IP address per line. Supports IPv4 and IPv6. Use CIDR notation for ranges (e.g., 192.168.1.0/24)', 'wp-sms'),
                        'placeholder' => "192.168.1.100\n10.0.0.0/8\n2001:db8::/32",
                        'rows'        => 10,
                        'show_if'     => [
                            'otp_ip_whitelist_enabled' => true,
                        ],
                    ]),
                    new Field([
                        'key'         => 'otp_ip_whitelist_bypass_rate_limit',
                        'label'       => __('Bypass Rate Limiting', 'wp-sms'),
                        'type'        => 'checkbox',
                        'description' => __('Whitelisted IPs bypass rate limiting', 'wp-sms'),
                        'default'     => true,
                        'show_if'     => [
                            'otp_ip_whitelist_enabled' => true,
                        ],
                    ]),
                    new Field([
                        'key'         => 'otp_ip_whitelist_bypass_mfa',
                        'label'       => __('Bypass MFA Requirements', 'wp-sms'),
                        'type'        => 'checkbox',
                        'description' => __('Whitelisted IPs bypass multi-factor authentication', 'wp-sms'),
                        'default'     => false,
                        'show_if'     => [
                            'otp_ip_whitelist_enabled' => true,
                        ],
                    ]),
                    new Field([
                        'key'         => 'otp_ip_whitelist_log_bypasses',
                        'label'       => __('Log Whitelist Bypasses', 'wp-sms'),
                        'type'        => 'checkbox',
                        'description' => __('Log authentication events when whitelist rules are applied', 'wp-sms'),
                        'default'     => true,
                        'show_if'     => [
                            'otp_ip_whitelist_enabled' => true,
                        ],
                    ]),
                ]
            ]),

            // Rate Limiting Section
            new Section([
                'id'       => 'rate_limiting',
                'title'    => __('Rate Limiting', 'wp-sms'),
                'subtitle' => __('Configure rate limiting policies for authentication attempts', 'wp-sms'),
                'order'    => 2,
                'fields'   => [
                    new Field([
                        'key'         => 'otp_rate_limit_enabled',
                        'label'       => __('Enable Rate Limiting', 'wp-sms'),
                        'type'        => 'checkbox',
                        'description' => __('Enable rate limiting for authentication endpoints', 'wp-sms'),
                        'default'     => true,
                    ]),
                    new Field([
                        'key'         => 'otp_rate_limit_max_attempts',
                        'label'       => __('Max Attempts', 'wp-sms'),
                        'type'        => 'number',
                        'description' => __('Maximum attempts allowed within the time window', 'wp-sms'),
                        'default'     => 15,
                        'min'         => 1,
                        'max'         => 100,
                        'readonly'    => true,
                        'tag'         => Tags::COMING_SOON,
                    ]),
                    new Field([
                        'key'         => 'otp_rate_limit_window_seconds',
                        'label'       => __('Time Window (seconds)', 'wp-sms'),
                        'type'        => 'number',
                        'description' => __('Time window for rate limiting in seconds', 'wp-sms'),
                        'default'     => 300,
                        'min'         => 60,
                        'max'         => 3600,
                        'readonly'    => true,
                        'tag'         => Tags::COMING_SOON,
                    ]),
                ]
            ]),

            // MFA Enforcement Section
            new Section([
                'id'       => 'mfa_enforcement',
                'title'    => __('MFA Enforcement', 'wp-sms'),
                'subtitle' => __('Control which users and roles are required to use multi-factor authentication', 'wp-sms'),
                'order'    => 3,
                'fields'   => [
                    new Field([
                        'key'         => 'otp_mfa_enforcement_enabled',
                        'label'       => __('Enable MFA Enforcement', 'wp-sms'),
                        'type'        => 'checkbox',
                        'description' => __('Enable role-based and user-specific MFA enforcement policies', 'wp-sms'),
                        'default'     => false,
                    ]),
                    new Field([
                        'key'         => 'otp_mfa_enforcement_strategy',
                        'label'       => __('Enforcement Strategy', 'wp-sms'),
                        'type'        => 'select',
                        'description' => __('Choose how MFA enforcement is applied', 'wp-sms'),
                        'options'     => [
                            'all_users'      => __('All Users (Global)', 'wp-sms'),
                            'specific_roles' => __('Specific Roles', 'wp-sms'),
                            'specific_users' => __('Specific Users', 'wp-sms'),
                            'roles_and_users' => __('Specific Roles and Users', 'wp-sms'),
                        ],
                        'default'     => 'specific_roles',
                        'show_if'     => [
                            'otp_mfa_enforcement_enabled' => true,
                        ],
                    ]),
                    new Field([
                        'key'         => 'otp_mfa_enforcement_roles',
                        'label'       => __('Required Roles', 'wp-sms'),
                        'type'        => 'multiselect',
                        'description' => __('Select roles that must use MFA (e.g., Administrator, Shop Manager, Editor)', 'wp-sms'),
                        'options'     => $this->getAvailableRoles(),
                        'placeholder' => __('Select roles...', 'wp-sms'),
                        'show_if'     => [
                            'otp_mfa_enforcement_enabled' => true,
                            'otp_mfa_enforcement_strategy' => ['specific_roles', 'roles_and_users'],
                        ],
                    ]),
                    new Field([
                        'key'         => 'otp_mfa_enforcement_users',
                        'label'       => __('Required Users', 'wp-sms'),
                        'type'        => 'textarea',
                        'description' => __('Enter user IDs or usernames (one per line) that must use MFA regardless of their role', 'wp-sms'),
                        'placeholder' => "admin\njohn_doe\n42",
                        'rows'        => 5,
                        'show_if'     => [
                            'otp_mfa_enforcement_enabled' => true,
                            'otp_mfa_enforcement_strategy' => ['specific_users', 'roles_and_users'],
                        ],
                    ]),
                    new Field([
                        'key'         => 'otp_mfa_enforcement_excluded_roles',
                        'label'       => __('Excluded Roles', 'wp-sms'),
                        'type'        => 'multiselect',
                        'description' => __('Roles to exclude from MFA enforcement (e.g., Subscriber, Customer)', 'wp-sms'),
                        'options'     => $this->getAvailableRoles(),
                        'placeholder' => __('Select roles to exclude...', 'wp-sms'),
                        'show_if'     => [
                            'otp_mfa_enforcement_enabled' => true,
                            'otp_mfa_enforcement_strategy' => 'all_users',
                        ],
                    ]),
                    new Field([
                        'key'         => 'otp_mfa_enforcement_grace_period',
                        'label'       => __('Grace Period (days)', 'wp-sms'),
                        'type'        => 'number',
                        'description' => __('Number of days users have to set up MFA before enforcement begins (0 = immediate)', 'wp-sms'),
                        'default'     => 7,
                        'min'         => 0,
                        'max'         => 90,
                        'show_if'     => [
                            'otp_mfa_enforcement_enabled' => true,
                        ],
                    ]),
                    new Field([
                        'key'         => 'otp_mfa_enforcement_allow_skip',
                        'label'       => __('Allow Skip During Grace Period', 'wp-sms'),
                        'type'        => 'checkbox',
                        'description' => __('Users can skip MFA setup during grace period (will be prompted again at next login)', 'wp-sms'),
                        'default'     => true,
                        'show_if'     => [
                            'otp_mfa_enforcement_enabled' => true,
                        ],
                    ]),
                    new Field([
                        'key'         => 'otp_mfa_enforcement_reminder_frequency',
                        'label'       => __('Reminder Frequency', 'wp-sms'),
                        'type'        => 'select',
                        'description' => __('How often to remind users to set up MFA during grace period', 'wp-sms'),
                        'options'     => [
                            'every_login' => __('Every Login', 'wp-sms'),
                            'daily'       => __('Daily', 'wp-sms'),
                            'weekly'      => __('Weekly', 'wp-sms'),
                            'never'       => __('Never', 'wp-sms'),
                        ],
                        'default'     => 'daily',
                        'show_if'     => [
                            'otp_mfa_enforcement_enabled' => true,
                            'otp_mfa_enforcement_allow_skip' => true,
                        ],
                    ]),
                ]
            ]),

            // Redirect Settings Section
            new Section([
                'id'       => 'redirects',
                'title'    => __('Redirect Settings', 'wp-sms'),
                'subtitle' => __('Configure where users are redirected after login and registration', 'wp-sms'),
                'order'    => 4,
                'fields'   => [
                    new Field([
                        'key'         => 'otp_login_redirect_url',
                        'label'       => __('Default Login Redirect', 'wp-sms'),
                        'type'        => 'text',
                        'description' => __('Where users are redirected after successful login (leave empty for homepage)', 'wp-sms'),
                        'placeholder' => '/',
                        'default'     => '',
                    ]),
                    new Field([
                        'key'         => 'otp_register_redirect_url',
                        'label'       => __('Default Register Redirect', 'wp-sms'),
                        'type'        => 'text',
                        'description' => __('Where users are redirected after successful registration (leave empty for homepage)', 'wp-sms'),
                        'placeholder' => '/',
                        'default'     => '',
                    ]),
                    new Field([
                        'key'         => 'otp_enable_role_based_redirects',
                        'label'       => __('Enable Role-Based Redirects', 'wp-sms'),
                        'type'        => 'checkbox',
                        'description' => __('Redirect users based on their role after login', 'wp-sms'),
                        'default'     => false,
                    ]),
                    new Field([
                        'key'         => 'otp_role_redirects',
                        'label'       => __('Role-Based Redirect URLs', 'wp-sms'),
                        'type'        => 'textarea',
                        'description' => __('Format: role_name|/redirect-url (one per line). Example: subscriber|/dashboard', 'wp-sms'),
                        'placeholder' => "administrator|/wp-admin\neditor|/editor-dashboard\nsubscriber|/my-account",
                        'rows'        => 8,
                        'show_if'     => [
                            'otp_enable_role_based_redirects' => true,
                        ],
                    ]),
                    new Field([
                        'key'         => 'otp_auto_login_after_register',
                        'label'       => __('Auto-Login After Registration', 'wp-sms'),
                        'type'        => 'checkbox',
                        'description' => __('Automatically log users in after successful registration', 'wp-sms'),
                        'default'     => true,
                    ]),
                    new Field([
                        'key'         => 'otp_preserve_redirect_to',
                        'label'       => __('Preserve ?redirect_to Parameter', 'wp-sms'),
                        'type'        => 'checkbox',
                        'description' => __('Honor ?redirect_to query parameter from protected pages', 'wp-sms'),
                        'default'     => true,
                    ]),
                    new Field([
                        'key'         => 'otp_admin_redirect_to_dashboard',
                        'label'       => __('Admins to Dashboard', 'wp-sms'),
                        'type'        => 'checkbox',
                        'description' => __('Always redirect administrators to WP dashboard (overrides other settings)', 'wp-sms'),
                        'default'     => true,
                    ]),
                ]
            ]),

            // Password Reset Section
            new Section([
                'id'       => 'password_reset',
                'title'    => __('Password Reset', 'wp-sms'),
                'subtitle' => __('Configure password recovery options for password-based authentication', 'wp-sms'),
                'order'    => 5,
                'fields'   => [
                    new Field([
                        'key'         => 'otp_password_reset_enabled',
                        'label'       => __('Enable Password Reset', 'wp-sms'),
                        'type'        => 'checkbox',
                        'description' => __('Allow users to reset their password via OTP/Magic Link verification', 'wp-sms'),
                        'default'     => true,
                    ]),
                    new Field([
                        'key'         => 'otp_password_reset_token_expiry',
                        'label'       => __('Reset Token Expiry (minutes)', 'wp-sms'),
                        'type'        => 'number',
                        'description' => __('How long reset tokens/codes remain valid', 'wp-sms'),
                        'default'     => 15,
                        'min'         => 5,
                        'max'         => 60,
                        'show_if'     => [
                            'otp_password_reset_enabled' => true,
                        ],
                    ]),
                    new Field([
                        'key'         => 'otp_password_reset_auto_login',
                        'label'       => __('Auto-Login After Reset', 'wp-sms'),
                        'type'        => 'checkbox',
                        'description' => __('Automatically log users in after successful password reset', 'wp-sms'),
                        'default'     => true,
                        'show_if'     => [
                            'otp_password_reset_enabled' => true,
                        ],
                    ]),
                    new Field([
                        'key'         => 'otp_password_reset_allowed_identifiers',
                        'label'       => __('Allowed Recovery Identifiers', 'wp-sms'),
                        'type'        => 'multiselect',
                        'description' => __('Which identifiers can be used to initiate password reset', 'wp-sms'),
                        'options'     => [
                            'email' => __('Email Address', 'wp-sms'),
                            'phone' => __('Phone Number', 'wp-sms'),
                        ],
                        'default'     => ['email', 'phone'],
                        'show_if'     => [
                            'otp_password_reset_enabled' => true,
                        ],
                    ]),
                    new Field([
                        'key'         => 'otp_password_reset_require_verification',
                        'label'       => __('Require Identifier Verification', 'wp-sms'),
                        'type'        => 'checkbox',
                        'description' => __('Only allow reset for identifiers that were verified during registration', 'wp-sms'),
                        'default'     => true,
                        'show_if'     => [
                            'otp_password_reset_enabled' => true,
                        ],
                    ]),
                    new Field([
                        'key'         => 'otp_password_reset_min_password_length',
                        'label'       => __('Minimum Password Length', 'wp-sms'),
                        'type'        => 'number',
                        'description' => __('Minimum characters required for new password', 'wp-sms'),
                        'default'     => 8,
                        'min'         => 6,
                        'max'         => 32,
                        'show_if'     => [
                            'otp_password_reset_enabled' => true,
                        ],
                    ]),
                ]
            ]),

            // Security Section
            new Section([
                'id'       => 'security',
                'title'    => __('Security Settings', 'wp-sms'),
                'subtitle' => __('Advanced security configuration', 'wp-sms'),
                'order'    => 6,
                'fields'   => [
                    new Field([
                        'key'         => 'otp_session_cleanup_enabled',
                        'label'       => __('Auto Cleanup Expired Sessions', 'wp-sms'),
                        'type'        => 'checkbox',
                        'description' => __('Automatically clean up expired OTP sessions and pending users', 'wp-sms'),
                        'default'     => true,
                    ]),
                    new Field([
                        'key'         => 'otp_pending_user_expiry_hours',
                        'label'       => __('Pending User Expiry (hours)', 'wp-sms'),
                        'type'        => 'number',
                        'description' => __('Delete pending users after this many hours', 'wp-sms'),
                        'default'     => 24,
                        'min'         => 1,
                        'max'         => 168,
                        'show_if'     => [
                            'otp_session_cleanup_enabled' => true,
                        ],
                    ]),
                    new Field([
                        'key'         => 'otp_geo_blocking_enabled',
                        'label'       => __('Enable Geo-blocking', 'wp-sms'),
                        'type'        => 'checkbox',
                        'description' => __('Block authentication from specific countries', 'wp-sms'),
                        'default'     => false,
                        'readonly'    => true,
                        'tag'         => Tags::COMING_SOON,
                    ]),
                ]
            ]),
        ];
    }

    public function getFields(): array
    {
        // Legacy method - return all fields from all sections for backward compatibility
        $allFields = [];
        foreach ($this->getSections() as $section) {
            $allFields = array_merge($allFields, $section->getFields());
        }

        return $allFields;
    }

    /**
     * Get available WordPress roles for MFA enforcement
     *
     * @return array
     */
    private function getAvailableRoles(): array
    {
        global $wp_roles;

        if (!isset($wp_roles)) {
            $wp_roles = new \WP_Roles();
        }

        $roles = [];
        foreach ($wp_roles->get_names() as $roleKey => $roleName) {
            $roles[$roleKey] = translate_user_role($roleName);
        }

        return $roles;
    }
}

