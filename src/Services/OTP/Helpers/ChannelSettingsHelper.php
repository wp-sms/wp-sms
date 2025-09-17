<?php

namespace WP_SMS\Services\OTP\Helpers;

use WP_SMS\Option;

/**
 * Channel Settings Helper
 *
 * Provides methods to retrieve channel configuration data for the start API response.
 * This class maps the OTP channel settings to the required API response format.
 */
class ChannelSettingsHelper
{
    /**
     * Get all channel settings for the start API response
     *
     * @return array
     */
    public static function getAllChannelSettings()
    {
        return [
            'channels' => self::getChannelsData(),
            'policies' => self::getPoliciesData(),
        ];
    }

    /**
     * Get channels configuration data
     *
     * @return array
     */
    public static function getChannelsData()
    {
        return [
            'email' => self::getEmailChannelData(),
            'phone' => self::getPhoneChannelData(),
            'username' => self::getUsernameChannelData(),
            'password' => self::getPasswordChannelData(),
        ];
    }

    /**
     * Get email channel configuration
     * Only includes active (non-readonly) options
     *
     * @return array
     */
    public static function getEmailChannelData()
    {
        $enabled = (bool) Option::getOption('otp_channel_email', false, false);
        $required = (bool) Option::getOption('otp_channel_email_required_signup', false, false);
        $verify = (bool) Option::getOption('otp_channel_email_verify_signup', false, true);
        $allowPassword = (bool) Option::getOption('otp_channel_password_allow_signin', false, true);
        $allowOtp = $enabled && in_array('otp', (array) Option::getOption('otp_channel_email_verification_method', false, ['otp']));
        $allowMagic = $enabled && in_array('link', (array) Option::getOption('otp_channel_email_verification_method', false, ['otp']));
        $otpDigits = (int) Option::getOption('otp_channel_email_otp_digits', false, 6);
        $fallbackEnabled = (bool) Option::getOption('otp_channel_email_fallback_enabled', false, true);

        return [
            'enabled' => $enabled,
            'required' => $required,
            'verify' => $verify,
            'allow_password' => $allowPassword,
            'allow_otp' => $allowOtp,
            'allow_magic' => $allowMagic,
            'otp_digits' => $otpDigits,
            'fallback_enabled' => $fallbackEnabled,
        ];
    }

    /**
     * Get phone channel configuration
     * Only includes active (non-readonly) options
     *
     * @return array
     */
    public static function getPhoneChannelData()
    {
        $enabled = (bool) Option::getOption('otp_channel_phone', false, false);
        $required = (bool) Option::getOption('otp_channel_phone_required_signup', false, false);
        $verify = (bool) Option::getOption('otp_channel_phone_verify_signup', false, true);
        $allowPassword = (bool) Option::getOption('otp_channel_password_allow_signin', false, true);
        $allowOtp = $enabled && in_array('otp', (array) Option::getOption('otp_channel_phone_verification_method', false, ['otp']));
        $allowMagic = $enabled && in_array('link', (array) Option::getOption('otp_channel_phone_verification_method', false, ['otp']));
        $otpDigits = (int) Option::getOption('otp_channel_phone_otp_digits', false, 6);
        $fallbackEnabled = (bool) Option::getOption('otp_channel_phone_fallback_enabled', false, true);
        $smsEnabled = (bool) Option::getOption('otp_channel_phone_sms', false, true);
        $smartAuth = (bool) Option::getOption('otp_channel_phone_smart_auth', false, true);

        return [
            'enabled' => $enabled,
            'required' => $required,
            'verify' => $verify,
            'allow_password' => $allowPassword,
            'allow_otp' => $allowOtp,
            'allow_magic' => $allowMagic,
            'otp_digits' => $otpDigits,
            'fallback_enabled' => $fallbackEnabled,
            'sms_enabled' => $smsEnabled,
            'smart_auth' => $smartAuth,
        ];
    }

    /**
     * Get username channel configuration
     *
     * @return array
     */
    public static function getUsernameChannelData()
    {
        $enabled = (bool) Option::getOption('otp_channel_username', false, false);
        $required = (bool) Option::getOption('otp_channel_username_required_signup', false, true);
        $minLength = (int) Option::getOption('otp_channel_username_min_length', false, 3);
        $maxLength = (int) Option::getOption('otp_channel_username_max_length', false, 20);
        $uniqueRequirement = (bool) Option::getOption('otp_channel_username_unique_requirement', false, true);
        $caseSensitive = (bool) Option::getOption('otp_channel_username_case_sensitive', false, false);
        $realTimeCheck = (bool) Option::getOption('otp_channel_username_real_time_check', false, true);
        $allowSignin = (bool) Option::getOption('otp_channel_username_allow_signin', false, true);

        return [
            'enabled' => $enabled,
            'required' => $required,
            'min_length' => $minLength,
            'max_length' => $maxLength,
            'unique_requirement' => $uniqueRequirement,
            'case_sensitive' => $caseSensitive,
            'real_time_check' => $realTimeCheck,
            'allow_signin' => $allowSignin,
        ];
    }

    /**
     * Get password channel configuration
     * Only includes active (non-readonly) options
     *
     * @return array
     */
    public static function getPasswordChannelData()
    {
        $enabled = (bool) Option::getOption('otp_channel_password', false, false);
        $required = (bool) Option::getOption('otp_channel_password_required_signup', false, true);
        $allowSignin = (bool) Option::getOption('otp_channel_password_allow_signin', false, true);
        
        // Include password policy settings in the channel
        $minLength = (int) Option::getOption('otp_channel_password_min_length', false, 8);
        $maxLength = (int) Option::getOption('otp_channel_password_max_length', false, 128);
        $requireUppercase = (bool) Option::getOption('otp_channel_password_require_uppercase', false, true);
        $requireLowercase = (bool) Option::getOption('otp_channel_password_require_lowercase', false, true);
        $requireNumbers = (bool) Option::getOption('otp_channel_password_require_numbers', false, true);
        $requireSpecial = (bool) Option::getOption('otp_channel_password_require_special', false, false);
        $strengthMeter = (bool) Option::getOption('otp_channel_password_strength_meter', false, true);
        $expiryDays = (int) Option::getOption('otp_channel_password_expiry_days', false, 90);

        return [
            'enabled' => $enabled,
            'required' => $required,
            'allow_signin' => $allowSignin,
            'min_length' => $minLength,
            'max_length' => $maxLength,
            'require_uppercase' => $requireUppercase,
            'require_lowercase' => $requireLowercase,
            'require_numbers' => $requireNumbers,
            'require_special' => $requireSpecial,
            'strength_meter' => $strengthMeter,
            'expiry_days' => $expiryDays,
        ];
    }

    /**
     * Get policies configuration data
     *
     * @return array
     */
    public static function getPoliciesData()
    {
        return [
            'ttl' => self::getTtlPolicyData(),
            'rate_limits' => self::getRateLimitsPolicyData(),
        ];
    }

    /**
     * Get TTL (Time To Live) policy configuration
     *
     * @return array
     */
    public static function getTtlPolicyData()
    {
        // Get OTP expiry times in seconds from the actual fields
        $emailExpirySeconds = (int) Option::getOption('otp_channel_email_expiry_seconds', false, 300);
        $phoneExpirySeconds = (int) Option::getOption('otp_channel_phone_expiry_seconds', false, 300);
        
        // Convert to minutes for the API response
        $emailOtpMinutes = round($emailExpirySeconds / 60);
        $phoneOtpMinutes = round($phoneExpirySeconds / 60);
        
        // Use the longer expiry time as the general OTP expiry
        $otpMinutes = max($emailOtpMinutes, $phoneOtpMinutes);

        return [
            'otp_minutes' => $otpMinutes,
            'email_otp_minutes' => $emailOtpMinutes,
            'phone_otp_minutes' => $phoneOtpMinutes,
        ];
    }

    /**
     * Get rate limits policy configuration
     * Only includes active (non-readonly) options
     *
     * @return array
     */
    public static function getRateLimitsPolicyData()
    {
        // Since rate limiting and retry limits are readonly/coming soon,
        // we'll use default values for now
        $otpSendPerHour = 5; // Default: 5 requests per hour
        $resendCooldownSec = 30; // Default: 30 seconds cooldown

        return [
            'otp_send_per_hour' => $otpSendPerHour,
            'resend_cooldown_sec' => $resendCooldownSec,
        ];
    }

    /**
     * Get specific channel data by name
     *
     * @param string $channelName
     * @return array|null
     */
    public static function getChannelData($channelName)
    {
        $channels = self::getChannelsData();
        
        return isset($channels[$channelName]) ? $channels[$channelName] : null;
    }

    /**
     * Get specific policy data by name
     *
     * @param string $policyName
     * @return array|null
     */
    public static function getPolicyData($policyName)
    {
        $policies = self::getPoliciesData();
        
        return isset($policies[$policyName]) ? $policies[$policyName] : null;
    }

    /**
     * Check if a specific channel is enabled
     *
     * @param string $channelName
     * @return bool
     */
    public static function isChannelEnabled($channelName)
    {
        $channelData = self::getChannelData($channelName);
        
        return $channelData && isset($channelData['enabled']) ? $channelData['enabled'] : false;
    }

    /**
     * Check if a specific channel is required
     *
     * @param string $channelName
     * @return bool
     */
    public static function isChannelRequired($channelName)
    {
        $channelData = self::getChannelData($channelName);
        
        return $channelData && isset($channelData['required']) ? $channelData['required'] : false;
    }

    /**
     * Get all enabled channels
     *
     * @return array
     */
    public static function getEnabledChannels()
    {
        $channels = self::getChannelsData();
        $enabledChannels = [];

        foreach ($channels as $name => $config) {
            if (isset($config['enabled']) && $config['enabled']) {
                $enabledChannels[$name] = $config;
            }
        }

        return $enabledChannels;
    }

    /**
     * Get all required channels
     *
     * @return array
     */
    public static function getRequiredChannels()
    {
        $channels = self::getChannelsData();
        $requiredChannels = [];

        foreach ($channels as $name => $config) {
            if (isset($config['required']) && $config['required'] && $config['enabled']) {
                $requiredChannels[$name] = $config;
            }
        }

        return $requiredChannels;
    }
}
