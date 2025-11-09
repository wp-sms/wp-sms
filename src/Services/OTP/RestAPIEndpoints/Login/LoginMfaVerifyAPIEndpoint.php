<?php

namespace WP_SMS\Services\OTP\RestAPIEndpoints\Login;

use WP_REST_Server;
use WP_REST_Response;
use WP_REST_Request;
use WP_Error;
use WP_SMS\Services\OTP\RestAPIEndpoints\Abstracts\RestAPIEndpointsAbstract;
use WP_SMS\Services\OTP\Helpers\UserHelper;
use WP_SMS\Services\OTP\Helpers\RedirectHelper;
use WP_SMS\Services\OTP\AuthChannel\MagicLink\MagicLinkService;

class LoginMfaVerifyAPIEndpoint extends RestAPIEndpointsAbstract
{
    public function registerRoutes(): void
    {
        register_rest_route('wpsms/v1', '/login/mfa-verify', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'handleRequest'],
            'permission_callback' => '__return_true',
            'args'                => $this->getArgs(),
        ]);
    }

    /**
     * Get endpoint arguments with WordPress validation
     */
    private function getArgs(): array
    {
        return [
            'flow_id' => [
                'required'          => true,
                'type'              => 'string',
                'description'       => 'Original flow ID from the login process',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'mfa_flow_id' => [
                'required'          => true,
                'type'              => 'string',
                'description'       => 'MFA flow ID from the MFA challenge',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'otp_code' => [
                'required'          => false,
                'type'              => 'string',
                'description'       => 'MFA OTP code',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'magic_token' => [
                'required'          => false,
                'type'              => 'string',
                'description'       => 'MFA magic link token',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'totp_code' => [
                'required'          => false,
                'type'              => 'string',
                'description'       => 'TOTP code from authenticator app',
                'sanitize_callback' => 'sanitize_text_field',
            ],
        ];
    }

    /**
     * Main request handler - clean entry point
     */
    public function handleRequest(WP_REST_Request $request)
    {
        try {
            // Extract request data
            $flowId = (string) $request->get_param('flow_id');
            $mfaFlowId = (string) $request->get_param('mfa_flow_id');
            $otpCode = (string) $request->get_param('otp_code');
            $magicToken = (string) $request->get_param('magic_token');
            $totpCode = (string) $request->get_param('totp_code');
            $ip = $this->getClientIp($request);

            // Rate limiting
            $rateLimitCheck = $this->checkRateLimits($mfaFlowId, $ip, 'mfa_verify');
            if (is_wp_error($rateLimitCheck)) {
                return $rateLimitCheck;
            }

            // Get user by MFA flow ID
            $user = $this->getUserByMfaFlowId($mfaFlowId);
            if (!$user) {
                $this->incrementRateLimits($mfaFlowId, $ip, 'mfa_verify');
                return $this->createErrorResponse(
                    'invalid_flow',
                    __('Invalid MFA session. Please restart login.', 'wp-sms'),
                    404
                );
            }

            // Verify the original flow ID matches
            $storedFlowId = get_user_meta($user->ID, 'wpsms_login_flow_id', true);
            if ($storedFlowId !== $flowId) {
                return $this->createErrorResponse(
                    'flow_mismatch',
                    __('Invalid session. Please restart login.', 'wp-sms'),
                    400
                );
            }

            // Determine verification method
            $verificationMethod = $this->determineVerificationMethod($otpCode, $magicToken, $totpCode);
            
            // Validate that required code/token is provided
            if ($verificationMethod === 'otp' && empty($otpCode)) {
                return $this->createErrorResponse(
                    'otp_code_required',
                    __('MFA OTP code is required', 'wp-sms'),
                    400
                );
            }
            
            if ($verificationMethod === 'magic' && empty($magicToken)) {
                return $this->createErrorResponse(
                    'magic_token_required',
                    __('MFA magic token is required', 'wp-sms'),
                    400
                );
            }
            
            if ($verificationMethod === 'totp' && empty($totpCode)) {
                return $this->createErrorResponse(
                    'totp_code_required',
                    __('TOTP code is required', 'wp-sms'),
                    400
                );
            }

            // Perform MFA verification
            $verificationSuccess = $this->performMfaVerification($mfaFlowId, $verificationMethod, $otpCode, $magicToken, $totpCode);
            if (!$verificationSuccess) {
                // Increment rate limits on failure
                $this->incrementRateLimits($mfaFlowId, $ip, 'mfa_verify');
                $this->logAuthEvent($mfaFlowId, 'mfa_challenge_verify', 'deny', $verificationMethod, $ip, null, ['user_id' => $user->ID]);
                return $this->createErrorResponse(
                    'verification_failed',
                    __('Invalid MFA code. Please try again.', 'wp-sms'),
                    401
                );
            }

            // MFA verification successful - complete login
            $authToken = $this->generateAuthToken($user->ID, $flowId);
            
            // Clean up flow IDs
            delete_user_meta($user->ID, 'wpsms_login_flow_id');
            delete_user_meta($user->ID, 'wpsms_mfa_flow_id');
            
            // Log events
            $this->logAuthEvent($mfaFlowId, 'mfa_challenge_verify', 'allow', $verificationMethod, $ip, null, ['user_id' => $user->ID]);
            $this->logAuthEvent($flowId, 'login_success', 'allow', 'system', $ip, null, ['user_id' => $user->ID]);
            $this->incrementRateLimits($mfaFlowId, $ip, 'mfa_verify');
            
            // Build login success response
            return $this->buildLoginSuccessResponse($user, $flowId, $authToken);

        } catch (\Exception $e) {
            return $this->handleException($e, 'mfaVerify');
        }
    }

    /**
     * Get user by MFA flow ID
     */
    private function getUserByMfaFlowId(string $mfaFlowId)
    {
        $users = get_users([
            'meta_key' => 'wpsms_mfa_flow_id',
            'meta_value' => $mfaFlowId,
            'number' => 1,
        ]);

        return !empty($users) ? $users[0] : null;
    }

    /**
     * Determine verification method
     */
    private function determineVerificationMethod(string $otpCode, string $magicToken, string $totpCode): string
    {
        if (!empty($totpCode)) return 'totp';
        if (!empty($magicToken)) return 'magic';
        return 'otp';
    }

    /**
     * Perform MFA verification
     */
    private function performMfaVerification(string $mfaFlowId, string $method, string $otpCode, string $magicToken, string $totpCode): bool
    {
        if ($method === 'totp') {
            // TODO: Implement TOTP validation
            return false;
        }
        
        if ($method === 'magic') {
            $magicService = new MagicLinkService();
            return $magicService->validate($mfaFlowId, $magicToken);
        }
        
        // OTP
        return $this->otpService->validate($mfaFlowId, $otpCode);
    }

    /**
     * Generate authentication token
     */
    private function generateAuthToken(int $userId, string $flowId): string
    {
        // Generate secure token for session
        $token = bin2hex(random_bytes(32));
        
        // Store token with expiration
        set_transient('wpsms_auth_token_' . $token, [
            'user_id' => $userId,
            'flow_id' => $flowId,
            'created_at' => time(),
        ], 3600); // 1 hour
        
        return $token;
    }

    /**
     * Build login success response
     */
    private function buildLoginSuccessResponse($user, string $flowId, string $authToken): WP_REST_Response
    {
        // Log the user into WordPress
        wp_clear_auth_cookie();
        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID, true); // true = remember me
        
        // Fire WordPress login action
        do_action('wp_login', $user->user_login, $user);
        
        // Get redirect URL
        $redirectTo = RedirectHelper::getRedirectToFromRequest();
        $redirectUrl = RedirectHelper::getLoginRedirectUrl($user, null, $redirectTo);
        
        $data = [
            'user_id' => $user->ID,
            'user_login' => $user->user_login,
            'user_email' => $user->user_email,
            'display_name' => $user->display_name,
            'auth_token' => $authToken,
            'next_step' => 'complete',
            'redirect_url' => $redirectUrl,
        ];

        return $this->createSuccessResponse($data, __('Login successful', 'wp-sms'));
    }
}

