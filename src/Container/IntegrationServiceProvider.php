<?php

namespace WSms\Container;

use WSms\Integration\Auth\AuthIntegration;
use WSms\Integration\Contact\ContactIntegration;
use WSms\Integration\ContactForm7\ContactForm7Integration;
use WSms\Integration\EmailOctopus\EmailOctopusIntegration;
use WSms\Integration\Mailtrap\MailtrapIntegration;
use WSms\Integration\IntegrationRegistry;
use WSms\Integration\Schedule\ScheduleIntegration;
use WSms\Integration\Webhook\WebhookIntegration;
use WSms\Integration\WooCommerce\WooCommerceIntegration;
use WSms\Integration\WordPress\WordPressIntegration;
use WSms\Integration\Line\LineIntegration;
use WSms\Integration\Telegram\TelegramIntegration;
use WSms\Integration\Contracts\IntegrationInterface;
use WSms\Integration\Contracts\SupportsContactImport;
use WSms\Integration\Contracts\SupportsContactSync;
use WSms\Integration\Contracts\SupportsSuppressionSync;
use WSms\Integration\Marketing\ImportSyncManager;
use WSms\Integration\Marketing\OutboundSyncManager;
use WSms\Integration\Marketing\SuppressionPoller;
use WSms\Integration\WpSms\WpSmsIntegration;
use WSms\Flow\Trigger\TriggerRegistry;
use WSms\Flow\Action\ActionRegistry;

defined('ABSPATH') || exit;

class IntegrationServiceProvider implements ServiceProvider
{
    /** @var class-string[] */
    private array $integrations = [
        WebhookIntegration::class,
        WooCommerceIntegration::class,
        ContactForm7Integration::class,
    ];

    public function register(ServiceContainer $container): void
    {
        $container->register('integration.registry', fn() => new IntegrationRegistry());
    }

    public function boot(ServiceContainer $container): void
    {
        add_action('init', function () use ($container) {
            $registry = $container->get('integration.registry');
            $triggers = $container->get('flow.triggers');
            $actions = $container->get('flow.actions');

            // Register WpSmsIntegration first (needs constructor injection)
            $this->registerIntegration(
                new WpSmsIntegration(
                    $container->get('message.dispatcher'),
                    $container->get('gateway.registry'),
                    $container->get('event.dispatcher'),
                    $container->get('contact.tag_repository'),
                    $container->get('contact.list_repository'),
                    $container->get('campaign.audience_resolver'),
                    $container->get('template.engine'),
                    $container->get('template.catalog_manager'),
                ),
                $registry,
                $triggers,
                $actions,
            );

            // Register TelegramIntegration (needs constructor injection)
            $this->registerIntegration(
                new TelegramIntegration($container->get('telegram.bot_client')),
                $registry,
                $triggers,
                $actions,
            );

            // Register LineIntegration (needs constructor injection)
            $this->registerIntegration(
                new LineIntegration($container->get('line.bot_client')),
                $registry,
                $triggers,
                $actions,
            );

            // Register ContactIntegration (needs constructor injection)
            $this->registerIntegration(
                new ContactIntegration(
                    $container->get('contact.repository'),
                    $container->get('contact.tag_repository'),
                    $container->get('contact.list_repository'),
                    $container->get('flow.repository'),
                    $container->get('flow.runner'),
                ),
                $registry,
                $triggers,
                $actions,
            );

            // Register ScheduleIntegration (needs constructor injection)
            $this->registerIntegration(
                new ScheduleIntegration(
                    $container->get('flow.repository'),
                    $container->get('flow.runner'),
                ),
                $registry,
                $triggers,
                $actions,
            );

            // Register AuthIntegration (needs form repository for FormRegistrationTrigger)
            $this->registerIntegration(
                new AuthIntegration(
                    $container->get('auth.form_repository'),
                ),
                $registry,
                $triggers,
                $actions,
            );

            // Register EmailOctopusIntegration
            $this->registerIntegration(
                new EmailOctopusIntegration($container->get('contact.repository')),
                $registry,
                $triggers,
                $actions,
            );

            // Register MailtrapIntegration
            $this->registerIntegration(
                new MailtrapIntegration($container->get('contact.repository')),
                $registry,
                $triggers,
                $actions,
            );

            // Register WordPressIntegration (needs contact.repository for import)
            $this->registerIntegration(
                new WordPressIntegration($container->get('contact.repository')),
                $registry,
                $triggers,
                $actions,
            );

            // Simple integrations (no constructor injection)
            foreach ($this->integrations as $integrationClass) {
                $this->registerIntegration(new $integrationClass(), $registry, $triggers, $actions);
            }

            try {
                do_action('wsms_register_integrations', $registry, $triggers, $actions);
            } catch (\Throwable $e) {
                error_log('[WP-SMS] Integration registration failed: ' . $e->getMessage());
            }

            // Wire marketing sync for sync-capable integrations
            $this->wireMarketingSync($registry, $container);

            // Wire contact import sync
            $this->wireImportSync($registry, $container);
        });
    }

    private function wireMarketingSync(IntegrationRegistry $registry, ServiceContainer $container): void
    {
        $syncIntegrations = [];
        foreach ($registry->getAll() as $integration) {
            if ($integration->isConnected() && $integration instanceof SupportsContactSync) {
                $syncIntegrations[] = $integration;
            }
        }

        if (empty($syncIntegrations)) {
            return;
        }

        $outbound = new OutboundSyncManager($syncIntegrations, $container->get('queue'), $container->get('contact.repository'));
        $outbound->listen();

        $suppressionIntegrations = array_filter(
            $syncIntegrations,
            fn($i) => $i instanceof SupportsSuppressionSync,
        );

        if (!empty($suppressionIntegrations)) {
            $poller = new SuppressionPoller($suppressionIntegrations, $container->get('contact.repository'));
            $container->register('marketing.suppression_poller', fn() => $poller);

            add_action('wsms_suppression_poll', function (array $args) use ($poller) {
                $poller->poll($args['integration_id'] ?? '');
            });

            $state = get_option(ImportSyncManager::STATE_KEY, []);
            foreach ($suppressionIntegrations as $integration) {
                $settings = $state[$integration->getId()]['sync_settings'] ?? [];
                $pollEnabled = $settings['poll_enabled'] ?? true;
                $pollInterval = $settings['poll_interval'] ?? 3600;

                if ($pollEnabled && !as_has_scheduled_action('wsms_suppression_poll', ['integration_id' => $integration->getId()], 'wsms')) {
                    as_schedule_recurring_action(
                        time() + $pollInterval,
                        $pollInterval,
                        'wsms_suppression_poll',
                        ['integration_id' => $integration->getId()],
                        'wsms',
                    );
                }
            }
        }

        $container->get('queue.processor')->registerHandler(
            'marketing_push_contact',
            function (array $payload) use ($registry) {
                $integrationId = $payload['integration_id'] ?? '';
                $integration = $registry->get($integrationId);

                if (!$integration || !$integration instanceof SupportsContactSync) {
                    return;
                }

                $contact = $payload['contact'] ?? [];
                $state = get_option(ImportSyncManager::STATE_KEY, []);
                $config = $state[$integrationId]['sync_settings'] ?? [];

                $result = $integration->pushContact($contact, $config);

                // Update stats
                $stats = $state[$integrationId]['stats'] ?? [];
                $stats['last_push_at'] = gmdate('c');
                if ($result->success) {
                    $stats['total_pushed'] = ($stats['total_pushed'] ?? 0) + 1;
                    $stats['last_error'] = null;
                } else {
                    $stats['last_error'] = $result->error;
                    if ($result->retryable) {
                        throw new \RuntimeException('Retryable push failure: ' . $result->error);
                    }
                }
                $state[$integrationId]['stats'] = $stats;
                update_option(ImportSyncManager::STATE_KEY, $state, false);
            },
        );
    }

    private function wireImportSync(IntegrationRegistry $registry, ServiceContainer $container): void
    {
        $importManager = new ImportSyncManager($registry, $container->get('queue'));
        $container->register('marketing.import_manager', fn() => $importManager);

        $container->get('queue.processor')->registerHandler(
            'sync_contact_import_batch',
            fn(array $payload) => $importManager->processBatch(
                $payload['integration_id'] ?? '',
                $payload['batch_size'] ?? 100,
                $payload['after_cursor'] ?? null,
            ),
        );

        // Wire auto-sync hooks for connected import integrations
        $state = get_option(ImportSyncManager::STATE_KEY, []);
        foreach ($registry->getAll() as $integration) {
            if (!$integration instanceof SupportsContactImport || !$integration->isConnected()) {
                continue;
            }
            $importSettings = $state[$integration->getId()]['import_settings'] ?? [];
            if (empty($importSettings['auto_sync'])) {
                continue;
            }

            if ($integration instanceof WordPressIntegration) {
                add_action('user_register', fn(int $userId) =>
                    $integration->importOne($userId, $importSettings), 5);
                add_action('profile_update', fn(int $userId) =>
                    $integration->importOne($userId, $importSettings), 10);
                add_action('delete_user', fn(int $userId) =>
                    $integration->handleImportDeletion($userId), 10);
            }
        }
    }

    private function registerIntegration(
        IntegrationInterface $integration,
        IntegrationRegistry $registry,
        TriggerRegistry $triggers,
        ActionRegistry $actions,
    ): void {
        $registry->register($integration);

        if ($integration->isConnected()) {
            foreach ($integration->getTriggers() as $trigger) {
                $triggers->register($trigger);
            }
            foreach ($integration->getActions() as $action) {
                $actions->register($action);
            }
        }

        $integration->boot();
    }
}
