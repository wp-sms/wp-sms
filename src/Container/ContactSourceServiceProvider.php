<?php

namespace WSms\Container;

use WSms\Contact\Source\ContactSourceManager;
use WSms\Contact\Source\ContactSourceRegistry;
use WSms\Contact\Source\WordPressUsersSource;
use WSms\Integration\EmailOctopus\EmailOctopusContactSource;
use WSms\Integration\Mailtrap\MailtrapContactSource;

defined('ABSPATH') || exit;

class ContactSourceServiceProvider implements ServiceProvider
{
    public function register(ServiceContainer $container): void
    {
        $container->register('contact_source.registry', function () use ($container) {
            $registry = new ContactSourceRegistry();
            $registry->register(new WordPressUsersSource($container->get('contact.repository')));

            $eoSource = new EmailOctopusContactSource($container->get('contact.repository'));
            if ($eoSource->isAvailable()) {
                $registry->register($eoSource);
            }

            $mtSource = new MailtrapContactSource($container->get('contact.repository'));
            if ($mtSource->isAvailable()) {
                $registry->register($mtSource);
            }

            return $registry;
        });

        $container->register('contact_source.manager', fn($c) => new ContactSourceManager(
            $c->get('contact_source.registry'),
            $c->get('contact.repository'),
            $c->get('queue'),
        ));
    }

    public function boot(ServiceContainer $container): void
    {
        // Register batch job handler
        $processor = $container->get('queue.processor');
        $processor->registerHandler('sync_contact_source_batch', function (array $payload) use ($container) {
            $container->get('contact_source.manager')->processBatch(
                $payload['source_type'],
                $payload['batch_size'] ?? 100,
                $payload['after_id'] ?? null,
            );
        });

        // Register auto-sync hooks for connected sources
        $this->registerAutoSyncHooks($container);
    }

    private function registerAutoSyncHooks(ServiceContainer $container): void
    {
        $manager = $container->get('contact_source.manager');
        $sources = $manager->getAll();

        foreach ($sources as $type => $data) {
            if (($data['status'] ?? '') !== 'connected') {
                continue;
            }
            if (empty($data['config']['auto_sync'])) {
                continue;
            }

            if ($type === 'wordpress_users') {
                // Sync on user registration (priority 5 — before flow triggers at 20)
                add_action('user_register', function (int $userId) use ($container) {
                    $config = $container->get('contact_source.manager')->get('wordpress_users')['config'] ?? [];
                    $container->get('contact_source.registry')->get('wordpress_users')->syncOne($userId, $config);
                }, 5);

                // Sync on profile update
                add_action('profile_update', function (int $userId) use ($container) {
                    $config = $container->get('contact_source.manager')->get('wordpress_users')['config'] ?? [];
                    $container->get('contact_source.registry')->get('wordpress_users')->syncOne($userId, $config);
                }, 10);

                // Handle user deletion
                add_action('delete_user', function (int $userId) use ($container) {
                    $container->get('contact_source.registry')->get('wordpress_users')->handleDeletion($userId);
                }, 10);
            }
        }
    }
}
