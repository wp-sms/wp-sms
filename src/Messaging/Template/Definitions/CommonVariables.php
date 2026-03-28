<?php

namespace WSms\Messaging\Template\Definitions;

use WSms\Messaging\Template\ValueObjects\VariableDefinition;

defined('ABSPATH') || exit;

class CommonVariables
{
    public static function userName(): VariableDefinition
    {
        return new VariableDefinition(
            'user_name',
            __('User Name', 'wp-sms'),
            __("The user's display name.", 'wp-sms'),
            false,
            'John',
        );
    }

    public static function siteName(): VariableDefinition
    {
        return new VariableDefinition(
            'site_name',
            __('Site Name', 'wp-sms'),
            __('The name of the website.', 'wp-sms'),
            false,
            'My Website',
        );
    }

    public static function securityContext(): VariableDefinition
    {
        return new VariableDefinition(
            'security_context',
            __('Security Context', 'wp-sms'),
            __('Security details about the request (device, location, IP).', 'wp-sms'),
            false,
            "Device: Chrome on Windows\nLocation: US\nIP: 192.168.1.1\nIf you did not request this, please secure your account immediately.",
        );
    }

    public static function expiryMinutes(string $example = '10'): VariableDefinition
    {
        return new VariableDefinition(
            'expiry_minutes',
            __('Expiry Minutes', 'wp-sms'),
            __('How many minutes until the code/link expires.', 'wp-sms'),
            true,
            $example,
        );
    }

    public static function otpCode(): VariableDefinition
    {
        return new VariableDefinition(
            'otp_code',
            __('OTP Code', 'wp-sms'),
            __('The one-time verification code.', 'wp-sms'),
            true,
            '482916',
        );
    }
}
