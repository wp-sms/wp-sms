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
        $allowPassword = $enabled && in_array('password', (array) Option::getOption('otp_channel_email_verification_method', false, ['otp']));
        $allowOtp = $enabled && in_array('otp', (array) Option::getOption('otp_channel_email_verification_method', false, ['otp']));
        $allowMagic = $enabled && in_array('link', (array) Option::getOption('otp_channel_email_verification_method', false, ['otp']));
        $otpDigits = (int) Option::getOption('otp_channel_email_otp_digits', false, 6);
        $passwordIsRequired = (bool) Option::getOption('otp_channel_email_password_is_required', false, false);
        $allowSignin = (bool) Option::getOption('otp_channel_email_allow_signin', false, true); 
        $allowUsernameOnLogin = (bool) Option::getOption('otp_channel_email_allow_username_on_login', false, true);

        return [
            'enabled' => $enabled,
            'required' => $required,
            'verify' => $verify,
            'allow_password' => $allowPassword,
            'allow_otp' => $allowOtp,
            'allow_magic' => $allowMagic,
            'otp_digits' => $otpDigits,
            'password_is_required' => $passwordIsRequired,
            'allow_signin' => $allowSignin,
            'allow_username_on_login' => $allowUsernameOnLogin,
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
        $passwordIsRequired = (bool) Option::getOption('otp_channel_phone_password_is_required', false, false);
        $allowSignin = (bool) Option::getOption('otp_channel_phone_allow_signin', false, true);
        
        return [
            'enabled' => $enabled,
            'required' => $required,
            'verify' => $verify,
            'allow_password' => $allowPassword,
            'allow_otp' => $allowOtp,
            'allow_magic' => $allowMagic,
            'otp_digits' => $otpDigits,
            'password_is_required' => $passwordIsRequired,
            'allow_signin' => $allowSignin,
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
