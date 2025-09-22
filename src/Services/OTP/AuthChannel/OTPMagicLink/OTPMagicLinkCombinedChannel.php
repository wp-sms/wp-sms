<?php

namespace WP_SMS\Services\OTP\AuthChannel\OTPMagicLink;

use WP_SMS\Services\OTP\Contracts\Interfaces\AuthChannelInterface;
use WP_SMS\Services\OTP\AuthChannel\OTP\OtpService;
use WP_SMS\Services\OTP\AuthChannel\MagicLink\MagicLinkService;
use WP_SMS\Services\OTP\Delivery\DeliveryChannelManager;
use WP_SMS\Services\OTP\Delivery\Email\Templating\TemplateRenderer as EmailTemplateRenderer;
use WP_SMS\Services\OTP\Delivery\PhoneNumber\Templating\TemplateRenderer as SmsTemplateRenderer;

/**
 * Class OTPMagicLinkCombinedChannel
 *
 * Combined authentication channel that handles both OTP and Magic Link together.
 * This channel creates both OTP and Magic Link sessions and sends them via a single message
 * using the appropriate delivery channel based on the identifier type.
 *
 * ## Usage
 *
 * $channel = new OTPMagicLinkCombinedChannel();
 * 
 * // Generate combined auth
 * $result = $channel->generate($flowId, $identifier, $identifierType, $otpDigits);
 * 
 * // Send combined message
 * $sendResult = $channel->sendCombined($identifier, $otpSession, $magicLink, $context);
 *
 */
class OTPMagicLinkCombinedChannel implements AuthChannelInterface
{
    protected OtpService $otpService;
    protected MagicLinkService $magicLinkService;
    protected DeliveryChannelManager $channelManager;
    protected EmailTemplateRenderer $emailRenderer;
    protected SmsTemplateRenderer $smsRenderer;

    public function __construct()
    {
        $this->otpService = new OtpService();
        $this->magicLinkService = new MagicLinkService();
        $this->channelManager = new DeliveryChannelManager();
        $this->emailRenderer = new EmailTemplateRenderer();
        $this->smsRenderer = new SmsTemplateRenderer();
    }

    /**
     * @return string
     */
    public function getKey(): string
    {
        return 'combined';
    }

    /**
     * Generate both OTP and Magic Link for a flow
     */
    public function generate(string $flowId, string $identifier, string $identifierType, int $otpDigits = 6): array
    {
        // Generate OTP session
        $otpSession = $this->otpService->generate($flowId, $identifier, $otpDigits);
        
        // Generate Magic Link
        $magicLink = $this->magicLinkService->generate($flowId, $identifier, $identifierType);
        
        return [
            'otp_session' => [
                'flow_id' => $otpSession->flow_id,
                'code' => $otpSession->code,
                'channel' => $otpSession->channel,
                'expires_at' => $otpSession->expires_at,
            ],
            'magic_link' => $magicLink,
        ];
    }

    /**
     * Send combined OTP and Magic Link message
     */
    public function sendCombined(string $identifier, array $otpSession, string $magicLink, string $context = 'register'): array
    {
        try {
            // Determine delivery channel based on identifier type
            $isEmail = $this->isEmail($identifier);
            $channelKey = $isEmail ? 'email' : 'sms';
            $deliveryChannel = $this->channelManager->get($channelKey);
            
            // Determine template type based on context
            $templateType = $context === 'login' ? 'combined_login' : 'combined_register';
            
            // Calculate expiry time in minutes
            $expiresAt = strtotime($otpSession['expires_at']);
            $expiresInMinutes = max(1, round(($expiresAt - time()) / 60));
            // Prepare context for template rendering
            $templateContext = [
                'template' => $templateType,
                'otp_code' => $otpSession['code'],
                'magic_link' => $magicLink,
                'expires_in_minutes' => $expiresInMinutes,
                'user_display_name' => __('User', 'wp-sms'),
                'site_name' => wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES),
            ];
            
            // Send via delivery channel
            $success = $deliveryChannel->send($identifier, '', $templateContext);
            
            if ($success) {
                return [
                    'success' => true,
                    'channel_used' => $channelKey,
                    'message_type' => 'combined'
                ];
            } else {
                return [
                    'success' => false,
                    'error' => __('Failed to send combined authentication message', 'wp-sms')
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Check if identifier is an email
     */
    protected function isEmail(string $identifier): bool
    {
        return filter_var($identifier, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Invalidate both OTP and Magic Link for a flow
     */
    public function invalidate(string $flowId): void
    {
        $this->otpService->invalidate($flowId);
        $this->magicLinkService->invalidate($flowId);
    }

    /**
     * Check if either OTP or Magic Link exists for a flow
     */
    public function exists(string $flowId): bool
    {
        return $this->otpService->exists($flowId) || $this->magicLinkService->exists($flowId);
    }
}
