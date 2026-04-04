<?php

namespace WSms\Rest;

use WSms\Exception\ValidationException;
use WSms\PhoneRestriction\CountryResolver;
use WSms\PhoneRestriction\DatabaseUpdater;
use WSms\PhoneRestriction\EnhancedPhoneDatabase;
use WSms\PhoneRestriction\RestrictionSettings;
use WSms\PhoneRestriction\SendingPolicyGuard;
use WSms\Support\GeoCountryResolver;

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
                'permission_callback' => $this->canManageSection('settings'),
            ],
            [
                'methods'             => 'PUT',
                'callback'            => [$this, 'updateSettings'],
                'permission_callback' => $this->canManageSection('settings'),
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/phone-restriction/db/download', [
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'downloadDb'],
                'permission_callback' => $this->canManageSection('settings'),
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/phone-restriction/db/status', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'dbStatus'],
                'permission_callback' => $this->canManageSection('settings'),
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/phone-restriction/check', [
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'checkPhone'],
                'permission_callback' => $this->canManageSection('settings'),
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/phone-restriction/countries', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'countries'],
                'permission_callback' => $this->canManageSection('settings'),
            ],
        ]);
    }

    public function getSettings(): \WP_REST_Response
    {
        return $this->handle(function () {
            $geo = GeoCountryResolver::resolveWithSource();

            return new \WP_REST_Response([
                'success'       => true,
                'settings'      => $this->settings->all(),
                'db_status'     => EnhancedPhoneDatabase::getStatus(),
                'geo_detection' => [
                    'active'  => $geo['country'] !== null,
                    'country' => $geo['country'],
                    'source'  => $geo['source'],
                ],
            ]);
        });
    }

    public function updateSettings(\WP_REST_Request $request): \WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            $body = $request->get_json_params();

            if (!is_array($body) || empty($body)) {
                throw ValidationException::field('settings', __('Invalid settings data.', 'wp-sms'));
            }

            $sanitized = $this->sanitizeSettings($body);
            $this->settings->save($sanitized);

            return new \WP_REST_Response([
                'success'  => true,
                'settings' => $this->settings->all(),
            ]);
        });
    }

    public function downloadDb(): \WP_REST_Response
    {
        return $this->handle(function () {
            $result = $this->dbUpdater->download();

            return new \WP_REST_Response([
                'success'   => $result['success'],
                'message'   => $result['message'],
                'db_status' => EnhancedPhoneDatabase::getStatus(),
            ], $result['success'] ? 200 : 502);
        });
    }

    public function dbStatus(): \WP_REST_Response
    {
        return $this->handle(function () {
            return new \WP_REST_Response([
                'success'   => true,
                'db_status' => EnhancedPhoneDatabase::getStatus(),
            ]);
        });
    }

    public function checkPhone(\WP_REST_Request $request): \WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            $body  = $request->get_json_params();
            $phone = $body['phone'] ?? '';

            if (empty($phone) || !is_string($phone)) {
                throw ValidationException::field('phone', __('Phone number is required.', 'wp-sms'));
            }

            $authResult      = $this->guard->check($phone, 'auth');
            $messagingResult = $this->guard->check($phone, 'messaging');
            $countries       = $this->resolver->getAllCountries();

            return new \WP_REST_Response([
                'success'      => true,
                'country'      => $authResult->country,
                'country_name' => $countries[$authResult->country] ?? null,
                'number_type'  => $authResult->numberType,
                'auth'         => [
                    'allowed' => $authResult->allowed,
                    'reason'  => $authResult->reason,
                ],
                'messaging'    => [
                    'allowed' => $messagingResult->allowed,
                    'reason'  => $messagingResult->reason,
                ],
            ]);
        });
    }

    public function countries(): \WP_REST_Response
    {
        return $this->handle(function () {
            return new \WP_REST_Response([
                'success'   => true,
                'countries' => $this->resolver->getAllCountries(),
            ]);
        });
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
        $arrayFields   = ['allowed_countries', 'blocked_types', 'preferred_countries'];
        $enumFields    = [
            'mode'         => ['allow', 'block'],
            'display_mode' => ['international', 'separate_dial_code', 'national'],
        ];

        foreach ($values as $key => $value) {
            if (isset($enumFields[$key])) {
                $allowed = $enumFields[$key];
                $sanitized[$key] = in_array($value, $allowed, true) ? $value : $allowed[0];
            } elseif (in_array($key, $booleanFields, true)) {
                $sanitized[$key] = (bool) $value;
            } elseif ($key === 'default_country') {
                $sanitized[$key] = is_string($value)
                    ? strtoupper(substr(sanitize_text_field($value), 0, 2))
                    : '';
            } elseif (in_array($key, $arrayFields, true)) {
                $sanitized[$key] = is_array($value)
                    ? array_values(array_filter(array_map('sanitize_text_field', $value)))
                    : [];
            }
        }

        return $sanitized;
    }
}
