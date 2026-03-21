<?php

namespace WSms\Rest;

use WSms\PhoneRestriction\CountryResolver;
use WSms\PhoneRestriction\DatabaseUpdater;
use WSms\PhoneRestriction\EnhancedPhoneDatabase;
use WSms\PhoneRestriction\RestrictionSettings;
use WSms\PhoneRestriction\SendingPolicyGuard;

defined('ABSPATH') || exit;

class PhoneRestrictionController extends Controller
{
    public function __construct(
        private readonly RestrictionSettings $settings,
        private readonly CountryResolver $resolver,
        private readonly SendingPolicyGuard $guard,
        private readonly DatabaseUpdater $dbUpdater,
    ) {
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/phone-restriction/settings', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'getSettings'],
                'permission_callback' => [$this, 'canManage'],
            ],
            [
                'methods'             => 'PUT',
                'callback'            => [$this, 'updateSettings'],
                'permission_callback' => [$this, 'canManage'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/phone-restriction/db/download', [
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'downloadDb'],
                'permission_callback' => [$this, 'canManage'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/phone-restriction/db/status', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'dbStatus'],
                'permission_callback' => [$this, 'canManage'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/phone-restriction/check', [
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'checkPhone'],
                'permission_callback' => [$this, 'canManage'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/phone-restriction/countries', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'countries'],
                'permission_callback' => [$this, 'canManage'],
            ],
        ]);
    }

    public function getSettings(): \WP_REST_Response
    {
        return new \WP_REST_Response([
            'success'   => true,
            'settings'  => $this->settings->all(),
            'db_status' => EnhancedPhoneDatabase::getStatus(),
        ]);
    }

    public function updateSettings(\WP_REST_Request $request): \WP_REST_Response
    {
        $body = $request->get_json_params();

        if (!is_array($body) || empty($body)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => __('Invalid settings data.', 'wp-sms'),
            ], 400);
        }

        $sanitized = $this->sanitizeSettings($body);
        $this->settings->save($sanitized);

        return new \WP_REST_Response([
            'success'  => true,
            'settings' => $this->settings->all(),
        ]);
    }

    public function downloadDb(): \WP_REST_Response
    {
        $result = $this->dbUpdater->download();

        return new \WP_REST_Response([
            'success'   => $result['success'],
            'message'   => $result['message'],
            'db_status' => EnhancedPhoneDatabase::getStatus(),
        ], $result['success'] ? 200 : 502);
    }

    public function dbStatus(): \WP_REST_Response
    {
        return new \WP_REST_Response([
            'success'   => true,
            'db_status' => EnhancedPhoneDatabase::getStatus(),
        ]);
    }

    public function checkPhone(\WP_REST_Request $request): \WP_REST_Response
    {
        $body    = $request->get_json_params();
        $phone   = $body['phone'] ?? '';
        $context = $body['context'] ?? 'auth';

        if (empty($phone) || !is_string($phone)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => __('Phone number is required.', 'wp-sms'),
            ], 400);
        }

        if (!in_array($context, ['auth', 'messaging'], true)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => __('Context must be "auth" or "messaging".', 'wp-sms'),
            ], 400);
        }

        $result    = $this->guard->check($phone, $context);
        $countries = $this->resolver->getAllCountries();

        return new \WP_REST_Response([
            'success'      => true,
            'allowed'      => $result->allowed,
            'country'      => $result->country,
            'country_name' => $countries[$result->country] ?? null,
            'number_type'  => $result->numberType,
            'reason'       => $result->reason,
        ]);
    }

    public function countries(): \WP_REST_Response
    {
        return new \WP_REST_Response([
            'success'   => true,
            'countries' => $this->resolver->getAllCountries(),
        ]);
    }

    private function sanitizeSettings(array $body): array
    {
        $sanitized = [];
        $validSections = array_keys(RestrictionSettings::defaults());

        foreach ($validSections as $section) {
            if (!isset($body[$section]) || !is_array($body[$section])) {
                continue;
            }

            $sanitized[$section] = $this->sanitizeSection($body[$section]);
        }

        return $sanitized;
    }

    private function sanitizeSection(array $values): array
    {
        $sanitized     = [];
        $booleanFields = ['enabled', 'auto_update'];
        $arrayFields   = ['allowed_countries', 'blocked_types'];

        foreach ($values as $key => $value) {
            if ($key === 'mode') {
                $sanitized[$key] = in_array($value, ['allow', 'block'], true) ? $value : 'allow';
            } elseif (in_array($key, $booleanFields, true)) {
                $sanitized[$key] = (bool) $value;
            } elseif (in_array($key, $arrayFields, true)) {
                $sanitized[$key] = is_array($value)
                    ? array_values(array_filter(array_map('sanitize_text_field', $value)))
                    : [];
            }
        }

        return $sanitized;
    }
}
