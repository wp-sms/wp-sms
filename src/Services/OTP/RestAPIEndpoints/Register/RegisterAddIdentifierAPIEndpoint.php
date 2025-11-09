<?php

namespace WP_SMS\Services\OTP\RestAPIEndpoints\Register;

use WP_REST_Server;
use WP_REST_Response;
use WP_REST_Request;
use WP_Error;
use WP_SMS\Services\OTP\RestAPIEndpoints\Abstracts\RestAPIEndpointsAbstract;
use WP_SMS\Services\OTP\Helpers\UserHelper;
use WP_SMS\Services\OTP\Helpers\ChannelSettingsHelper;
use WP_SMS\Services\OTP\Models\OtpSessionModel;
use WP_SMS\Services\OTP\Models\MagicLinkModel;
use WP_SMS\Services\OTP\AuthChannel\MagicLink\MagicLinkService;
use WP_SMS\Services\OTP\AuthChannel\OTPMagicLink\OTPMagicLinkCombinedChannel;

class RegisterAddIdentifierAPIEndpoint extends RestAPIEndpointsAbstract
{
    public function registerRoutes(): void
    {
        register_rest_route('wpsms/v1', '/register/add-identifier', [
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
            'identifier' => [
                'required'          => true,
                'type'              => 'string',
                'description'       => 'New identifier to add (email or phone number)',
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
            // Extract request data
            $flowId = (string) $request->get_param('flow_id');
            $identifier = (string) $request->get_param('identifier');
            $ip = $this->getClientIp($request);

            // Rate limiting
            $rateLimitCheck = $this->checkRateLimits($identifier, $ip, 'register_add_identifier');
            if (is_wp_error($rateLimitCheck)) {
                return $rateLimitCheck;
            }

            // Get user by flow ID
            $user = UserHelper::getUserByFlowId($flowId);
            if (!$user) {
                return $this->createErrorResponse(
                    'user_not_found',
                    __('No user found for this flow', 'wp-sms'),
                    404
                );
            }

            // Ensure user is still pending
            if (!UserHelper::isPendingUser($user->ID)) {
                return $this->createErrorResponse(
                    'already_verified',
                    __('User has already been verified', 'wp-sms'),
                    409
                );
            }

            // Validate identifier type
            $identifierType = UserHelper::getIdentifierType($identifier);
            if ($identifierType === 'unknown') {
                return $this->createErrorResponse(
                    'invalid_identifier',
                    __('Invalid identifier format. Provide a valid email or phone.', 'wp-sms'),
                    400
                );
            }

            // Normalize identifier
            $identifierNormalized = $this->normalizeIdentifier($identifier, $identifierType);

            // Validate this type is required
            $requiredChannels = ChannelSettingsHelper::getRequiredChannels();
            if (!isset($requiredChannels[$identifierType]) || empty($requiredChannels[$identifierType]['required'])) {
                return $this->createErrorResponse(
                    'identifier_not_required',
                    __('This identifier type is not required', 'wp-sms'),
                    400
                );
            }

            // Check if this type already verified
            $verifiedIdentifiers = UserHelper::getVerifiedIdentifiers($user->ID);
            if (isset($verifiedIdentifiers[$identifierType])) {
                return $this->createErrorResponse(
                    'already_verified_type',
                    __('This identifier type has already been verified', 'wp-sms'),
                    409
                );
            }

            // Check identifier availability
            $availabilityCheck = $this->checkIdentifierAvailability($identifierNormalized, $identifierType, $user->ID);
            if ($availabilityCheck instanceof WP_REST_Response) {
                return $availabilityCheck;
            }

            // Load channel settings
            $channelSettings = $this->channelSettingsHelper->getChannelData($identifierType);
            if (!$channelSettings) {
                return $this->createErrorResponse(
                    'channel_not_configured',
                    __('This channel is not configured.', 'wp-sms'),
                    400
                );
            }

            // Validate at least one auth method is available
            $allowOtp = !empty($channelSettings['allow_otp']);
            $allowMagic = !empty($channelSettings['allow_magic']);

            if (!$allowOtp && !$allowMagic) {
                return $this->createErrorResponse(
                    'no_auth_method',
                    __('No authentication method is enabled for this identifier type. Please contact administrator.', 'wp-sms'),
                    400
                );
            }

            // Generate new flow ID and persist identifier
            $newFlowId = uniqid('flow_', true);
            $updateSuccess = UserHelper::updateUserMeta($user->ID, [
                'identifier' => $identifierNormalized,
                'identifier_type' => $identifierType,
                'flow_id' => $newFlowId,
            ]);

            if (!$updateSuccess) {
                return $this->createErrorResponse(
                    'update_failed',
                    __('Failed to update user identifier', 'wp-sms'),
                    500
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
                // Combined mode - always generate new since we have a new flow ID
                $generated = $combinedService->generate($newFlowId, $identifierNormalized, $identifierType, $otpDigits);
                $otpSession = $this->mapOtpSessionForResponse($generated['otp_session']);
                $magicLink = $generated['magic_link'];
                $isNewOtp = true;
                $isNewMagic = true;
            } else {
                // Separate OTP or Magic Link
                if ($allowOtp) {
                    $otpSession = $this->createNewOtpSession($newFlowId, $identifierNormalized, $otpDigits);
                    $isNewOtp = true;
                }

                if ($allowMagic && $magicService) {
                    $magicLink = $magicService->generate($newFlowId, $identifierNormalized, $identifierType);
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
            $this->logAuthEvent($newFlowId, 'register_add_identifier', 'allow', $primaryChannel, $ip);
            $this->incrementRateLimits($identifierNormalized, $ip, 'register_add_identifier');

            // Build response
            return $this->buildResponse(
                $user->ID,
                $newFlowId,
                $identifierNormalized,
                $identifierType,
                $identifier,
                $otpSession,
                $magicLink,
                $useCombined,
                $primaryChannel,
                $verifiedIdentifiers
            );

        } catch (\Exception $e) {
            return $this->handleException($e, 'addIdentifier');
        }
    }

    /**
     * Check if identifier is available for use
     */
    private function checkIdentifierAvailability(string $identifierNormalized, string $identifierType, int $userId): bool|WP_REST_Response
    {
        $existing = UserHelper::getUserByIdentifier($identifierNormalized);
        if ($existing && (int) $existing->ID !== $userId) {
            return $this->createErrorResponse(
                'identifier_taken',
                __('This identifier is already in use by another user', 'wp-sms'),
                409
            );
        }

        if ($identifierType === 'email') {
            $existing = get_user_by('email', $identifierNormalized);
            if ($existing && (int) $existing->ID !== $userId) {
                return $this->createErrorResponse(
                    'email_taken',
                    __('This email is already in use by another user', 'wp-sms'),
                    409
                );
            }
        }

        return true;
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
            // Send combined message
            $sendResult = $combinedService->sendCombined(
                $identifierNormalized,
                $otpSession,
                $magicLink,
                'register'
            );
            if (empty($sendResult['success'])) {
                return $this->createErrorResponse(
                    'send_failed',
                    $sendResult['error'] ?? __('Failed to send combined authentication message', 'wp-sms'),
                    500
                );
            }
            $results['combined'] = $sendResult;
        } else {
            // Send separate OTP and/or Magic Link
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
        int $userId,
        string $flowId,
        string $identifierNormalized,
        string $identifierType,
        string $identifierRaw,
        $otpSession,
        $magicLink,
        bool $useCombined,
        string $primaryChannel,
        array $verifiedIdentifiers
    ): WP_REST_Response {
        $nextRequired = UserHelper::getNextRequiredIdentifier($userId, ChannelSettingsHelper::getRequiredChannels());

        $data = [
            'user_id' => $userId,
            'flow_id' => $flowId,
            'identifier' => $identifierNormalized,
            'identifier_type' => $identifierType,
            'identifier_masked' => $this->maskIdentifier($identifierRaw, $identifierType),
            'channel_used' => $primaryChannel,
            'verified_identifiers' => $verifiedIdentifiers,
            'next_required_identifier' => $nextRequired,
            'next_step' => $nextRequired ? 'verify_next' : 'verify_current',
            'otp_enabled' => (bool) $otpSession,
            'magic_link_enabled' => (bool) $magicLink,
            'combined_enabled' => $useCombined,
        ];

        if ($otpSession && isset($otpSession['expires_at'])) {
            $data['otp_ttl_seconds'] = $this->getOtpTtlSeconds($otpSession['expires_at']);
        }

        return $this->createSuccessResponse($data, __('Identifier added successfully. Please verify.', 'wp-sms'));
    }

    /**
     * WordPress validation callback for identifier
     */
    public function validateIdentifier($value, $request, $param)
    {
        if (empty($value)) {
            return new WP_Error('invalid_identifier', __('Identifier is required.', 'wp-sms'), ['status' => 400]);
        }
        
        $identifierType = UserHelper::getIdentifierType($value);
        if ($identifierType === 'unknown') {
            return new WP_Error('invalid_identifier', __('Invalid identifier format. Please provide a valid email address or phone number.', 'wp-sms'), ['status' => 400]);
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
        $normalized = preg_replace('/[^\d\+]/', '', $normalized);
        if (strpos($normalized, '+') > 0) {
            $normalized = '+' . preg_replace('/\+/', '', $normalized);
        }
        return $normalized;
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
        return ($type === 'email') ? $this->maskEmail($identifier) : $this->maskPhone($identifier);
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
