<?php

namespace WP_SMS\Services\OTP\RestAPIEndpoints\MFA;

use WP_REST_Server;
use WP_REST_Response;
use WP_REST_Request;
use WP_Error;
use WP_SMS\Services\OTP\RestAPIEndpoints\Abstracts\RestAPIEndpointsAbstract;
use WP_SMS\Services\OTP\Models\IdentifierModel;
use WP_SMS\Services\OTP\Helpers\ChannelSettingsHelper;

class MfaFactorsAPIEndpoint extends RestAPIEndpointsAbstract
{
    public function registerRoutes(): void
    {
        register_rest_route('wpsms/v1', '/mfa/factors', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'handleRequest'],
            'permission_callback' => 'is_user_logged_in',
        ]);
    }

    /**
     * Get all MFA factors for current user
     */
    public function handleRequest(WP_REST_Request $request)
    {
        try {
            $userId = get_current_user_id();

            // Get all identifiers for this user
            $identifierModel = new IdentifierModel();
            $identifiers = $identifierModel->getAllByUserId($userId);

            // Get MFA settings
            $mfaChannels = ChannelSettingsHelper::getMfaChannelsData();

            // Build factors list
            $factors = [];

            foreach ($identifiers as $identifier) {
                $factorType = $identifier['factor_type'];

                // Only include MFA-eligible types
                if (!in_array($factorType, ['email', 'phone', 'totp', 'webauthn', 'backup'])) {
                    continue;
                }

                $factors[] = [
                    'id' => $identifier['id'],
                    'type' => $factorType,
                    'label' => $this->getFactorLabel($factorType, $identifier['factor_value']),
                    'value_masked' => $this->maskValue($identifier['factor_value'], $factorType),
                    'verified' => (bool) $identifier['verified'],
                    'verified_at' => $identifier['verified_at'],
                    'last_used_at' => $identifier['last_used_at'],
                    'created_at' => $identifier['created_at'],
                ];
            }

            // Get allowed MFA types
            $allowedTypes = [];
            foreach ($mfaChannels as $type => $config) {
                if (!empty($config['enabled'])) {
                    $allowedTypes[] = $type;
                }
            }

            $data = [
                'factors' => $factors,
                'allowed_types' => $allowedTypes,
                'total_enrolled' => count($factors),
                'policy' => [
                    'min_factors' => 0, // Can be configured
                    'allowed_types' => $allowedTypes,
                ]
            ];

            return $this->createSuccessResponse($data, __('MFA factors retrieved successfully', 'wp-sms'));

        } catch (\Exception $e) {
            return $this->handleException($e, 'getMfaFactors');
        }
    }

    /**
     * Get human-readable label for factor
     */
    private function getFactorLabel(string $type, string $value): string
    {
        switch ($type) {
            case 'email':
                return __('Email OTP', 'wp-sms') . ' (' . $this->maskEmail($value) . ')';
            case 'phone':
                return __('Phone OTP', 'wp-sms') . ' (' . $this->maskPhone($value) . ')';
            case 'totp':
                return __('Authenticator App (TOTP)', 'wp-sms');
            case 'webauthn':
                return __('Security Key / Biometric', 'wp-sms');
            case 'backup':
                return __('Backup Codes', 'wp-sms');
            default:
                return ucfirst($type);
        }
    }

    /**
     * Mask value based on type
     */
    private function maskValue(string $value, string $type): string
    {
        if ($type === 'email') {
            return $this->maskEmail($value);
        }
        if ($type === 'phone') {
            return $this->maskPhone($value);
        }
        return '***';
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

