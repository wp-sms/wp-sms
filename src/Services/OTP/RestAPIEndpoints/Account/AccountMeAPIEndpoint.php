<?php

namespace WP_SMS\Services\OTP\RestAPIEndpoints\Account;

use WP_REST_Server;
use WP_REST_Response;
use WP_REST_Request;
use WP_Error;
use WP_SMS\Services\OTP\RestAPIEndpoints\Abstracts\RestAPIEndpointsAbstract;
use WP_SMS\Services\OTP\Helpers\UserHelper;
use WP_SMS\Services\OTP\Models\IdentifierModel;

class AccountMeAPIEndpoint extends RestAPIEndpointsAbstract
{
    public function registerRoutes(): void
    {
        // GET - Retrieve profile
        register_rest_route('wpsms/v1', '/account/me', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'getProfile'],
            'permission_callback' => 'is_user_logged_in',
        ]);

        // POST - Update profile
        register_rest_route('wpsms/v1', '/account/me', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'updateProfile'],
            'permission_callback' => 'is_user_logged_in',
            'args'                => $this->getUpdateArgs(),
        ]);
    }

    /**
     * Get profile data
     */
    public function getProfile(WP_REST_Request $request)
    {
        try {
            $userId = get_current_user_id();
            $user = get_user_by('id', $userId);

            if (!$user) {
                return $this->createErrorResponse(
                    'user_not_found',
                    __('User not found', 'wp-sms'),
                    404
                );
            }

            // Get identifiers
            $identifierModel = new IdentifierModel();
            $identifiers = $identifierModel->getAllByUserId($userId);

            $email = null;
            $phone = null;

            foreach ($identifiers as $identifier) {
                if ($identifier['factor_type'] === 'email') {
                    $email = [
                        'id' => $identifier['id'],
                        'value_masked' => $this->maskEmail($identifier['factor_value']),
                        'verified' => (bool) $identifier['verified'],
                        'verified_at' => $identifier['verified_at'],
                    ];
                }
                if ($identifier['factor_type'] === 'phone') {
                    $phone = [
                        'id' => $identifier['id'],
                        'value_masked' => $this->maskPhone($identifier['factor_value']),
                        'verified' => (bool) $identifier['verified'],
                        'verified_at' => $identifier['verified_at'],
                    ];
                }
            }

            // Get MFA summary
            $mfaFactors = array_filter($identifiers, function($id) {
                return in_array($id['factor_type'], ['email', 'phone', 'totp', 'webauthn', 'backup']);
            });

            $enrolledTypes = array_unique(array_column($mfaFactors, 'factor_type'));

            $data = [
                'user' => [
                    'id' => $userId,
                    'username' => $user->user_login,
                    'first_name' => get_user_meta($userId, 'first_name', true),
                    'last_name' => get_user_meta($userId, 'last_name', true),
                    'display_name' => $user->display_name,
                    'email' => $email ?: ['value_masked' => $this->maskEmail($user->user_email), 'verified' => false],
                    'phone' => $phone,
                    'locale' => get_user_meta($userId, 'locale', true) ?: get_locale(),
                    'avatar_url' => get_avatar_url($userId),
                ],
                'mfa_summary' => [
                    'enrolled' => $enrolledTypes,
                    'allowed' => ['email', 'phone'], // TOTP and others coming soon
                    'total_factors' => count($mfaFactors),
                ]
            ];

            return $this->createSuccessResponse($data, __('Profile retrieved successfully', 'wp-sms'));

        } catch (\Exception $e) {
            return $this->handleException($e, 'getProfile');
        }
    }

    /**
     * Update profile
     */
    public function updateProfile(WP_REST_Request $request)
    {
        try {
            $userId = get_current_user_id();
            $user = get_user_by('id', $userId);

            if (!$user) {
                return $this->createErrorResponse(
                    'user_not_found',
                    __('User not found', 'wp-sms'),
                    404
                );
            }

            $updated = false;

            // Update first name
            if ($request->has_param('first_name')) {
                update_user_meta($userId, 'first_name', sanitize_text_field($request->get_param('first_name')));
                $updated = true;
            }

            // Update last name
            if ($request->has_param('last_name')) {
                update_user_meta($userId, 'last_name', sanitize_text_field($request->get_param('last_name')));
                $updated = true;
            }

            // Update display name
            if ($request->has_param('display_name')) {
                wp_update_user([
                    'ID' => $userId,
                    'display_name' => sanitize_text_field($request->get_param('display_name'))
                ]);
                $updated = true;
            }

            // Update locale
            if ($request->has_param('locale')) {
                update_user_meta($userId, 'locale', sanitize_text_field($request->get_param('locale')));
                $updated = true;
            }

            if (!$updated) {
                return $this->createErrorResponse(
                    'no_changes',
                    __('No changes were made', 'wp-sms'),
                    400
                );
            }

            // Log event
            $this->logAuthEvent(
                'profile_' . $userId,
                'profile_update',
                'allow',
                'system',
                $this->getClientIp($request),
                null,
                ['user_id' => $userId]
            );

            // Return updated profile
            return $this->getProfile($request);

        } catch (\Exception $e) {
            return $this->handleException($e, 'updateProfile');
        }
    }

    /**
     * Get update arguments
     */
    private function getUpdateArgs(): array
    {
        return [
            'first_name' => [
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'last_name' => [
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'display_name' => [
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'locale' => [
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
        ];
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
}

