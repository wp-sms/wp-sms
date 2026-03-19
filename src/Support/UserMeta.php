<?php

namespace WSms\Support;

defined('ABSPATH') || exit;

/**
 * Centralized user meta key constants.
 *
 * Every wsms_ user meta key used across the plugin is defined here
 * to prevent typos and enable IDE autocompletion.
 */
final class UserMeta
{
    public const PHONE = 'wsms_phone';
    public const EMAIL_VERIFIED = 'wsms_email_verified';
    public const PHONE_VERIFIED = 'wsms_phone_verified';
    public const PENDING_PHONE = 'wsms_pending_phone';
    public const PENDING_EMAIL = 'wsms_pending_email';
    public const REGISTRATION_STATUS = 'wsms_registration_status';
    public const REGISTRATION_CREATED_AT = 'wsms_registration_created_at';
    public const EMAIL_PLACEHOLDER = 'wsms_email_placeholder';
    public const HAS_USABLE_PASSWORD = 'wsms_has_usable_password';
    public const SUSPENDED = 'wsms_suspended';
    public const MFA_ENABLED = 'wsms_mfa_enabled';
    public const MFA_ENROLLMENT_PENDING = 'wsms_mfa_enrollment_pending';
    public const TRUSTED_DEVICES = 'wsms_trusted_devices';
    public const FAILED_ATTEMPTS = 'wsms_failed_attempts';
    public const LOCKOUT_UNTIL = 'wsms_lockout_until';
    public const CUSTOM_AVATAR = 'wsms_custom_avatar';
    public const SOCIAL_AVATAR = 'wsms_social_avatar';
}
