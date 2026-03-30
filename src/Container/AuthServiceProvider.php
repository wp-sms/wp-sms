<?php

namespace WSms\Container;

use WSms\Auth\AccountLockout;
use WSms\Auth\AccountManager;
use WSms\Auth\AccountSuspension;
use WSms\Database\Connection;
use WSms\Auth\ApiAuthGuard;
use WSms\Auth\AuthOrchestrator;
use WSms\Auth\AuthRouter;
use WSms\Auth\AuthSession;
use WSms\Auth\AuthBlock;
use WSms\Auth\AuthShortcode;
use WSms\Auth\AvatarManager;
use WSms\Auth\CaptchaGuard;
use WSms\Auth\CaptchaProviders\HcaptchaProvider;
use WSms\Auth\CaptchaProviders\RecaptchaProvider;
use WSms\Auth\CaptchaProviders\TurnstileProvider;
use WSms\Auth\LoginGuard;
use WSms\Auth\PolicyEngine;
use WSms\Auth\ProfileFieldRegistry;
use WSms\Auth\RateLimiter;
use WSms\Auth\RegistrationFormRepository;
use WSms\Auth\SettingsRepository;
use WSms\Auth\TrustedDeviceManager;

defined('ABSPATH') || exit;

/**
 * Auth service provider — registers authentication and policy services.
 *
 * @since 8.0
 */
class AuthServiceProvider implements ServiceProvider
{
    /** {@inheritDoc} */
    public function register(ServiceContainer $container): void
    {
        $container->register('auth.settings', function () {
            return new SettingsRepository();
        });

        $container->register('auth.field_registry', function () use ($container) {
            return new ProfileFieldRegistry(
                $container->get('auth.settings'),
            );
        });

        $container->register('auth.avatar_manager', function () {
            return new AvatarManager();
        });

        $container->register('auth.policy', function () use ($container) {
            return new PolicyEngine(
                $container->get('mfa.manager'),
                $container->get('auth.settings'),
                $container->get('auth.field_registry'),
            );
        });

        $container->register('auth.session', function () use ($container) {
            return new AuthSession(
                $container->get('verification.otp_generator'),
            );
        });

        $container->register('auth.rate_limiter', function () {
            return new RateLimiter();
        });

        $container->register('auth.lockout', function () use ($container) {
            return new AccountLockout(
                $container->get('auth.settings'),
            );
        });

        $container->register('auth.suspension', function () {
            return new AccountSuspension();
        });

        $container->register('auth.trusted_devices', function () use ($container) {
            return new TrustedDeviceManager(
                $container->get('auth.settings'),
            );
        });

        $container->register('auth.orchestrator', function () use ($container) {
            return new AuthOrchestrator(
                $container->get('auth.policy'),
                $container->get('mfa.manager'),
                $container->get('audit.logger'),
                $container->get('auth.session'),
                $container->get('auth.lockout'),
                $container->get('auth.account_manager'),
                $container->get('auth.settings'),
                $container->get('auth.trusted_devices'),
                $container->get('auth.suspension'),
            );
        });

        $container->register('auth.account_manager', function () use ($container) {
            return new AccountManager(
                $container->get('audit.logger'),
                $container->get('verification.otp_service'),
                $container->get('mfa.manager'),
                $container->get('auth.session'),
                $container->get('auth.settings'),
                $container->get('message.dispatcher'),
                $container->get('template.manager'),
                $container->get('auth.field_registry'),
                $container->get('auth.trusted_devices'),
                $container->get('log.app'),
            );
        });

        $container->register('auth.captcha_guard', function () use ($container) {
            return new CaptchaGuard([
                'turnstile' => new TurnstileProvider(),
                'recaptcha' => new RecaptchaProvider(),
                'hcaptcha'  => new HcaptchaProvider(),
            ], $container->get('auth.settings'));
        });

        $container->register('auth.router', function () use ($container) {
            return new AuthRouter(
                $container->get('auth.settings'),
                $container->get('branding.repository'),
            );
        });

        $container->register('auth.shortcode', function () use ($container) {
            return new AuthShortcode(
                $container->get('auth.settings'),
                $container->get('branding.repository'),
            );
        });

        $container->register('auth.block', function () use ($container) {
            return new AuthBlock(
                $container->get('auth.shortcode'),
            );
        });

        $container->register('auth.form_repository', function () {
            return new RegistrationFormRepository();
        });

        $container->register('auth.login_guard', function () use ($container) {
            return new LoginGuard(
                $container->get('auth.policy'),
                $container->get('auth.session'),
                $container->get('mfa.manager'),
                $container->get('auth.settings'),
                $container->get('auth.suspension'),
            );
        });

        $container->register('auth.api_guard', function () use ($container) {
            return new ApiAuthGuard(
                $container->get('mfa.manager'),
                $container->get('auth.suspension'),
            );
        });
    }

    /** {@inheritDoc} */
    public function boot(ServiceContainer $container): void
    {
        // Transition mode: skip auth hooks that conflict with the active migration source plugin.
        if (get_option('wsms_transition_mode')) {
            $this->bootNonAuthHooks($container);
            return;
        }

        $container->get('auth.router')->setCaptchaGuard($container->get('auth.captcha_guard'));
        $container->get('auth.router')->registerHooks();
        $container->get('auth.shortcode')->registerHooks();
        $container->get('auth.block')->registerHooks();

        $container->get('auth.login_guard')->registerHooks();
        $container->get('auth.api_guard')->registerHooks();

        // Register custom profile field meta on init.
        add_action('init', function () use ($container) {
            $container->get('auth.field_registry')->registerMeta();
        });

        // Avatar: WordPress integration hooks.
        $avatarManager = $container->get('auth.avatar_manager');
        add_filter('get_avatar_url', [$avatarManager, 'filterGetAvatarUrl'], 10, 3);
        add_filter('get_avatar', [$avatarManager, 'filterGetAvatar'], 10, 6);
        add_action('delete_user', [$avatarManager, 'cleanupOnUserDelete']);

        // Clean up custom table rows when a user is deleted.
        add_action('delete_user', function (int $userId) use ($container) {
            $db = $container->get(Connection::class);
            $db->delete(Connection::TABLE_USER_FACTORS, ['user_id' => $userId]);
            $db->delete(Connection::TABLE_VERIFICATIONS, ['user_id' => $userId]);
        });

        // GDPR: profile fields data exporter.
        add_filter('wp_privacy_personal_data_exporters', function (array $exporters) use ($container) {
            $exporters['wsms-profile-fields'] = [
                'exporter_friendly_name' => __('WSMS Profile Fields', 'wp-sms'),
                'callback'               => function (string $email, int $page) use ($container) {
                    return $this->exportProfileFieldData($container, $email, $page);
                },
            ];
            $exporters['wsms-avatar'] = [
                'exporter_friendly_name' => __('WSMS Avatar', 'wp-sms'),
                'callback'               => [$avatarManager, 'exportPersonalData'],
            ];
            $exporters['wsms-contact'] = [
                'exporter_friendly_name' => __('WSMS Contact Data', 'wp-sms'),
                'callback'               => function (string $email, int $page) use ($container) {
                    return (new \WSms\Privacy\ContactDataExporter(
                        $container->get('contact.repository'),
                        $container->get(Connection::class),
                    ))->export($email, $page);
                },
            ];
            $exporters['wsms-message-log'] = [
                'exporter_friendly_name' => __('WSMS Message History', 'wp-sms'),
                'callback'               => function (string $email, int $page) use ($container) {
                    return (new \WSms\Privacy\MessageLogHandler(
                        $container->get('contact.repository'),
                        $container->get(Connection::class),
                    ))->export($email, $page);
                },
            ];

            return $exporters;
        });

        add_filter('wp_privacy_personal_data_erasers', function (array $erasers) use ($container) {
            $erasers['wsms-profile-fields'] = [
                'eraser_friendly_name' => __('WSMS Profile Fields', 'wp-sms'),
                'callback'             => function (string $email, int $page) use ($container) {
                    return $this->eraseProfileFieldData($container, $email, $page);
                },
            ];
            $erasers['wsms-avatar'] = [
                'eraser_friendly_name' => __('WSMS Avatar', 'wp-sms'),
                'callback'             => [$avatarManager, 'erasePersonalData'],
            ];
            $erasers['wsms-contact'] = [
                'eraser_friendly_name' => __('WSMS Contact Data', 'wp-sms'),
                'callback'             => function (string $email, int $page) use ($container) {
                    return (new \WSms\Privacy\ContactDataEraser(
                        $container->get('contact.repository'),
                        $container->get(Connection::class),
                    ))->erase($email, $page);
                },
            ];
            $erasers['wsms-message-log'] = [
                'eraser_friendly_name' => __('WSMS Message History', 'wp-sms'),
                'callback'             => function (string $email, int $page) use ($container) {
                    return (new \WSms\Privacy\MessageLogHandler(
                        $container->get('contact.repository'),
                        $container->get(Connection::class),
                    ))->erase($email, $page);
                },
            ];
            $erasers['wsms-auth-log'] = [
                'eraser_friendly_name' => __('WSMS Authentication Logs', 'wp-sms'),
                'callback'             => function (string $email, int $page) use ($container) {
                    return (new \WSms\Privacy\AuthLogEraser(
                        $container->get(Connection::class),
                    ))->erase($email, $page);
                },
            ];

            return $erasers;
        });

        // Privacy policy suggested text.
        add_action('admin_init', function () {
            if (!function_exists('wp_add_privacy_policy_content')) {
                return;
            }
            wp_add_privacy_policy_content(
                'WSMS',
                wp_kses_post($this->buildPrivacyPolicyText()),
            );
        });

        // Inject settings into MFA manager and channels for consistent config access.
        $settingsRepo = $container->get('auth.settings');
        $mfaManager = $container->get('mfa.manager');
        $mfaManager->setSettingsRepository($settingsRepo);
        foreach ($mfaManager->getAvailableChannels() as $channel) {
            if (method_exists($channel, 'setSettingsRepository')) {
                $channel->setSettingsRepository($settingsRepo);
            }
        }
        // MagicLinkChannel is not in getAvailableChannels() — inject separately.
        $container->get('mfa.channel.magic')->setSettingsRepository($settingsRepo);

        // Block wp_mail to placeholder email addresses.
        add_filter('pre_wp_mail', function ($null, $atts) {
            $to = is_array($atts['to'] ?? '') ? implode(',', $atts['to']) : ($atts['to'] ?? '');
            $recipients = array_map('trim', explode(',', $to));

            foreach ($recipients as $r) {
                if (!AccountManager::isPlaceholderEmail($r)) {
                    return $null; // At least one real recipient — allow.
                }
            }

            return false; // All placeholder — block.
        }, 10, 2);
    }

    /**
     * Register only non-conflicting hooks during transition mode.
     *
     * Skips: auth.router, auth.login_guard, auth.api_guard, auth.block, auth.shortcode
     * Keeps: GDPR, avatar, privacy, profile fields, placeholder email filter
     */
    private function bootNonAuthHooks(ServiceContainer $container): void
    {
        // Profile field meta registration.
        add_action('init', function () use ($container) {
            $container->get('auth.field_registry')->registerMeta();
        });

        // Avatar hooks.
        $avatarManager = $container->get('auth.avatar_manager');
        add_filter('get_avatar_url', [$avatarManager, 'filterGetAvatarUrl'], 10, 3);
        add_filter('get_avatar', [$avatarManager, 'filterGetAvatar'], 10, 6);
        add_action('delete_user', [$avatarManager, 'cleanupOnUserDelete']);

        // User deletion cleanup.
        add_action('delete_user', function (int $userId) use ($container) {
            $db = $container->get(Connection::class);
            $db->delete(Connection::TABLE_USER_FACTORS, ['user_id' => $userId]);
            $db->delete(Connection::TABLE_VERIFICATIONS, ['user_id' => $userId]);
        });

        // GDPR exporters/erasers (same as full boot).
        add_filter('wp_privacy_personal_data_exporters', function (array $exporters) use ($container, $avatarManager) {
            $exporters['wsms-profile-fields'] = [
                'exporter_friendly_name' => __('WSMS Profile Fields', 'wp-sms'),
                'callback'               => function (string $email, int $page) use ($container) {
                    return $this->exportProfileFieldData($container, $email, $page);
                },
            ];
            $exporters['wsms-avatar'] = [
                'exporter_friendly_name' => __('WSMS Avatar', 'wp-sms'),
                'callback'               => [$avatarManager, 'exportPersonalData'],
            ];
            return $exporters;
        });

        add_filter('wp_privacy_personal_data_erasers', function (array $erasers) use ($container, $avatarManager) {
            $erasers['wsms-profile-fields'] = [
                'eraser_friendly_name' => __('WSMS Profile Fields', 'wp-sms'),
                'callback'             => function (string $email, int $page) use ($container) {
                    return $this->eraseProfileFieldData($container, $email, $page);
                },
            ];
            $erasers['wsms-avatar'] = [
                'eraser_friendly_name' => __('WSMS Avatar', 'wp-sms'),
                'callback'             => [$avatarManager, 'erasePersonalData'],
            ];
            return $erasers;
        });

        // Privacy policy.
        add_action('admin_init', function () {
            if (!function_exists('wp_add_privacy_policy_content')) {
                return;
            }
            wp_add_privacy_policy_content(
                'WSMS',
                wp_kses_post($this->buildPrivacyPolicyText()),
            );
        });

        // Placeholder email filter.
        add_filter('pre_wp_mail', function ($null, $atts) {
            $to = is_array($atts['to'] ?? '') ? implode(',', $atts['to']) : ($atts['to'] ?? '');
            $recipients = array_map('trim', explode(',', $to));

            foreach ($recipients as $r) {
                if (!AccountManager::isPlaceholderEmail($r)) {
                    return $null;
                }
            }

            return false;
        }, 10, 2);

        // Settings injection for MFA (needed for non-auth uses).
        $settingsRepo = $container->get('auth.settings');
        $mfaManager = $container->get('mfa.manager');
        $mfaManager->setSettingsRepository($settingsRepo);
    }

    private function exportProfileFieldData(ServiceContainer $container, string $email, int $page): array
    {
        $user = get_user_by('email', $email);

        if (!$user) {
            return ['data' => [], 'done' => true];
        }

        /** @var ProfileFieldRegistry $registry */
        $registry = $container->get('auth.field_registry');
        $data = [];

        foreach ($registry->getCustomFields() as $field) {
            $value = $registry->readValue($user->ID, $field);
            if (!empty($value)) {
                $data[] = ['name' => $field->label, 'value' => (string) $value];
            }
        }

        $exportItems = [];
        if (!empty($data)) {
            $exportItems[] = [
                'group_id'    => 'wsms-profile-fields',
                'group_label' => __('WSMS Profile Fields', 'wp-sms'),
                'item_id'     => 'wsms-fields-' . $user->ID,
                'data'        => $data,
            ];
        }

        return ['data' => $exportItems, 'done' => true];
    }

    private function buildPrivacyPolicyText(): string
    {
        $authSettings = get_option('wsms_auth_settings', []);
        $messagingSettings = get_option('wsms_messaging_settings', []);

        $logRetentionDays = (int) ($authSettings['log_retention_days'] ?? 30);
        $messageLogRetentionDays = (int) ($messagingSettings['message_log_retention_days'] ?? 90);
        $logVerbosity = $authSettings['log_verbosity'] ?? 'standard';
        $trustedDevicesEnabled = !empty($authSettings['trusted_devices']['enabled'] ?? false);

        $text = '<h2>' . __('Subscription & Contact Data', 'wp-sms') . '</h2>';
        $text .= '<p>' . __('When you subscribe through our forms, we collect your name, email address, and/or phone number. We use this data to send you the communications you opted into. Your subscription status, source, and any tags are stored to manage your preferences.', 'wp-sms') . '</p>';

        $text .= '<h2>' . __('Message History', 'wp-sms') . '</h2>';
        $text .= '<p>' . sprintf(
            __('We log messages sent to you (channel, status, and date) for delivery tracking and troubleshooting. Message logs are automatically deleted after %d days.', 'wp-sms'),
            $messageLogRetentionDays,
        ) . '</p>';

        $text .= '<h2>' . __('Authentication Logs', 'wp-sms') . '</h2>';
        if ($logVerbosity === 'minimal') {
            $text .= '<p>' . sprintf(
                __('We record login events and their status for security purposes. Authentication logs are automatically deleted after %d days.', 'wp-sms'),
                $logRetentionDays,
            ) . '</p>';
        } else {
            $text .= '<p>' . sprintf(
                __('We record login events including IP address, browser information, and approximate location for security purposes. Authentication logs are automatically deleted after %d days.', 'wp-sms'),
                $logRetentionDays,
            ) . '</p>';
        }

        if ($trustedDevicesEnabled) {
            $text .= '<h2>' . __('Trusted Devices', 'wp-sms') . '</h2>';
            $text .= '<p>' . __('When you choose to trust a device, we store a cookie on your browser to skip multi-factor authentication on future visits. You can remove trusted devices from your profile at any time.', 'wp-sms') . '</p>';
        }

        $text .= '<h2>' . __('Data Retention & Your Rights', 'wp-sms') . '</h2>';
        $text .= '<p>' . __('You may request export or deletion of your personal data by contacting us. Upon an erasure request, your contact record and associated tags are deleted, message log recipients are anonymized, and authentication logs are removed.', 'wp-sms') . '</p>';
        $text .= '<p>' . __('You can unsubscribe at any time by replying STOP to SMS messages or clicking the unsubscribe link in emails.', 'wp-sms') . '</p>';

        return $text;
    }

    private function eraseProfileFieldData(ServiceContainer $container, string $email, int $page): array
    {
        $user = get_user_by('email', $email);

        if (!$user) {
            return ['items_removed' => false, 'items_retained' => false, 'messages' => [], 'done' => true];
        }

        /** @var ProfileFieldRegistry $registry */
        $registry = $container->get('auth.field_registry');
        $removed = false;

        foreach ($registry->getCustomFields() as $field) {
            delete_user_meta($user->ID, $field->metaKey);
            $removed = true;
        }

        return [
            'items_removed'  => $removed,
            'items_retained' => false,
            'messages'       => [],
            'done'           => true,
        ];
    }
}
