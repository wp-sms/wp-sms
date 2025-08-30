<?php

namespace WP_SMS\Services\OTP\RestAPIEndpoints\MagicLink;

use WP_REST_Server;
use WP_REST_Response;
use WP_REST_Request;
use WP_Error;
use WP_SMS\Services\OTP\AuthChannel\MagicLink\MagicLinkService;
use WP_SMS\Services\OTP\Models\MagicLinkModel;
use WP_SMS\Services\OTP\Models\AuthEventModel;
use WP_SMS\Services\OTP\Security\RateLimiter;
use WP_SMS\Utils\DateUtils;

class MagicLinkRestAPIEndpoints
{
    protected MagicLinkService $magicLinkService;
    protected RateLimiter $rateLimiter;

    public function __construct()
    {
        $this->magicLinkService = new MagicLinkService();
        $this->rateLimiter = new RateLimiter();
    }

    /**
     * Initialize the service
     */
    public function init(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    /**
     * Register REST API routes for Magic Link endpoints.
     */
    public function registerRoutes(): void
    {
        register_rest_route('wpsms/v1', '/magic-link/send', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'sendMagicLink'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('wpsms/v1', '/magic-link/verify', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'verifyMagicLink'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('wpsms/v1', '/magic-link/status/(?P<flow_id>[^/]+)', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'getStatus'],
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * Send magic link to user
     */
    public function sendMagicLink(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        // 1. Extract and validate input
        $identifier = $this->extractAndValidateIdentifier($request);
        if (is_wp_error($identifier)) {
            return $identifier;
        }

        $ip = $this->getClientIp($request);

        // 2. Apply rate limiting
        $rateLimitResult = $this->checkRateLimits($identifier['value'], $ip);
        if (is_wp_error($rateLimitResult)) {
            return $rateLimitResult;
        }

        // 3. Check for existing session
        $existingSession = $this->checkExistingSession($identifier, $ip);
        if ($existingSession) {
            return $existingSession;
        }

        // 4. Generate and send magic link
        $magicLinkResult = $this->generateAndSendMagicLink($identifier, $ip);
        if (is_wp_error($magicLinkResult)) {
            return $magicLinkResult;
        }

        // 5. Return success response
        return $this->createSuccessResponse($magicLinkResult);
    }

    /**
     * Extract and validate identifier from request
     */
    private function extractAndValidateIdentifier(WP_REST_Request $request): array|WP_Error
    {
        $phone = $request->get_param('phone');
        $email = $request->get_param('email');

        if (!$phone && !$email) {
            return new WP_Error('invalid_identifier', __('Phone number or email is required.', 'wp-sms'), ['status' => 400]);
        }

        return [
            'value' => $phone ?: $email,
            'type' => $phone ? 'phone' : 'email',
            'channel' => $phone ? 'sms' : 'email',
            'phone' => $phone,
            'email' => $email
        ];
    }

    /**
     * Get client IP address from request headers
     */
    private function getClientIp(WP_REST_Request $request): string
    {
        return $request->get_header('X-Forwarded-For') ?: $request->get_header('REMOTE_ADDR') ?: '0.0.0.0';
    }

    /**
     * Check rate limits for identifier and IP
     */
    private function checkRateLimits(string $identifier, string $ip): bool|WP_Error
    {
        $rateKeyIdentifier = 'magic_link:identifier:' . md5($identifier);
        $rateKeyIp = 'magic_link:ip:' . md5($ip);

        if (!$this->rateLimiter->isAllowed($rateKeyIdentifier) || !$this->rateLimiter->isAllowed($rateKeyIp)) {
            return new WP_Error('rate_limited', __('Too many magic link requests. Please try again later.', 'wp-sms'), ['status' => 429]);
        }

        return true;
    }

    /**
     * Check for existing unexpired session
     */
    private function checkExistingSession(array $identifier, string $ip): ?WP_Error
    {
        $field = $identifier['type'];
        $value = $identifier['value'];
        
        // Check for existing magic link sessions
        $existing = MagicLinkModel::find([
            'identifier' => $value,
            'identifier_type' => $field,
            'used_at' => null
        ]);
        
        if ($existing && strtotime($existing['expires_at']) > time()) {
            $this->logAuthEvent($existing['flow_id'], 'magic_link_duplicate_request', 'deny', $identifier['channel'], $ip);

            return new WP_Error('existing_session', __('A magic link has already been sent to this identifier.', 'wp-sms'), [
                'status'            => 409,
                'flow_id'           => $existing['flow_id'],
                'expires_at'        => DateUtils::utcDateTimeToTimestamp($existing['expires_at']),
                'remaining_seconds' => DateUtils::getSecondsRemaining($existing['expires_at']),
            ]);
        }

        return null;
    }

    /**
     * Generate magic link and send it
     */
    private function generateAndSendMagicLink(array $identifier, string $ip): array|WP_Error
    {
        $flowId = wp_generate_uuid4();
        
        // Generate magic link using the service with identifier information
        $magicLinkUrl = $this->magicLinkService->generate($flowId, $identifier['value'], $identifier['type']);

        // Send magic link via SMS or email using the service
        $deliveryResult = $this->magicLinkService->sendMagicLink(
            $identifier['value'],
            $magicLinkUrl,
            $identifier['channel'],
            $this->getMagicLinkMessageData($flowId, $magicLinkUrl)
        );

        if (!$deliveryResult['success']) {
            $this->logAuthEvent($flowId, 'magic_link_delivery_failed', 'deny', $identifier['channel'], $ip);
            return new WP_Error('delivery_failed', __('Failed to send magic link. Please try again later.', 'wp-sms'), ['status' => 500]);
        }

        // Log success and increment rate limits
        $this->logAuthEvent($flowId, 'magic_link_sent', 'allow', $deliveryResult['channel_used'], $ip);
        $this->incrementRateLimits($identifier['value'], $ip);

        return [
            'flow_id' => $flowId,
            'channel_used' => $deliveryResult['channel_used']
        ];
    }

    /**
     * Get magic link message data for SMS and email
     */
    private function getMagicLinkMessageData(string $flowId, string $magicLinkUrl): array
    {
        return [
            'flow_id' => $flowId,
            'sms_message' => sprintf(__('Click here to login: %s', 'wp-sms'), $magicLinkUrl),
            'email_message' => sprintf(__('Click here to login: %s', 'wp-sms'), $magicLinkUrl),
            'email_subject' => __('Your Magic Link Login', 'wp-sms'),
            'whatsapp_message' => sprintf(__('Click here to login: %s', 'wp-sms'), $magicLinkUrl),
            'viber_message' => sprintf(__('Click here to login: %s', 'wp-sms'), $magicLinkUrl),
            'call_message' => sprintf(__('Your magic link is: %s', 'wp-sms'), $magicLinkUrl),
        ];
    }

    /**
     * Log authentication event
     */
    private function logAuthEvent(string $flowId, string $eventType, string $result, string $channel, string $ip): void
    {
        try {
            $data = [
                'event_id' => wp_generate_uuid4(),
                'flow_id' => $flowId,
                'timestamp_utc' => DateUtils::getCurrentUtcDateTime(),
                'user_id' => null,
                'channel' => $channel,
                'event_type' => $eventType,
                'result' => $result,
                'client_ip_masked' => $ip,
                'retention_days' => 30,
            ];

            AuthEventModel::insert($data);
        } catch (\Exception $e) {
            error_log("[WP-SMS] Failed to log auth event: " . $e->getMessage());
        }
    }

    /**
     * Increment rate limit counters
     */
    private function incrementRateLimits(string $identifier, string $ip): void
    {
        $rateKeyIdentifier = 'magic_link:identifier:' . md5($identifier);
        $rateKeyIp = 'magic_link:ip:' . md5($ip);

        $this->rateLimiter->increment($rateKeyIdentifier);
        $this->rateLimiter->increment($rateKeyIp);
    }

    /**
     * Create success response with session details
     */
    private function createSuccessResponse(array $magicLinkResult): WP_REST_Response
    {
        $session = MagicLinkModel::find(['flow_id' => $magicLinkResult['flow_id']]);
        
        return new WP_REST_Response([
            'flow_id' => $magicLinkResult['flow_id'],
            'expires_at' => $session ? strtotime($session['expires_at']) : null,
            'resend_cooldown' => 300, // 5 minutes for magic links
            'channel_used' => $magicLinkResult['channel_used'],
        ]);
    }

    /**
     * Verify magic link token
     */
    public function verifyMagicLink(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $flowId = $request->get_param('flow_id');
        $token = $request->get_param('token');
        $ip = $this->getClientIp($request);

        // Validate session exists
        $session = MagicLinkModel::find(['flow_id' => $flowId]);
        if (!$session) {
            return new WP_Error('invalid_session', __('Magic link session not found or expired.', 'wp-sms'), ['status' => 400]);
        }

        // Validate magic link token using the service
        $isValid = $this->magicLinkService->validate($flowId, $token);
        $eventType = 'verified';
        $result = 'deny';

        if ($isValid) {
            $result = 'allow';
            // Magic link is automatically marked as used in the service
        } else {
            $eventType = 'failed';
        }

        // Log verification event
        $this->logAuthEvent($flowId, $eventType, $result, 'magic_link', $ip);

        return new WP_REST_Response([
            'verified' => $isValid,
            'reason' => $isValid ? 'success' : 'invalid_token',
        ]);
    }

    /**
     * Get magic link status
     */
    public function getStatus(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $flowId = $request->get_param('flow_id');
        $session = MagicLinkModel::find(['flow_id' => $flowId]);

        if (!$session) {
            return new WP_Error('invalid_session', __('Magic link session not found or expired.', 'wp-sms'), ['status' => 400]);
        }

        $expiresAt = DateUtils::utcDateTimeToTimestamp($session['expires_at']);
        $remaining = DateUtils::getSecondsRemaining($session['expires_at']);

        return new WP_REST_Response([
            'flow_id'            => $flowId,
            'expires_at'         => $expiresAt,
            'remaining_seconds'  => $remaining,
            'used_at'            => $session['used_at'] ? DateUtils::utcDateTimeToTimestamp($session['used_at']) : null,
        ]);
    }
}
