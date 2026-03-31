<?php

namespace WSms\Rest;

use WP_REST_Request;
use WP_REST_Response;
use WSms\Audit\AuditLogger;
use WSms\Audit\ReportAggregator;
use WSms\Auth\ProfileFieldRegistry;
use WSms\Auth\SettingsRepository;
use WSms\Enums\EnrollmentTiming;
use WSms\Enums\EventType;
use WSms\Enums\LogVerbosity;
use WSms\Exception\NotFoundException;
use WSms\Exception\ValidationException;
use WSms\Messaging\Gateway\GatewayRegistry;
use WSms\Mfa\MfaManager;
use WSms\Social\SocialAccountRepository;
use WSms\Support\UserMeta;

defined('ABSPATH') || exit;

class AdminController extends Controller
{
    /** Top-level scalar/array setting keys allowed for direct writes. */
    private const ALLOWED_SCALAR_SETTINGS = [
        'auth_enabled',
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
        'profile_fields',
        'site_phone',
        'site_phone_channel',
        'terms_url',
        'privacy_url',
        'mfa_policy_activated_at',
        'subscription_consent_text',
        'subscription_consent_required',
    ];

    /** Channel keys that accept nested sub-objects. */
    private const ALLOWED_CHANNEL_KEYS = [
        'phone',
        'email',
        'password',
        'backup_codes',
        'totp',
        'passkey',
        'captcha',
        'social',
        'telegram',
        'line',
        'woocommerce',
        'contact_form_7',
        'trusted_devices',
    ];

    public function __construct(
        private AuditLogger $auditLogger,
        private MfaManager $mfaManager,
        private SettingsRepository $settingsRepo,
        private ?ProfileFieldRegistry $fieldRegistry = null,
        private ?ReportAggregator $reportAggregator = null,
        private ?GatewayRegistry $gatewayRegistry = null,
    ) {
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/auth/admin/settings', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'handleGetSettings'],
                'permission_callback' => [$this, 'canManage'],
            ],
            [
                'methods'             => 'PUT',
                'callback'            => [$this, 'handleUpdateSettings'],
                'permission_callback' => [$this, 'canManage'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/auth/admin/logs', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'handleGetLogs'],
                'permission_callback' => [$this, 'canManage'],
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
                'permission_callback' => [$this, 'canManage'],
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
            'permission_callback' => [$this, 'canManage'],
            'args'                => [
                'id' => ['required' => true, 'type' => 'integer'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/auth/admin/reports', [
            'methods'             => 'GET',
            'callback'            => [$this, 'handleGetReports'],
            'permission_callback' => [$this, 'canManage'],
            'args'                => [
                'range' => [
                    'required'          => false,
                    'type'              => 'integer',
                    'default'           => 30,
                    'sanitize_callback' => function ($value) {
                        return max(7, min(90, (int) $value));
                    },
                ],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/auth/admin/meta-keys', [
            'methods'             => 'GET',
            'callback'            => [$this, 'handleGetMetaKeys'],
            'permission_callback' => [$this, 'canManage'],
        ]);
    }

    public function handleGetSettings(WP_REST_Request $request): WP_REST_Response
    {
        return $this->handle(function () {
            return new WP_REST_Response([
                'success'  => true,
                'settings' => $this->settingsRepo->all(),
            ]);
        });
    }

    public function handleUpdateSettings(WP_REST_Request $request): WP_REST_Response
    {
        return $this->handle(function () use ($request) {
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

            // WhatsApp doesn't support magic links — strip it server-side.
            $phoneDelivery = $updated['phone']['delivery_channel'] ?? 'sms';
            if ($phoneDelivery === 'whatsapp') {
                $methods = $updated['phone']['verification_methods'] ?? ['otp'];
                $methods = array_values(array_filter($methods, fn($m) => $m !== 'magic_link'));
                $updated['phone']['verification_methods'] = $methods ?: ['otp'];
            }

            // Reset grace baseline whenever new roles are added to the MFA policy.
            if ($this->hasNewMfaRoles($current, $updated)) {
                $updated['mfa_policy_activated_at'] = time();
            }

            $errors = $this->validateSettings($updated, $current);

            if (!empty($errors)) {
                throw new ValidationException($errors);
            }

            update_option('wsms_auth_settings', $updated);
            $this->settingsRepo->invalidateCache();

            $baseUrlChanged = ($current['auth_base_url'] ?? '/account') !== ($updated['auth_base_url'] ?? '/account');
            $authToggled = ($current['auth_enabled'] ?? false) !== ($updated['auth_enabled'] ?? false);
            if ($baseUrlChanged || $authToggled) {
                update_option('wsms_flush_rewrite', '1', true);
            }

            return new WP_REST_Response([
                'success'  => true,
                'message'  => __('Settings updated.', 'wp-sms'),
                'settings' => $this->settingsRepo->all(),
            ]);
        });
    }

    public function handleGetLogs(WP_REST_Request $request): WP_REST_Response
    {
        return $this->handle(function () use ($request) {
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
                $item['user_display'] = $userMap[$item['user_id']] ?? null;
            }
            unset($item);

            return new WP_REST_Response([
                'success'  => true,
                'items'    => $result['items'],
                'total'    => $result['total'],
                'page'     => $page,
                'per_page' => $perPage,
            ]);
        });
    }

    public function handleDeleteLogs(WP_REST_Request $request): WP_REST_Response
    {
        return $this->handle(function () use ($request) {
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
                'message' => sprintf(__('Deleted %d log entries.', 'wp-sms'), $deleted),
            ]);
        });
    }

    public function handleDisableUserMfa(WP_REST_Request $request): WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            $userId = (int) $request->get_param('id');
            $user = get_userdata($userId);

            if (!$user) {
                throw NotFoundException::entity('User', (string) $userId);
            }

            $this->mfaManager->disableAllFactors($userId);

            $this->auditLogger->log(EventType::MfaAdminBypass, 'success', $userId, [
                'admin_id' => get_current_user_id(),
            ]);

            return $this->ok(['message' => __('All MFA factors have been disabled for this user.', 'wp-sms')]);
        });
    }

    public function handleGetReports(WP_REST_Request $request): WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            if (!$this->reportAggregator) {
                return new WP_REST_Response([
                    'success' => false,
                    'error'   => 'unavailable',
                    'message' => __('Report aggregator not available.', 'wp-sms'),
                ], 500);
            }

            $range = (int) $request->get_param('range');

            return new WP_REST_Response([
                'success' => true,
                ...$this->reportAggregator->getReport($range),
            ]);
        });
    }

    public function handleGetMetaKeys(WP_REST_Request $request): WP_REST_Response
    {
        return $this->handle(function () {
            if (!$this->fieldRegistry) {
                return new WP_REST_Response([
                    'success' => false,
                    'error'   => 'unavailable',
                    'message' => __('Profile field registry not available.', 'wp-sms'),
                ], 500);
            }

            return new WP_REST_Response([
                'success'   => true,
                'meta_keys' => $this->fieldRegistry->scanMetaKeys(),
            ]);
        });
    }

    private function hasNewMfaRoles(array $current, array $updated): bool
    {
        $oldRoles = $current['mfa_required_roles'] ?? [];
        $newRoles = $updated['mfa_required_roles'] ?? [];

        return !empty(array_diff($newRoles, $oldRoles));
    }

    private function validateSettings(array $settings, array $current = []): array
    {
        $errors = [];

        // Prevent admin from requiring MFA for their own role without having MFA enrolled.
        $currentUser = wp_get_current_user();

        if ($currentUser->ID && $this->hasNewMfaRoles($current, $settings)) {
            $newRoles = $settings['mfa_required_roles'] ?? [];
            $oldRoles = $current['mfa_required_roles'] ?? [];
            $newlyAdded = array_diff(
                array_intersect($currentUser->roles, $newRoles),
                array_intersect($currentUser->roles, $oldRoles),
            );

            if (!empty($newlyAdded) && !(bool) get_user_meta($currentUser->ID, UserMeta::MFA_ENABLED, true)) {
                $errors[] = 'You must enroll in MFA before requiring it for your own role.';
            }
        }

        foreach (['phone', 'email', 'telegram', 'line'] as $channel) {
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

            if (!in_array($channel, ['telegram', 'line'], true) && !empty($ch['otp_gateway']) && $this->gatewayRegistry) {
                $gateway = $this->gatewayRegistry->get($ch['otp_gateway']);
                if ($gateway === null) {
                    $errors[] = "{$channel}.otp_gateway references an unknown gateway.";
                }
            }

            if ($channel === 'phone' && isset($ch['delivery_channel'])) {
                $allowedDeliveryChannels = ['sms', 'whatsapp', 'viber', 'rcs'];
                if (!in_array($ch['delivery_channel'], $allowedDeliveryChannels, true)) {
                    $errors[] = "phone.delivery_channel must be one of: " . implode(', ', $allowedDeliveryChannels) . '.';
                }

                // Validate gateway supports the selected delivery channel (reuses $gateway from above).
                if (!empty($ch['otp_gateway']) && isset($gateway) && $gateway !== null) {
                    if (!in_array($ch['delivery_channel'], $gateway->getSupportedChannels(), true)) {
                        $errors[] = "phone.otp_gateway '{$ch['otp_gateway']}' does not support the '{$ch['delivery_channel']}' delivery channel.";
                    }
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

        if (isset($settings['site_phone']) && !is_string($settings['site_phone'])) {
            $errors[] = 'site_phone must be a string.';
        }

        if (isset($settings['site_phone_channel'])) {
            $allowed = ['sms', 'whatsapp', 'telegram'];
            if (!in_array($settings['site_phone_channel'], $allowed, true)) {
                $errors[] = 'site_phone_channel must be one of: ' . implode(', ', $allowed) . '.';
            }
        }

        // Require at least one identifier channel (email or phone) to be required or in registration_fields.
        $emailRequired = !empty(($settings['email'] ?? [])['enabled']) && !empty(($settings['email'] ?? [])['required_at_signup']);
        $phoneRequired = !empty(($settings['phone'] ?? [])['enabled']) && !empty(($settings['phone'] ?? [])['required_at_signup']);
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

        // Profile fields validation.
        if (isset($settings['profile_fields']) && is_array($settings['profile_fields']) && $this->fieldRegistry) {
            $fieldErrors = $this->fieldRegistry->validateFieldsConfig($settings['profile_fields']);
            $errors = array_merge($errors, $fieldErrors);
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

        // Legal link URL validation.
        foreach (['terms_url', 'privacy_url'] as $urlKey) {
            if (!empty($settings[$urlKey]) && !filter_var($settings[$urlKey], FILTER_VALIDATE_URL)) {
                $errors[] = "{$urlKey} must be a valid URL.";
            }
        }

        return $errors;
    }

}
