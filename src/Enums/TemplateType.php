<?php

namespace WSms\Enums;

enum TemplateType: string
{
    case Otp = 'otp';
    case MagicLink = 'magic_link';
    case OtpWithMagicLink = 'otp_with_magic_link';
    case EmailVerification = 'email_verification';
    case PhoneVerification = 'phone_verification';
    case PasswordReset = 'password_reset';
    case Welcome = 'welcome';
    case AccountAlert = 'account_alert';
    case AccountSuspended = 'account_suspended';

    /**
     * Resolve the template type for a combined OTP / magic-link delivery.
     */
    public static function forCombinedDelivery(?string $otpCode, ?string $magicLinkUrl): self
    {
        if ($otpCode !== null && $magicLinkUrl !== null) {
            return self::OtpWithMagicLink;
        }

        if ($magicLinkUrl !== null) {
            return self::MagicLink;
        }

        return self::Otp;
    }
}
