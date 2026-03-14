<?php

namespace WSms\Rest;

use WP_REST_Request;
use WP_REST_Response;
use WSms\Audit\AuditLogger;
use WSms\Enums\EnrollmentTiming;
use WSms\Enums\EventType;
use WSms\Enums\LogVerbosity;
use WSms\Mfa\MfaManager;
use WSms\Social\SocialAccountRepository;

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
        'social_profile_sync',
        'pending_user_cleanup_enabled',
        'pending_user_ttl_hours',
    ];

    /** Channel keys that accept nested sub-objects. */
    private const ALLOWED_CHANNEL_KEYS = [
        'phone',
        'email',
        'password',
        'backup_codes',
        'totp',
        'captcha',
        'social',
        'telegram',
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
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'handleGetLogs'],
                'permission_callback' => [$this, 'checkAdmin'],
                'args'                => [
                    'page'      => ['required' => false, 'type' => 'integer', 'default' => 1],
                    'per_page'  => ['required' => false, 'type' => 'integer', 'default' => 50],
                    'user_id'   => ['required' => false, 'type' => 'integer'],
                    'event'     => ['required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                    'status'    => ['required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                    'date_from' => ['required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                    'date_to'   => ['required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                ],
            ],
            [
                'methods'             => 'DELETE',
                'callback'            => [$this, 'handleDeleteLogs'],
                'permission_callback' => [$this, 'checkAdmin'],
                'args'                => [
                    'event'     => ['required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                    'status'    => ['required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                    'date_from' => ['required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                    'date_to'   => ['required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                ],
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

        $errors = $this->validateSettings($updated);

        if (!empty($errors)) {
            return new WP_REST_Response([
                'success' => false,
                'error'   => 'validation_failed',
                'message' => 'Invalid settings values.',
                'errors'  => $errors,
            ], 400);
        }

        update_option('wsms_auth_settings', $updated);

        // Flush rewrite rules when auth_base_url changes.
        if (($current['auth_base_url'] ?? '/account') !== ($updated['auth_base_url'] ?? '/account')) {
            set_transient('wsms_flush_rewrite', '1');
        }

        return new WP_REST_Response([
            'success'  => true,
            'message'  => 'Settings updated.',
            'settings' => $updated,
        ]);
    }

    public function handleGetLogs(WP_REST_Request $request): WP_REST_Response
    {
        $filters = array_filter([
            'user_id'   => $request->get_param('user_id'),
            'event'     => $request->get_param('event'),
            'status'    => $request->get_param('status'),
            'date_from' => $request->get_param('date_from'),
            'date_to'   => $request->get_param('date_to'),
        ]);

        $page = max(1, (int) $request->get_param('page'));
        $perPage = min(100, max(1, (int) $request->get_param('per_page')));

        $result = $this->auditLogger->getEvents($filters, $page, $perPage);

        // Hydrate user display names (single batch query via cache_users).
        $userIds = array_unique(array_filter(array_column($result['items'], 'user_id')));
        $intIds = array_map('intval', $userIds);
        cache_users($intIds);

        $userMap = [];

        foreach ($intIds as $uid) {
            $u = get_userdata($uid);
            if ($u) {
                $userMap[$uid] = ['display_name' => $u->display_name, 'email' => $u->user_email];
            }
        }

        foreach ($result['items'] as &$item) {
            $item->user_display = $userMap[$item->user_id] ?? null;
        }
        unset($item);

        return new WP_REST_Response([
            'success'  => true,
            'items'    => $result['items'],
            'total'    => $result['total'],
            'page'     => $page,
            'per_page' => $perPage,
        ]);
    }

    public function handleDeleteLogs(WP_REST_Request $request): WP_REST_Response
    {
        $filters = array_filter([
            'event'     => $request->get_param('event'),
            'status'    => $request->get_param('status'),
            'date_from' => $request->get_param('date_from'),
            'date_to'   => $request->get_param('date_to'),
        ]);

        $deleted = $this->auditLogger->deleteAll($filters);

        return new WP_REST_Response([
            'success' => true,
            'deleted' => $deleted,
            'message' => "Deleted {$deleted} log entries.",
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

    private function validateSettings(array $settings): array
    {
        $errors = [];

        foreach (['phone', 'email', 'telegram'] as $channel) {
            $ch = $settings[$channel] ?? [];

            if (isset($ch['code_length']) && !in_array((int) $ch['code_length'], [4, 6], true)) {
                $errors[] = "{$channel}.code_length must be 4 or 6.";
            }

            if (isset($ch['expiry'])) {
                $v = (int) $ch['expiry'];
                if ($v < 60 || $v > 3600) {
                    $errors[] = "{$channel}.expiry must be between 60 and 3600.";
                }
            }

            if (isset($ch['max_attempts'])) {
                $v = (int) $ch['max_attempts'];
                if ($v < 1 || $v > 20) {
                    $errors[] = "{$channel}.max_attempts must be between 1 and 20.";
                }
            }

            if (isset($ch['cooldown'])) {
                $v = (int) $ch['cooldown'];
                if ($v < 10 || $v > 300) {
                    $errors[] = "{$channel}.cooldown must be between 10 and 300.";
                }
            }
        }

        if (isset($settings['enrollment_timing']) && EnrollmentTiming::tryFrom($settings['enrollment_timing']) === null) {
            $allowed = array_column(EnrollmentTiming::cases(), 'value');
            $errors[] = 'enrollment_timing must be one of: ' . implode(', ', $allowed) . '.';
        }

        if (isset($settings['grace_period_days'])) {
            $v = (int) $settings['grace_period_days'];
            if ($v < 1 || $v > 90) {
                $errors[] = 'grace_period_days must be between 1 and 90.';
            }
        }

        if (isset($settings['log_verbosity']) && LogVerbosity::tryFrom($settings['log_verbosity']) === null) {
            $allowed = array_column(LogVerbosity::cases(), 'value');
            $errors[] = 'log_verbosity must be one of: ' . implode(', ', $allowed) . '.';
        }

        if (isset($settings['log_retention_days'])) {
            $v = (int) $settings['log_retention_days'];
            if ($v < 1 || $v > 365) {
                $errors[] = 'log_retention_days must be between 1 and 365.';
            }
        }

        if (isset($settings['auth_base_url'])) {
            $url = $settings['auth_base_url'];
            if (!preg_match('#^/[a-zA-Z0-9\-/]*$#', $url)) {
                $errors[] = 'auth_base_url must start with / and contain only alphanumeric characters and hyphens.';
            }
        }

        // Require at least one identifier channel (email or phone) to be required or in registration_fields.
        $emailRequired = !empty(($settings['email'] ?? [])['required_at_signup']);
        $phoneRequired = !empty(($settings['phone'] ?? [])['required_at_signup']);
        $regFields = $settings['registration_fields'] ?? ['email', 'password'];
        $hasEmailField = in_array('email', $regFields, true);
        $hasPhoneField = in_array('phone', $regFields, true);

        if (!$emailRequired && !$phoneRequired && !$hasEmailField && !$hasPhoneField) {
            $errors[] = 'At least one identifier (email or phone) must be required at signup or included in registration fields.';
        }

        // Captcha settings validation.
        $captcha = $settings['captcha'] ?? [];

        if (!empty($captcha['enabled'])) {
            if (empty($captcha['site_key']) || empty($captcha['secret_key'])) {
                $errors[] = 'captcha: site_key and secret_key are required when CAPTCHA is enabled.';
            }

            $allowedProviders = ['turnstile', 'recaptcha', 'hcaptcha'];
            $provider = $captcha['provider'] ?? 'turnstile';

            if (!in_array($provider, $allowedProviders, true)) {
                $errors[] = 'captcha.provider must be one of: ' . implode(', ', $allowedProviders) . '.';
            }

            $allowedActions = ['login', 'register', 'forgot_password', 'identify'];
            $protectedActions = $captcha['protected_actions'] ?? [];

            foreach ($protectedActions as $action) {
                if (!in_array($action, $allowedActions, true)) {
                    $errors[] = "captcha.protected_actions: invalid action '{$action}'.";
                }
            }
        }

        // Social profile sync validation.
        if (isset($settings['social_profile_sync'])) {
            $allowed = ['registration_only', 'every_login'];
            if (!in_array($settings['social_profile_sync'], $allowed, true)) {
                $errors[] = 'social_profile_sync must be one of: ' . implode(', ', $allowed) . '.';
            }
        }

        // Social provider settings validation.
        $social = $settings['social'] ?? [];
        $validProviders = SocialAccountRepository::SOCIAL_PROVIDERS;
        foreach ($social as $provider => $providerSettings) {
            if (!in_array($provider, $validProviders, true)) {
                $errors[] = "social: unknown provider '{$provider}'.";
                continue;
            }
            if (!empty($providerSettings['enabled'])) {
                if (empty($providerSettings['client_id'])) {
                    $errors[] = "social.{$provider}: client_id is required when enabled.";
                }
                if (empty($providerSettings['client_secret'])) {
                    $errors[] = "social.{$provider}: client_secret is required when enabled.";
                }
            }
        }

        $bc = $settings['backup_codes'] ?? [];

        if (isset($bc['count'])) {
            $v = (int) $bc['count'];
            if ($v < 4 || $v > 20) {
                $errors[] = 'backup_codes.count must be between 4 and 20.';
            }
        }

        if (isset($bc['length'])) {
            $v = (int) $bc['length'];
            if ($v < 6 || $v > 12) {
                $errors[] = 'backup_codes.length must be between 6 and 12.';
            }
        }

        return $errors;
    }

}
