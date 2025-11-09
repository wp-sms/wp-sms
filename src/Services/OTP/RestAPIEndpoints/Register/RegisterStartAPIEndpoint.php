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
    public function registerRoutes(): void
    {
        register_rest_route('wpsms/v1', '/register/start', [
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
                'description'       => 'Identifier for registration (phone number, email, etc.)',
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

            // Validate identifier format
            $identifierType = UserHelper::getIdentifierType($identifier);
            if ($identifierType === 'unknown') {
                return $this->createErrorResponse(
                    'invalid_identifier',
                    __('Invalid identifier format. Please provide a valid email address or phone number.', 'wp-sms'),
                    400
                );
            }

            // Normalize identifier
            $identifierNormalized = $this->normalizeIdentifier($identifier, $identifierType);

            // Rate limiting
            $rateLimitCheck = $this->checkRateLimits($identifierNormalized, $ip, 'register_init');
            if (is_wp_error($rateLimitCheck)) {
                return $rateLimitCheck;
            }

            // Check availability
            $availabilityCheck = $this->checkAvailability($identifierNormalized, $identifierType);
            if ($availabilityCheck instanceof WP_REST_Response) {
                return $availabilityCheck;
            }

            // Get or create pending user
            $user = $this->getOrCreatePendingUser($identifierNormalized);
            if (!$user) {
                return $this->createErrorResponse(
                    'user_creation_failed',
                    __('Unable to create or retrieve pending user', 'wp-sms'),
                    500
                );
            }

            $flowId = UserHelper::getUserFlowId($user->ID);

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
            $allowPassword = !empty($channelSettings['allow_password']);

            if (!$allowOtp && !$allowMagic && !$allowPassword) {
                return $this->createErrorResponse(
                    'no_auth_method',
                    __('No authentication method is enabled for this identifier type. Please contact administrator.', 'wp-sms'),
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

            if ($useCombined) {
                // Combined mode
                if (!$combinedService->exists($flowId)) {
                    $generated = $combinedService->generate($flowId, $identifierNormalized, $identifierType, $otpDigits);
                    $otpSession = $generated['otp_session'];
                    $magicLink = $generated['magic_link'];
                    $isNewOtp = true;
                    $isNewMagic = true;
                } else {
                    // Reuse existing
                    $otpSession = $this->getExistingOtpSession($flowId);
                    $magicLink = $this->getExistingMagicLink($flowId);
                }
            } else {
                // Separate OTP or Magic Link
                if ($allowOtp) {
                    $otpSession = $this->getExistingOtpSession($flowId);
                    if (!$otpSession) {
                        $otpSession = $this->createNewOtpSession($flowId, $identifierNormalized, $otpDigits);
                        $isNewOtp = true;
                    }
                }

                if ($allowMagic && $magicService) {
                    $magicLink = $this->getExistingMagicLink($flowId);
                    if (!$magicLink) {
                        $magicLink = $magicService->generate($flowId, $identifierNormalized, $identifierType);
                        $isNewMagic = true;
                    }
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
            $this->logAuthEvent($flowId, 'register_init', 'allow', $primaryChannel, $ip);
            $this->incrementRateLimits($identifierNormalized, $ip, 'register_init');

            // Build response
            return $this->buildResponse($user, $flowId, $identifierType, $identifier, $otpSession, $magicLink, $useCombined, $primaryChannel);
        } catch (\Exception $e) {
            return $this->handleException($e, 'startRegister');
        }
    }

    /**
     * Check if identifier is available
     */
    private function checkAvailability(string $identifierNormalized, string $identifierType): bool|WP_REST_Response
    {
        if ($this->isIdentifierUnavailable($identifierNormalized, $identifierType)) {
            return $this->createErrorResponse(
                'identifier_taken',
                __('This identifier is already in use.', 'wp-sms'),
                409
            );
        }

        if ($identifierType === 'email') {
            $existing = get_user_by('email', $identifierNormalized);
            $isPending = $existing ? UserHelper::isPendingUser($existing->ID) : false;
            if ($existing && !$isPending) {
                return $this->createErrorResponse(
                    'email_exists',
                    __('An account with this email already exists. Please try logging in instead.', 'wp-sms'),
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

        if ($useCombined) {
            // Send combined message
            if ($isNewOtp && $isNewMagic) {
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
                $results['combined'] = ['success' => true, 'reused' => true];
            }
        } else {
            // Send separate OTP and/or Magic Link
            if ($allowOtp && $otpSession) {
                if ($isNewOtp) {
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
                } else {
                    $results['otp'] = ['success' => true, 'reused' => true];
                }
            }

            if ($allowMagic && $magicLink && $magicService) {
                if ($isNewMagic) {
                    $sendResult = $magicService->sendMagicLink($identifierNormalized, $magicLink);
                    if (empty($sendResult['success'])) {
                        return $this->createErrorResponse(
                            'send_failed',
                            $sendResult['error'] ?? __('Failed to send Magic Link', 'wp-sms'),
                            500
                        );
                    }
                    $results['magic_link'] = $sendResult;
                } else {
                    $channelUsed = ($identifierType === 'email') ? 'email' : 'sms';
                    $results['magic_link'] = ['success' => true, 'channel_used' => $channelUsed, 'reused' => true];
                }
            }
        }

        return $results;
    }

    /**
     * Build success response
     */
    private function buildResponse($user, string $flowId, string $identifierType, string $identifierRaw, $otpSession, $magicLink, bool $useCombined, string $primaryChannel): WP_REST_Response
    {
        $isNewSession = ($otpSession || $magicLink);
        $message = $isNewSession
            ? __('Registration initiated successfully', 'wp-sms')
            : __('Registration reinitiated successfully', 'wp-sms');

        $data = [
            'flow_id' => $flowId,
            'user_id' => $user->ID,
            'identifier_type' => $identifierType,
            'identifier_masked' => $this->maskIdentifier($identifierRaw, $identifierType),
            'next_step' => 'verify',
            'otp_enabled' => (bool) $otpSession,
            'magic_link_enabled' => (bool) $magicLink,
            'combined_enabled' => $useCombined,
            'channel_used' => $primaryChannel,
        ];

        if ($otpSession && isset($otpSession['expires_at'])) {
            $data['otp_ttl_seconds'] = $this->getOtpTtlSeconds($otpSession['expires_at']);
        }

        return $this->createSuccessResponse($data, $message);
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
     * Check if identifier is already taken
     */
    private function isIdentifierUnavailable(string $identifierNorm, string $type): bool
    {
        $model = new IdentifierModel();
        $hash = md5($identifierNorm);
        $found = $model->find([
            'value_hash' => $hash,
            'verified' => true,
        ]);


        if($type === 'email') {
            $existing = get_user_by('email', $identifierNorm);
            if($existing && UserHelper::isPendingUser($existing->ID)) {
                return true;
            }
        }

        return !empty($found);
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
     * Get or create pending user
     */
    private function getOrCreatePendingUser(string $identifierNorm)
    {
        $existing = UserHelper::getUserByIdentifier($identifierNorm);
        if ($existing && UserHelper::isPendingUser($existing->ID)) {
            return $existing;
        }
        $flowId = uniqid('flow_', true);
        return UserHelper::createPendingUser($identifierNorm, ['flow_id' => $flowId]);
    }

    /**
     * Get existing valid OTP session
     */
    private function getExistingOtpSession(string $flowId)
    {
        $existing = OtpSessionModel::getByFlowId($flowId);
        if ($existing && isset($existing['expires_at']) && strtotime($existing['expires_at']) > time()) {
            return $existing;
        }
        return null;
    }

    /**
     * Get existing valid magic link
     */
    private function getExistingMagicLink(string $flowId)
    {
        $existing = MagicLinkModel::getExistingValidLink($flowId);
        return $existing ? $existing : null;
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
