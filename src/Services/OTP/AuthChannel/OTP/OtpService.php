<?php

namespace WP_SMS\Services\OTP\AuthChannel\OTP;

use WP_SMS\Services\OTP\Contracts\Interfaces\AuthChannelInterface;
use WP_SMS\Services\OTP\Models\OtpSessionModel;
use WP_SMS\Services\OTP\OTPChannelHelper;
use WP_SMS\Services\OTP\Delivery\DeliveryChannelManager;
use WP_SMS\Services\OTP\Delivery\Email\EmailChannel;
use WP_SMS\Services\OTP\Delivery\PhoneNumber\SmsChannel;

class OtpService implements AuthChannelInterface
{
    protected int $defaultTtl = 300;
    protected int $defaultCodeLength = 6;
    protected DeliveryChannelManager $channelManager;
    protected OTPChannelHelper $channelHelper;

    public function __construct()
    {
        $this->channelManager = new DeliveryChannelManager();
        $this->channelHelper = new OTPChannelHelper();
    }

    public function getKey(): string
    {
        return 'otp';
    }

    /**
     * Generate a new OTP and persist it in the database.
     */
    public function generate(string $flowId, ?string $phone = null, ?string $email = null, string $preferredChannel = 'sms'): string
    {
        // Generate secure OTP
        $length = $this->defaultCodeLength;
        $length = max(4, min(10, $length));
        $hash = bin2hex(openssl_random_pseudo_bytes(16));
        $values = array_values(unpack('C*', $hash));
        $offset = ($values[count($values) - 1] & 0xF);
        $code = ($values[$offset + 0] & 0x7F) << 24
            | ($values[$offset + 1] & 0xFF) << 16
            | ($values[$offset + 2] & 0xFF) << 8
            | ($values[$offset + 3] & 0xFF);
        $otp = $code % (10 ** $length);
        $otpCode = str_pad((string) $otp, $length, '0', STR_PAD_LEFT);

        // Determine the primary identifier and channel
        $primaryChannel = $preferredChannel ?: ($phone ? 'sms' : 'email');

        // Save to DB with delivery information
        OtpSessionModel::createSession(
            flowId: $flowId,
            code: $otpCode,
            expiresInSeconds: $this->defaultTtl,
            phone: $phone,
            email: $email,
            channel: $primaryChannel
        );

        return $otpCode;
    }

    /**
     * Send OTP via the best available channel with fallback support
     */
    public function sendOTP(string $identifier, string $otpCode, string $preferredChannel = 'sms', array $context = []): array
    {
        $result = [
            'success' => false,
            'channel_used' => null,
            'fallback_used' => false,
            'error' => null,
        ];

        // Determine if this is a phone number or email
        $isEmail = $this->isEmail($identifier);
        $isPhone = $isEmail ? false : true;
        if (!$isEmail && !$isPhone) {
            $result['error'] = 'Invalid identifier format';
            return $result;
        }

        // Set up channels based on identifier type
        if ($isPhone) {
            $channels = $this->getPhoneChannels($preferredChannel);
        } else {
            $channels = $this->getEmailChannels($preferredChannel);
        }

        // Try to send via preferred channel first
        $primaryChannel = $channels[0];
        $primaryResult = $this->sendViaChannel($primaryChannel, $identifier, $otpCode, $context);

        if ($primaryResult['success']) {
            $result['success'] = true;
            $result['channel_used'] = $primaryChannel;
            return $result;
        }

        // Primary channel failed, try fallback if enabled
        if (count($channels) > 1 && $this->isFallbackEnabled($isPhone ? 'sms' : 'email')) {
            $fallbackChannel = $channels[1];
            $fallbackResult = $this->sendViaChannel($fallbackChannel, $identifier, $otpCode, $context);

            if ($fallbackResult['success']) {
                $result['success'] = true;
                $result['channel_used'] = $fallbackChannel;
                $result['fallback_used'] = true;
                return $result;
            }
        }

        // All channels failed
        $result['error'] = 'Failed to send OTP via all available channels';
        return $result;
    }

    /**
     * Get available channels for phone number
     */
    protected function getPhoneChannels(string $preferredChannel): array
    {
        $channels = [];
        $phoneSettings = $this->channelHelper->getChannelSettings('phone');

        if ($phoneSettings['enabled']) {
            if ($preferredChannel === 'sms' && $phoneSettings['sms']) {
                $channels[] = 'sms';
            }
            
            // Add other phone channels if enabled
            if ($phoneSettings['whatsapp']) {
                $channels[] = 'whatsapp';
            }
            if ($phoneSettings['viber']) {
                $channels[] = 'viber';
            }
            if ($phoneSettings['call']) {
                $channels[] = 'call';
            }

            // If SMS wasn't the preferred channel, add it as fallback
            if ($preferredChannel !== 'sms' && $phoneSettings['sms'] && !in_array('sms', $channels)) {
                $channels[] = 'sms';
            }
        }

        // Add email as fallback if enabled
        if ($this->channelHelper->isChannelEnabled('email') && $phoneSettings['fallback_enabled']) {
            $channels[] = 'email';
        }

        return $channels;
    }

    /**
     * Get available channels for email
     */
    protected function getEmailChannels(string $preferredChannel): array
    {
        $channels = [];
        $emailSettings = $this->channelHelper->getChannelSettings('email');

        if ($emailSettings['enabled']) {
            if ($preferredChannel === 'email') {
                $channels[] = 'email';
            }
        }

        // Add SMS as fallback if enabled
        if ($this->channelHelper->isChannelEnabled('phone') && $emailSettings['fallback_enabled']) {
            $channels[] = 'sms';
        }

        return $channels;
    }

    /**
     * Send OTP via a specific channel
     */
    protected function sendViaChannel(string $channel, string $identifier, string $otpCode, array $context): array
    {
        try {
            $channelService = $this->channelManager->get($channel);
            
            // Prepare message based on channel
            $message = $this->prepareMessage($channel, $otpCode, $context);
            
            // Send via channel
            $success = $channelService->send($identifier, $message, $context);
            
            return [
                'success' => $success,
                'channel' => $channel,
                'error' => $success ? null : "Failed to send via {$channel}"
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'channel' => $channel,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Prepare message content based on channel
     */
    protected function prepareMessage(string $channel, string $otpCode, array $context): string
    {
        $defaultMessage = sprintf(__('Your verification code is: %s', 'wp-sms'), $otpCode);
        
        switch ($channel) {
            case 'sms':
                return $context['sms_message'] ?? $defaultMessage;
            case 'email':
                return $context['email_message'] ?? $defaultMessage;
            case 'whatsapp':
                return $context['whatsapp_message'] ?? $defaultMessage;
            case 'viber':
                return $context['viber_message'] ?? $defaultMessage;
            case 'call':
                return $context['call_message'] ?? $defaultMessage;
            default:
                return $defaultMessage;
        }
    }

    /**
     * Check if fallback is enabled for a channel
     */
    protected function isFallbackEnabled(string $channel): bool
    {
        $settings = $this->channelHelper->getChannelSettings($channel);
        return isset($settings['fallback_enabled']) ? $settings['fallback_enabled'] : false;
    }


    /**
     * Check if identifier is an email
     */
    protected function isEmail(string $identifier): bool
    {
        return filter_var($identifier, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Validate an input OTP against the stored session.
     */
    public function validate(string $flowId, string $input): bool
    {
        $record = OtpSessionModel::find(['flow_id' => $flowId]);

        if (! $record) {
            return false;
        }

        if (strtotime($record['expires_at']) < time()) {
            $this->invalidate($flowId);
            return false;
        }

        $inputHash = hash('sha256', $input);
        $isValid = hash_equals($record['otp_hash'], $inputHash);

        if ($isValid) {
            $this->invalidate($flowId);
        }

        return $isValid;
    }

    /**
     * Manually invalidate an OTP session.
     */
    public function invalidate(string $flowId): void
    {
        OtpSessionModel::deleteBy(['flow_id' => $flowId]);
    }

    /**
     * Check whether a session exists and is not expired.
     */
    public function exists(string $flowId): bool
    {
        $record = OtpSessionModel::find(['flow_id' => $flowId]);

        if (! $record) {
            return false;
        }

        return strtotime($record['expires_at']) >= time();
    }
}
