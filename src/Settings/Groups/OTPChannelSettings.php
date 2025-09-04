<?php

namespace WP_SMS\Settings\Groups;

use WP_SMS\Settings\Abstracts\AbstractSettingGroup;
use WP_SMS\Settings\Field;
use WP_SMS\Settings\Section;
use WP_SMS\Settings\LucideIcons;
use WP_SMS\Settings\Tags;

class OTPChannelSettings extends AbstractSettingGroup
{
    public function getName(): string
    {
        return 'otp';
    }

    public function getLabel(): string
    {
        return __('OTP Channels', 'wp-sms');
    }

    public function getIcon(): string
    {
        return LucideIcons::SHIELD;
    }

    /**
     * Hide OTP Channels from API endpoints by default.
     * This group contains sensitive OTP configuration that should not be exposed.
     *
     * @return bool
     */
    public function isApiVisible(): bool
    {
        // TODO: Hide OTP Channels from API endpoints by default.
        // This group contains sensitive OTP configuration that should not be exposed.
        return true;
    }

    public function getSections(): array
    {
        return [
            new Section([
                'id' => 'otp_channels',
                'title' => __('OTP Channels', 'wp-sms'),
                'subtitle' => __('Configure OTP verification channels and their settings', 'wp-sms'),
                'order' => 2,
                'fields' => [
                    // Username Channel
                    new Field([
                        'key' => 'otp_channel_username',
                        'label' => __('Username', 'wp-sms'),
                        'type' => 'checkbox',
                        'default' => false,
                        'sub_fields' => [
                            new Field([
                                'key' => 'otp_channel_username_min_length',
                                'label' => __('Minimum Length', 'wp-sms'),
                                'type' => 'number',
                                'description' => __('Minimum username length requirement', 'wp-sms'),
                                'default' => 3,
                                'min' => 2,
                                'max' => 10,
                            ]),
                            new Field([
                                'key' => 'otp_channel_username_max_length',
                                'label' => __('Maximum Length', 'wp-sms'),
                                'type' => 'number',
                                'description' => __('Maximum username length requirement', 'wp-sms'),
                                'default' => 20,
                                'min' => 5,
                                'max' => 50,
                            ]),
                            new Field([
                                'key' => 'otp_channel_username_unique_requirement',
                                'label' => __('Require Unique Username', 'wp-sms'),
                                'type' => 'checkbox',
                                'description' => __('Ensure usernames are unique across the system', 'wp-sms'),
                                'default' => true,
                            ]),
                            new Field([
                                'key' => 'otp_channel_username_case_sensitive',
                                'label' => __('Case Sensitive', 'wp-sms'),
                                'type' => 'checkbox',
                                'description' => __('Treat usernames as case sensitive', 'wp-sms'),
                                'default' => false,
                            ]),
                            new Field([
                                'key' => 'otp_channel_username_allowed_characters',
                                'label' => __('Allowed Characters', 'wp-sms'),
                                'type' => 'multiselect',
                                'description' => __('Select which characters are allowed in usernames', 'wp-sms'),
                                'options' => [
                                    'alphanumeric' => __('Alphanumeric (a-z, A-Z, 0-9)', 'wp-sms'),
                                    'underscores' => __('Underscores (_)', 'wp-sms'),
                                    'hyphens' => __('Hyphens (-)', 'wp-sms'),
                                    'dots' => __('Dots (.)', 'wp-sms'),
                                ],
                                'default' => ['alphanumeric', 'underscores', 'hyphens', 'dots'],
                            ]),
                            new Field([
                                'key' => 'otp_channel_username_reserved_words',
                                'label' => __('Reserved Words (Blacklist)', 'wp-sms'),
                                'type' => 'textarea',
                                'description' => __('Comma-separated list of reserved usernames that cannot be used', 'wp-sms'),
                                'placeholder' => 'admin, root, system, test, demo',
                                'default' => 'admin, root, system, test, demo',
                                'rows' => 3,
                            ]),
                            new Field([
                                'key' => 'otp_channel_username_real_time_check',
                                'label' => __('Real-time Availability Check', 'wp-sms'),
                                'type' => 'checkbox',
                                'description' => __('Check username availability in real-time as user types', 'wp-sms'),
                                'default' => true,
                            ]),
                            new Field([
                                'key' => 'otp_channel_username_field_label',
                                'label' => __('Username Field Label', 'wp-sms'),
                                'type' => 'text',
                                'description' => __('Custom label for the username field in forms', 'wp-sms'),
                                'default' => __('Username', 'wp-sms'),
                                'placeholder' => __('Username', 'wp-sms'),
                            ]),
                            new Field([
                                'key' => 'otp_channel_username_required_signup',
                                'label' => __('Required at Sign Up', 'wp-sms'),
                                'type' => 'checkbox',
                                'description' => __('Username is required during user registration', 'wp-sms'),
                                'default' => true,
                            ]),
                            new Field([
                                'key' => 'otp_channel_username_allow_signin',
                                'label' => __('Allow to Sign In', 'wp-sms'),
                                'type' => 'checkbox',
                                'description' => __('Allow users to sign in using username', 'wp-sms'),
                                'default' => true,
                            ]),

                        ]
                    ]),
                    // Password Channel
                    new Field([
                        'key' => 'otp_channel_password',
                        'label' => __('Password', 'wp-sms'),
                        'type' => 'checkbox',
                        'default' => false,
                        'sub_fields' => [
                            new Field([
                                'key' => 'otp_channel_password_min_length',
                                'label' => __('Minimum Length', 'wp-sms'),
                                'type' => 'number',
                                'description' => __('Minimum password length requirement', 'wp-sms'),
                                'default' => 8,
                                'min' => 6,
                                'max' => 20,
                            ]),
                            new Field([
                                'key' => 'otp_channel_password_max_length',
                                'label' => __('Maximum Length', 'wp-sms'),
                                'type' => 'number',
                                'description' => __('Maximum password length requirement', 'wp-sms'),
                                'default' => 128,
                                'min' => 10,
                                'max' => 255,
                            ]),
                            new Field([
                                'key' => 'otp_channel_password_require_uppercase',
                                'label' => __('Require Uppercase', 'wp-sms'),
                                'type' => 'checkbox',
                                'description' => __('Password must contain at least one uppercase letter', 'wp-sms'),
                                'default' => true,
                            ]),
                            new Field([
                                'key' => 'otp_channel_password_require_lowercase',
                                'label' => __('Require Lowercase', 'wp-sms'),
                                'type' => 'checkbox',
                                'description' => __('Password must contain at least one lowercase letter', 'wp-sms'),
                                'default' => true,
                            ]),
                            new Field([
                                'key' => 'otp_channel_password_require_numbers',
                                'label' => __('Require Numbers', 'wp-sms'),
                                'type' => 'checkbox',
                                'description' => __('Password must contain at least one number', 'wp-sms'),
                                'default' => true,
                            ]),
                            new Field([
                                'key' => 'otp_channel_password_require_special',
                                'label' => __('Require Special Characters', 'wp-sms'),
                                'type' => 'checkbox',
                                'description' => __('Password must contain at least one special character', 'wp-sms'),
                                'default' => false,
                            ]),
                            new Field([
                                'key' => 'otp_channel_password_strength_meter',
                                'label' => __('Show Strength Meter', 'wp-sms'),
                                'type' => 'checkbox',
                                'description' => __('Display password strength indicator to users', 'wp-sms'),
                                'default' => true,
                            ]),
                            new Field([
                                'key' => 'otp_channel_password_expiry_days',
                                'label' => __('Password Expiry (days)', 'wp-sms'),
                                'type' => 'number',
                                'description' => __('Number of days before password expires (0 = never)', 'wp-sms'),
                                'default' => 90,
                                'min' => 0,
                                'max' => 365,
                            ]),
                            new Field([
                                'key' => 'otp_channel_password_history',
                                'label' => __('Password History', 'wp-sms'),
                                'type' => 'number',
                                'description' => __('Prevent reuse of last N passwords', 'wp-sms'),
                                'default' => 5,
                                'min' => 1,
                                'max' => 20,
                                'readonly' => true,
                                'tag' => Tags::COMING_SOON,
                            ]),
                            new Field([
                                'key' => 'otp_channel_password_account_lockout',
                                'label' => __('Account Lockout', 'wp-sms'),
                                'type' => 'number',
                                'description' => __('Lock account after X failed attempts', 'wp-sms'),
                                'default' => 5,
                                'min' => 3,
                                'max' => 10,
                                'readonly' => true,
                                'tag' => Tags::COMING_SOON,
                            ]),
                            new Field([
                                'key' => 'otp_channel_password_complexity_score',
                                'label' => __('Password Complexity Score', 'wp-sms'),
                                'type' => 'number',
                                'description' => __('Minimum strength requirement', 'wp-sms'),
                                'default' => 3,
                                'min' => 1,
                                'max' => 5,
                                'readonly' => true,
                                'tag' => Tags::COMING_SOON,
                            ]),
                            new Field([
                                'key' => 'otp_channel_password_force_change',
                                'label' => __('Force Password Change', 'wp-sms'),
                                'type' => 'checkbox',
                                'description' => __('Require password change on first login', 'wp-sms'),
                                'default' => false,
                                'readonly' => true,
                                'tag' => Tags::COMING_SOON,
                            ]),
                            new Field([
                                'key' => 'otp_channel_password_common_blacklist',
                                'label' => __('Common Password Blacklist', 'wp-sms'),
                                'type' => 'textarea',
                                'description' => __('Block common weak passwords', 'wp-sms'),
                                'placeholder' => 'password, 123456, qwerty',
                                'default' => 'password, 123456, qwerty',
                                'rows' => 3,
                                'readonly' => true,
                                'tag' => Tags::COMING_SOON,
                            ]),
                            new Field([
                                'key' => 'otp_channel_password_personal_info_check',
                                'label' => __('Personal Info Check', 'wp-sms'),
                                'type' => 'checkbox',
                                'description' => __('Prevent passwords containing username/email', 'wp-sms'),
                                'default' => true,
                                'readonly' => true,
                                'tag' => Tags::COMING_SOON,
                            ]),
                            new Field([
                                'key' => 'otp_channel_password_sequential_limit',
                                'label' => __('Sequential Character Limit', 'wp-sms'),
                                'type' => 'number',
                                'description' => __('Prevent patterns like "123456"', 'wp-sms'),
                                'default' => 3,
                                'min' => 2,
                                'max' => 6,
                                'readonly' => true,
                                'tag' => Tags::COMING_SOON,
                            ]),
                            new Field([
                                'key' => 'otp_channel_password_required_signup',
                                'label' => __('Required at Sign Up', 'wp-sms'),
                                'type' => 'checkbox',
                                'description' => __('Password is required during user registration', 'wp-sms'),
                                'default' => true,
                            ]),
                            new Field([
                                'key' => 'otp_channel_password_allow_signin',
                                'label' => __('Allow to Sign In', 'wp-sms'),
                                'type' => 'checkbox',
                                'description' => __('Allow users to sign in using password', 'wp-sms'),
                                'default' => true,
                            ]),

                        ]
                    ]),
                    // Phone Channel
                    new Field([
                        'key' => 'otp_channel_phone',
                        'label' => __('Phone Channel', 'wp-sms'),
                        'type' => 'checkbox',
                        'default' => false,
                        'sub_fields' => [
                            new Field([
                                'key' => 'otp_channel_phone_verification_method',
                                'label' => __('Verification Method', 'wp-sms'),
                                'type' => 'multiselect',
                                'description' => __('Select verification methods for phone channel', 'wp-sms'),
                                'options' => [
                                    'otp' => __('OTP Code', 'wp-sms'),
                                    'link' => __('Verification Link', 'wp-sms'),
                                ],
                                'default' => ['otp'],
                            ]),
                            new Field([
                                'key' => 'otp_channel_phone_otp_digits',
                                'label' => __('OTP Digits', 'wp-sms'),
                                'type' => 'number',
                                'description' => __('Number of digits in OTP codes for phone verification', 'wp-sms'),
                                'default' => 6,
                                'min' => 4,
                                'max' => 8,
                            ]),
                            new Field([
                                'key' => 'otp_channel_phone_expiry_seconds',
                                'label' => __('OTP Expiry (seconds)', 'wp-sms'),
                                'type' => 'number',
                                'description' => __('Time in seconds before phone OTP codes expire', 'wp-sms'),
                                'default' => 300,
                                'min' => 60,
                                'max' => 1800,
                                'step' => 30,
                            ]),
                            new Field([
                                'key' => 'otp_channel_phone_smart_auth',
                                'label' => __('Smart Auth', 'wp-sms'),
                                'type' => 'checkbox',
                                'description' => __('Automatically switch between delivery methods after failure (e.g., WhatsApp to SMS)', 'wp-sms'),
                                'default' => true,
                            ]),
                            new Field([
                                'key' => 'otp_channel_phone_fallback_enabled',
                                'label' => __('Enable Fallback to Email', 'wp-sms'),
                                'type' => 'checkbox',
                                'description' => __('Automatically send OTP via email if SMS delivery fails', 'wp-sms'),
                                'default' => true,
                            ]),
                            new Field([
                                'key' => 'otp_channel_phone_sms',
                                'label' => __('Enable SMS Delivery', 'wp-sms'),
                                'type' => 'checkbox',
                                'description' => __('Allow OTP delivery via SMS', 'wp-sms'),
                                'default' => true,
                            ]),
                            new Field([
                                'key' => 'otp_channel_phone_whatsapp',
                                'label' => __('Enable WhatsApp Delivery', 'wp-sms'),
                                'type' => 'checkbox',
                                'description' => __('Allow OTP delivery via WhatsApp', 'wp-sms'),
                                'default' => false,
                                'readonly' => true,
                                'tag' => Tags::COMING_SOON,
                            ]),
                            new Field([
                                'key' => 'otp_channel_phone_viber',
                                'label' => __('Enable Viber Delivery', 'wp-sms'),
                                'type' => 'checkbox',
                                'description' => __('Allow OTP delivery via Viber', 'wp-sms'),
                                'default' => false,
                                'readonly' => true,
                                'tag' => Tags::COMING_SOON,
                            ]),
                            new Field([
                                'key' => 'otp_channel_phone_call',
                                'label' => __('Enable Phone Call Delivery', 'wp-sms'),
                                'type' => 'checkbox',
                                'description' => __('Allow OTP delivery via phone call', 'wp-sms'),
                                'default' => false,
                                'readonly' => true,
                                'tag' => Tags::COMING_SOON,
                            ]),

                            new Field([
                                'key' => 'otp_channel_phone_country_code',
                                'label' => __('Country Code Handling', 'wp-sms'),
                                'type' => 'checkbox',
                                'description' => __('Automatically add country codes to phone numbers', 'wp-sms'),
                                'default' => true,
                                'readonly' => true,
                                'tag' => Tags::COMING_SOON,
                            ]),
                            new Field([
                                'key' => 'otp_channel_phone_retry_limits',
                                'label' => __('Retry Limits', 'wp-sms'),
                                'type' => 'number',
                                'description' => __('Maximum OTP resend attempts allowed', 'wp-sms'),
                                'default' => 3,
                                'min' => 1,
                                'max' => 10,
                                'readonly' => true,
                                'tag' => Tags::COMING_SOON,
                            ]),
                            new Field([
                                'key' => 'otp_channel_phone_rate_limiting',
                                'label' => __('Rate Limiting', 'wp-sms'),
                                'type' => 'number',
                                'description' => __('Minutes between OTP requests to prevent spam', 'wp-sms'),
                                'default' => 1,
                                'min' => 1,
                                'max' => 60,
                                'readonly' => true,
                                'tag' => Tags::COMING_SOON,
                            ]),
                            new Field([
                                'key' => 'otp_channel_phone_suspicious_detection',
                                'label' => __('Suspicious Activity Detection', 'wp-sms'),
                                'type' => 'checkbox',
                                'description' => __('Flag unusual OTP request patterns', 'wp-sms'),
                                'default' => true,
                                'readonly' => true,
                                'tag' => Tags::COMING_SOON,
                            ]),
                            new Field([
                                'key' => 'otp_channel_phone_geolocation_restrictions',
                                'label' => __('Geolocation Restrictions', 'wp-sms'),
                                'type' => 'multiselect',
                                'description' => __('Limit OTP to specific countries', 'wp-sms'),
                                'options' => [
                                    'us' => __('United States', 'wp-sms'),
                                    'ca' => __('Canada', 'wp-sms'),
                                    'gb' => __('United Kingdom', 'wp-sms'),
                                    'au' => __('Australia', 'wp-sms'),
                                    'de' => __('Germany', 'wp-sms'),
                                    'fr' => __('France', 'wp-sms'),
                                ],
                                'default' => [],
                                'readonly' => true,
                                'tag' => Tags::COMING_SOON,
                            ]),
                            new Field([
                                'key' => 'otp_channel_phone_required_signup',
                                'label' => __('Required at Sign Up', 'wp-sms'),
                                'type' => 'checkbox',
                                'description' => __('Phone number is required during user registration', 'wp-sms'),
                                'default' => false,
                            ]),
                            new Field([
                                'key' => 'otp_channel_phone_verify_signup',
                                'label' => __('Verify at Sign Up', 'wp-sms'),
                                'type' => 'checkbox',
                                'description' => __('Phone number must be verified during registration', 'wp-sms'),
                                'default' => true,
                            ]),
                            new Field([
                                'key' => 'otp_channel_phone_allow_signin',
                                'label' => __('Allow to Sign In', 'wp-sms'),
                                'type' => 'checkbox',
                                'description' => __('Allow users to sign in using phone number', 'wp-sms'),
                                'default' => true,
                            ]),
                            new Field([
                                'key' => 'otp_channel_phone_use_as_mfa',
                                'label' => __('Use as MFA (2FA)', 'wp-sms'),
                                'type' => 'checkbox',
                                'description' => __('Allow phone number to be used as second factor authentication', 'wp-sms'),
                                'default' => false,
                            ]),
                        ]
                    ]),
                    // Email Channel
                    new Field([
                        'key' => 'otp_channel_email',
                        'label' => __('Email', 'wp-sms'),
                        'type' => 'checkbox',
                        'default' => false,
                        'sub_fields' => [
                            new Field([
                                'key' => 'otp_channel_email_verification_method',
                                'label' => __('Verification Method', 'wp-sms'),
                                'type' => 'multiselect',
                                'description' => __('Select verification methods for email channel', 'wp-sms'),
                                'options' => [
                                    'otp' => __('OTP Code', 'wp-sms'),
                                    'link' => __('Verification Link', 'wp-sms'),
                                    'both' => __('Both Methods', 'wp-sms'),
                                ],
                                'default' => ['otp'],
                            ]),
                            new Field([
                                'key' => 'otp_channel_email_otp_digits',
                                'label' => __('OTP Digits', 'wp-sms'),
                                'type' => 'number',
                                'description' => __('Number of digits in OTP codes for email verification', 'wp-sms'),
                                'default' => 6,
                                'min' => 4,
                                'max' => 8,
                            ]),
                            new Field([
                                'key' => 'otp_channel_email_expiry_seconds',
                                'label' => __('OTP Expiry (seconds)', 'wp-sms'),
                                'type' => 'number',
                                'description' => __('Time in seconds before email OTP codes expire', 'wp-sms'),
                                'default' => 300,
                                'min' => 60,
                                'max' => 1800,
                                'step' => 30,
                            ]),
                            new Field([
                                'key' => 'otp_channel_email_fallback_enabled',
                                'label' => __('Enable Fallback to SMS', 'wp-sms'),
                                'type' => 'checkbox',
                                'description' => __('Automatically send OTP via SMS if email delivery fails', 'wp-sms'),
                                'default' => true,
                            ]),
                            new Field([
                                'key' => 'otp_channel_email_scheduling',
                                'label' => __('Email Scheduling', 'wp-sms'),
                                'type' => 'checkbox',
                                'description' => __('Allow delayed email sending', 'wp-sms'),
                                'default' => false,
                                'tag' => Tags::COMING_SOON,
                            ]),
                            new Field([
                                'key' => 'otp_channel_email_multiple_recipients',
                                'label' => __('Multiple Recipients', 'wp-sms'),
                                'type' => 'checkbox',
                                'description' => __('Send to backup email addresses', 'wp-sms'),
                                'default' => false,
                                'tag' => Tags::COMING_SOON,
                            ]),
                            new Field([
                                'key' => 'otp_channel_email_required_signup',
                                'label' => __('Required at Sign Up', 'wp-sms'),
                                'type' => 'checkbox',
                                'description' => __('Email is required during user registration', 'wp-sms'),
                                'default' => false,
                            ]),
                            new Field([
                                'key' => 'otp_channel_email_verify_signup',
                                'label' => __('Verify at Sign Up', 'wp-sms'),
                                'type' => 'checkbox',
                                'description' => __('Email must be verified during registration', 'wp-sms'),
                                'default' => true,
                            ]),
                            new Field([
                                'key' => 'otp_channel_email_allow_signin',
                                'label' => __('Allow to Sign In', 'wp-sms'),
                                'type' => 'checkbox',
                                'description' => __('Allow users to sign in using email', 'wp-sms'),
                                'default' => true,
                            ]),
                            new Field([
                                'key' => 'otp_channel_email_use_as_mfa',
                                'label' => __('Use as MFA (2FA)', 'wp-sms'),
                                'type' => 'checkbox',
                                'description' => __('Allow email to be used as second factor authentication', 'wp-sms'),
                                'default' => false,
                            ]),
                        ]
                    ]),
                ]
            ]),
            new Section([
                'id'       => 'branding_sms_templates',
                'title'    => __('Branding', 'wp-sms'),
                'subtitle' => __('SMS Templates', 'wp-sms'),
                'order'    => 100,
                'fields'   => [
                    new Field([
                        'key'               => 'sms_tpl_otp_body',
                        'label'             => __('OTP Code SMS — Body (Text only)', 'wp-sms'),
                        'type'              => 'textarea',
                        'rows'              => 6,
                        'group_label'       => __('OTP Code SMS', 'wp-sms'),
                        'description'       => sprintf(
                            __('Plain text only. Max 2000 chars. Placeholders: %s', 'wp-sms'),
                            esc_html('{{otp_code}} {{expires_in_minutes}} {{user_display_name}} {{site_name}}')
                        ),
                        'placeholder'       => __('{{site_name}} code: {{otp_code}}. Expires in {{expires_in_minutes}} min. If this wasn’t you, ignore.', 'wp-sms'),
                        'sanitize_callback' => ['\\WP_SMS\\Services\\OTP\\Delivery\\PhoneNumber\\Templating\\SanitizeCallbacks', 'body'],
                    ]),
                    new Field([
                        'key'               => 'sms_tpl_otp_revert',
                        'label'             => __('Revert to Default', 'wp-sms'),
                        'type'              => 'checkbox',
                        'group_label'       => __('OTP Code SMS', 'wp-sms'),
                        'description'       => __('If checked, this template uses the default body.', 'wp-sms'),
                        'default'           => false,
                        'sanitize_callback' => ['\\WP_SMS\\Services\\OTP\\Delivery\\PhoneNumber\\Templating\\SanitizeCallbacks', 'revert'],
                    ]),

                    new Field([
                        'key'               => 'sms_tpl_magic_body',
                        'label'             => __('Magic Link SMS — Body (Text only)', 'wp-sms'),
                        'type'              => 'textarea',
                        'rows'              => 6,
                        'group_label'       => __('Magic Link SMS', 'wp-sms'),
                        'description'       => sprintf(
                            __('Plain text only. Max 2000 chars. Placeholders: %s', 'wp-sms'),
                            esc_html('{{magic_link}} {{user_display_name}} {{expires_in_minutes}} {{site_name}}')
                        ),
                        'placeholder'       => __('{{site_name}} login: {{magic_link}} (valid {{expires_in_minutes}} min).', 'wp-sms'),
                        'sanitize_callback' => ['\\WP_SMS\\Services\\OTP\\Delivery\\PhoneNumber\\Templating\\SanitizeCallbacks', 'body'],
                    ]),
                    new Field([
                        'key'               => 'sms_tpl_magic_revert',
                        'label'             => __('Revert to Default', 'wp-sms'),
                        'type'              => 'checkbox',
                        'group_label'       => __('Magic Link SMS', 'wp-sms'),
                        'default'           => false,
                        'sanitize_callback' => ['\\WP_SMS\\Services\\OTP\\Delivery\\PhoneNumber\\Templating\\SanitizeCallbacks', 'revert'],
                    ]),

                    new Field([
                        'key'               => 'sms_tpl_reset_body',
                        'label'             => __('Password Reset SMS — Body (Text only)', 'wp-sms'),
                        'type'              => 'textarea',
                        'rows'              => 6,
                        'group_label'       => __('Password Reset SMS', 'wp-sms'),
                        'description'       => sprintf(
                            __('Plain text only. Max 2000 chars. Placeholders: %s', 'wp-sms'),
                            esc_html('{{reset_link}} {{user_display_name}} {{expires_in_minutes}} {{site_name}}')
                        ),
                        'placeholder'       => __('Reset {{site_name}} password: {{reset_link}} (valid {{expires_in_minutes}} min).', 'wp-sms'),
                        'sanitize_callback' => ['\\WP_SMS\\Services\\OTP\\Delivery\\PhoneNumber\\Templating\\SanitizeCallbacks', 'body'],
                    ]),
                    new Field([
                        'key'               => 'sms_tpl_reset_revert',
                        'label'             => __('Revert to Default', 'wp-sms'),
                        'type'              => 'checkbox',
                        'group_label'       => __('Password Reset SMS', 'wp-sms'),
                        'default'           => false,
                        'sanitize_callback' => ['\\WP_SMS\\Services\\OTP\\Delivery\\PhoneNumber\\Templating\\SanitizeCallbacks', 'revert'],
                    ]),
                ],
            ])
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
}
