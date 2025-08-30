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

class OtpRestController
{
    protected OtpService $otpService;
    protected RateLimiter $rateLimiter;

    public function __construct()
    {
        $this->otpService = new OtpService();
        $this->rateLimiter = new RateLimiter();
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
        $phone = $request->get_param('phone');
        $email = $request->get_param('email');
        $ip    = $request->get_header('X-Forwarded-For') ?: $request->get_header('REMOTE_ADDR');

        // Validate that at least one identifier is provided
        if (!$phone && !$email) {
            return new WP_Error('invalid_identifier', __('Phone number or email is required.', 'wp-sms'), ['status' => 400]);
        }

        // Determine the primary identifier and channel
        $primaryIdentifier = $phone ?: $email;
        $primaryChannel = $phone ? 'sms' : 'email';

        // Rate limit keys
        $rateKeyIdentifier = 'otp:identifier:' . md5($primaryIdentifier);
        $rateKeyIp    = 'otp:ip:' . md5($ip);

        // Apply rate limiting
        if (! $this->rateLimiter->isAllowed($rateKeyIdentifier) || ! $this->rateLimiter->isAllowed($rateKeyIp)) {
            return new WP_Error('rate_limited', __('Too many OTP requests. Please try again later.', 'wp-sms'), ['status' => 429]);
        }

        // Check for existing unexpired session
        $identifierField = $phone ? 'phone' : 'email';
        if (OtpSessionModel::exists([$identifierField => $primaryIdentifier, 'expires_at' => DateUtils::getUnexpiredSqlCondition()])) {
            $existing = OtpSessionModel::findAll([$identifierField => $primaryIdentifier, 'expires_at' => DateUtils::getUnexpiredSqlCondition()]);
            $active   = $existing[0];

            try {
                AuthEventModel::insert([
                    'event_id'         => wp_generate_uuid4(),
                    'flow_id'          => $active['flow_id'],
                    'timestamp_utc'    => DateUtils::getCurrentUtcDateTime(),
                    'user_id'          => null,
                    'channel'          => $active['channel'] ?? 'sms',
                    'event_type'       => 'duplicate_request',
                    'result'           => 'deny',
                    'client_ip_masked' => $ip,
                    'retention_days'   => 30,
                ]);
            } catch (\Exception $e) {
                error_log("[WP-SMS] Failed to log duplicate request: " . $e->getMessage());
            }

            return new WP_Error('existing_session', __('An OTP has already been sent to this identifier.', 'wp-sms'), [
                'status'            => 409,
                'session_id'        => $active['flow_id'],
                'expires_at'        => DateUtils::utcDateTimeToTimestamp($active['expires_at']),
                'remaining_seconds' => DateUtils::getSecondsRemaining($active['expires_at']),
            ]);
        }

        // Generate OTP and save session
        $sessionId = wp_generate_uuid4();
        $code = $this->otpService->generate(
            flowId: $sessionId, 
            userId: 0, 
            phone: $phone, 
            email: $email, 
            preferredChannel: $primaryChannel
        );

        // Increment rate limits
        $this->rateLimiter->increment($rateKeyIdentifier);
        $this->rateLimiter->increment($rateKeyIp);

        // Send OTP via OTP service with fallback support
        $deliveryResult = $this->otpService->sendOTP(
            $primaryIdentifier,
            $code,
            $primaryChannel,
            [
                'session_id' => $sessionId,
                'sms_message' => sprintf(__('Your verification code is: %s', 'wp-sms'), $code),
                'email_message' => sprintf(__('Your verification code is: %s', 'wp-sms'), $code),
                'email_subject' => __('Your Verification Code', 'wp-sms'),
            ]
        );

        if (!$deliveryResult['success']) {
            // Log delivery failure
            try {
                AuthEventModel::insert([
                    'event_id'         => wp_generate_uuid4(),
                    'flow_id'          => $sessionId,
                    'timestamp_utc'    => DateUtils::getCurrentUtcDateTime(),
                    'user_id'          => null,
                    'channel'          => $primaryChannel,
                    'event_type'       => 'delivery_failed',
                    'result'           => 'deny',
                    'client_ip_masked' => $ip,
                    'retention_days'   => 30,
                ]);
            } catch (\Exception $e) {
                error_log("[WP-SMS] Failed to log delivery failure: " . $e->getMessage());
            }

            return new WP_Error('delivery_failed', __('Failed to send OTP. Please try again later.', 'wp-sms'), ['status' => 500]);
        }

        // Log successful delivery
        try {
            AuthEventModel::insert([
                'event_id'         => wp_generate_uuid4(),
                'flow_id'          => $sessionId,
                'timestamp_utc'    => DateUtils::getCurrentUtcDateTime(),
                'user_id'          => null,
                'channel'          => $deliveryResult['channel_used'],
                'event_type'       => 'sent',
                'result'           => 'allow',
                'client_ip_masked' => $ip,
                'retention_days'   => 30,
                'fallback_used'    => $deliveryResult['fallback_used'] ? 1 : 0,
            ]);
        } catch (\Exception $e) {
            error_log("[WP-SMS] Failed to log OTP send event: " . $e->getMessage());
        }

        // Get session data for expiration time
        $session = OtpSessionModel::getByFlowId($sessionId);
        $expiresAt = $session ? $session['expires_at'] : null;

        return new WP_REST_Response([
            'session_id'       => $sessionId,
            'expires_at'       => $expiresAt ? strtotime($expiresAt) : null,
            'resend_cooldown'  => 60, // TODO: make configurable
            'channel_used'     => $deliveryResult['channel_used'],
            'fallback_used'    => $deliveryResult['fallback_used'],
        ]);
    }

    public function verifyOtp(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $sessionId = $request->get_param('session_id');
        $inputCode = $request->get_param('code');
        $ip        = $request->get_header('X-Forwarded-For') ?: $request->get_header('REMOTE_ADDR');

        $session = OtpSessionModel::getByFlowId($sessionId);
        if (! $session) {
            return new WP_Error('invalid_session', __('Session not found or expired.', 'wp-sms'), ['status' => 400]);
        }

        $status     = $this->otpService->validate($sessionId, $inputCode);
        $eventType  = 'verified';
        $result     = 'deny';
        $attempts   = (int) $session['attempt_count'];

        if ($status === true) {
            $result = 'allow';
            OtpSessionModel::deleteBy(['session_id' => $sessionId]);
        } else {
            $eventType = 'failed';
            $attempts += 1;
            OtpSessionModel::updateBy(['attempt_count' => $attempts], ['session_id' => $sessionId]);
        }

        try {
            AuthEventModel::insert([
                'event_id'         => wp_generate_uuid4(),
                'flow_id'          => $sessionId,
                'timestamp_utc'    => DateUtils::getCurrentUtcDateTime(),
                'user_id'          => null,
                'channel'          => $session['channel'] ?? 'sms', // Use stored channel or default
                'event_type'       => $eventType,
                'result'           => $result,
                'client_ip_masked' => $ip,
                'attempt_count'    => $attempts,
                'retention_days'   => 30,
            ]);
        } catch (\Exception $e) {
            error_log("[WP-SMS] Failed to log OTP verification event: " . $e->getMessage());
        }

        return new WP_REST_Response([
            'verified'       => $status === true,
            'reason'         => $status ? 'success' : 'incorrect',
            'attempt_count'  => $attempts,
        ]);
    }

    public function getStatus(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $sessionId = $request->get_param('session_id');
        $session   = OtpSessionModel::getByFlowId($sessionId);

        if (! $session) {
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
