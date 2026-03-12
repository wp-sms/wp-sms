<?php

namespace WSms\Enums;

enum EventType: string
{
    case LoginSuccess = 'login_success';
    case LoginFailure = 'login_failure';
    case Logout = 'logout';
    case Register = 'register';
    case PasswordResetRequest = 'password_reset_request';
    case PasswordResetComplete = 'password_reset_complete';
    case PasswordChange = 'password_change';
    case EmailChange = 'email_change';
    case EmailVerified = 'email_verified';
    case OtpSent = 'otp_sent';
    case OtpVerified = 'otp_verified';
    case OtpFailed = 'otp_failed';
    case OtpExpired = 'otp_expired';
    case MagicLinkSent = 'magic_link_sent';
    case MagicLinkVerified = 'magic_link_verified';
    case MfaEnrolled = 'mfa_enrolled';
    case MfaUnenrolled = 'mfa_unenrolled';
    case MfaAdminBypass = 'mfa_admin_bypass';
    case BackupCodeUsed = 'backup_code_used';
    case BackupCodesRegenerated = 'backup_codes_regenerated';
    case PhoneVerified = 'phone_verified';
    case AccountLocked = 'account_locked';
    case AccountUnlocked = 'account_unlocked';
}
