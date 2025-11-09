<?php

namespace WP_SMS\Services\OTP\RestAPIEndpoints\Login;

use WP_REST_Server;
use WP_REST_Response;
use WP_REST_Request;
use WP_Error;
use WP_SMS\Services\OTP\RestAPIEndpoints\Abstracts\RestAPIEndpointsAbstract;
use WP_SMS\Services\OTP\Helpers\UserHelper;
use WP_SMS\Services\OTP\Models\OtpSessionModel;
use WP_SMS\Services\OTP\Models\MagicLinkModel;
use WP_SMS\Services\OTP\Models\IdentifierModel;
use WP_SMS\Services\OTP\AuthChannel\MagicLink\MagicLinkService;
use WP_SMS\Services\OTP\AuthChannel\OTPMagicLink\OTPMagicLinkCombinedChannel;

class LoginStartAPIEndpoint extends RestAPIEndpointsAbstract
{
    public function registerRoutes(): void
    {
        register_rest_route('wpsms/v1', '/login/start', [
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
            'identifier' => [
                'required'          => true,
                'type'              => 'string',
                'description'       => 'Identifier for login (username, email, or phone number)',
                'sanitize_callback' => 'sanitize_text_field',
                'validate_callback' => [$this, 'validateIdentifier'],
            ],
        ];
    }

    /**
     * Main request handler - clean entry point
     */
    public function handleRequest(WP_REST_Request $request)
    {
        try {
            // Extract and validate request data
            $identifier = (string) $request->get_param('identifier');
            $ip = $this->getClientIp($request);
            
            // Determine identifier type (email, phone, username)
            $identifierType = $this->determineIdentifierType($identifier);
            
            // Normalize identifier
            $identifierNormalized = $this->normalizeIdentifier($identifier, $identifierType);
            
            // Rate limiting
            $rateLimitCheck = $this->checkRateLimits($identifierNormalized, $ip, 'login_init');
            if (is_wp_error($rateLimitCheck)) {
                return $rateLimitCheck;
            }
            
            // Find user by identifier
            $user = $this->getUserByIdentifier($identifierNormalized, $identifierType);
            if (!$user) {
                // Don't reveal if user exists - security
                return $this->createErrorResponse(
                    'invalid_credentials',
                    __('Invalid identifier. Please check and try again.', 'wp-sms'),
                    401
                );
            }
            
            // Check if user is active (not pending)
            if (UserHelper::isPendingUser($user->ID)) {
                return $this->createErrorResponse(
                    'pending_user',
                    __('This account is pending verification. Please complete registration first.', 'wp-sms'),
                    403
                );
            }
            
            // Generate flow ID for this login session
            $flowId = uniqid('flow_', true);
            update_user_meta($user->ID, 'wpsms_login_flow_id', $flowId);
            
            // Load channel settings for this identifier type
            $channelSettings = $this->channelSettingsHelper->getChannelData($identifierType);

            if (!$channelSettings || !$channelSettings['enabled'] || !$channelSettings['allow_signin']) {
                return $this->createErrorResponse(
                    'signin_disabled',
                    __('Sign in with this identifier type is not enabled.', 'wp-sms'),
                    403
                );
            }
            
            // Validate at least one auth method is available
            $allowOtp = !empty($channelSettings['allow_otp']);
            $allowMagic = !empty($channelSettings['allow_magic']);
            $allowPassword = !empty($channelSettings['allow_password']);
            
            if (!$allowOtp && !$allowMagic && !$allowPassword) {
                return $this->createErrorResponse(
                    'no_auth_method',
                    __('No authentication method is enabled. Please contact administrator.', 'wp-sms'),
                    400
                );
            }
            
            // Initialize auth services
            $magicService = ($allowMagic) ? new MagicLinkService() : null;
            $useCombined = ($allowOtp && $allowMagic);
            $combinedService = $useCombined ? new OTPMagicLinkCombinedChannel() : null;
            $otpDigits = (int) ($channelSettings['otp_digits'] ?? 6);
            
            // Generate authentication artifacts
            $otpSession = null;
            $magicLink = null;
            $isNewOtp = false;
            $isNewMagic = false;
            
            if ($useCombined && $combinedService) {
                $generated = $combinedService->generate($flowId, $identifierNormalized, $identifierType, $otpDigits);
                $otpSession = $generated['otp_session'];
                $magicLink = $generated['magic_link'];
                $isNewOtp = true;
                $isNewMagic = true;
            } else {
                // Separate OTP or Magic Link
                if ($allowOtp) {
                    $otpSession = $this->createNewOtpSession($flowId, $identifierNormalized, $otpDigits);
                    $isNewOtp = true;
                }
                
                if ($allowMagic && $magicService) {
                    $magicLink = $magicService->generate($flowId, $identifierNormalized, $identifierType);
                    $isNewMagic = true;
                }
            }
            
            // Send artifacts
            $sendResults = $this->sendArtifacts(
                $identifierNormalized,
                $identifierType,
                $allowOtp,
                $allowMagic,
                $useCombined,
                $otpSession,
                $magicLink,
                $isNewOtp,
                $isNewMagic,
                $combinedService,
                $magicService
            );
            
            if ($sendResults instanceof WP_REST_Response) {
                return $sendResults;
            }
            
            // Log event and increment rate limits
            $primaryChannel = $this->derivePrimaryChannel($sendResults, $identifierType);
            $this->logAuthEvent($flowId, 'login_init', 'allow', $primaryChannel, $ip, null, ['user_id' => $user->ID]);
            $this->incrementRateLimits($identifierNormalized, $ip, 'login_init');
            
            // Check if MFA is required for this user
            $mfaRequired = $this->isMfaRequired($user->ID);
            
            // Build response
            return $this->buildResponse(
                $user,
                $flowId,
                $identifierType,
                $identifier,
                $otpSession,
                $magicLink,
                $useCombined,
                $primaryChannel,
                $allowPassword,
                $mfaRequired
            );

        } catch (\Exception $e) {
            return $this->handleException($e, 'loginStart');
        }
    }

    /**
     * Determine identifier type
     */
    private function determineIdentifierType(string $identifier): string
    {
        // Check if email
        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            return 'email';
        }
        
        // Check if phone
        $cleanNumber = preg_replace('/[^\d+]/', '', $identifier);
        if (preg_match('/^(\+\d{7,15}|\d{7,15})$/', $cleanNumber)) {
            return 'phone';
        }
        
        // Otherwise treat as username
        return 'username';
    }

    /**
     * Get user by identifier
     */
    private function getUserByIdentifier(string $identifier, string $type)
    {
        if ($type === 'email') {
            return get_user_by('email', $identifier);
        }
        
        if ($type === 'username') {
            return get_user_by('login', $identifier);
        }
        
        if ($type === 'phone') {
            // Check in identifiers table
            $identifierModel = new IdentifierModel();
            $found = $identifierModel->find([
                'value_hash' => md5($identifier),
                'factor_type' => 'phone',
                'verified' => true,
            ]);
            
            if ($found && isset($found['user_id'])) {
                return get_user_by('id', $found['user_id']);
            }
            
            // Fallback to user meta
            return UserHelper::getUserByIdentifier($identifier);
        }
        
        return null;
    }

    /**
     * Check if MFA is required for user
     */
    private function isMfaRequired(int $userId): bool
    {
        // For now, assume MFA is globally enabled if user has verified identifiers
        // We'll check if user has any MFA factors enrolled
        $identifierModel = new IdentifierModel();
        $identifiers = $identifierModel->getAllByUserId($userId);
        
        // If user has multiple verified identifiers, MFA is available
        return count($identifiers) >= 2;
    }

    /**
     * Send authentication artifacts
     */
    private function sendArtifacts(
        string $identifierNormalized,
        string $identifierType,
        bool $allowOtp,
        bool $allowMagic,
        bool $useCombined,
        $otpSession,
        $magicLink,
        bool $isNewOtp,
        bool $isNewMagic,
        $combinedService,
        $magicService
    ): array|WP_REST_Response {
        $results = [];

        if ($useCombined && $combinedService) {
            $sendResult = $combinedService->sendCombined(
                $identifierNormalized,
                $otpSession,
                $magicLink,
                'login'
            );
            if (empty($sendResult['success'])) {
                return $this->createErrorResponse(
                    'send_failed',
                    $sendResult['error'] ?? __('Failed to send authentication message', 'wp-sms'),
                    500
                );
            }
            $results['combined'] = $sendResult;
        } else {
            if ($allowOtp && $otpSession) {
                $sendResult = $this->otpService->sendOTP(
                    $identifierNormalized,
                    $otpSession['code'],
                    $otpSession['channel']
                );
                if (empty($sendResult['success'])) {
                    return $this->createErrorResponse(
                        'send_failed',
                        $sendResult['error'] ?? __('Failed to send OTP', 'wp-sms'),
                        500
                    );
                }
                $results['otp'] = $sendResult;
            }

            if ($allowMagic && $magicLink && $magicService) {
                $sendResult = $magicService->sendMagicLink($identifierNormalized, $magicLink);
                if (empty($sendResult['success'])) {
                    return $this->createErrorResponse(
                        'send_failed',
                        $sendResult['error'] ?? __('Failed to send Magic Link', 'wp-sms'),
                        500
                    );
                }
                $results['magic_link'] = $sendResult;
            }
        }

        return $results;
    }

    /**
     * Build success response
     */
    private function buildResponse(
        $user,
        string $flowId,
        string $identifierType,
        string $identifierRaw,
        $otpSession,
        $magicLink,
        bool $useCombined,
        string $primaryChannel,
        bool $allowPassword,
        bool $mfaRequired
    ): WP_REST_Response {
        $data = [
            'flow_id' => $flowId,
            'identifier_type' => $identifierType,
            'identifier_masked' => $this->maskIdentifier($identifierRaw, $identifierType),
            'next_step' => 'verify',
            'otp_enabled' => (bool) $otpSession,
            'magic_link_enabled' => (bool) $magicLink,
            'password_enabled' => $allowPassword,
            'combined_enabled' => $useCombined,
            'channel_used' => $primaryChannel,
            'mfa_required' => $mfaRequired,
        ];

        if ($otpSession && isset($otpSession['expires_at'])) {
            $data['otp_ttl_seconds'] = $this->getOtpTtlSeconds($otpSession['expires_at']);
        }

        return $this->createSuccessResponse($data, __('Login initiated successfully', 'wp-sms'));
    }

    /**
     * WordPress validation callback for identifier
     */
    public function validateIdentifier($value, $request, $param)
    {
        if (empty($value)) {
            return new WP_Error('invalid_identifier', __('Identifier is required.', 'wp-sms'), ['status' => 400]);
        }
        
        return true;
    }

    /**
     * Normalize identifier based on type
     */
    private function normalizeIdentifier(string $identifier, string $type): string
    {
        $normalized = trim($identifier);
        if ($type === 'email') {
            return strtolower($normalized);
        }
        if ($type === 'phone') {
            $normalized = preg_replace('/[^\d\+]/', '', $normalized);
            if (strpos($normalized, '+') > 0) {
                $normalized = '+' . preg_replace('/\+/', '', $normalized);
            }
            return $normalized;
        }
        return $normalized; // username
    }

    /**
     * Create new OTP session
     */
    private function createNewOtpSession(string $flowId, string $identifierNorm, int $otpDigits)
    {
        try {
            $otp = $this->otpService->generate($flowId, $identifierNorm, $otpDigits);
            return $this->mapOtpSessionForResponse($otp);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Map OTP session to response format
     */
    private function mapOtpSessionForResponse($otpSession): array
    {
        if (is_array($otpSession)) {
            return [
                'flow_id' => isset($otpSession['flow_id']) ? $otpSession['flow_id'] : null,
                'code' => isset($otpSession['code']) ? $otpSession['code'] : null,
                'channel' => isset($otpSession['channel']) ? $otpSession['channel'] : null,
                'expires_at' => isset($otpSession['expires_at']) ? $otpSession['expires_at'] : null,
            ];
        }
        return [
            'flow_id' => isset($otpSession->flow_id) ? $otpSession->flow_id : null,
            'code' => isset($otpSession->code) ? $otpSession->code : null,
            'channel' => isset($otpSession->channel) ? $otpSession->channel : null,
            'expires_at' => isset($otpSession->expires_at) ? $otpSession->expires_at : null,
        ];
    }

    /**
     * Get OTP TTL in seconds
     */
    private function getOtpTtlSeconds(string $expiresAt): int
    {
        $expires = strtotime($expiresAt);
        $now = time();
        return max(0, $expires - $now);
    }

    /**
     * Mask identifier for response
     */
    private function maskIdentifier(string $identifier, string $type): string
    {
        if ($type === 'email') {
            return $this->maskEmail($identifier);
        }
        if ($type === 'phone') {
            return $this->maskPhone($identifier);
        }
        // Username - mask middle characters
        $len = strlen($identifier);
        if ($len <= 4) return str_repeat('*', $len);
        return substr($identifier, 0, 2) . str_repeat('*', max(0, $len - 4)) . substr($identifier, -2);
    }

    /**
     * Mask email address
     */
    private function maskEmail(string $email): string
    {
        $parts = explode('@', $email);
        if (count($parts) !== 2) return $email;
        $username = $parts[0];
        $domain = $parts[1];
        $maskedUsername = (strlen($username) <= 2) ? $username : substr($username, 0, 2) . str_repeat('*', strlen($username) - 2);
        return $maskedUsername . '@' . $domain;
    }

    /**
     * Mask phone number
     */
    private function maskPhone(string $phone): string
    {
        $len = strlen($phone);
        if ($len <= 4) return str_repeat('*', $len);
        return substr($phone, 0, 3) . str_repeat('*', max(0, $len - 6)) . substr($phone, -3);
    }

    /**
     * Derive primary channel from send results
     */
    private function derivePrimaryChannel(array $sendResults, string $identifierType): string
    {
        if (isset($sendResults['combined']['channel_used'])) return (string) $sendResults['combined']['channel_used'];
        if (isset($sendResults['otp']['channel_used'])) return (string) $sendResults['otp']['channel_used'];
        if (isset($sendResults['magic_link']['channel_used'])) return (string) $sendResults['magic_link']['channel_used'];
        return $identifierType;
    }
}

