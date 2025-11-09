<?php

namespace WP_SMS\Services\OTP\RestAPIEndpoints\Account;

use WP_REST_Server;
use WP_REST_Response;
use WP_REST_Request;
use WP_Error;
use WP_SMS\Services\OTP\RestAPIEndpoints\Abstracts\RestAPIEndpointsAbstract;
use WP_SMS\Services\OTP\Helpers\UserHelper;
use WP_SMS\Services\OTP\Models\IdentifierModel;
use WP_SMS\Services\OTP\Models\OtpSessionModel;

class AccountEmailAPIEndpoint extends RestAPIEndpointsAbstract
{
    public function registerRoutes(): void
    {
        // Start email change
        register_rest_route('wpsms/v1', '/account/email', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'startEmailChange'],
            'permission_callback' => 'is_user_logged_in',
            'args'                => $this->getStartArgs(),
        ]);

        // Verify email change
        register_rest_route('wpsms/v1', '/account/email/verify', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'verifyEmailChange'],
            'permission_callback' => 'is_user_logged_in',
            'args'                => $this->getVerifyArgs(),
        ]);
    }

    /**
     * Start email change - send verification code
     */
    public function startEmailChange(WP_REST_Request $request)
    {
        try {
            $userId = get_current_user_id();
            $newEmail = (string) $request->get_param('email');
            $ip = $this->getClientIp($request);

            // Normalize email
            $newEmail = strtolower(trim($newEmail));

            // Check if email is already in use
            $existingUser = get_user_by('email', $newEmail);
            if ($existingUser && $existingUser->ID !== $userId) {
                return $this->createErrorResponse(
                    'email_in_use',
                    __('This email is already in use by another account', 'wp-sms'),
                    409
                );
            }

            // Check in identifiers table
            $identifierModel = new IdentifierModel();
            $existing = $identifierModel->find([
                'value_hash' => md5($newEmail),
                'factor_type' => 'email',
                'verified' => true,
            ]);

            if ($existing && (int) $existing['user_id'] !== $userId) {
                return $this->createErrorResponse(
                    'email_in_use',
                    __('This email is already in use', 'wp-sms'),
                    409
                );
            }

            // Rate limiting
            $rateLimitCheck = $this->checkRateLimits('email_change_' . $userId, $ip, 'account_email_change');
            if (is_wp_error($rateLimitCheck)) {
                return $rateLimitCheck;
            }

            // Generate flow ID
            $flowId = uniqid('email_change_', true);
            update_user_meta($userId, 'wpsms_email_change_flow_id', $flowId);
            update_user_meta($userId, 'wpsms_email_change_new', $newEmail);

            // Generate and send OTP
            $otpDigits = 6;
            $otpSession = $this->otpService->generate($flowId, $newEmail, $otpDigits);

            $sendResult = $this->otpService->sendOTP(
                $newEmail,
                $otpSession->code,
                'email',
                ['template' => 'email_change']
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
                'identifier_update_start',
                'allow',
                'email',
                $ip,
                null,
                ['user_id' => $userId]
            );

            $this->incrementRateLimits('email_change_' . $userId, $ip, 'account_email_change');

            $data = [
                'flow_id' => $flowId,
                'email_masked' => $this->maskEmail($newEmail),
                'next_step' => 'verify',
                'otp_ttl_seconds' => 300,
            ];

            return $this->createSuccessResponse($data, __('Verification code sent to new email address', 'wp-sms'));

        } catch (\Exception $e) {
            return $this->handleException($e, 'startEmailChange');
        }
    }

    /**
     * Verify email change
     */
    public function verifyEmailChange(WP_REST_Request $request)
    {
        try {
            $userId = get_current_user_id();
            $flowId = (string) $request->get_param('flow_id');
            $code = (string) $request->get_param('code');
            $ip = $this->getClientIp($request);

            // Rate limiting
            $rateLimitCheck = $this->checkRateLimits('email_verify_' . $userId, $ip, 'account_email_verify');
            if (is_wp_error($rateLimitCheck)) {
                return $rateLimitCheck;
            }

            // Verify flow belongs to this user
            $storedFlowId = get_user_meta($userId, 'wpsms_email_change_flow_id', true);
            if ($storedFlowId !== $flowId) {
                return $this->createErrorResponse(
                    'invalid_flow',
                    __('Invalid verification session', 'wp-sms'),
                    400
                );
            }

            // Get new email from meta
            $newEmail = get_user_meta($userId, 'wpsms_email_change_new', true);
            if (empty($newEmail)) {
                return $this->createErrorResponse(
                    'session_expired',
                    __('Verification session expired. Please start again.', 'wp-sms'),
                    400
                );
            }

            // Validate OTP
            $isValid = $this->otpService->validate($flowId, $code);
            if (!$isValid) {
                $this->incrementRateLimits('email_verify_' . $userId, $ip, 'account_email_verify');
                $this->logAuthEvent($flowId, 'identifier_verify', 'deny', 'email', $ip, null, ['user_id' => $userId]);
                return $this->createErrorResponse(
                    'invalid_code',
                    __('Invalid or expired verification code', 'wp-sms'),
                    400
                );
            }

            // Update email in WordPress user
            $updateResult = wp_update_user([
                'ID' => $userId,
                'user_email' => $newEmail,
            ]);

            if (is_wp_error($updateResult)) {
                return $this->createErrorResponse(
                    'update_failed',
                    __('Failed to update email address', 'wp-sms'),
                    500
                );
            }

            // Update or create identifier record
            $identifierModel = new IdentifierModel();
            $existing = $identifierModel->getByUserAndType($userId, 'email');

            if ($existing) {
                // Update existing
                IdentifierModel::updateBy(
                    [
                        'factor_value' => $newEmail,
                        'value_hash' => md5($newEmail),
                        'verified' => true,
                        'verified_at' => current_time('mysql'),
                    ],
                    ['id' => $existing['id']]
                );
            } else {
                // Create new
                IdentifierModel::insert([
                    'user_id' => $userId,
                    'factor_type' => 'email',
                    'factor_value' => $newEmail,
                    'value_hash' => md5($newEmail),
                    'verified' => true,
                    'created_at' => current_time('mysql'),
                    'verified_at' => current_time('mysql'),
                ]);
            }

            // Clean up meta
            delete_user_meta($userId, 'wpsms_email_change_flow_id');
            delete_user_meta($userId, 'wpsms_email_change_new');

            // Log success
            $this->logAuthEvent(
                $flowId,
                'identifier_verify',
                'allow',
                'email',
                $ip,
                null,
                ['user_id' => $userId]
            );

            $this->incrementRateLimits('email_verify_' . $userId, $ip, 'account_email_verify');

            return $this->createSuccessResponse(
                ['email' => $newEmail],
                __('Email address updated successfully', 'wp-sms')
            );

        } catch (\Exception $e) {
            return $this->handleException($e, 'verifyEmailChange');
        }
    }

    /**
     * Get start arguments
     */
    private function getStartArgs(): array
    {
        return [
            'email' => [
                'required' => true,
                'type' => 'string',
                'sanitize_callback' => 'sanitize_email',
                'validate_callback' => [$this, 'validateEmail'],
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
     * Validate email
     */
    public function validateEmail($value, $request, $param)
    {
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return new WP_Error('invalid_email', __('Invalid email format', 'wp-sms'), ['status' => 400]);
        }
        return true;
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
