<?php

namespace WSms\Container;

use WSms\Auth\ProfileFieldRegistry;
use WSms\Auth\SettingsRepository;
use WSms\Database\Connection;
use WSms\Privacy\AuthLogEraser;
use WSms\Privacy\ContactDataEraser;
use WSms\Privacy\ContactDataExporter;
use WSms\Privacy\MessageLogHandler;

defined('ABSPATH') || exit;

/**
 * Privacy service provider — registers all GDPR exporters, erasers, and policy text.
 *
 * Runs unconditionally regardless of auth_enabled or transition_mode,
 * because contacts, message logs, and auth logs exist independently of auth state.
 *
 * @since 8.0
 */
class PrivacyServiceProvider implements ServiceProvider
{
    /** {@inheritDoc} */
    public function register(ServiceContainer $container): void
    {
    }

    /** {@inheritDoc} */
    public function boot(ServiceContainer $container): void
    {
        $this->registerExporters($container);
        $this->registerErasers($container);
        $this->registerPrivacyPolicyText($container);
    }

    private function registerExporters(ServiceContainer $container): void
    {
        add_filter('wp_privacy_personal_data_exporters', function (array $exporters) use ($container) {
            $exporters['wsms-profile-fields'] = [
                'exporter_friendly_name' => __('WSMS Profile Fields', 'wp-sms'),
                'callback'               => function (string $email, int $page) use ($container) {
                    return $this->exportProfileFieldData($container, $email, $page);
                },
            ];
            $exporters['wsms-avatar'] = [
                'exporter_friendly_name' => __('WSMS Avatar', 'wp-sms'),
                'callback'               => function (string $email, int $page) use ($container) {
                    return $container->get('auth.avatar_manager')->exportPersonalData([], $email, $page);
                },
            ];
            $exporters['wsms-contact'] = [
                'exporter_friendly_name' => __('WSMS Contact Data', 'wp-sms'),
                'callback'               => function (string $email, int $page) use ($container) {
                    return (new ContactDataExporter(
                        $container->get('contact.repository'),
                        $container->get(Connection::class),
                    ))->export($email, $page);
                },
            ];
            $exporters['wsms-message-log'] = [
                'exporter_friendly_name' => __('WSMS Message History', 'wp-sms'),
                'callback'               => function (string $email, int $page) use ($container) {
                    return (new MessageLogHandler(
                        $container->get('contact.repository'),
                        $container->get(Connection::class),
                    ))->export($email, $page);
                },
            ];

            return $exporters;
        });
    }

    private function registerErasers(ServiceContainer $container): void
    {
        add_filter('wp_privacy_personal_data_erasers', function (array $erasers) use ($container) {
            $erasers['wsms-profile-fields'] = [
                'eraser_friendly_name' => __('WSMS Profile Fields', 'wp-sms'),
                'callback'             => function (string $email, int $page) use ($container) {
                    return $this->eraseProfileFieldData($container, $email, $page);
                },
            ];
            $erasers['wsms-avatar'] = [
                'eraser_friendly_name' => __('WSMS Avatar', 'wp-sms'),
                'callback'             => function (string $email, int $page) use ($container) {
                    return $container->get('auth.avatar_manager')->erasePersonalData($email, $page);
                },
            ];
            $erasers['wsms-contact'] = [
                'eraser_friendly_name' => __('WSMS Contact Data', 'wp-sms'),
                'callback'             => function (string $email, int $page) use ($container) {
                    return (new ContactDataEraser(
                        $container->get('contact.repository'),
                        $container->get(Connection::class),
                    ))->erase($email, $page);
                },
            ];
            $erasers['wsms-message-log'] = [
                'eraser_friendly_name' => __('WSMS Message History', 'wp-sms'),
                'callback'             => function (string $email, int $page) use ($container) {
                    return (new MessageLogHandler(
                        $container->get('contact.repository'),
                        $container->get(Connection::class),
                    ))->erase($email, $page);
                },
            ];
            $erasers['wsms-auth-log'] = [
                'eraser_friendly_name' => __('WSMS Authentication Logs', 'wp-sms'),
                'callback'             => function (string $email, int $page) use ($container) {
                    return (new AuthLogEraser(
                        $container->get(Connection::class),
                    ))->erase($email, $page);
                },
            ];

            return $erasers;
        });
    }

    private function registerPrivacyPolicyText(ServiceContainer $container): void
    {
        add_action('admin_init', function () use ($container) {
            if (!function_exists('wp_add_privacy_policy_content')) {
                return;
            }
            wp_add_privacy_policy_content(
                'WSMS',
                wp_kses_post($this->buildPrivacyPolicyText($container->get('auth.settings'))),
            );
        });
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

    private function buildPrivacyPolicyText(SettingsRepository $settingsRepo): string
    {
        $authSettings = $settingsRepo->all();
        $messagingSettings = get_option('wsms_messaging_settings', []);

        $logRetentionDays = (int) $authSettings['log_retention_days'];
        $messageLogRetentionDays = (int) ($messagingSettings['message_log_retention_days'] ?? 90);
        $logVerbosity = $authSettings['log_verbosity'];
        $trustedDevicesEnabled = !empty($authSettings['trusted_devices']['enabled']);

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
}
