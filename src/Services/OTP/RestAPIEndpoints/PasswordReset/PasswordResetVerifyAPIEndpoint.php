<?php

namespace WP_SMS\Services\OTP\RestAPIEndpoints\PasswordReset;

use WP_REST_Server;
use WP_REST_Response;
use WP_REST_Request;
use WP_Error;
use WP_SMS\Services\OTP\RestAPIEndpoints\Abstracts\RestAPIEndpointsAbstract;
use WP_SMS\Services\OTP\Helpers\PasswordResetHelper;
use WP_SMS\Services\OTP\AuthChannel\MagicLink\MagicLinkService;

/**
 * Password Reset Verify API Endpoint
 *
 * POST /wp-json/wpsms/v1/password-reset/verify
 *
 * Verifies OTP code or magic link token for password reset
 */
class PasswordResetVerifyAPIEndpoint extends RestAPIEndpointsAbstract
{
    public function registerRoutes(): void
    {
        register_rest_route('wpsms/v1', '/password-reset/verify', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'handleRequest'],
            'permission_callback' => '__return_true',
            'args'                => $this->getArgs(),
        ]);
    }

    public function getArgs(): array
    {
        return [
            'flow_id' => [
                'required'          => true,
                'type'              => 'string',
                'description'       => __('Flow ID from init step', 'wp-sms'),
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'code' => [
                'required'          => false,
                'type'              => 'string',
                'description'       => __('OTP code (if using OTP method)', 'wp-sms'),
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'token' => [
                'required'          => false,
                'type'              => 'string',
                'description'       => __('Magic link token (if using magic link method)', 'wp-sms'),
                'sanitize_callback' => 'sanitize_text_field',
            ],
        ];
    }

    public function handleRequest(WP_REST_Request $request)
    {
        try {
            // Check if password reset is available
            if (!PasswordResetHelper::isPasswordResetAvailable()) {
                return $this->createErrorResponse(
                    'password_reset_disabled',
                    __('Password reset is not available', 'wp-sms'),
                    403
                );
            }

            // Extract parameters
            $flowId = (string) $request->get_param('flow_id');
            $code = (string) $request->get_param('code');
            $token = (string) $request->get_param('token');
            $ip = $this->getClientIp($request);

            // Find user by flow ID
            $user = $this->getUserByFlowId($flowId);
            
            if (!$user) {
                return $this->createErrorResponse(
                    'invalid_flow',
                    __('Invalid or expired reset session', 'wp-sms'),
                    400
                );
            }

            // Check if reset session is still valid
            $startedAt = get_user_meta($user->ID, 'wpsms_password_reset_started_at', true);
            $expirySeconds = PasswordResetHelper::getTokenExpirySeconds();
            
            if (empty($startedAt) || (time() - $startedAt) > $expirySeconds) {
                // Clean up expired session
                $this->cleanupResetSession($user->ID);
                
                return $this->createErrorResponse(
                    'session_expired',
                    __('Reset session has expired. Please start over', 'wp-sms'),
                    400
                );
            }

            // Rate limiting
            $rateLimitCheck = $this->checkRateLimits($flowId, $ip, 'password_reset_verify');
            if (is_wp_error($rateLimitCheck)) {
                return $this->createErrorResponse(
                    'rate_limited',
                    $rateLimitCheck->get_error_message(),
                    429
                );
            }

            // Determine verification method
            $method = !empty($token) ? 'magic' : 'otp';

            // Perform verification
            $verificationSuccess = $this->performVerification($flowId, $method, $code, $token);
            
            if (!$verificationSuccess) {
                $this->incrementRateLimits($flowId, $ip, 'password_reset_verify');
                $this->logAuthEvent($flowId, 'password_reset_verify', 'deny', $method, $ip, null, ['user_id' => $user->ID, 'reason' => 'invalid_code']);
                
                return $this->createErrorResponse(
                    'verification_failed',
                    __('Invalid or expired verification code', 'wp-sms'),
                    400
                );
            }

            // Generate reset token for the complete step
            $resetToken = wp_generate_password(64, false);
            update_user_meta($user->ID, 'wpsms_password_reset_token', $resetToken);
            update_user_meta($user->ID, 'wpsms_password_reset_verified_at', time());

            // Log success
            $this->logAuthEvent($flowId, 'password_reset_verify', 'allow', $method, $ip, null, ['user_id' => $user->ID]);
            $this->incrementRateLimits($flowId, $ip, 'password_reset_verify');

            return new WP_REST_Response([
                'success' => true,
                'data' => [
                    'reset_token' => $resetToken,
                    'user_id' => $user->ID,
                    'message' => __('Verification successful. You can now reset your password', 'wp-sms'),
                ],
            ], 200);

        } catch (\Exception $e) {
            return $this->handleException($e, 'passwordResetVerify');
        }
    }

    /**
     * Get user by flow ID
     */
    private function getUserByFlowId(string $flowId)
    {
        $users = get_users([
            'meta_key'   => 'wpsms_password_reset_flow_id',
            'meta_value' => $flowId,
            'number'     => 1,
        ]);

        return !empty($users) ? $users[0] : null;
    }

    /**
     * Perform verification
     */
    private function performVerification(string $flowId, string $method, string $code, string $token): bool
    {
        if ($method === 'magic') {
            $magicService = new MagicLinkService();
            $result = $magicService->validate($flowId, $token);
            return !empty($result);
        } else {
            $result = $this->otpService->validate($flowId, $code);
            return !empty($result);
        }
    }

    /**
     * Clean up reset session
     */
    private function cleanupResetSession(int $userId): void
    {
        delete_user_meta($userId, 'wpsms_password_reset_flow_id');
        delete_user_meta($userId, 'wpsms_password_reset_identifier');
        delete_user_meta($userId, 'wpsms_password_reset_started_at');
        delete_user_meta($userId, 'wpsms_password_reset_token');
        delete_user_meta($userId, 'wpsms_password_reset_verified_at');
    }
}

