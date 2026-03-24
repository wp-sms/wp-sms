<?php

namespace WSms\Container;

use WSms\Campaign\AudienceResolver;
use WSms\Campaign\CampaignDispatcher;
use WSms\Campaign\CampaignRepository;
use WSms\Campaign\GatewayThrottler;
use WSms\Campaign\QuietHoursGuard;
use WSms\Database\Connection;

defined('ABSPATH') || exit;

class CampaignServiceProvider implements ServiceProvider
{
    public function register(ServiceContainer $container): void
    {
        $container->register('campaign.repository', fn($c) => new CampaignRepository($c->get(Connection::class)));
        $container->register('campaign.quiet_hours', fn() => new QuietHoursGuard());
        $container->register('campaign.throttler', fn() => new GatewayThrottler());

        $container->register('campaign.audience_resolver', fn($c) => new AudienceResolver(
            $c->get('contact.segment_evaluator'),
            $c->get(Connection::class),
        ));

        $container->register('campaign.dispatcher', fn($c) => new CampaignDispatcher(
            $c->get('campaign.repository'),
            $c->get('campaign.audience_resolver'),
            $c->get('message.dispatcher'),
            $c->get('log.message'),
            $c->get('template.engine'),
            $c->get('gateway.registry'),
            $c->get('queue'),
            $c->get('event.dispatcher'),
            $c->get('campaign.quiet_hours'),
            $c->get('campaign.throttler'),
        ));
    }

    public function boot(ServiceContainer $container): void
    {
        // Register job handlers
        $processor = $container->get('queue.processor');

        $processor->registerHandler('process_campaign_batch', function (array $payload) use ($container) {
            $dispatcher = $container->get('campaign.dispatcher');
            $dispatcher->processBatch(
                $payload['campaign_id'],
                $payload['after_id'] ?? null,
                $payload['batch_size'] ?? 500,
            );
        });

        $processor->registerHandler('schedule_campaign_recurrence', function (array $payload) use ($container) {
            $dispatcher = $container->get('campaign.dispatcher');
            $dispatcher->scheduleRecurrence($payload['campaign_id']);
        });

        // Register cron to check for scheduled campaigns
        add_action('wsms_check_scheduled_campaigns', function () use ($container) {
            $repository = $container->get('campaign.repository');
            $dispatcher = $container->get('campaign.dispatcher');

            $dueCampaigns = $repository->findDueForSending();
            foreach ($dueCampaigns as $campaign) {
                $dispatcher->dispatch($campaign->getId());
            }
        });

        // Schedule the recurring check via Action Scheduler (deferred to init when AS is ready)
        add_action('init', function () {
            if (function_exists('as_has_scheduled_action') && !as_has_scheduled_action('wsms_check_scheduled_campaigns')) {
                as_schedule_recurring_action(time(), 60, 'wsms_check_scheduled_campaigns', [], 'wsms');
            }
        });
    }
}
