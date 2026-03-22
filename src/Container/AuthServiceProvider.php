<?php

namespace WSms\Container;

use WSms\Auth\AccountLockout;
use WSms\Auth\AccountManager;
use WSms\Auth\AccountSuspension;
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
            );
        });

        $container->register('auth.shortcode', function () use ($container) {
            return new AuthShortcode(
                $container->get('auth.settings'),
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
        add_action('delete_user', function (int $userId) {
            global $wpdb;

            $wpdb->delete($wpdb->prefix . 'wsms_user_factors', ['user_id' => $userId], ['%d']);
            $wpdb->delete($wpdb->prefix . 'wsms_verifications', ['user_id' => $userId], ['%d']);
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

            return $erasers;
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
