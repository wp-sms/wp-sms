<?php

namespace WP_SMS\Services\OTP\AuthChannel\OTP;

use WP_SMS\Services\OTP\Contracts\Interfaces\AuthChannelInterface;
use WP_SMS\Services\OTP\Models\OtpSessionModel;
use WP_SMS\Services\OTP\OTPChannelHelper;
use WP_SMS\Services\OTP\Delivery\DeliveryChannelManager;
use WP_SMS\Services\OTP\Contracts\Interfaces\DeliveryChannelInterface;
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
    public function generate(string $flowId, string $identifier)
    {
        // Validate identifier
        if (empty($identifier)) {
            throw new \InvalidArgumentException('Identifier must be provided');
        }

        // Check if there's already an unexpired session for this identifier
        if ($this->hasUnexpiredSession($identifier)) {
            throw new \Exception('An unexpired OTP session already exists for this identifier. Please wait before requesting a new code.');
        }

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

        // Determine the primary channel based on identifier type
        $identifierType = $this->getIdentifierType($identifier);
        if (!$identifierType) {
            throw new \InvalidArgumentException('Invalid identifier format');
        }
        
        $primaryChannel = $identifierType === 'phone' ? 'sms' : 'email';

        // Save to DB with delivery information
        $otpSession = OtpSessionModel::createSession(
            flowId: $flowId,
            code: $otpCode,
            expiresInSeconds: $this->defaultTtl,
            identifier: $identifier,
            channel: $primaryChannel
        );

        return $otpSession;
    }

    /**
     * Send OTP via the best available channel with fallback support
     */
    public function sendOTP(string $identifier, string $otpCode, string $preferredChannel = 'sms', array $context = []): array
    {
        $result = [
            'success' => false,
            'channel_used' => null,
            'error' => null,
        ];

        // Determine identifier type
        $identifierType = $this->getIdentifierType($identifier);
        if (!$identifierType) {
            $result['error'] = 'Invalid identifier format';
            return $result;
        }

        $deliveryChannels = $this->channelManager->get($identifierType === 'phone' ? 'sms' : 'email');
        // Try to send via preferred channel first
        $primaryResult = $this->sendViaChannel($deliveryChannels, $identifier, $otpCode, $context);

        if ($primaryResult['success']) {
            $result['success'] = true;
            $result['channel_used'] = $deliveryChannels->getKey();
            return $result;
        }

        // All channels failed
        $result['error'] = 'Failed to send OTP via all available channels';
        return $result;
    }

    /**
     * Send OTP via a specific channel
     */
    protected function sendViaChannel(DeliveryChannelInterface $channel, string $identifier, string $otpCode, array $context): array
    {
        try {
            
            // Prepare message based on channel
            $message = $this->prepareMessage($channel, $otpCode, $context);
            
            // Send via channel
            $success = $channel->send($identifier, $message, $context);
            
            return [
                'success' => $success,
                'channel' => $channel->getKey(),
                'error' => $success ? null : "Failed to send via {$channel}"
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'channel' => $channel->getKey(),
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Prepare message content based on channel
     */
    protected function prepareMessage(DeliveryChannelInterface $channel, string $otpCode, array $context): string
    {
        $defaultMessage = sprintf(__('Your verification code is: %s', 'wp-sms'), $otpCode);
        
        switch ($channel) {
            case 'sms':
            case 'phone':
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
    protected function isFallbackEnabled(DeliveryChannelInterface $channel): bool
    {
        $settings = $this->channelHelper->getChannelSettings($channel->getKey());
        return isset($settings['fallback_enabled']) ? $settings['fallback_enabled'] : false;
    }


    /**
     * Get the type of identifier (email or phone)
     */
    protected function getIdentifierType(string $identifier): ?string
    {
        if (filter_var($identifier, FILTER_VALIDATE_EMAIL) !== false) {
            return 'email';
        }
        
        // Check if it's a valid phone number
        $cleanNumber = preg_replace('/[^\d+]/', '', $identifier);
        if (preg_match('/^(\+\d{7,15}|\d{7,15})$/', $cleanNumber)) {
            return 'phone';
        }
        
        return null;
    }

    /**
     * Check if identifier is an email
     */
    protected function isEmail(string $identifier): bool
    {
        return $this->getIdentifierType($identifier) === 'email';
    }

    /**
     * Validate an input OTP against the stored session.
     */
    public function validate(string $flowId, string $input): bool
    {
        $record = OtpSessionModel::getByFlowId($flowId);

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
        $record = OtpSessionModel::getByFlowId($flowId);

        if (! $record) {
            return false;
        }

        return strtotime($record['expires_at']) >= time();
    }

    /**
     * Check if there's an unexpired session for an identifier
     */
    public function hasUnexpiredSession(string $identifier): bool
    {
        return OtpSessionModel::hasUnexpiredSessionByIdentifier($identifier);
    }

    /**
     * Get the most recent unexpired session for an identifier
     */
    public function getMostRecentUnexpiredSession(string $identifier): ?array
    {
        return OtpSessionModel::getMostRecentUnexpiredSessionByIdentifier($identifier);
    }

    /**
     * Invalidate all sessions for an identifier
     */
    public function invalidateAllSessionsForIdentifier(string $identifier): void
    {
        OtpSessionModel::deleteBy(['identifier' => $identifier]);
    }

    /**
     * Generate a new OTP, invalidating any existing sessions for the identifier first
     */
    public function generateWithInvalidation(string $flowId, string $identifier): string
    {
        // Invalidate any existing sessions for this identifier
        $this->invalidateAllSessionsForIdentifier($identifier);
        
        // Generate new OTP
        return $this->generate($flowId, $identifier);
    }
}
