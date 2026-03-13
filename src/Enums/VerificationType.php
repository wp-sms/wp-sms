<?php

namespace WSms\Enums;

enum VerificationType: string
{
    case Otp = 'otp';
    case MagicLink = 'magic_link';
    case EmailVerify = 'email_verify';
    case PhoneVerify = 'phone_verify';
    case PasswordReset = 'password_reset';

    /**
     * Resolve the verification type for a channel name ('email' or 'phone').
     */
    public static function forChannel(string $channel): self
    {
        return match ($channel) {
            'email' => self::EmailVerify,
            'phone' => self::PhoneVerify,
            default => throw new \InvalidArgumentException("Unknown verification channel: {$channel}"),
        };
    }
}
