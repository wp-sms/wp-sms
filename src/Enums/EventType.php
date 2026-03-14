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
    case TotpVerified = 'totp_verified';
    case TotpFailed = 'totp_failed';
    case PhoneVerified = 'phone_verified';
    case AccountLocked = 'account_locked';
    case AccountUnlocked = 'account_unlocked';
    case SocialLoginSuccess = 'social_login_success';
    case SocialLoginFailure = 'social_login_failure';
    case SocialAccountLinked = 'social_account_linked';
    case SocialAccountUnlinked = 'social_account_unlinked';
    case SocialRegistration = 'social_registration';
    case StandaloneVerificationSent = 'standalone_verification_sent';
    case StandaloneVerificationSuccess = 'standalone_verification_success';
    case StandaloneVerificationFailed = 'standalone_verification_failed';
}
