<?php

namespace WP_SMS\Services\OTP\RestAPIEndpoints\Login;

use WP_REST_Server;
use WP_REST_Response;
use WP_REST_Request;
use WP_Error;
use WP_SMS\Services\OTP\RestAPIEndpoints\Abstracts\RestAPIEndpointsAbstract;
use WP_SMS\Services\OTP\Helpers\UserHelper;
use WP_SMS\Services\OTP\Helpers\RedirectHelper;
use WP_SMS\Services\OTP\Models\IdentifierModel;
use WP_SMS\Services\OTP\AuthChannel\MagicLink\MagicLinkService;

class LoginVerifyAPIEndpoint extends RestAPIEndpointsAbstract
{
    public function registerRoutes(): void
    {
        register_rest_route('wpsms/v1', '/login/verify', [
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
                'description'       => 'Flow ID from the login process',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'otp_code' => [
                'required'          => false,
                'type'              => 'string',
                'description'       => 'OTP code received by the user',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'magic_token' => [
                'required'          => false,
                'type'              => 'string',
                'description'       => 'Magic link token received by the user',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'password' => [
                'required'          => false,
                'type'              => 'string',
                'description'       => 'User password',
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
            $otpCode = (string) $request->get_param('otp_code');
            $magicToken = (string) $request->get_param('magic_token');
            $password = (string) $request->get_param('password');
            $ip = $this->getClientIp($request);

            // Rate limiting
            $rateLimitCheck = $this->checkRateLimits($flowId, $ip, 'login_verify');
            if (is_wp_error($rateLimitCheck)) {
                return $rateLimitCheck;
            }

            // Get user by flow ID
            $user = $this->getUserByLoginFlowId($flowId);
            if (!$user) {
                // Increment rate limit on failure
                $this->incrementRateLimits($flowId, $ip, 'login_verify');
                return $this->createErrorResponse(
                    'invalid_flow',
                    __('Invalid login session. Please start login again.', 'wp-sms'),
                    404
                );
            }

            // Determine verification method
            $verificationMethod = $this->determineVerificationMethod($otpCode, $magicToken, $password);
            
            // Validate that required code/token/password is provided
            if ($verificationMethod === 'otp' && empty($otpCode)) {
                return $this->createErrorResponse(
                    'otp_code_required',
                    __('OTP code is required', 'wp-sms'),
                    400
                );
            }
            
            if ($verificationMethod === 'magic' && empty($magicToken)) {
                return $this->createErrorResponse(
                    'magic_token_required',
                    __('Magic token is required', 'wp-sms'),
                    400
                );
            }
            
            if ($verificationMethod === 'password' && empty($password)) {
                return $this->createErrorResponse(
                    'password_required',
                    __('Password is required', 'wp-sms'),
                    400
                );
            }

            // Perform verification
            $verificationSuccess = $this->performVerification($user, $flowId, $verificationMethod, $otpCode, $magicToken, $password);
            if (!$verificationSuccess) {
                // Increment rate limits on failure
                $this->incrementRateLimits($flowId, $ip, 'login_verify');
                $this->logAuthEvent($flowId, 'login_verify', 'deny', $verificationMethod, $ip, null, ['user_id' => $user->ID]);
                return $this->createErrorResponse(
                    'verification_failed',
                    __('Invalid credentials. Please try again.', 'wp-sms'),
                    401
                );
            }

            // Check if MFA is required
            $mfaRequired = $this->isMfaRequired($user->ID);
            
            // Log successful primary auth
            $this->logAuthEvent($flowId, 'login_verify', 'allow', $verificationMethod, $ip, null, ['user_id' => $user->ID]);
            $this->incrementRateLimits($flowId, $ip, 'login_verify');

            // Build response
            if ($mfaRequired) {
                return $this->buildMfaRequiredResponse($user, $flowId);
            } else {
                return $this->buildLoginSuccessResponse($user, $flowId);
            }

        } catch (\Exception $e) {
            return $this->handleException($e, 'loginVerify');
        }
    }

    /**
     * Get user by login flow ID
     */
    private function getUserByLoginFlowId(string $flowId)
    {
        $users = get_users([
            'meta_key' => 'wpsms_login_flow_id',
            'meta_value' => $flowId,
            'number' => 1,
        ]);

        return !empty($users) ? $users[0] : null;
    }

    /**
     * Determine verification method based on provided parameters
     */
    private function determineVerificationMethod(string $otpCode, string $magicToken, string $password): string
    {
        if (!empty($password)) return 'password';
        if (!empty($magicToken)) return 'magic';
        return 'otp';
    }

    /**
     * Perform verification based on method
     */
    private function performVerification($user, string $flowId, string $method, string $otpCode, string $magicToken, string $password): bool
    {
        if ($method === 'password') {
            return wp_check_password($password, $user->data->user_pass, $user->ID);
        }
        
        if ($method === 'magic') {
            $magicService = new MagicLinkService();
            return $magicService->validate($flowId, $magicToken);
        }
        
        // OTP
        return $this->otpService->validate($flowId, $otpCode);
    }

    /**
     * Check if MFA is required for user
     */
    private function isMfaRequired(int $userId): bool
    {
        // Check if user has any MFA factors enrolled
        $identifierModel = new IdentifierModel();
        $identifiers = $identifierModel->getAllByUserId($userId);
        
        // If user has multiple verified identifiers, MFA is available
        return count($identifiers) >= 2;
    }

    /**
     * Build MFA required response
     */
    private function buildMfaRequiredResponse($user, string $flowId): WP_REST_Response
    {
        // Get available MFA methods for this user
        $mfaOptions = $this->getAvailableMfaMethods($user->ID);
        
        $data = [
            'flow_id' => $flowId,
            'next_step' => 'mfa_challenge',
            'mfa_required' => true,
            'mfa_options' => $mfaOptions,
            'message' => __('Primary authentication successful. MFA verification required.', 'wp-sms'),
        ];

        return $this->createSuccessResponse($data, __('MFA verification required', 'wp-sms'));
    }

    /**
     * Build login success response
     */
    private function buildLoginSuccessResponse($user, string $flowId): WP_REST_Response
    {
        // Log the user into WordPress
        wp_clear_auth_cookie();
        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID, true); // true = remember me
        
        // Fire WordPress login action
        do_action('wp_login', $user->user_login, $user);
        
        // Generate auth token for API reference
        $authToken = $this->generateAuthToken($user->ID, $flowId);
        
        // Get redirect URL
        $redirectTo = RedirectHelper::getRedirectToFromRequest();
        $redirectUrl = RedirectHelper::getLoginRedirectUrl($user, null, $redirectTo);
        
        // Clean up flow ID
        delete_user_meta($user->ID, 'wpsms_login_flow_id');
        
        // Log login success
        $this->logAuthEvent($flowId, 'login_success', 'allow', 'system', $this->getClientIp($GLOBALS['wp']->request ?? new \stdClass()), null, ['user_id' => $user->ID]);
        
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

    /**
     * Get available MFA methods for user
     */
    private function getAvailableMfaMethods(int $userId): array
    {
        $identifierModel = new IdentifierModel();
        $identifiers = $identifierModel->getAllByUserId($userId);
        
        $mfaOptions = [];
        
        foreach ($identifiers as $identifier) {
            $factorType = $identifier['factor_type'];
            
            // Skip if same as primary auth channel (stored in flow meta)
            // For now, include all verified identifiers as potential MFA options
            
            if ($factorType === 'email' || $factorType === 'phone') {
                $mfaOptions[] = [
                    'type' => $factorType,
                    'masked' => $this->maskIdentifier($identifier['factor_value'], $factorType),
                    'methods' => ['otp', 'magic'], // Based on MFA settings
                ];
            }
            
            if ($factorType === 'totp') {
                $mfaOptions[] = [
                    'type' => 'totp',
                    'methods' => ['totp'],
                ];
            }
            
            if ($factorType === 'webauthn') {
                $mfaOptions[] = [
                    'type' => 'biometric',
                    'methods' => ['webauthn'],
                ];
            }
        }
        
        return $mfaOptions;
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
     * Mask identifier for response
     */
    private function maskIdentifier(string $identifier, string $type): string
    {
        if ($type === 'email') {
            $parts = explode('@', $identifier);
            if (count($parts) !== 2) return $identifier;
            $username = $parts[0];
            $domain = $parts[1];
            $maskedUsername = (strlen($username) <= 2) ? $username : substr($username, 0, 2) . str_repeat('*', strlen($username) - 2);
            return $maskedUsername . '@' . $domain;
        }
        
        if ($type === 'phone') {
            $len = strlen($identifier);
            if ($len <= 4) return str_repeat('*', $len);
            return substr($identifier, 0, 3) . str_repeat('*', max(0, $len - 6)) . substr($identifier, -3);
        }
        
        return $identifier;
    }
}

