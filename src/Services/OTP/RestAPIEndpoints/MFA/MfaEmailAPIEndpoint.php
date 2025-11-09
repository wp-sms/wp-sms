<?php

namespace WP_SMS\Services\OTP\RestAPIEndpoints\MFA;

use WP_REST_Server;
use WP_REST_Response;
use WP_REST_Request;
use WP_Error;
use WP_SMS\Services\OTP\RestAPIEndpoints\Abstracts\RestAPIEndpointsAbstract;
use WP_SMS\Services\OTP\Models\IdentifierModel;
use WP_SMS\Services\OTP\Helpers\ChannelSettingsHelper;

class MfaEmailAPIEndpoint extends RestAPIEndpointsAbstract
{
    public function registerRoutes(): void
    {
        // Add email MFA
        register_rest_route('wpsms/v1', '/mfa/email/add', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'addEmailMfa'],
            'permission_callback' => 'is_user_logged_in',
            'args'                => $this->getAddArgs(),
        ]);

        // Verify email MFA
        register_rest_route('wpsms/v1', '/mfa/email/verify', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'verifyEmailMfa'],
            'permission_callback' => 'is_user_logged_in',
            'args'                => $this->getVerifyArgs(),
        ]);

        // Remove email MFA
        register_rest_route('wpsms/v1', '/mfa/email/(?P<id>\d+)', [
            'methods'             => WP_REST_Server::DELETABLE,
            'callback'            => [$this, 'removeEmailMfa'],
            'permission_callback' => 'is_user_logged_in',
        ]);
    }

    /**
     * Add email MFA factor
     */
    public function addEmailMfa(WP_REST_Request $request)
    {
        try {
            $userId = get_current_user_id();
            $email = (string) $request->get_param('email');
            $ip = $this->getClientIp($request);

            // Normalize email
            $email = strtolower(trim($email));

            // Check if MFA email is enabled
            $mfaSettings = ChannelSettingsHelper::getMfaEmailChannelData();
            if (!$mfaSettings || !$mfaSettings['enabled']) {
                return $this->createErrorResponse(
                    'mfa_disabled',
                    __('Email MFA is not enabled', 'wp-sms'),
                    403
                );
            }

            // Check if already enrolled
            $identifierModel = new IdentifierModel();
            $existing = $identifierModel->find([
                'user_id' => $userId,
                'factor_type' => 'email',
                'value_hash' => md5($email),
            ]);

            if ($existing) {
                return $this->createErrorResponse(
                    'already_enrolled',
                    __('This email is already enrolled as MFA', 'wp-sms'),
                    409
                );
            }

            // Rate limiting
            $rateLimitCheck = $this->checkRateLimits('mfa_email_add_' . $userId, $ip, 'mfa_enroll');
            if (is_wp_error($rateLimitCheck)) {
                return $rateLimitCheck;
            }

            // Generate flow ID
            $flowId = uniqid('mfa_email_', true);
            update_user_meta($userId, 'wpsms_mfa_email_flow_id', $flowId);
            update_user_meta($userId, 'wpsms_mfa_email_new', $email);

            // Generate and send OTP
            $otpDigits = (int) ($mfaSettings['otp_digits'] ?? 6);
            $otpSession = $this->otpService->generate($flowId, $email, $otpDigits);

            $sendResult = $this->otpService->sendOTP(
                $email,
                $otpSession->code,
                'email',
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
                'email',
                $ip,
                null,
                ['user_id' => $userId]
            );

            $this->incrementRateLimits('mfa_email_add_' . $userId, $ip, 'mfa_enroll');

            $data = [
                'flow_id' => $flowId,
                'email_masked' => $this->maskEmail($email),
                'next_step' => 'verify',
                'otp_ttl_seconds' => (int) ($mfaSettings['expiry_seconds'] ?? 300),
            ];

            return $this->createSuccessResponse($data, __('Verification code sent to email', 'wp-sms'));

        } catch (\Exception $e) {
            return $this->handleException($e, 'addEmailMfa');
        }
    }

    /**
     * Verify and activate email MFA
     */
    public function verifyEmailMfa(WP_REST_Request $request)
    {
        try {
            $userId = get_current_user_id();
            $flowId = (string) $request->get_param('flow_id');
            $code = (string) $request->get_param('code');
            $ip = $this->getClientIp($request);

            // Rate limiting
            $rateLimitCheck = $this->checkRateLimits('mfa_email_verify_' . $userId, $ip, 'mfa_enroll_verify');
            if (is_wp_error($rateLimitCheck)) {
                return $rateLimitCheck;
            }

            // Verify flow belongs to this user
            $storedFlowId = get_user_meta($userId, 'wpsms_mfa_email_flow_id', true);
            if ($storedFlowId !== $flowId) {
                return $this->createErrorResponse(
                    'invalid_flow',
                    __('Invalid verification session', 'wp-sms'),
                    400
                );
            }

            // Get email from meta
            $email = get_user_meta($userId, 'wpsms_mfa_email_new', true);
            if (empty($email)) {
                return $this->createErrorResponse(
                    'session_expired',
                    __('Verification session expired. Please start again.', 'wp-sms'),
                    400
                );
            }

            // Validate OTP
            $isValid = $this->otpService->validate($flowId, $code);
            if (!$isValid) {
                $this->incrementRateLimits('mfa_email_verify_' . $userId, $ip, 'mfa_enroll_verify');
                $this->logAuthEvent($flowId, 'mfa_enroll_verify', 'deny', 'email', $ip, null, ['user_id' => $userId]);
                return $this->createErrorResponse(
                    'invalid_code',
                    __('Invalid or expired verification code', 'wp-sms'),
                    400
                );
            }

            // Create new MFA identifier
            $factorId = IdentifierModel::insert([
                'user_id' => $userId,
                'factor_type' => 'email',
                'factor_value' => $email,
                'value_hash' => md5($email),
                'verified' => true,
                'created_at' => current_time('mysql'),
                'verified_at' => current_time('mysql'),
            ]);

            // Clean up meta
            delete_user_meta($userId, 'wpsms_mfa_email_flow_id');
            delete_user_meta($userId, 'wpsms_mfa_email_new');

            // Log success
            $this->logAuthEvent(
                $flowId,
                'mfa_enroll_verify',
                'allow',
                'email',
                $ip,
                null,
                ['user_id' => $userId, 'factor_id' => $factorId]
            );

            $this->incrementRateLimits('mfa_email_verify_' . $userId, $ip, 'mfa_enroll_verify');

            return $this->createSuccessResponse(
                ['factor_id' => $factorId, 'type' => 'email'],
                __('Email MFA enrolled successfully', 'wp-sms')
            );

        } catch (\Exception $e) {
            return $this->handleException($e, 'verifyEmailMfa');
        }
    }

    /**
     * Remove email MFA factor
     */
    public function removeEmailMfa(WP_REST_Request $request)
    {
        try {
            $userId = get_current_user_id();
            $factorId = (int) $request->get_param('id');
            $ip = $this->getClientIp($request);

            // Get the factor
            $identifierModel = new IdentifierModel();
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

            // Verify it's an email factor
            if ($factor['factor_type'] !== 'email') {
                return $this->createErrorResponse(
                    'invalid_type',
                    __('This is not an email MFA factor', 'wp-sms'),
                    400
                );
            }

            // Check if this is the last factor (safety check)
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
                'email',
                $ip,
                null,
                ['user_id' => $userId, 'factor_id' => $factorId]
            );

            return $this->createSuccessResponse(
                ['factor_id' => $factorId],
                __('Email MFA removed successfully', 'wp-sms')
            );

        } catch (\Exception $e) {
            return $this->handleException($e, 'removeEmailMfa');
        }
    }

    /**
     * Get add arguments
     */
    private function getAddArgs(): array
    {
        return [
            'email' => [
                'required' => true,
                'type' => 'string',
                'sanitize_callback' => 'sanitize_email',
                'validate_callback' => function($value) {
                    if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                        return new WP_Error('invalid_email', __('Invalid email format', 'wp-sms'), ['status' => 400]);
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
     * Mask email
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
     * Mask phone
     */
    private function maskPhone(string $phone): string
    {
        $len = strlen($phone);
        if ($len <= 4) return str_repeat('*', $len);
        return substr($phone, 0, 3) . str_repeat('*', max(0, $len - 6)) . substr($phone, -3);
    }
}

