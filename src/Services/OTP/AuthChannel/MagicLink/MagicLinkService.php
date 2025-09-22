<?php

namespace WP_SMS\Services\OTP\AuthChannel\MagicLink;

use WP_SMS\Services\OTP\Models\MagicLinkModel;
use WP_SMS\Services\OTP\AuthChannel\MagicLink\MagicLinkPayload;
use WP_SMS\Services\OTP\Contracts\Interfaces\AuthChannelInterface;
use WP_SMS\Services\OTP\Delivery\DeliveryChannelManager;
use WP_SMS\Services\OTP\OTPChannelHelper;

class MagicLinkService implements AuthChannelInterface
{
    protected int $defaultTtl = 600;
    protected string $loginUrlBase = '/?magic_login=1';
    protected DeliveryChannelManager $channelManager;
    protected OTPChannelHelper $channelHelper;

    public function __construct()
    {
        $this->channelManager = new DeliveryChannelManager();
        $this->channelHelper = new OTPChannelHelper();
    }

    public function getKey(): string
    {
        return 'magic_link';
    }

    public function exists(string $flowId): bool
    {
        $record = MagicLinkModel::find(['flow_id' => $flowId]);

        if (! $record) {
            return false;
        }

        $isExpired = strtotime($record['expires_at']) < time();
        $isUsed = !empty($record['used_at']);
        
        if ($isExpired || $isUsed) {
            // Delete expired or used record
            $this->invalidate($flowId);
            return false;
        }

        return true;
    }

    
    /**
     * Generate a new magic login link for a given user and flow ID.
     */
    public function generate(string $flowId, ?string $identifier = null, ?string $identifierType = null): string
    {
        $token = bin2hex(random_bytes(16)); // Secure 32-char token

        MagicLinkModel::createSession(
            flowId: $flowId,
            token: $token,
            identifier: $identifier,
            identifierType: $identifierType,
            expiresInSeconds: $this->defaultTtl
        );

        return $this->buildUrl($token, $flowId);
    }

    /**
     * Build a fully-qualified magic login URL.
     */
    public function buildUrl(string $token, string $flowId): string
    {
        $query = http_build_query([
            'magic_token' => $token,
            'flow_id'     => $flowId,
        ]);

        return home_url($this->loginUrlBase) . '&' . $query;
    }

    /**
     * Validate a magic link token and return the user ID if valid.
     */
    public function validate(string $flowId, string $inputToken): ?string
    {
        $record = MagicLinkModel::find(['flow_id' => $flowId]);

        if (! $record) {
            return null;
        }

        $payload = new MagicLinkPayload(
            flowId: $record['flow_id'],
            tokenHash: $record['token_hash'],
            expiresAt: strtotime($record['expires_at']),
            usedAt: $record['used_at'] ? strtotime($record['used_at']) : null,
        );

        if ($payload->isExpired() || $payload->isUsed()) {
            return null;
        }

        if (! $payload->matchesToken($inputToken)) {
            return null;
        }

        // Mark the token as used (one-time use)
        MagicLinkModel::markAsUsed($flowId);

        return $payload->flowId;
    }

    /**
     * Invalidate a magic link (e.g., expired manually).
     */
    public function invalidate(string $flowId): void
    {
        MagicLinkModel::deleteByFlowId($flowId);
    }

    /**
     * Send magic link via the best available channel with fallback support
     */
    public function sendMagicLink(string $identifier, string $magicLinkUrl, string $preferredChannel = 'sms', array $context = []): array
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
        $primaryResult = $this->sendViaChannel($primaryChannel, $identifier, $magicLinkUrl, $context);

        if ($primaryResult['success']) {
            $result['success'] = true;
            $result['channel_used'] = $primaryChannel;
            return $result;
        }

        // Primary channel failed, try fallback if enabled
        if (count($channels) > 1 && $this->isFallbackEnabled($isPhone ? 'sms' : 'email')) {
            $fallbackChannel = $channels[1];
            $fallbackResult = $this->sendViaChannel($fallbackChannel, $identifier, $magicLinkUrl, $context);

            if ($fallbackResult['success']) {
                $result['success'] = true;
                $result['channel_used'] = $fallbackChannel;
                $result['fallback_used'] = true;
                return $result;
            }
        }

        // All channels failed
        $result['error'] = 'Failed to send magic link via all available channels';
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
     * Send magic link via a specific channel
     */
    protected function sendViaChannel(string $channel, string $identifier, string $magicLinkUrl, array $context): array
    {
        try {
            $channelService = $this->channelManager->get($channel);
            
            // Prepare message based on channel
            $message = $this->prepareMessage($channel, $magicLinkUrl, $context);
            
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
    protected function prepareMessage(string $channel, string $magicLinkUrl, array $context): string
    {
        $defaultMessage = sprintf(__('Click here to login: %s', 'wp-sms'), $magicLinkUrl);
        
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
}
