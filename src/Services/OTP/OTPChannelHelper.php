<?php

namespace WP_SMS\Services\OTP;

use WP_SMS\Option;

/**
 * OTP Channel Helper Class
 * 
 * Simple helper class to access OTP channel settings
 */
class OTPChannelHelper
{
    /**
     * Check if a channel is enabled
     */
    public static function isChannelEnabled(string $channel): bool
    {
        return (bool) Option::getOption("otp_channel_{$channel}");
    }

    /**
     * Get a specific channel setting
     */
    public static function getChannelSetting(string $channel, string $setting, $default = null)
    {
        $key = "otp_channel_{$channel}_{$setting}";
        $value = Option::getOption($key);
        return $value !== '' ? $value : $default;
    }

    /**
     * Get all settings for a specific channel
     */
    public static function getChannelSettings(string $channel): array
    {
        $settings = [];
        switch ($channel) {
            case 'username':
                $settings = [
                    'enabled' => self::isChannelEnabled('username'),
                    'min_length' => self::getChannelSetting('username', 'min_length', 3),
                    'max_length' => self::getChannelSetting('username', 'max_length', 20),
                    'unique_requirement' => self::getChannelSetting('username', 'unique_requirement', true),
                    'case_sensitive' => self::getChannelSetting('username', 'case_sensitive', false),
                    'allowed_characters' => self::getChannelSetting('username', 'allowed_characters', ['alphanumeric', 'underscores', 'hyphens', 'dots']),
                    'reserved_words' => self::getChannelSetting('username', 'reserved_words', 'admin, root, system, test, demo'),
                    'real_time_check' => self::getChannelSetting('username', 'real_time_check', true),
                    'field_label' => self::getChannelSetting('username', 'field_label', 'Username'),
                    'required_signup' => self::getChannelSetting('username', 'required_signup', true),
                    'allow_signin' => self::getChannelSetting('username', 'allow_signin', true),
                ];
                break;
            case 'password':
                $settings = [
                    'enabled' => self::isChannelEnabled('password'),
                    'min_length' => self::getChannelSetting('password', 'min_length', 8),
                    'max_length' => self::getChannelSetting('password', 'max_length', 128),
                    'require_uppercase' => self::getChannelSetting('password', 'require_uppercase', true),
                    'require_lowercase' => self::getChannelSetting('password', 'require_lowercase', true),
                    'require_numbers' => self::getChannelSetting('password', 'require_numbers', true),
                    'require_special' => self::getChannelSetting('password', 'require_special', false),
                    'strength_meter' => self::getChannelSetting('password', 'strength_meter', true),
                    'expiry_days' => self::getChannelSetting('password', 'expiry_days', 90),
                    'required_signup' => self::getChannelSetting('password', 'required_signup', true),
                    'allow_signin' => self::getChannelSetting('password', 'allow_signin', true),
                ];
                break;
            case 'phone':
                $settings = [
                    'enabled' => self::isChannelEnabled('phone'),
                    'verification_method' => self::getChannelSetting('phone', 'verification_method', ['otp']),
                    'otp_digits' => self::getChannelSetting('phone', 'otp_digits', 6),
                    'expiry_seconds' => self::getChannelSetting('phone', 'expiry_seconds', 300),
                    'smart_auth' => self::getChannelSetting('phone', 'smart_auth', true),
                    'fallback_enabled' => self::getChannelSetting('phone', 'fallback_enabled', true),
                    'sms' => self::getChannelSetting('phone', 'sms', true),
                    'whatsapp' => self::getChannelSetting('phone', 'whatsapp', false),
                    'viber' => self::getChannelSetting('phone', 'viber', false),
                    'call' => self::getChannelSetting('phone', 'call', false),
                    'required_signup' => self::getChannelSetting('phone', 'required_signup', false),
                    'verify_signup' => self::getChannelSetting('phone', 'verify_signup', true),
                    'allow_signin' => self::getChannelSetting('phone', 'allow_signin', true),
                    'use_as_mfa' => self::getChannelSetting('phone', 'use_as_mfa', false),
                ];
                break;
            case 'email':
                $settings = [
                    'enabled' => self::isChannelEnabled('email'),
                    'verification_method' => self::getChannelSetting('email', 'verification_method', ['otp']),
                    'otp_digits' => self::getChannelSetting('email', 'otp_digits', 6),
                    'expiry_seconds' => self::getChannelSetting('email', 'expiry_seconds', 300),
                    'fallback_enabled' => self::getChannelSetting('email', 'fallback_enabled', true),
                    'required_signup' => self::getChannelSetting('email', 'required_signup', false),
                    'verify_signup' => self::getChannelSetting('email', 'verify_signup', true),
                    'allow_signin' => self::getChannelSetting('email', 'allow_signin', true),
                    'use_as_mfa' => self::getChannelSetting('email', 'use_as_mfa', false),
                ];
                break;
        }
        return $settings;
    }
    /**
     * Get all OTP settings for frontend
     */
    public static function getAllSettings(): array
    {
        return [
            'channels' => [
                'username' => self::getChannelSettings('username'),
                'password' => self::getChannelSettings('password'),
                'phone' => self::getChannelSettings('phone'),
                'email' => self::getChannelSettings('email'),
            ]
        ];
    }

    /**
     * Get MFA channels (only phone and email can be MFA)
     */
    public static function getMfaChannels(): array
    {
        $mfaChannels = [];

        if (self::isChannelEnabled('phone') && self::getChannelSetting('phone', 'use_as_mfa')) {
            $mfaChannels['phone'] = self::getChannelSettings('phone');
        }

        if (self::isChannelEnabled('email') && self::getChannelSetting('email', 'use_as_mfa')) {
            $mfaChannels['email'] = self::getChannelSettings('email');
        }

        return $mfaChannels;
    }

    /**
     * Check if fallback is enabled for a channel
     */
    public static function isFallbackEnabled(string $channel): bool
    {
        return (bool) self::getChannelSetting($channel, 'fallback_enabled', false);
    }

    /**
     * Get available fallback channels for a given channel
     */
    public static function getFallbackChannels(string $channel): array
    {
        $fallbackChannels = [];
        
        if ($channel === 'phone' && self::isFallbackEnabled('phone')) {
            if (self::isChannelEnabled('email')) {
                $fallbackChannels[] = 'email';
            }
        } elseif ($channel === 'email' && self::isFallbackEnabled('email')) {
            if (self::isChannelEnabled('phone')) {
                $fallbackChannels[] = 'sms';
            }
        }
        
        return $fallbackChannels;
    }
}
