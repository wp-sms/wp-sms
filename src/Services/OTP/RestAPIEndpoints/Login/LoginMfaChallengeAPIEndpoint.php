<?php

namespace WP_SMS\Services\OTP\RestAPIEndpoints\Login;

use WP_REST_Server;
use WP_REST_Response;
use WP_REST_Request;
use WP_Error;
use WP_SMS\Services\OTP\RestAPIEndpoints\Abstracts\RestAPIEndpointsAbstract;
use WP_SMS\Services\OTP\Helpers\UserHelper;
use WP_SMS\Services\OTP\Models\IdentifierModel;
use WP_SMS\Services\OTP\Models\OtpSessionModel;
use WP_SMS\Services\OTP\AuthChannel\MagicLink\MagicLinkService;
use WP_SMS\Services\OTP\AuthChannel\OTPMagicLink\OTPMagicLinkCombinedChannel;

class LoginMfaChallengeAPIEndpoint extends RestAPIEndpointsAbstract
{
    public function registerRoutes(): void
    {
        register_rest_route('wpsms/v1', '/login/mfa-challenge', [
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
                'description'       => 'Flow ID from the login process',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'mfa_method' => [
                'required'          => true,
                'type'              => 'string',
                'description'       => 'MFA method to use (email, phone, totp, biometric)',
                'sanitize_callback' => 'sanitize_text_field',
                'validate_callback' => [$this, 'validateMfaMethod'],
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
            $mfaMethod = (string) $request->get_param('mfa_method');
            $ip = $this->getClientIp($request);

            // Rate limiting
            $rateLimitCheck = $this->checkRateLimits($flowId, $ip, 'mfa_challenge');
            if (is_wp_error($rateLimitCheck)) {
                return $rateLimitCheck;
            }

            // Get user by flow ID
            $user = $this->getUserByLoginFlowId($flowId);
            if (!$user) {
                return $this->createErrorResponse(
                    'invalid_flow',
                    __('Invalid login session. Please start login again.', 'wp-sms'),
                    404
                );
            }

            // Validate user has this MFA method enrolled
            $mfaIdentifier = $this->getUserMfaIdentifier($user->ID, $mfaMethod);
            if (!$mfaIdentifier) {
                return $this->createErrorResponse(
                    'mfa_not_enrolled',
                    __('This MFA method is not enrolled for your account.', 'wp-sms'),
                    400
                );
            }

            // Load MFA channel settings
            $mfaSettings = $this->channelSettingsHelper->getMfaChannelData($mfaMethod);
            if (!$mfaSettings || !$mfaSettings['enabled']) {
                return $this->createErrorResponse(
                    'mfa_disabled',
                    __('This MFA method is not enabled.', 'wp-sms'),
                    400
                );
            }

            // Generate MFA challenge
            $allowOtp = !empty($mfaSettings['allow_otp']);
            $allowMagic = !empty($mfaSettings['allow_magic']);
            $useCombined = ($allowOtp && $allowMagic);
            $otpDigits = (int) ($mfaSettings['otp_digits'] ?? 6);
            
            // Generate MFA flow ID
            $mfaFlowId = uniqid('mfa_', true);
            update_user_meta($user->ID, 'wpsms_mfa_flow_id', $mfaFlowId);
            
            // Initialize services
            $magicService = ($allowMagic) ? new MagicLinkService() : null;
            $combinedService = $useCombined ? new OTPMagicLinkCombinedChannel() : null;
            
            // Generate authentication artifacts
            $otpSession = null;
            $magicLink = null;
            
            if ($useCombined && $combinedService) {
                $generated = $combinedService->generate($mfaFlowId, $mfaIdentifier, $mfaMethod, $otpDigits);
                $otpSession = $generated['otp_session'];
                $magicLink = $generated['magic_link'];
            } else {
                if ($allowOtp) {
                    $otpSession = $this->createNewOtpSession($mfaFlowId, $mfaIdentifier, $otpDigits);
                }
                
                if ($allowMagic && $magicService) {
                    $magicLink = $magicService->generate($mfaFlowId, $mfaIdentifier, $mfaMethod);
                }
            }
            
            // Send MFA challenge
            $sendResults = $this->sendMfaChallenge(
                $mfaIdentifier,
                $mfaMethod,
                $allowOtp,
                $allowMagic,
                $useCombined,
                $otpSession,
                $magicLink,
                $combinedService,
                $magicService
            );
            
            if ($sendResults instanceof WP_REST_Response) {
                return $sendResults;
            }
            
            // Log event
            $primaryChannel = $this->derivePrimaryChannel($sendResults, $mfaMethod);
            $this->logAuthEvent($mfaFlowId, 'mfa_challenge_sent', 'allow', $primaryChannel, $ip, null, ['user_id' => $user->ID]);
            $this->incrementRateLimits($flowId, $ip, 'mfa_challenge');
            
            // Build response
            return $this->buildResponse($mfaFlowId, $mfaMethod, $mfaIdentifier, $otpSession, $magicLink, $useCombined);

        } catch (\Exception $e) {
            return $this->handleException($e, 'mfaChallenge');
        }
    }

    /**
     * Get user MFA identifier
     */
    private function getUserMfaIdentifier(int $userId, string $mfaMethod)
    {
        if ($mfaMethod === 'totp' || $mfaMethod === 'biometric') {
            // These don't require sending to an identifier
            return $mfaMethod;
        }
        
        $identifierModel = new IdentifierModel();
        $identifier = $identifierModel->getByUserAndType($userId, $mfaMethod);
        
        return $identifier ? $identifier['factor_value'] : null;
    }

    /**
     * Get user by login flow ID
     */
    private function getUserByLoginFlowId(string $flowId)
    {
        $users = get_users([
            'meta_key' => 'wpsms_login_flow_id',
            'meta_value' => $flowId,
            'number' => 1,
        ]);

        return !empty($users) ? $users[0] : null;
    }

    /**
     * Send MFA challenge
     */
    private function sendMfaChallenge(
        string $mfaIdentifier,
        string $mfaMethod,
        bool $allowOtp,
        bool $allowMagic,
        bool $useCombined,
        $otpSession,
        $magicLink,
        $combinedService,
        $magicService
    ): array|WP_REST_Response {
        $results = [];

        if ($useCombined && $combinedService) {
            $sendResult = $combinedService->sendCombined(
                $mfaIdentifier,
                $otpSession,
                $magicLink,
                'mfa'
            );
            if (empty($sendResult['success'])) {
                return $this->createErrorResponse(
                    'send_failed',
                    $sendResult['error'] ?? __('Failed to send MFA challenge', 'wp-sms'),
                    500
                );
            }
            $results['combined'] = $sendResult;
        } else {
            if ($allowOtp && $otpSession) {
                $sendResult = $this->otpService->sendOTP(
                    $mfaIdentifier,
                    $otpSession['code'],
                    $otpSession['channel']
                );
                if (empty($sendResult['success'])) {
                    return $this->createErrorResponse(
                        'send_failed',
                        $sendResult['error'] ?? __('Failed to send MFA OTP', 'wp-sms'),
                        500
                    );
                }
                $results['otp'] = $sendResult;
            }

            if ($allowMagic && $magicLink && $magicService) {
                $sendResult = $magicService->sendMagicLink($mfaIdentifier, $magicLink);
                if (empty($sendResult['success'])) {
                    return $this->createErrorResponse(
                        'send_failed',
                        $sendResult['error'] ?? __('Failed to send MFA link', 'wp-sms'),
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
        string $mfaFlowId,
        string $mfaMethod,
        string $mfaIdentifier,
        $otpSession,
        $magicLink,
        bool $useCombined
    ): WP_REST_Response {
        $data = [
            'mfa_flow_id' => $mfaFlowId,
            'mfa_method' => $mfaMethod,
            'mfa_identifier_masked' => $this->maskIdentifier($mfaIdentifier, $mfaMethod),
            'next_step' => 'mfa_verify',
            'otp_enabled' => (bool) $otpSession,
            'magic_link_enabled' => (bool) $magicLink,
            'combined_enabled' => $useCombined,
        ];

        if ($otpSession && isset($otpSession['expires_at'])) {
            $data['otp_ttl_seconds'] = $this->getOtpTtlSeconds($otpSession['expires_at']);
        }

        return $this->createSuccessResponse($data, __('MFA challenge sent successfully', 'wp-sms'));
    }

    /**
     * WordPress validation callback for MFA method
     */
    public function validateMfaMethod($value, $request, $param)
    {
        $allowed = ['email', 'phone', 'totp', 'biometric'];

        if (!in_array($value, $allowed)) {
            return new WP_Error('invalid_mfa_method', __('Invalid MFA method.', 'wp-sms'), ['status' => 400]);
        }
        
        return true;
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
            $parts = explode('@', $identifier);
            if (count($parts) !== 2) return $identifier;
            $username = $parts[0];
            $domain = $parts[1];
            $maskedUsername = (strlen($username) <= 2) ? $username : substr($username, 0, 2) . str_repeat('*', strlen($username) - 2);
            return $maskedUsername . '@' . $domain;
        }
        
        if ($type === 'phone') {
            $len = strlen($identifier);
            if ($len <= 4) return str_repeat('*', $len);
            return substr($identifier, 0, 3) . str_repeat('*', max(0, $len - 6)) . substr($identifier, -3);
        }
        
        return $identifier;
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

