<?php

namespace WP_SMS\Services\OTP\RestAPIEndpoints\PasswordReset;

use WP_REST_Server;
use WP_REST_Response;
use WP_REST_Request;
use WP_Error;
use WP_SMS\Services\OTP\RestAPIEndpoints\Abstracts\RestAPIEndpointsAbstract;
use WP_SMS\Services\OTP\Helpers\PasswordResetHelper;
use WP_SMS\Services\OTP\Helpers\UserHelper;
use WP_SMS\Services\OTP\AuthChannel\OTP\OtpService;
use WP_SMS\Services\OTP\AuthChannel\MagicLink\MagicLinkService;

/**
 * Password Reset Init API Endpoint
 *
 * POST /wp-json/wpsms/v1/password-reset/init
 *
 * Initiates password reset by sending OTP/Magic Link to user's verified identifier
 */
class PasswordResetInitAPIEndpoint extends RestAPIEndpointsAbstract
{
    public function registerRoutes(): void
    {
        register_rest_route('wpsms/v1', '/password-reset/init', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'handleRequest'],
            'permission_callback' => '__return_true',
            'args'                => $this->getArgs(),
        ]);
    }

    public function getArgs(): array
    {
        return [
            'identifier' => [
                'required'          => true,
                'type'              => 'string',
                'description'       => __('Email address or phone number', 'wp-sms'),
                'sanitize_callback' => 'sanitize_text_field',
                'validate_callback' => function ($param) {
                    if (empty($param)) {
                        return new WP_Error(
                            'identifier_required',
                            __('Email or phone number is required', 'wp-sms')
                        );
                    }
                    return true;
                },
            ],
            'auth_method' => [
                'required'          => true,
                'type'              => 'string',
                'description'       => __('Authentication method: otp or magic', 'wp-sms'),
                'enum'              => ['otp', 'magic'],
                'default'           => 'otp',
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
            $identifier = (string) $request->get_param('identifier');
            $authMethod = (string) $request->get_param('auth_method');
            $ip = $this->getClientIp($request);

            // Validate identifier type
            $identifierType = UserHelper::getIdentifierType($identifier);
            if ($identifierType === 'unknown') {
                return $this->createErrorResponse(
                    'invalid_identifier',
                    __('Invalid email or phone number format', 'wp-sms'),
                    400
                );
            }

            // Check if identifier type is allowed
            if (!PasswordResetHelper::isIdentifierAllowed($identifierType)) {
                return $this->createErrorResponse(
                    'identifier_not_allowed',
                    __('This type of identifier is not allowed for password reset', 'wp-sms'),
                    403
                );
            }

            // Rate limiting
            $rateLimitCheck = $this->checkRateLimits($identifier, $ip, 'password_reset_init');
            if (is_wp_error($rateLimitCheck)) {
                $this->logAuthEvent(
                    wp_generate_password(32, false),
                    'password_reset_init',
                    'deny',
                    $identifierType,
                    $ip,
                    null,
                    ['reason' => 'rate_limited']
                );
                return $this->createErrorResponse(
                    'rate_limited',
                    $rateLimitCheck->get_error_message(),
                    429
                );
            }

            // Find user by identifier
            $user = PasswordResetHelper::findUserByIdentifier($identifier);
            
            if (!$user) {
                // Security: Don't reveal if user exists
                // Return success but don't actually send anything
                $flowId = wp_generate_password(32, false);
                $this->logAuthEvent($flowId, 'password_reset_init', 'deny', $identifierType, $ip, null, ['reason' => 'user_not_found']);
                
                return new WP_REST_Response([
                    'success' => true,
                    'message' => __('If an account exists with this identifier, you will receive a verification code', 'wp-sms'),
                ], 200);
            }

            // Generate flow ID
            $flowId = wp_generate_password(32, false);
            
            // Store flow ID in user meta
            update_user_meta($user->ID, 'wpsms_password_reset_flow_id', $flowId);
            update_user_meta($user->ID, 'wpsms_password_reset_identifier', $identifier);
            update_user_meta($user->ID, 'wpsms_password_reset_started_at', time());

            // Send verification based on method
            $sendResult = $this->sendVerification($flowId, $identifier, $identifierType, $authMethod);
            
            if (!$sendResult) {
                return $this->createErrorResponse(
                    'send_failed',
                    __('Failed to send verification. Please try again', 'wp-sms'),
                    500
                );
            }

            // Log event
            $this->logAuthEvent($flowId, 'password_reset_init', 'allow', $identifierType, $ip, null, ['user_id' => $user->ID]);
            $this->incrementRateLimits($flowId, $ip, 'password_reset_init');

            return new WP_REST_Response([
                'success' => true,
                'data' => [
                    'flow_id' => $flowId,
                    'identifier_masked' => self::maskIdentifier($identifier, $identifierType),
                    'auth_method' => $authMethod,
                    'expires_in_minutes' => PasswordResetHelper::getTokenExpiry(),
                ],
                'message' => __('Verification code sent successfully', 'wp-sms'),
            ], 200);

        } catch (\Exception $e) {
            return $this->handleException($e, 'passwordResetInit');
        }
    }

    /**
     * Send verification code/link
     */
    private function sendVerification(string $flowId, string $identifier, string $identifierType, string $authMethod): bool
    {
        if ($authMethod === 'magic') {
            $magicService = new MagicLinkService();
            $result = $magicService->send($flowId, $identifier, $identifierType, 'password_reset');
            return !empty($result);
        } else {
            $result = $this->otpService->send($flowId, $identifier, $identifierType, 'password_reset');
            return !empty($result);
        }
    }

    /**
     * Mask identifier
     */
    private function maskIdentifier(string $identifier, string $type): string
    {
        if ($type === 'email') {
            $parts = explode('@', $identifier);
            if (count($parts) !== 2) return $identifier;
            
            $username = $parts[0];
            $domain = $parts[1];
            $maskedUsername = strlen($username) <= 2 
                ? $username 
                : substr($username, 0, 2) . str_repeat('*', strlen($username) - 2);
            
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

