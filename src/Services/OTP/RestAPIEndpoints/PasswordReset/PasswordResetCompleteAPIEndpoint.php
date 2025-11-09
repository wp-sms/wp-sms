<?php

namespace WP_SMS\Services\OTP\RestAPIEndpoints\PasswordReset;

use WP_REST_Server;
use WP_REST_Response;
use WP_REST_Request;
use WP_Error;
use WP_SMS\Services\OTP\RestAPIEndpoints\Abstracts\RestAPIEndpointsAbstract;
use WP_SMS\Services\OTP\Helpers\PasswordResetHelper;
use WP_SMS\Services\OTP\Helpers\RedirectHelper;

/**
 * Password Reset Complete API Endpoint
 *
 * POST /wp-json/wpsms/v1/password-reset/complete
 *
 * Completes password reset by setting new password
 */
class PasswordResetCompleteAPIEndpoint extends RestAPIEndpointsAbstract
{
    public function registerRoutes(): void
    {
        register_rest_route('wpsms/v1', '/password-reset/complete', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'handleRequest'],
            'permission_callback' => '__return_true',
            'args'                => $this->getArgs(),
        ]);
    }

    public function getArgs(): array
    {
        return [
            'reset_token' => [
                'required'          => true,
                'type'              => 'string',
                'description'       => __('Reset token from verify step', 'wp-sms'),
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'new_password' => [
                'required'          => true,
                'type'              => 'string',
                'description'       => __('New password', 'wp-sms'),
                'validate_callback' => function ($param) {
                    if (empty($param)) {
                        return new WP_Error(
                            'password_required',
                            __('New password is required', 'wp-sms')
                        );
                    }
                    
                    // Validate password strength
                    $validation = PasswordResetHelper::validatePassword($param);
                    if (!$validation['valid']) {
                        return new WP_Error(
                            'weak_password',
                            implode(' ', $validation['errors'])
                        );
                    }
                    
                    return true;
                },
            ],
            'confirm_password' => [
                'required'          => true,
                'type'              => 'string',
                'description'       => __('Confirm new password', 'wp-sms'),
                'validate_callback' => function ($param, $request) {
                    $newPassword = $request->get_param('new_password');
                    
                    if ($param !== $newPassword) {
                        return new WP_Error(
                            'password_mismatch',
                            __('Passwords do not match', 'wp-sms')
                        );
                    }
                    
                    return true;
                },
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
            $resetToken = (string) $request->get_param('reset_token');
            $newPassword = (string) $request->get_param('new_password');
            $ip = $this->getClientIp($request);

            // Find user by reset token
            $user = $this->getUserByResetToken($resetToken);
            
            if (!$user) {
                return $this->createErrorResponse(
                    'invalid_token',
                    __('Invalid or expired reset token', 'wp-sms'),
                    400
                );
            }

            // Get flow ID for logging
            $flowId = get_user_meta($user->ID, 'wpsms_password_reset_flow_id', true);
            
            // Check verification timestamp (must complete within expiry window)
            $verifiedAt = get_user_meta($user->ID, 'wpsms_password_reset_verified_at', true);
            $expirySeconds = PasswordResetHelper::getTokenExpirySeconds();
            
            if (empty($verifiedAt) || (time() - $verifiedAt) > $expirySeconds) {
                $this->cleanupResetSession($user->ID);
                
                return $this->createErrorResponse(
                    'token_expired',
                    __('Reset token has expired. Please start over', 'wp-sms'),
                    400
                );
            }

            // Rate limiting
            $rateLimitCheck = $this->checkRateLimits($resetToken, $ip, 'password_reset_complete');
            if (is_wp_error($rateLimitCheck)) {
                return $this->createErrorResponse(
                    'rate_limited',
                    $rateLimitCheck->get_error_message(),
                    429
                );
            }

            // Update user password
            wp_set_password($newPassword, $user->ID);
            
            // Invalidate all other sessions
            $sessions = \WP_Session_Tokens::get_instance($user->ID);
            $sessions->destroy_all();

            // Clean up reset session
            $this->cleanupResetSession($user->ID);

            // Auto-login if enabled
            $authToken = null;
            $redirectUrl = null;
            
            if (PasswordResetHelper::isAutoLoginEnabled()) {
                // Log the user into WordPress
                wp_clear_auth_cookie();
                wp_set_current_user($user->ID);
                wp_set_auth_cookie($user->ID, true);
                
                // Fire WordPress login action
                do_action('wp_login', $user->user_login, $user);
                
                // Generate auth token for reference
                $authToken = $this->generateAuthToken($user->ID, $flowId);
                $redirectUrl = RedirectHelper::getLoginRedirectUrl($user);
            }

            // Log success
            $this->logAuthEvent($flowId, 'password_reset_complete', 'allow', 'system', $ip, null, ['user_id' => $user->ID]);
            $this->incrementRateLimits($resetToken, $ip, 'password_reset_complete');

            $data = [
                'user_id' => $user->ID,
                'auto_login' => PasswordResetHelper::isAutoLoginEnabled(),
                'message' => __('Password reset successful', 'wp-sms'),
            ];

            if ($authToken) {
                $data['auth_token'] = $authToken;
                $data['redirect_url'] = $redirectUrl;
            }

            return new WP_REST_Response([
                'success' => true,
                'data' => $data,
            ], 200);

        } catch (\Exception $e) {
            return $this->handleException($e, 'passwordResetComplete');
        }
    }

    /**
     * Get user by reset token
     */
    private function getUserByResetToken(string $resetToken)
    {
        $users = get_users([
            'meta_key'   => 'wpsms_password_reset_token',
            'meta_value' => $resetToken,
            'number'     => 1,
        ]);

        return !empty($users) ? $users[0] : null;
    }

    /**
     * Generate authentication token
     */
    private function generateAuthToken(int $userId, string $flowId): string
    {
        $token = bin2hex(random_bytes(32));
        
        set_transient('wpsms_auth_token_' . $token, [
            'user_id'    => $userId,
            'flow_id'    => $flowId,
            'created_at' => time(),
        ], 3600);
        
        return $token;
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

