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

class RegisterAddIdentifierAPIEndpoint extends RestAPIEndpointsAbstract
{

    /**
     * Register REST API routes for adding additional identifiers.
     */
    public function registerRoutes(): void
    {
        register_rest_route('wpsms/v1', '/register/add-identifier', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'addIdentifier'],
            'permission_callback' => '__return_true',
            'args'                => $this->getAddIdentifierArgs(),
        ]);
    }

    /**
     * Get arguments for the add identifier endpoint
     *
     * @return array
     */
    private function getAddIdentifierArgs()
    {
        return [
            'flow_id' => [
                'required'          => true,
                'type'              => 'string',
                'description'       => 'Flow ID from the registration process',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'identifier' => [
                'required'          => true,
                'type'              => 'string',
                'description'       => 'New identifier to add (email or phone number)',
                'sanitize_callback' => 'sanitize_text_field',
            ],
        ];
    }


    /**
     * Add additional identifier and create OTP session
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function addIdentifier(WP_REST_Request $request)
    {
        try {
            // Get and validate request parameters
            $flowId = $request->get_param('flow_id');
            $newIdentifier = $request->get_param('identifier');
            $ip = $this->getClientIp($request);

            // Check rate limiting
            $rateLimitResult = $this->checkRateLimits($newIdentifier, $ip, 'register_add_identifier');
            if (is_wp_error($rateLimitResult)) {
                return $rateLimitResult;
            }

            // Get user by flow_id
            $user = UserHelper::getUserByFlowId($flowId);
            if (!$user) {
                return $this->createErrorResponse(
                    'user_not_found',
                    __('No user found for this flow', 'wp-sms'),
                    404
                );
            }

            // Check if user is still pending
            if (!UserHelper::isPendingUser($user->ID)) {
                return $this->createErrorResponse(
                    'user_already_verified',
                    __('User has already been verified', 'wp-sms'),
                    409
                );
            }

            // Get required channel settings
            $requiredChannelSettings = ChannelSettingsHelper::getRequiredChannels();
            
            // Check if this identifier type is required
            $identifierType = UserHelper::getIdentifierType($newIdentifier);
            if (!isset($requiredChannelSettings[$identifierType]) || !$requiredChannelSettings[$identifierType]['required']) {
                return $this->createErrorResponse(
                    'identifier_not_required',
                    __('This identifier type is not required', 'wp-sms'),
                    400
                );
            }

            // Check if this identifier type is already verified
            $verifiedIdentifiers = UserHelper::getVerifiedIdentifiers($user->ID);
            if (isset($verifiedIdentifiers[$identifierType])) {
                return $this->createErrorResponse(
                    'identifier_already_verified',
                    __('This identifier type has already been verified', 'wp-sms'),
                    409
                );
            }

            // Check if identifier is already in use by another user
            $existingUser = UserHelper::getUserByIdentifier($newIdentifier);
            if ($existingUser && $existingUser->ID !== $user->ID) {
                return $this->createErrorResponse(
                    'identifier_in_use',
                    __('This identifier is already in use by another user', 'wp-sms'),
                    409
                );
            }

            // Generate new flow ID for this identifier
            $newFlowId = uniqid('flow_', true);

            // Update user's current identifier to the new one
            $updateResult = UserHelper::updateUserMeta($user->ID, [
                'identifier' => $newIdentifier,
                'identifier_type' => $identifierType,
                'flow_id' => $newFlowId
            ]);

            if (!$updateResult) {
                return $this->createErrorResponse(
                    'identifier_update_failed',
                    __('Failed to update user identifier', 'wp-sms'),
                    500
                );
            }

            // Generate OTP for the new identifier
            try {
                $otpSession = $this->otpService->generate($newFlowId, $newIdentifier);
            } catch (\Exception $e) {
                // Handle unexpired session exception
                if (strpos($e->getMessage(), 'unexpired OTP session') !== false) {
                    return $this->createErrorResponse(
                        'session_exists',
                        $e->getMessage(),
                        409
                    );
                }
                // Re-throw other exceptions
                throw $e;
            }

            // Send OTP via the service
            $sendResult = $this->otpService->sendOTP($newIdentifier, $otpSession->code, $otpSession->channel);

            if (!$sendResult['success']) {
                return $this->createErrorResponse(
                    'otp_send_failed',
                    $sendResult['error'] ?? __('Failed to send OTP', 'wp-sms'),
                    500
                );
            }

            // Log the auth event
            $this->logAuthEvent($newFlowId, 'register_add_identifier', 'allow', $sendResult['channel_used'], $ip);

            // Increment rate limits
            $this->incrementRateLimits($newIdentifier, $ip, 'register_add_identifier');

            // Get next required identifier after this one
            $nextRequiredIdentifier = UserHelper::getNextRequiredIdentifier($user->ID, $requiredChannelSettings);

            // Return success response
            return $this->createSuccessResponse([
                'user_id' => $user->ID,
                'flow_id' => $newFlowId,
                'identifier' => $newIdentifier,
                'identifier_type' => $identifierType,
                'channel_used' => $sendResult['channel_used'],
                'verified_identifiers' => UserHelper::getVerifiedIdentifiers($user->ID),
                'next_required_identifier' => $nextRequiredIdentifier,
                'next_step' => $nextRequiredIdentifier ? 'verify_next' : 'verify_current',
            ], __('Identifier added successfully. Please verify the OTP.', 'wp-sms'));
            
        } catch (\Exception $e) {
            return $this->handleException($e, 'addIdentifier');
        }
    }
}
