<?php

namespace WP_SMS\Services\OTP\RestAPIEndpoints\Account;

use WP_REST_Server;
use WP_REST_Response;
use WP_REST_Request;
use WP_Error;
use WP_SMS\Services\OTP\RestAPIEndpoints\Abstracts\RestAPIEndpointsAbstract;
use WP_SMS\Services\OTP\Helpers\UserHelper;
use WP_SMS\Services\OTP\Models\IdentifierModel;

class AccountPhoneAPIEndpoint extends RestAPIEndpointsAbstract
{
    public function registerRoutes(): void
    {
        // Start phone change
        register_rest_route('wpsms/v1', '/account/phone', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'startPhoneChange'],
            'permission_callback' => 'is_user_logged_in',
            'args'                => $this->getStartArgs(),
        ]);

        // Verify phone change
        register_rest_route('wpsms/v1', '/account/phone/verify', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'verifyPhoneChange'],
            'permission_callback' => 'is_user_logged_in',
            'args'                => $this->getVerifyArgs(),
        ]);
    }

    /**
     * Start phone change - send verification code
     */
    public function startPhoneChange(WP_REST_Request $request)
    {
        try {
            $userId = get_current_user_id();
            $newPhone = (string) $request->get_param('phone');
            $ip = $this->getClientIp($request);

            // Normalize phone
            $newPhone = $this->normalizePhone($newPhone);

            // Check if phone is already in use
            $identifierModel = new IdentifierModel();
            $existing = $identifierModel->find([
                'value_hash' => md5($newPhone),
                'factor_type' => 'phone',
                'verified' => true,
            ]);

            if ($existing && (int) $existing['user_id'] !== $userId) {
                return $this->createErrorResponse(
                    'phone_in_use',
                    __('This phone number is already in use', 'wp-sms'),
                    409
                );
            }

            // Rate limiting
            $rateLimitCheck = $this->checkRateLimits('phone_change_' . $userId, $ip, 'account_phone_change');
            if (is_wp_error($rateLimitCheck)) {
                return $rateLimitCheck;
            }

            // Generate flow ID
            $flowId = uniqid('phone_change_', true);
            update_user_meta($userId, 'wpsms_phone_change_flow_id', $flowId);
            update_user_meta($userId, 'wpsms_phone_change_new', $newPhone);

            // Generate and send OTP
            $otpDigits = 6;
            $otpSession = $this->otpService->generate($flowId, $newPhone, $otpDigits);

            $sendResult = $this->otpService->sendOTP(
                $newPhone,
                $otpSession->code,
                'sms',
                ['template' => 'phone_change']
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
                'phone',
                $ip,
                null,
                ['user_id' => $userId]
            );

            $this->incrementRateLimits('phone_change_' . $userId, $ip, 'account_phone_change');

            $data = [
                'flow_id' => $flowId,
                'phone_masked' => $this->maskPhone($newPhone),
                'next_step' => 'verify',
                'otp_ttl_seconds' => 300,
            ];

            return $this->createSuccessResponse($data, __('Verification code sent to new phone number', 'wp-sms'));

        } catch (\Exception $e) {
            return $this->handleException($e, 'startPhoneChange');
        }
    }

    /**
     * Verify phone change
     */
    public function verifyPhoneChange(WP_REST_Request $request)
    {
        try {
            $userId = get_current_user_id();
            $flowId = (string) $request->get_param('flow_id');
            $code = (string) $request->get_param('code');
            $ip = $this->getClientIp($request);

            // Rate limiting
            $rateLimitCheck = $this->checkRateLimits('phone_verify_' . $userId, $ip, 'account_phone_verify');
            if (is_wp_error($rateLimitCheck)) {
                return $rateLimitCheck;
            }

            // Verify flow belongs to this user
            $storedFlowId = get_user_meta($userId, 'wpsms_phone_change_flow_id', true);
            if ($storedFlowId !== $flowId) {
                return $this->createErrorResponse(
                    'invalid_flow',
                    __('Invalid verification session', 'wp-sms'),
                    400
                );
            }

            // Get new phone from meta
            $newPhone = get_user_meta($userId, 'wpsms_phone_change_new', true);
            if (empty($newPhone)) {
                return $this->createErrorResponse(
                    'session_expired',
                    __('Verification session expired. Please start again.', 'wp-sms'),
                    400
                );
            }

            // Validate OTP
            $isValid = $this->otpService->validate($flowId, $code);
            if (!$isValid) {
                $this->incrementRateLimits('phone_verify_' . $userId, $ip, 'account_phone_verify');
                $this->logAuthEvent($flowId, 'identifier_verify', 'deny', 'phone', $ip, null, ['user_id' => $userId]);
                return $this->createErrorResponse(
                    'invalid_code',
                    __('Invalid or expired verification code', 'wp-sms'),
                    400
                );
            }

            // Update or create identifier record
            $identifierModel = new IdentifierModel();
            $existing = $identifierModel->getByUserAndType($userId, 'phone');

            if ($existing) {
                // Update existing
                IdentifierModel::updateBy(
                    [
                        'factor_value' => $newPhone,
                        'value_hash' => md5($newPhone),
                        'verified' => true,
                        'verified_at' => current_time('mysql'),
                    ],
                    ['id' => $existing['id']]
                );
            } else {
                // Create new
                IdentifierModel::insert([
                    'user_id' => $userId,
                    'factor_type' => 'phone',
                    'factor_value' => $newPhone,
                    'value_hash' => md5($newPhone),
                    'verified' => true,
                    'created_at' => current_time('mysql'),
                    'verified_at' => current_time('mysql'),
                ]);
            }

            // Update user meta
            update_user_meta($userId, 'wpsms_phone', $newPhone);

            // Clean up meta
            delete_user_meta($userId, 'wpsms_phone_change_flow_id');
            delete_user_meta($userId, 'wpsms_phone_change_new');

            // Log success
            $this->logAuthEvent(
                $flowId,
                'identifier_verify',
                'allow',
                'phone',
                $ip,
                null,
                ['user_id' => $userId]
            );

            $this->incrementRateLimits('phone_verify_' . $userId, $ip, 'account_phone_verify');

            return $this->createSuccessResponse(
                ['phone' => $newPhone],
                __('Phone number updated successfully', 'wp-sms')
            );

        } catch (\Exception $e) {
            return $this->handleException($e, 'verifyPhoneChange');
        }
    }

    /**
     * Get start arguments
     */
    private function getStartArgs(): array
    {
        return [
            'phone' => [
                'required' => true,
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'validate_callback' => [$this, 'validatePhone'],
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
     * Validate phone
     */
    public function validatePhone($value, $request, $param)
    {
        $cleanNumber = preg_replace('/[^\d+]/', '', $value);
        if (!preg_match('/^(\+\d{7,15}|\d{7,15})$/', $cleanNumber)) {
            return new WP_Error('invalid_phone', __('Invalid phone number format', 'wp-sms'), ['status' => 400]);
        }
        return true;
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
}
