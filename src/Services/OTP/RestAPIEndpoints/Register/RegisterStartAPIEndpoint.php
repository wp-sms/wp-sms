<?php

namespace WP_SMS\Services\OTP\RestAPIEndpoints\Register;

use WP_REST_Server;
use WP_REST_Response;
use WP_REST_Request;
use WP_Error;
use WP_SMS\Services\OTP\RestAPIEndpoints\Abstracts\RestAPIEndpointsAbstract;
use WP_SMS\Services\OTP\Helpers\UserHelper;

class RegisterStartAPIEndpoint extends RestAPIEndpointsAbstract
{

    /**
     * Register REST API routes for OTP endpoints.
     */
    public function registerRoutes(): void
    {
        register_rest_route('wpsms/v1', '/register/start', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'startRegister'],
            'permission_callback' => '__return_true',
            'args'                => $this->getStartRegisterArgs(),
        ]);
    }

    /**
     * Get arguments for the start register endpoint
     *
     * @return array
     */
    private function getStartRegisterArgs()
    {
        return [
            'identifier' => [
                'required'          => true,
                'type'              => 'string',
                'description'       => 'Identifier for registration (phone number, email, etc.)',
                'sanitize_callback' => 'sanitize_text_field',
                'validate_callback' => [$this, 'validateIdentifier'],
            ],
        ];
    }


    /**
     * Initialize registration endpoint - handles POST request for registration initiation
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function startRegister(WP_REST_Request $request)
    {
        try {
            // Get and validate request parameters
            $identifier = $request->get_param('identifier');
            $ip = $this->getClientIp($request);

            // Check rate limiting
            $rateLimitResult = $this->checkRateLimits($identifier, $ip, 'register_init');
            if (is_wp_error($rateLimitResult)) {
                return $rateLimitResult;
            }

            // Generate flow ID
            $flowId = uniqid('flow_', true);
            
            // Create or get pending user
            $pendingUser = UserHelper::createPendingUser($identifier, [
                'flow_id' => $flowId
            ]);
            
            if (!$pendingUser) {
                return $this->createErrorResponse(
                    'user_creation_failed',
                    __('Unable to create pending user', 'wp-sms'),
                    500
                );
            }
            
            try {
                $otpSession = $this->otpService->generate(
                    $flowId,
                    $identifier
                );
            } catch (\Exception $e) {
                // Handle unexpired session exception
                if (strpos($e->getMessage(), 'unexpired OTP session') !== false) {
                    return $this->createErrorResponse(
                        'session_exists',
                        $e->getMessage(),
                        409 // Conflict status code
                    );
                }
                // Re-throw other exceptions
                throw $e;
            }

            // Send OTP via the service (it will determine the best channel)
            $sendResult = $this->otpService->sendOTP($identifier, $otpSession->code, $otpSession->channel);

            if (!$sendResult['success']) {
                return $this->createErrorResponse(
                    'otp_send_failed',
                    $sendResult['error'] ?? __('Failed to send OTP', 'wp-sms'),
                    500
                );
            }

            // Log the auth event
            $this->logAuthEvent($flowId, 'register_init', 'allow', $sendResult['channel_used'], $ip);

            // Increment rate limits
            $this->incrementRateLimits($identifier, $ip, 'register_init');

            // Return success response
            return $this->createSuccessResponse([
                'flow_id' => $otpSession->flow_id,
                'user_id' => $pendingUser->ID,
                'identifier' => $identifier,
                'channel_used' => $sendResult['channel_used'],
                'next_step' => 'verify',
            ], __('Registration initiated successfully', 'wp-sms'));
            
        } catch (\Exception $e) {
            return $this->handleException($e, 'initRegister');
        }
    }

}