<?php

namespace WP_SMS\Services\OTP\RestAPIEndpoints\Register;

use WP_REST_Server;
use WP_REST_Response;
use WP_REST_Request;
use WP_Error;
use WP_SMS\Services\OTP\RestAPIEndpoints\Abstracts\RestAPIEndpointsAbstract;
use WP_SMS\Services\OTP\Helpers\UserHelper;
use WP_SMS\Services\OTP\Models\OtpSessionModel;
use WP_SMS\Services\OTP\Models\IdentifierModel;

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
            // 1. Parse & normalize
            $identifier = $request->get_param('identifier');
            $ip = $this->getClientIp($request);
            
            $identifierType = UserHelper::getIdentifierType($identifier);
            $identifierMasked = $this->maskIdentifier($identifier, $identifierType);

            // 2. Rate limit
            $rateLimitResult = $this->checkRateLimits($identifier, $ip, 'register_init');
            if (is_wp_error($rateLimitResult)) {
                return $rateLimitResult;
            }

            // 3. Availability check
            if ($this->isIdentifierUnavailable($identifier)) {
                return $this->createErrorResponse(
                    'identifier_unavailable',
                    __('This identifier is already in use.', 'wp-sms'),
                    409
                );
            }

            // 3.1. Additional email check for existing WordPress users
            if ($identifierType === 'email') {
                $existingWpUser = get_user_by('email', $identifier);
                if ($existingWpUser) {
                    return $this->createErrorResponse(
                        'email_already_exists',
                        __('An account with this email already exists. Please try logging in instead.', 'wp-sms'),
                        409
                    );
                }
            }

            // 4. Pending user handling
            $pendingUser = $this->getOrCreatePendingUser($identifier);
            if (!$pendingUser) {
                return $this->createErrorResponse(
                    'user_creation_failed',
                    __('Unable to create or retrieve pending user', 'wp-sms'),
                    500
                );
            }

            $flowId = UserHelper::getUserFlowId($pendingUser->ID);

            // 5. OTP session lookup (reuse-first)
            $existingOtpSession = $this->getExistingOtpSession($flowId);
            $otpSession = null;
            $isNewSession = false;
            if ($existingOtpSession) {
                // Reuse existing session - do not send OTP
                $otpSession = $existingOtpSession;
                $isNewSession = false;
            } else {
                // Create new session
                $otpSession = $this->createNewOtpSession($flowId, $identifier);
                if (!$otpSession) {
                    return $this->createErrorResponse(
                        'session_creation_failed',
                        __('Unable to create OTP session', 'wp-sms'),
                        500
                    );
                }
                $isNewSession = true;
            }

            // 6. Send OTP only if it's a new session
            if ($isNewSession) {
                $sendResult = $this->otpService->sendOTP($identifier, $otpSession['code'], $otpSession['channel']);
            } else {
                // For existing session, just return success without sending
                $sendResult = ['success' => true, 'channel_used' => $otpSession['channel']];
            }
            
            if (!$sendResult['success']) {
                return $this->createErrorResponse(
                    'otp_send_failed',
                    $sendResult['error'] ?? __('Failed to send OTP', 'wp-sms'),
                    500
                );
            }

            // 7. Log + counters
            $this->logAuthEvent($flowId, 'register_init', 'allow', $sendResult['channel_used'], $ip);
            $this->incrementRateLimits($identifier, $ip, 'register_init');

            // 8. Success
            $successMessage = $isNewSession 
                ? __('Registration initiated successfully', 'wp-sms')
                : __('Registration reinitiated successfully', 'wp-sms');
                
            return $this->createSuccessResponse([
                'flow_id' => $flowId,
                'user_id' => $pendingUser->ID,
                'identifier_type' => $identifierType,
                'identifier_masked' => $identifierMasked,
                'channel_used' => $sendResult['channel_used'],
                'otp_ttl_seconds' => $this->getOtpTtlSeconds($otpSession['expires_at']),
                'next_step' => 'verify',
            ], $successMessage);
            
        } catch (\Exception $e) {
            return $this->handleException($e, 'initRegister');
        }
    }

    /**
     * Check if identifier is unavailable (verified in IdentifierModel)
     */
    private function isIdentifierUnavailable(string $identifier): bool
    {
        $identifierModel = new IdentifierModel();
        $identifierHash = md5($identifier);
        $existingIdentifier = $identifierModel->find([
            'value_hash' => $identifierHash,
            'verified' => true
        ]);
        return !empty($existingIdentifier);
    }

    /**
     * Get or create pending user
     */
    private function getOrCreatePendingUser(string $identifier): ?\WP_User
    {
        // Check if pending user already exists
        $existingUser = UserHelper::getUserByIdentifier($identifier);
        if ($existingUser && UserHelper::isPendingUser($existingUser->ID)) {
            return $existingUser;
        }

        // Generate flow ID if user doesn't have one
        $flowId = uniqid('flow_', true);
        
        // Create new pending user
        return UserHelper::createPendingUser($identifier, [
            'flow_id' => $flowId
        ]);
    }

    /**
     * Get existing OTP session by flow_id
     */
    private function getExistingOtpSession(string $flowId): ?array
    {
        $existingSession = OtpSessionModel::getByFlowId($flowId);
        
        if ($existingSession && strtotime($existingSession['expires_at']) > time()) {
            return $existingSession;
        }
        
        return null;
    }

    /**
     * Create new OTP session
     */
    private function createNewOtpSession(string $flowId, string $identifier): ?array
    {
        try {
            $otpSession = $this->otpService->generate($flowId, $identifier);
            return [
                'flow_id' => $otpSession->flow_id,
                'code' => $otpSession->code,
                'channel' => $otpSession->channel,
                'expires_at' => $otpSession->expires_at,
            ];
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get OTP TTL in seconds
     */
    private function getOtpTtlSeconds(string $expiresAt): int
    {
        $expiresTimestamp = strtotime($expiresAt);
        $now = time();
        return max(0, $expiresTimestamp - $now);
    }

    /**
     * Mask identifier for privacy
     */
    private function maskIdentifier(string $identifier, string $type): string
    {
        if ($type === 'email') {
            return $this->maskEmail($identifier);
        } else {
            return $this->maskPhone($identifier);
        }
    }

    /**
     * Mask email for privacy
     */
    private function maskEmail(string $email): string
    {
        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return $email;
        }
        
        $username = $parts[0];
        $domain = $parts[1];
        
        if (strlen($username) <= 2) {
            $maskedUsername = $username;
        } else {
            $maskedUsername = substr($username, 0, 2) . str_repeat('*', strlen($username) - 2);
        }
        
        return $maskedUsername . '@' . $domain;
    }

    /**
     * Validate identifier format
     */
    public function validateIdentifier($value, $request, $param)
    {
        if (empty($value)) {
            return new WP_Error('invalid_identifier', __('Identifier is required.', 'wp-sms'));
        }

        $identifierType = UserHelper::getIdentifierType($value);
        
        if ($identifierType === 'unknown') {
            return new WP_Error('invalid_identifier', __('Invalid identifier format. Please provide a valid email address or phone number.', 'wp-sms'));
        }

        return true;
    }

    /**
     * Mask phone number for privacy
     */
    private function maskPhone(string $phone): string
    {
        if (strlen($phone) <= 4) {
            return str_repeat('*', strlen($phone));
        }
        
        return substr($phone, 0, 3) . str_repeat('*', strlen($phone) - 6) . substr($phone, -3);
    }

}