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
        $ip    = $request->get_header('X-Forwarded-For') ?: $request->get_header('REMOTE_ADDR');

        if (! $phone) {
            return new WP_Error('invalid_phone', __('Invalid phone number.', 'wp-sms'), ['status' => 400]);
        }

        // Rate limit keys
        $rateKeyPhone = 'otp:phone:' . md5($phone);
        $rateKeyIp    = 'otp:ip:' . md5($ip);

        // Apply rate limiting
        if (! $this->rateLimiter->isAllowed($rateKeyPhone) || ! $this->rateLimiter->isAllowed($rateKeyIp)) {
            return new WP_Error('rate_limited', __('Too many OTP requests. Please try again later.', 'wp-sms'), ['status' => 429]);
        }

        // Check for existing unexpired session
        if (OtpSessionModel::exists(['phone' => $phone, 'expires_at' => DateUtils::getUnexpiredSqlCondition()])) {
            $existing = OtpSessionModel::findAll(['phone' => $phone, 'expires_at' => DateUtils::getUnexpiredSqlCondition()]);
            $active   = $existing[0];

            try {
                AuthEventModel::insert([
                    'event_id'         => wp_generate_uuid4(),
                    'flow_id'          => $active['session_id'],
                    'timestamp_utc'    => DateUtils::getCurrentUtcDateTime(),
                    'user_id'          => null,
                    'channel'          => 'sms',
                    'event_type'       => 'duplicate_request',
                    'result'           => 'deny',
                    'client_ip_masked' => $ip,
                    'retention_days'   => 30,
                ]);
            } catch (\Exception $e) {
                error_log("[WP-SMS] Failed to log duplicate request: " . $e->getMessage());
            }

            return new WP_Error('existing_session', __('An OTP has already been sent to this number.', 'wp-sms'), [
                'status'            => 409,
                'session_id'        => $active['session_id'],
                'expires_at'        => DateUtils::utcDateTimeToTimestamp($active['expires_at']),
                'remaining_seconds' => DateUtils::getSecondsRemaining($active['expires_at']),
            ]);
        }

        // Generate OTP
        $code       = $this->otpService->generate(flowId: $sessionId = wp_generate_uuid4(), userId: 0);
        $otpHash    = hash('sha256', $code);
        $expiresAt  = gmdate('Y-m-d H:i:s', time() + 300); // 5 mins

        // Save session
        OtpSessionModel::insert([
            'session_id'    => $sessionId,
            'phone'         => $phone,
            'otp_hash'      => $otpHash,
            'expires_at'    => $expiresAt,
            'attempt_count' => 0,
        ]);

        // Increment rate limits
        $this->rateLimiter->increment($rateKeyPhone);
        $this->rateLimiter->increment($rateKeyIp);

        // TODO: Send via channel service (e.g., SmsChannel)
        error_log("[WP-SMS] Sending OTP to {$phone}: {$code}");

        // Log event
        try {
            AuthEventModel::insert([
                'event_id'         => wp_generate_uuid4(),
                'flow_id'          => $sessionId,
                'timestamp_utc'    => DateUtils::getCurrentUtcDateTime(),
                'user_id'          => null,
                'channel'          => 'sms',
                'event_type'       => 'sent',
                'result'           => 'allow',
                'client_ip_masked' => $ip,
                'retention_days'   => 30,
            ]);
        } catch (\Exception $e) {
            error_log("[WP-SMS] Failed to log OTP send event: " . $e->getMessage());
        }

        return new WP_REST_Response([
            'session_id'       => $sessionId,
            'expires_at'       => strtotime($expiresAt),
            'resend_cooldown'  => 60, // TODO: make configurable
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

        AuthEventModel::insert([
            'event_id'         => wp_generate_uuid4(),
            'flow_id'          => $sessionId,
            'timestamp_utc'    => DateUtils::getCurrentUtcDateTime(),
            'user_id'          => null,
            'channel'          => 'sms',
            'event_type'       => $eventType,
            'result'           => $result,
            'client_ip_masked' => $ip,
            'attempt_count'    => $attempts,
            'retention_days'   => 30,
        ]);

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
