<?php

namespace WSms\Rest;

use WP_REST_Request;
use WP_REST_Response;
use WSms\Audit\AuditLogger;
use WSms\Enums\EventType;
use WSms\Mfa\MfaManager;

defined('ABSPATH') || exit;

class AdminController
{
    private const NAMESPACE = 'wsms/v1';

    /** Top-level scalar/array setting keys allowed for direct writes. */
    private const ALLOWED_SCALAR_SETTINGS = [
        'mfa_required_roles',
        'enrollment_timing',
        'grace_period_days',
        'auto_create_users',
        'auth_base_url',
        'log_verbosity',
        'log_retention_days',
        'registration_fields',
        'redirect_login',
        'require_email_verification',
        'require_phone_verification',
    ];

    /** Channel keys that accept nested sub-objects. */
    private const ALLOWED_CHANNEL_KEYS = [
        'phone',
        'email',
        'password',
        'backup_codes',
    ];

    public function __construct(
        private AuditLogger $auditLogger,
        private MfaManager $mfaManager,
    ) {
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/auth/admin/settings', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'handleGetSettings'],
                'permission_callback' => [$this, 'checkAdmin'],
            ],
            [
                'methods'             => 'PUT',
                'callback'            => [$this, 'handleUpdateSettings'],
                'permission_callback' => [$this, 'checkAdmin'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/auth/admin/logs', [
            'methods'             => 'GET',
            'callback'            => [$this, 'handleGetLogs'],
            'permission_callback' => [$this, 'checkAdmin'],
            'args'                => [
                'page'     => ['required' => false, 'type' => 'integer', 'default' => 1],
                'per_page' => ['required' => false, 'type' => 'integer', 'default' => 50],
                'user_id'  => ['required' => false, 'type' => 'integer'],
                'event'    => ['required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                'status'   => ['required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/auth/admin/users/(?P<id>\d+)/mfa', [
            'methods'             => 'DELETE',
            'callback'            => [$this, 'handleDisableUserMfa'],
            'permission_callback' => [$this, 'checkAdmin'],
            'args'                => [
                'id' => ['required' => true, 'type' => 'integer'],
            ],
        ]);
    }

    public function checkAdmin(WP_REST_Request $request): bool
    {
        return current_user_can('manage_options');
    }

    public function handleGetSettings(WP_REST_Request $request): WP_REST_Response
    {
        $settings = get_option('wsms_auth_settings', []);

        return new WP_REST_Response([
            'success'  => true,
            'settings' => $settings,
        ]);
    }

    public function handleUpdateSettings(WP_REST_Request $request): WP_REST_Response
    {
        $current = get_option('wsms_auth_settings', []);
        $body = $request->get_params();
        $updated = $current;

        // Deep-merge channel sub-objects.
        foreach (self::ALLOWED_CHANNEL_KEYS as $channelKey) {
            if (array_key_exists($channelKey, $body) && is_array($body[$channelKey])) {
                $existing = $updated[$channelKey] ?? [];
                $updated[$channelKey] = array_merge($existing, $body[$channelKey]);
            }
        }

        // Merge scalar settings.
        foreach (self::ALLOWED_SCALAR_SETTINGS as $key) {
            if (array_key_exists($key, $body)) {
                $updated[$key] = $body[$key];
            }
        }

        update_option('wsms_auth_settings', $updated);

        return new WP_REST_Response([
            'success'  => true,
            'message'  => 'Settings updated.',
            'settings' => $updated,
        ]);
    }

    public function handleGetLogs(WP_REST_Request $request): WP_REST_Response
    {
        $filters = array_filter([
            'user_id' => $request->get_param('user_id'),
            'event'   => $request->get_param('event'),
            'status'  => $request->get_param('status'),
        ]);

        $page = max(1, (int) $request->get_param('page'));
        $perPage = min(100, max(1, (int) $request->get_param('per_page')));

        $result = $this->auditLogger->getEvents($filters, $page, $perPage);

        return new WP_REST_Response([
            'success' => true,
            'items'   => $result['items'],
            'total'   => $result['total'],
            'page'    => $page,
            'per_page' => $perPage,
        ]);
    }

    public function handleDisableUserMfa(WP_REST_Request $request): WP_REST_Response
    {
        $userId = (int) $request->get_param('id');
        $user = get_userdata($userId);

        if (!$user) {
            return new WP_REST_Response([
                'success' => false,
                'error'   => 'user_not_found',
                'message' => 'User not found.',
            ], 404);
        }

        $this->mfaManager->disableAllFactors($userId);

        $this->auditLogger->log(EventType::MfaAdminBypass, 'success', $userId, [
            'admin_id' => get_current_user_id(),
        ]);

        return new WP_REST_Response([
            'success' => true,
            'message' => 'All MFA factors have been disabled for this user.',
        ]);
    }
}
