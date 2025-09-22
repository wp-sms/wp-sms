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
use WP_SMS\Services\OTP\Models\MagicLinkModel;
use WP_SMS\Services\OTP\AuthChannel\MagicLink\MagicLinkService;
use WP_SMS\Services\OTP\AuthChannel\OTPMagicLink\OTPMagicLinkCombinedChannel;

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
                $isPendingUser = $existingWpUser ? UserHelper::isPendingUser($existingWpUser->ID) : false;
                if ($existingWpUser && !$isPendingUser) {
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

            $identifierChannelData = $this->channelSettingsHelper->getChannelData($identifierType);
            $allowPassword = $identifierChannelData['allow_password'];
            $allowOtp = $identifierChannelData['allow_otp'];
            $allowMagic = $identifierChannelData['allow_magic'];
            $otpDigits = $identifierChannelData['otp_digits'];
            $passwordIsRequired = $identifierChannelData['password_is_required'];
            $allowSignin = $identifierChannelData['allow_signin'];

            // 5. Validate that at least one authentication method is allowed
            if (!$allowPassword && !$allowOtp && !$allowMagic) {
                return $this->createErrorResponse(
                    'no_auth_method_allowed',
                    __('No authentication method is enabled for this identifier type. Please contact administrator.', 'wp-sms'),
                    400
                );
            }

            $flowId = UserHelper::getUserFlowId($pendingUser->ID);

            // 6. Initialize authentication sessions
            $otpSession = null;
            $magicLink = null;
            $isNewOtpSession = false;
            $isNewMagicLink = false;
            $useCombinedAuth = false;

            // 6.1. Check if both OTP and Magic Link are enabled
            if ($allowOtp && $allowMagic) {
                // Use combined auth channel
                $useCombinedAuth = true;
                $otpMagicLinkCombinedChannel = new OTPMagicLinkCombinedChannel();
                
                // Check if combined auth already exists
                $hasExistingCombined = $otpMagicLinkCombinedChannel->exists($flowId);
                if (!$hasExistingCombined) {
                    // Generate both OTP and Magic Link together
                    $combinedResult = $otpMagicLinkCombinedChannel->generate($flowId, $identifier, $identifierType, $otpDigits);
                    $otpSession = $combinedResult['otp_session'];
                    $magicLink = $combinedResult['magic_link'];
                    $isNewOtpSession = true;
                    $isNewMagicLink = true;
                } else {
                    // Reuse existing sessions
                    $otpSession = $this->getExistingOtpSession($flowId);
                    $magicLink = $this->getExistingMagicLink($flowId);
                    $isNewOtpSession = false;
                    $isNewMagicLink = false;
                }
            } else {
                // Use individual auth channels
                
                // 6.2. Create OTP session if OTP is allowed
                if ($allowOtp) {
                    $existingOtpSession = $this->getExistingOtpSession($flowId);
                    if ($existingOtpSession) {
                        // Reuse existing session - do not send OTP
                        $otpSession = $existingOtpSession;
                        $isNewOtpSession = false;
                    } else {
                        // Create new session
                        $otpSession = $this->createNewOtpSession($flowId, $identifier, $otpDigits);
                        if (!$otpSession) {
                            return $this->createErrorResponse(
                                'session_creation_failed',
                                __('Unable to create OTP session', 'wp-sms'),
                                500
                            );
                        }
                        $isNewOtpSession = true;
                    }
                }

                // 6.3. Create Magic Link if Magic Link is allowed
                if ($allowMagic) {
                    $hasExistingMagicLink = MagicLinkModel::hasValidLink($flowId);
                    $magicLinkService = new MagicLinkService();
                    $magicLink = $magicLinkService->generate($flowId, $identifier, $identifierType);
                    $isNewMagicLink = !$hasExistingMagicLink;
                }
            }

            // 7. Send authentication methods
            $sendResults = [];
            
            if ($useCombinedAuth) {
                if($isNewOtpSession && $isNewMagicLink) {
                    // 7.1. Send combined message using combined auth channel
                    $otpMagicLinkCombinedChannel = new OTPMagicLinkCombinedChannel();
                    $combinedResult = $otpMagicLinkCombinedChannel->sendCombined($identifier, $otpSession, $magicLink, 'register');
                    $sendResults['combined'] = $combinedResult;
                }else{
                    $combinedResult = [
                        'success' => true,
                        'channel_used' => $otpSession['channel'],
                        'message_type' => 'combined'
                    ];
                }
                
                if (!$combinedResult['success']) {
                    return $this->createErrorResponse(
                        'combined_send_failed',
                        $combinedResult['error'] ?? __('Failed to send combined authentication message', 'wp-sms'),
                        500
                    );
                }
            } else {
                // 7.2. Send individual messages
                
                // Send OTP only if it's a new session
                if ($allowOtp && $isNewOtpSession) {
                    $otpSendResult = $this->otpService->sendOTP($identifier, $otpSession['code'], $otpSession['channel']);
                    $sendResults['otp'] = $otpSendResult;
                    
                    if (!$otpSendResult['success']) {
                        return $this->createErrorResponse(
                            'otp_send_failed',
                            $otpSendResult['error'] ?? __('Failed to send OTP', 'wp-sms'),
                            500
                        );
                    }
                } elseif ($allowOtp && !$isNewOtpSession) {
                    // For existing OTP session, just return success without sending
                    $sendResults['otp'] = ['success' => true, 'channel_used' => $otpSession['channel']];
                }

                // Send Magic Link only if it's new
                if ($allowMagic && $isNewMagicLink) {
                    $magicLinkService = new MagicLinkService();
                    $magicLinkSendResult = $magicLinkService->sendMagicLink($identifier, $magicLink);
                    $sendResults['magic_link'] = $magicLinkSendResult;
                    
                    if (!$magicLinkSendResult['success']) {
                        return $this->createErrorResponse(
                            'magic_link_send_failed',
                            $magicLinkSendResult['error'] ?? __('Failed to send Magic Link', 'wp-sms'),
                            500
                        );
                    }
                } elseif ($allowMagic && !$isNewMagicLink) {
                    // For existing Magic Link, just return success without sending
                    $channelUsed = $identifierType === 'email' ? 'email' : 'sms';
                    $sendResults['magic_link'] = ['success' => true, 'channel_used' => $channelUsed];
                }
            }

            // 8. Log + counters
            $primaryChannel = $identifierType;
            if (isset($sendResults['combined']['channel_used'])) {
                $primaryChannel = $sendResults['combined']['channel_used'];
            } elseif (isset($sendResults['otp']['channel_used'])) {
                $primaryChannel = $sendResults['otp']['channel_used'];
            } elseif (isset($sendResults['magic_link']['channel_used'])) {
                $primaryChannel = $sendResults['magic_link']['channel_used'];
            }
            
            $this->logAuthEvent($flowId, 'register_init', 'allow', $primaryChannel, $ip);
            $this->incrementRateLimits($identifier, $ip, 'register_init');

            // 9. Success
            $isNewSession = $isNewOtpSession || $isNewMagicLink;
            $successMessage = $isNewSession 
                ? __('Registration initiated successfully', 'wp-sms')
                : __('Registration reinitiated successfully', 'wp-sms');
            
            // Prepare response data
            $responseData = [
                'flow_id' => $flowId,
                'user_id' => $pendingUser->ID,
                'identifier_type' => $identifierType,
                'identifier_masked' => $identifierMasked,
                'next_step' => 'verify',
            ];
            
            // Add OTP data if OTP session exists
            if ($otpSession) {
                $responseData['otp_ttl_seconds'] = $this->getOtpTtlSeconds($otpSession['expires_at']);
                $responseData['otp_enabled'] = true;
            } else {
                $responseData['otp_enabled'] = false;
            }
            
            // Add Magic Link data if Magic Link was created
            if ($magicLink) {
                $responseData['magic_link_enabled'] = true;
            } else {
                $responseData['magic_link_enabled'] = false;
            }
            
            // Add primary channel used
            if ($primaryChannel) {
                $responseData['channel_used'] = $primaryChannel;
            }
            
            // Add combined sending information
            if (isset($sendResults['combined'])) {
                $responseData['combined_enabled'] = true;
                $responseData['message_type'] = 'combined';
            } else {
                $responseData['combined_enabled'] = false;
            }
                
            return $this->createSuccessResponse($responseData, $successMessage);
            
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
     * Get existing Magic Link by flow_id
     */
    private function getExistingMagicLink(string $flowId)
    {
        $existingMagicLink = MagicLinkModel::getExistingValidLink($flowId);
        
        if ($existingMagicLink) {
            return $existingMagicLink;
        }
        
        return null;
    }



    /**
     * Create new OTP session
     */
    private function createNewOtpSession(string $flowId, string $identifier, int $otpDigits): ?array
    {
        try {
            $otpSession = $this->otpService->generate($flowId, $identifier, $otpDigits);
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