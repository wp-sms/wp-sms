<?php

namespace WP_SMS\Services\OTP\RestAPIEndpoints\Register;

use WP_REST_Server;
use WP_REST_Response;
use WP_REST_Request;
use WP_Error;
use WP_SMS\Services\OTP\RestAPIEndpoints\Abstracts\RestAPIEndpointsAbstract;
use WP_SMS\Services\OTP\AuthChannel\OTP\OtpService;
use WP_SMS\Services\OTP\Helpers\UserHelper;
use WP_SMS\Services\OTP\Helpers\ChannelSettingsHelper;
class RegisterVerifyAPIEndpoint extends RestAPIEndpointsAbstract
{

    /**
     * Register REST API routes for OTP verification endpoints.
     */
    public function registerRoutes(): void
    {
        register_rest_route('wpsms/v1', '/register/verify', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'verifyRegister'],
            'permission_callback' => '__return_true',
            'args'                => $this->getVerifyRegisterArgs(),
        ]);
    }

    /**
     * Get arguments for the verify register endpoint
     *
     * @return array
     */
    private function getVerifyRegisterArgs()
    {
        return [
            'flow_id' => [
                'required'          => true,
                'type'              => 'string',
                'description'       => 'Flow ID from the registration start process',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'otp_code' => [
                'required'          => true,
                'type'              => 'string',
                'description'       => 'OTP code received by the user',
                'sanitize_callback' => 'sanitize_text_field',
            ],
        ];
    }

   
    /**
     * Verify OTP and complete registration
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function verifyRegister(WP_REST_Request $request)
    {
        try {
            // Get and validate request parameters
            $flowId = $request->get_param('flow_id');
            $otpCode = $request->get_param('otp_code');
            $ip = $this->getClientIp($request);

            // Check rate limiting
            $rateLimitResult = $this->checkRateLimits($flowId, $ip, 'register_verify');
            if (is_wp_error($rateLimitResult)) {
                return $rateLimitResult;
            }

            // Get pending user by flow_id
            $pendingUser = UserHelper::getUserByFlowId($flowId);
            if (!$pendingUser) {
                return $this->createErrorResponse(
                    'user_not_found',
                    __('No pending user found for this flow', 'wp-sms'),
                    404
                );
            }

            // Check if user is still pending
            if (!UserHelper::isPendingUser($pendingUser->ID)) {
                return $this->createErrorResponse(
                    'user_already_verified',
                    __('User has already been verified', 'wp-sms'),
                    409
                );
            }

            // Validate OTP
            $isValidOtp = $this->otpService->validate($flowId, $otpCode);
            if (!$isValidOtp) {
                // Increment rate limits for failed attempt
                $this->incrementRateLimits($flowId, $ip, 'register_verify');
                
                return $this->createErrorResponse(
                    'invalid_otp',
                    __('Invalid or expired OTP code', 'wp-sms'),
                    400
                );
            }


            // Get required channel settings
            $requiredChannelSettings = ChannelSettingsHelper::getRequiredChannels();
            
            // Get the current identifier from the user
            $currentIdentifier = get_user_meta($pendingUser->ID, 'wpsms_identifier', true);
            
            // Mark this identifier as verified
            $verificationResult = UserHelper::markIdentifierVerified($pendingUser->ID, $currentIdentifier);
            if (!$verificationResult) {
                return $this->createErrorResponse(
                    'verification_failed',
                    __('Failed to mark identifier as verified', 'wp-sms'),
                    500
                );
            }

            // Check if all required identifiers are verified
            $allVerified = UserHelper::areAllRequiredIdentifiersVerified($pendingUser->ID, $requiredChannelSettings);
            if ($allVerified) {
                // All required identifiers are verified, activate the user
                $activationResult = UserHelper::activateUser($pendingUser->ID);
                if (!$activationResult) {
                    return $this->createErrorResponse(
                        'activation_failed',
                        __('Failed to activate user', 'wp-sms'),
                        500
                    );
                }
                
                $nextStep = 'complete';
                $status = 'verified';
                $message = __('Registration completed successfully', 'wp-sms');
            } else {
                // More identifiers need to be verified
                $nextRequiredIdentifier = UserHelper::getNextRequiredIdentifier($pendingUser->ID, $requiredChannelSettings);
                $nextStep = 'retrive_next';
                $status = 'partial_verified';
                $message = __('Identifier verified. Additional verification required.', 'wp-sms');
            }

            // Log the auth event
            $this->logAuthEvent($flowId, 'register_verify', 'allow', 'otp', $ip);

            // Increment rate limits for successful verification
            $this->incrementRateLimits($flowId, $ip, 'register_verify');

            // Prepare response data
            $responseData = [
                'user_id' => $pendingUser->ID,
                'flow_id' => $flowId,
                'status' => $status,
                'next_step' => $nextStep,
                'next_required_identifier' => $nextRequiredIdentifier,
                'verified_identifiers' => UserHelper::getVerifiedIdentifiers($pendingUser->ID),
            ];

            // Add next step information if more verification is needed
            if ($nextStep === 'verify_next') {
                $responseData['next_required_identifier'] = $nextRequiredIdentifier;
                $responseData['remaining_required'] = array_filter($requiredChannelSettings, function($settings, $channel) use ($pendingUser) {
                    return $settings['required'] && !isset(UserHelper::getVerifiedIdentifiers($pendingUser->ID)[$channel]);
                }, ARRAY_FILTER_USE_BOTH);
            }

            // Return success response
            return $this->createSuccessResponse($responseData, $message);
            
        } catch (\Exception $e) {
            return $this->handleException($e, 'verifyRegister');
        }
    }
}
