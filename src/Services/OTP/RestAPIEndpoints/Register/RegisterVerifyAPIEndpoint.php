<?php

namespace WP_SMS\Services\OTP\RestAPIEndpoints\Register;

use WP_REST_Server;
use WP_REST_Response;
use WP_REST_Request;
use WP_Error;
use WP_SMS\Services\OTP\RestAPIEndpoints\Abstracts\RestAPIEndpointsAbstract;
use WP_SMS\Services\OTP\Helpers\UserHelper;
use WP_SMS\Services\OTP\Helpers\ChannelSettingsHelper;
use WP_SMS\Services\OTP\AuthChannel\MagicLink\MagicLinkService;

class RegisterVerifyAPIEndpoint extends RestAPIEndpointsAbstract
{
    public function registerRoutes(): void
    {
        register_rest_route('wpsms/v1', '/register/verify', [
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
                'description'       => 'Flow ID from the registration process',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'action' => [
                'required'          => false,
                'type'              => 'string',
                'description'       => 'Action to perform: "verify" or "skip"',
                'sanitize_callback' => 'sanitize_text_field',
                'default'           => 'verify',
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
            $action = (string) $request->get_param('action');
            $otpCode = (string) $request->get_param('otp_code');
            $magicToken = (string) $request->get_param('magic_token');
            $ip = $this->getClientIp($request);

            // Default action to verify
            $action = empty($action) ? 'verify' : strtolower($action);

            // Rate limiting
            $rateLimitCheck = $this->checkRateLimits($flowId, $ip, 'register_verify');
            if (is_wp_error($rateLimitCheck)) {
                return $rateLimitCheck;
            }

            // Get user by flow ID
            $user = UserHelper::getUserByFlowId($flowId);
            if (!$user) {
                return $this->createErrorResponse(
                    'user_not_found',
                    __('No pending user found for this flow', 'wp-sms'),
                    404
                );
            }

            // Get current identifier from meta
            $identifier = get_user_meta($user->ID, 'wpsms_identifier', true);
            if (empty($identifier)) {
                return $this->createErrorResponse(
                    'identifier_not_found',
                    __('No identifier found for this flow. Please restart verification.', 'wp-sms'),
                    400
                );
            }

            // Get identifier type
            $identifierType = UserHelper::getIdentifierType($identifier);

            // Ensure user is still pending
            if (!UserHelper::isPendingUser($user->ID)) {
                return $this->createErrorResponse(
                    'already_verified',
                    __('User has already been verified', 'wp-sms'),
                    409
                );
            }

            // Handle skip action
            if ($action === 'skip') {
                return $this->handleSkip($user->ID, $flowId, $identifierType, $ip);
            }

            // Handle verify action (default)
            return $this->handleVerify($user, $flowId, $identifier, $identifierType, $otpCode, $magicToken, $ip);

        } catch (\Exception $e) {
            return $this->handleException($e, 'verifyRegister');
        }
    }

    /**
     * Handle skip action
     */
    private function handleSkip(int $userId, string $flowId, string $identifierType, string $ip): WP_REST_Response
    {
        // Check if this identifier type can be skipped (not required)
        $requiredChannels = ChannelSettingsHelper::getRequiredChannels();
        if (isset($requiredChannels[$identifierType]) && $requiredChannels[$identifierType]['required']) {
            return $this->createErrorResponse(
                'cannot_skip_required',
                __('This identifier type is required and cannot be skipped', 'wp-sms'),
                400
            );
        }

        // Mark identifier as skipped
        $markSuccess = UserHelper::markIdentifierSkipped($userId, $identifierType);
        if (!$markSuccess) {
            return $this->createErrorResponse(
                'skip_mark_failed',
                __('Failed to mark identifier as skipped', 'wp-sms'),
                500
            );
        }

        // Evaluate completion status
        $completionData = $this->evaluateCompletionStatus($userId);
        
        // Log event and increment rate limits
        $this->logAuthEvent($flowId, 'register_skip', 'allow', $identifierType, $ip);
        $this->incrementRateLimits($flowId, $ip, 'register_verify');

        // Build response
        return $this->buildResponse($userId, $flowId, 'skip', $completionData);
    }

    /**
     * Handle verify action
     */
    private function handleVerify($user, string $flowId, string $identifier, string $identifierType, string $otpCode, string $magicToken, string $ip): WP_REST_Response
    {
        // Determine verification method
        $verificationMethod = $this->determineVerificationMethod($otpCode, $magicToken);
        
        // Validate that required code/token is provided
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

        // Perform verification
        $verificationSuccess = $this->performVerification($flowId, $verificationMethod, $otpCode, $magicToken);
        if (!$verificationSuccess) {
            // Increment rate limits on failure
            $this->incrementRateLimits($flowId, $ip, 'register_verify');
            return $this->createErrorResponse(
                'verification_failed',
                __('Invalid or expired verification code/link', 'wp-sms'),
                400
            );
        }

        // Mark identifier as verified
        $markSuccess = UserHelper::markIdentifierVerified($user->ID, $identifier);
        if (!$markSuccess) {
            return $this->createErrorResponse(
                'verification_mark_failed',
                __('Failed to mark identifier as verified', 'wp-sms'),
                500
            );
        }

        // Evaluate completion status
        $completionData = $this->evaluateCompletionStatus($user->ID);
        
        // Log event and increment rate limits
        $this->logAuthEvent($flowId, 'register_verify', 'allow', $verificationMethod, $ip);
        $this->incrementRateLimits($flowId, $ip, 'register_verify');

        // Build response
        return $this->buildResponse($user->ID, $flowId, $verificationMethod, $completionData);
    }

    /**
     * Determine verification method based on provided parameters
     */
    private function determineVerificationMethod(string $otpCode, string $magicToken): string
    {
        // If magic token provided, prefer magic; else OTP
        return (!empty($magicToken)) ? 'magic' : 'otp';
    }

    /**
     * Perform verification based on method
     *
     * @return bool True if verification successful, false otherwise
     */
    private function performVerification(string $flowId, string $method, string $otpCode, string $magicToken): bool
    {
        if ($method === 'magic') {
            $magicService = new MagicLinkService();
            $result = $magicService->validate($flowId, $magicToken);
            // validate() returns token string on success, null on failure
            return !empty($result);
        } else {
            $result = $this->otpService->validate($flowId, $otpCode);
            // validate() returns code string on success, null on failure
            return !empty($result);
        }
    }

    /**
     * Evaluate completion status and activate user if needed
     */
    private function evaluateCompletionStatus(int $userId): array
    {
        $requiredChannels = ChannelSettingsHelper::getRequiredChannels();
        $allVerified = UserHelper::areAllRequiredIdentifiersVerified($userId, $requiredChannels);

        if ($allVerified) {
            // Activate user
            $activated = UserHelper::activateUser($userId);
            if (!$activated) {
                throw new \Exception(__('Failed to activate user', 'wp-sms'), 500);
            }

            return [
                'status' => 'verified',
                'next_step' => 'complete',
                'message' => __('Registration completed successfully', 'wp-sms'),
                'next_required_identifier' => null,
            ];
        } else {
            // More identifiers required
            $nextRequired = UserHelper::getNextRequiredIdentifier($userId, $requiredChannels);
            return [
                'status' => 'partial_verified',
                'next_step' => 'verify_next',
                'message' => __('Identifier verified. Additional verification required.', 'wp-sms'),
                'next_required_identifier' => $nextRequired,
            ];
        }
    }

    /**
     * Build success response
     */
    private function buildResponse(int $userId, string $flowId, string $verificationMethod, array $completionData): WP_REST_Response
    {
        $verifiedIdentifiers = UserHelper::getVerifiedIdentifiers($userId);
        $skippedIdentifiers = UserHelper::getSkippedIdentifiers($userId);
        
        // Get user object
        $user = get_user_by('id', $userId);

        $data = [
            'user_id' => $userId,
            'flow_id' => $flowId,
            'status' => $completionData['status'],
            'next_step' => $completionData['next_step'],
            'next_required_identifier' => $completionData['next_required_identifier'],
            'verified_identifiers' => $verifiedIdentifiers,
            'skipped_identifiers' => $skippedIdentifiers,
            'verified_via' => $verificationMethod,
            'registration_complete' => $completionData['status'] === 'complete',
        ];
        
        // Add redirect URL if registration is complete
        if ($completionData['status'] === 'complete' && $user) {
            // Check if auto-login after registration is enabled
            if (RedirectHelper::isAutoLoginAfterRegisterEnabled()) {
                // Log the user into WordPress
                wp_clear_auth_cookie();
                wp_set_current_user($user->ID);
                wp_set_auth_cookie($user->ID, true);
                
                // Fire WordPress login action
                do_action('wp_login', $user->user_login, $user);
            }
            
            $redirectUrl = RedirectHelper::getRegisterRedirectUrl($user);
            $data['redirect_url'] = $redirectUrl;
        }

        return $this->createSuccessResponse($data, $completionData['message']);
    }
}
