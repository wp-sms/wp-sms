<?php

namespace WP_SMS\Services\OTP\RestAPIEndpoints\MFA;

use WP_REST_Server;
use WP_REST_Response;
use WP_REST_Request;
use WP_Error;
use WP_SMS\Services\OTP\RestAPIEndpoints\Abstracts\RestAPIEndpointsAbstract;
use WP_SMS\Services\OTP\Models\IdentifierModel;
use WP_SMS\Services\OTP\Helpers\ChannelSettingsHelper;

class MfaPhoneAPIEndpoint extends RestAPIEndpointsAbstract
{
    public function registerRoutes(): void
    {
        // Add phone MFA
        register_rest_route('wpsms/v1', '/mfa/phone/add', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'addPhoneMfa'],
            'permission_callback' => 'is_user_logged_in',
            'args'                => $this->getAddArgs(),
        ]);

        // Verify phone MFA
        register_rest_route('wpsms/v1', '/mfa/phone/verify', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'verifyPhoneMfa'],
            'permission_callback' => 'is_user_logged_in',
            'args'                => $this->getVerifyArgs(),
        ]);

        // Remove phone MFA
        register_rest_route('wpsms/v1', '/mfa/phone/(?P<id>\d+)', [
            'methods'             => WP_REST_Server::DELETABLE,
            'callback'            => [$this, 'removePhoneMfa'],
            'permission_callback' => 'is_user_logged_in',
        ]);
    }

    /**
     * Add phone MFA factor
     */
    public function addPhoneMfa(WP_REST_Request $request)
    {
        try {
            $userId = get_current_user_id();
            $phone = (string) $request->get_param('phone');
            $ip = $this->getClientIp($request);

            // Normalize phone
            $phone = $this->normalizePhone($phone);

            // Check if MFA phone is enabled
            $mfaSettings = ChannelSettingsHelper::getMfaPhoneChannelData();
            if (!$mfaSettings || !$mfaSettings['enabled']) {
                return $this->createErrorResponse(
                    'mfa_disabled',
                    __('Phone MFA is not enabled', 'wp-sms'),
                    403
                );
            }

            // Check if already enrolled
            $identifierModel = new IdentifierModel();
            $existing = $identifierModel->find([
                'user_id' => $userId,
                'factor_type' => 'phone',
                'value_hash' => md5($phone),
            ]);

            if ($existing) {
                return $this->createErrorResponse(
                    'already_enrolled',
                    __('This phone number is already enrolled as MFA', 'wp-sms'),
                    409
                );
            }

            // Rate limiting
            $rateLimitCheck = $this->checkRateLimits('mfa_phone_add_' . $userId, $ip, 'mfa_enroll');
            if (is_wp_error($rateLimitCheck)) {
                return $rateLimitCheck;
            }

            // Generate flow ID
            $flowId = uniqid('mfa_phone_', true);
            update_user_meta($userId, 'wpsms_mfa_phone_flow_id', $flowId);
            update_user_meta($userId, 'wpsms_mfa_phone_new', $phone);

            // Generate and send OTP
            $otpDigits = (int) ($mfaSettings['otp_digits'] ?? 6);
            $otpSession = $this->otpService->generate($flowId, $phone, $otpDigits);

            $sendResult = $this->otpService->sendOTP(
                $phone,
                $otpSession->code,
                'sms',
                ['template' => 'mfa_enrollment']
            );

            if (empty($sendResult['success'])) {
                return $this->createErrorResponse(
                    'send_failed',
                    $sendResult['error'] ?? __('Failed to send verification code', 'wp-sms'),
                    500
                );
            }

            // Log event
            $this->logAuthEvent(
                $flowId,
                'mfa_enroll_start',
                'allow',
                'phone',
                $ip,
                null,
                ['user_id' => $userId]
            );

            $this->incrementRateLimits('mfa_phone_add_' . $userId, $ip, 'mfa_enroll');

            $data = [
                'flow_id' => $flowId,
                'phone_masked' => $this->maskPhone($phone),
                'next_step' => 'verify',
                'otp_ttl_seconds' => (int) ($mfaSettings['expiry_seconds'] ?? 300),
            ];

            return $this->createSuccessResponse($data, __('Verification code sent to phone', 'wp-sms'));

        } catch (\Exception $e) {
            return $this->handleException($e, 'addPhoneMfa');
        }
    }

    /**
     * Verify and activate phone MFA
     */
    public function verifyPhoneMfa(WP_REST_Request $request)
    {
        try {
            $userId = get_current_user_id();
            $flowId = (string) $request->get_param('flow_id');
            $code = (string) $request->get_param('code');
            $ip = $this->getClientIp($request);

            // Rate limiting
            $rateLimitCheck = $this->checkRateLimits('mfa_phone_verify_' . $userId, $ip, 'mfa_enroll_verify');
            if (is_wp_error($rateLimitCheck)) {
                return $rateLimitCheck;
            }

            // Verify flow belongs to this user
            $storedFlowId = get_user_meta($userId, 'wpsms_mfa_phone_flow_id', true);
            if ($storedFlowId !== $flowId) {
                return $this->createErrorResponse(
                    'invalid_flow',
                    __('Invalid verification session', 'wp-sms'),
                    400
                );
            }

            // Get phone from meta
            $phone = get_user_meta($userId, 'wpsms_mfa_phone_new', true);
            if (empty($phone)) {
                return $this->createErrorResponse(
                    'session_expired',
                    __('Verification session expired. Please start again.', 'wp-sms'),
                    400
                );
            }

            // Validate OTP
            $isValid = $this->otpService->validate($flowId, $code);
            if (!$isValid) {
                $this->incrementRateLimits('mfa_phone_verify_' . $userId, $ip, 'mfa_enroll_verify');
                $this->logAuthEvent($flowId, 'mfa_enroll_verify', 'deny', 'phone', $ip, null, ['user_id' => $userId]);
                return $this->createErrorResponse(
                    'invalid_code',
                    __('Invalid or expired verification code', 'wp-sms'),
                    400
                );
            }

            // Create new MFA identifier
            $factorId = IdentifierModel::insert([
                'user_id' => $userId,
                'factor_type' => 'phone',
                'factor_value' => $phone,
                'value_hash' => md5($phone),
                'verified' => true,
                'created_at' => current_time('mysql'),
                'verified_at' => current_time('mysql'),
            ]);

            // Clean up meta
            delete_user_meta($userId, 'wpsms_mfa_phone_flow_id');
            delete_user_meta($userId, 'wpsms_mfa_phone_new');

            // Log success
            $this->logAuthEvent(
                $flowId,
                'mfa_enroll_verify',
                'allow',
                'phone',
                $ip,
                null,
                ['user_id' => $userId, 'factor_id' => $factorId]
            );

            $this->incrementRateLimits('mfa_phone_verify_' . $userId, $ip, 'mfa_enroll_verify');

            return $this->createSuccessResponse(
                ['factor_id' => $factorId, 'type' => 'phone'],
                __('Phone MFA enrolled successfully', 'wp-sms')
            );

        } catch (\Exception $e) {
            return $this->handleException($e, 'verifyPhoneMfa');
        }
    }

    /**
     * Remove phone MFA factor
     */
    public function removePhoneMfa(WP_REST_Request $request)
    {
        try {
            $userId = get_current_user_id();
            $factorId = (int) $request->get_param('id');
            $ip = $this->getClientIp($request);

            // Get the factor
            $factor = IdentifierModel::find(['id' => $factorId]);

            if (!$factor) {
                return $this->createErrorResponse(
                    'factor_not_found',
                    __('MFA factor not found', 'wp-sms'),
                    404
                );
            }

            // Verify ownership
            if ((int) $factor['user_id'] !== $userId) {
                return $this->createErrorResponse(
                    'unauthorized',
                    __('You do not have permission to remove this factor', 'wp-sms'),
                    403
                );
            }

            // Verify it's a phone factor
            if ($factor['factor_type'] !== 'phone') {
                return $this->createErrorResponse(
                    'invalid_type',
                    __('This is not a phone MFA factor', 'wp-sms'),
                    400
                );
            }

            // Check if this is the last factor (safety check)
            $identifierModel = new IdentifierModel();
            $allFactors = $identifierModel->getAllByUserId($userId);
            if (count($allFactors) <= 1) {
                return $this->createErrorResponse(
                    'last_factor',
                    __('Cannot remove the last MFA factor. Add another factor first.', 'wp-sms'),
                    400
                );
            }

            // Delete the factor
            IdentifierModel::deleteBy(['id' => $factorId]);

            // Log event
            $this->logAuthEvent(
                'remove_' . $factorId,
                'mfa_remove',
                'allow',
                'phone',
                $ip,
                null,
                ['user_id' => $userId, 'factor_id' => $factorId]
            );

            return $this->createSuccessResponse(
                ['factor_id' => $factorId],
                __('Phone MFA removed successfully', 'wp-sms')
            );

        } catch (\Exception $e) {
            return $this->handleException($e, 'removePhoneMfa');
        }
    }

    /**
     * Get add arguments
     */
    private function getAddArgs(): array
    {
        return [
            'phone' => [
                'required' => true,
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'validate_callback' => function($value) {
                    $cleanNumber = preg_replace('/[^\d+]/', '', $value);
                    if (!preg_match('/^(\+\d{7,15}|\d{7,15})$/', $cleanNumber)) {
                        return new WP_Error('invalid_phone', __('Invalid phone number format', 'wp-sms'), ['status' => 400]);
                    }
                    return true;
                },
            ],
        ];
    }

    /**
     * Get verify arguments
     */
    private function getVerifyArgs(): array
    {
        return [
            'flow_id' => [
                'required' => true,
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'code' => [
                'required' => true,
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
        ];
    }

    /**
     * Normalize phone
     */
    private function normalizePhone(string $phone): string
    {
        $normalized = preg_replace('/[^\d\+]/', '', $phone);
        if (strpos($normalized, '+') > 0) {
            $normalized = '+' . preg_replace('/\+/', '', $normalized);
        }
        return $normalized;
    }

    /**
     * Mask phone
     */
    private function maskPhone(string $phone): string
    {
        $len = strlen($phone);
        if ($len <= 4) return str_repeat('*', $len);
        return substr($phone, 0, 3) . str_repeat('*', max(0, $len - 6)) . substr($phone, -3);
    }
}

