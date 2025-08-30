<?php

namespace WP_SMS\Services\OTP\RestAPIEndpoints\OTP;

use WP_REST_Server;
use WP_REST_Response;
use WP_REST_Request;
use WP_Error;
use WP_SMS\Services\OTP\AuthChannel\OTP\OtpService;
use WP_SMS\Services\OTP\Models\OtpSessionModel;
use WP_SMS\Services\OTP\Models\AuthEventModel;
use WP_SMS\Services\OTP\Security\RateLimiter;
use WP_SMS\Utils\DateUtils;

class OTPRestAPIEndpoints
{
    protected OtpService $otpService;
    protected RateLimiter $rateLimiter;

    public function __construct()
    {
        $this->otpService = new OtpService();
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
     * Register REST API routes for OTP endpoints.
     */
    public function registerRoutes(): void
    {
        register_rest_route('wpsms/v1', '/otp/send', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'sendOtp'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('wpsms/v1', '/otp/verify', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'verifyOtp'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('wpsms/v1', '/otp/status/(?P<session_id>[^/]+)', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'getStatus'],
            'permission_callback' => '__return_true',
        ]);
    }

    public function sendOtp(WP_REST_Request $request): WP_REST_Response|WP_Error
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

        // 4. Generate and send OTP
        $otpResult = $this->generateAndSendOtp($identifier, $ip);
        if (is_wp_error($otpResult)) {
            return $otpResult;
        }

        // 5. Return success response
        return $this->createSuccessResponse($otpResult);
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
        $rateKeyIdentifier = 'otp:identifier:' . md5($identifier);
        $rateKeyIp = 'otp:ip:' . md5($ip);

        if (!$this->rateLimiter->isAllowed($rateKeyIdentifier) || !$this->rateLimiter->isAllowed($rateKeyIp)) {
            return new WP_Error('rate_limited', __('Too many OTP requests. Please try again later.', 'wp-sms'), ['status' => 429]);
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
        
        // Use the model methods to check for existing sessions
        $existing = null;
        if ($field === 'phone') {
            $existing = OtpSessionModel::getMostRecentUnexpiredSession($value);
        } elseif ($field === 'email') {
            $existing = OtpSessionModel::getMostRecentUnexpiredSessionByEmail($value);
        }
        
        if ($existing) {
            $this->logAuthEvent($existing['flow_id'], 'otp_duplicate_request', 'deny', $existing['channel'] ?? 'sms', $ip);

            return new WP_Error('existing_session', __('An OTP has already been sent to this identifier.', 'wp-sms'), [
                'status'            => 409,
                'session_id'        => $existing['flow_id'],
                'expires_at'        => DateUtils::utcDateTimeToTimestamp($existing['expires_at']),
                'remaining_seconds' => DateUtils::getSecondsRemaining($existing['expires_at']),
            ]);
        }

        return null;
    }

    /**
     * Generate OTP code and send it
     */
    private function generateAndSendOtp(array $identifier, string $ip): array|WP_Error
    {
        $sessionId = wp_generate_uuid4();
        
        // Generate OTP code
        $code = $this->otpService->generate(
            $sessionId, 
            $identifier['phone'], 
            $identifier['email'], 
            $identifier['channel']
        );

        // Send OTP
        $deliveryResult = $this->otpService->sendOTP(
            $identifier['value'],
            $code,
            $identifier['channel'],
            $this->getOtpMessageData($sessionId, $code)
        );

        if (!$deliveryResult['success']) {
            $this->logAuthEvent($sessionId, 'otp_delivery_failed', 'deny', $identifier['channel'], $ip);
            return new WP_Error('delivery_failed', __('Failed to send OTP. Please try again later.', 'wp-sms'), ['status' => 500]);
        }

        // Log success and increment rate limits
        $this->logAuthEvent($sessionId, 'otp_sent', 'allow', $deliveryResult['channel_used'], $ip);
        $this->incrementRateLimits($identifier['value'], $ip);

        return [
            'session_id' => $sessionId,
            'channel_used' => $deliveryResult['channel_used']
        ];
    }

    /**
     * Get OTP message data for SMS and email
     */
    private function getOtpMessageData(string $sessionId, string $code): array
    {
        return [
            'session_id' => $sessionId,
            'sms_message' => sprintf(__('Your verification code is: %s', 'wp-sms'), $code),
            'email_message' => sprintf(__('Your verification code is: %s', 'wp-sms'), $code),
            'email_subject' => __('Your Verification Code', 'wp-sms'),
        ];
    }

    /**
     * Log authentication event
     */
    private function logAuthEvent(string $flowId, string $eventType, string $result, string $channel, string $ip, ?int $attempts = null): void
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

            if ($attempts !== null) {
                $data['attempt_count'] = $attempts;
            }

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
        $rateKeyIdentifier = 'otp:identifier:' . md5($identifier);
        $rateKeyIp = 'otp:ip:' . md5($ip);

        $this->rateLimiter->increment($rateKeyIdentifier);
        $this->rateLimiter->increment($rateKeyIp);
    }

    /**
     * Create success response with session details
     */
    private function createSuccessResponse(array $otpResult): WP_REST_Response
    {
        $session = OtpSessionModel::getByFlowId($otpResult['session_id']);
        
        return new WP_REST_Response([
            'session_id' => $otpResult['session_id'],
            'expires_at' => $session ? strtotime($session['expires_at']) : null,
            'resend_cooldown' => 60,
            'channel_used' => $otpResult['channel_used'],
        ]);
    }


    public function verifyOtp(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $sessionId = $request->get_param('session_id');
        $inputCode = $request->get_param('code');
        $ip = $this->getClientIp($request);

        // Validate session exists
        $session = OtpSessionModel::getByFlowId($sessionId);
        if (!$session) {
            return new WP_Error('invalid_session', __('Session not found or expired.', 'wp-sms'), ['status' => 400]);
        }

        // Validate OTP code
        $status = $this->otpService->validate($sessionId, $inputCode);
        $eventType = 'verified';
        $result = 'deny';
        $attempts = (int) $session['attempt_count'];

        if ($status === true) {
            $result = 'allow';
            OtpSessionModel::deleteBy(['session_id' => $sessionId]);
        } else {
            $eventType = 'failed';
            $attempts += 1;
            OtpSessionModel::updateBy(['attempt_count' => $attempts], ['session_id' => $sessionId]);
        }

        // Log verification event
        $this->logAuthEvent($sessionId, $eventType, $result, $session['channel'] ?? 'sms', $ip, $attempts);

        return new WP_REST_Response([
            'verified' => $status === true,
            'reason' => $status ? 'success' : 'incorrect',
            'attempt_count' => $attempts,
        ]);
    }

    public function getStatus(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $sessionId = $request->get_param('session_id');
        $session   = OtpSessionModel::getByFlowId($sessionId);

        if (!$session) {
            return new WP_Error('invalid_session', __('Session not found or expired.', 'wp-sms'), ['status' => 400]);
        }

        $expiresAt = DateUtils::utcDateTimeToTimestamp($session['expires_at']);
        $remaining = DateUtils::getSecondsRemaining($session['expires_at']);

        return new WP_REST_Response([
            'session_id'       => $sessionId,
            'expires_at'       => $expiresAt,
            'remaining_seconds'=> $remaining,
            'attempt_count'    => (int) $session['attempt_count'],
        ]);
    }
}
