<?php

namespace WSms\Container;

use WSms\Database\Connection;
use WSms\Migration\MigrationBackupService;
use WSms\Migration\MigrationBatchHandler;
use WSms\Migration\MigrationManager;
use WSms\Migration\MigrationStateManager;
use WSms\Migration\TransitionNotice;
use WSms\Migration\Adapters\Digits\DigitsDetector;
use WSms\Migration\Adapters\Digits\DigitsMigrator;
use WSms\Migration\Adapters\Digits\DigitsShortcodeCompat;
use WSms\Migration\Adapters\Digits\DigitsRedirectCompat;
use WSms\Migration\Adapters\Digits\Steps\UserPhoneStep;
use WSms\Migration\Adapters\Digits\Steps\TotpEnrollmentStep;
use WSms\Migration\Adapters\Digits\Steps\SettingsStep;
use WSms\Rest\MigrationController;

defined('ABSPATH') || exit;

class MigrationServiceProvider implements ServiceProvider
{
    public function register(ServiceContainer $container): void
    {
        $container->register('migration.state_manager', fn() => new MigrationStateManager());

        $container->register('migration.backup_service', fn($c) => new MigrationBackupService(
            $c->get(Connection::class),
        ));

        $container->register('migration.manager', fn($c) => new MigrationManager(
            $c->get('migration.state_manager'),
            $c->get('queue'),
        ));

        $container->register('migration.detector.digits', fn($c) => new DigitsDetector(
            $c->get(Connection::class),
        ));

        $container->register('migration.shortcode_compat.digits', fn($c) => new DigitsShortcodeCompat(
            $c->get(Connection::class),
        ));

        $container->register('migration.redirect_compat.digits', fn() => new DigitsRedirectCompat());

        $container->register('migration.adapter.digits', fn($c) => new DigitsMigrator(
            $c->get('migration.detector.digits'),
            $c->get('migration.backup_service'),
            new UserPhoneStep(
                $c->get(Connection::class),
                $c->get('migration.backup_service'),
                $c->get('migration.detector.digits'),
            ),
            new TotpEnrollmentStep(
                $c->get(Connection::class),
                $c->get('mfa.factor_repository'),
                $c->get('mfa.secret_encryptor'),
                $c->get('migration.backup_service'),
                $c->get('migration.detector.digits'),
            ),
            new SettingsStep(
                $c->get('migration.backup_service'),
            ),
        ));

        $container->register('migration.batch_handler', fn($c) => new MigrationBatchHandler(
            $c->get('migration.manager'),
            $c->get('migration.state_manager'),
            $c->get('queue'),
            $c->get('log.app'),
        ));

        $container->register('migration.notice', fn($c) => new TransitionNotice(
            $c->get('migration.detector.digits'),
            $c->get('migration.state_manager'),
        ));

        $container->register('rest.migration', fn($c) => new MigrationController(
            $c->get('migration.manager'),
            $c->get('migration.state_manager'),
            $c->get('migration.detector.digits'),
            $c->get('migration.shortcode_compat.digits'),
            $c->get('migration.redirect_compat.digits'),
            $c->get('auth.settings'),
        ));
    }

    public function boot(ServiceContainer $container): void
    {
        // Transition mode detection — must run before auth hooks.
        $this->detectTransitionMode($container);

        // Register Digits adapter with manager.
        $manager = $container->get('migration.manager');
        $manager->registerMigrator($container->get('migration.adapter.digits'));

        // Register migration batch job handler.
        $processor = $container->get('queue.processor');
        $processor->registerHandler('migration_batch', function (array $payload) use ($container) {
            $container->get('migration.batch_handler')->handle($payload);
        });

        // Admin notice for transition/migration states.
        if (is_admin()) {
            $container->get('migration.notice')->registerHooks();
        }

        // REST routes.
        add_action('rest_api_init', function () use ($container) {
            $container->get('rest.migration')->registerRoutes();
        });

        // Post-switchover compatibility shims (shortcodes + redirects).
        $container->get('migration.shortcode_compat.digits')->register();
        $container->get('migration.redirect_compat.digits')->register();
    }

    private function detectTransitionMode(ServiceContainer $container): void
    {
        $detector = $container->get('migration.detector.digits');
        $current = get_option('wsms_transition_mode');

        if ($detector->isActive()) {
            if ($current !== 'digits') {
                update_option('wsms_transition_mode', 'digits', false);
            }
        } elseif ($current === 'digits') {
            delete_option('wsms_transition_mode');
        }
    }
}
