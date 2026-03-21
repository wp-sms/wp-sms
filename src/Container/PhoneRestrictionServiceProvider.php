<?php

namespace WSms\Container;

use WSms\PhoneRestriction\CountryResolver;
use WSms\PhoneRestriction\DatabaseUpdater;
use WSms\PhoneRestriction\EnhancedPhoneDatabase;
use WSms\PhoneRestriction\RestrictionSettings;
use WSms\PhoneRestriction\SendingPolicyGuard;

defined('ABSPATH') || exit;

class PhoneRestrictionServiceProvider implements ServiceProvider
{
    public function register(ServiceContainer $container): void
    {
        $container->register('phone_restriction.settings', fn() => new RestrictionSettings());

        $container->register('phone_restriction.enhanced_db', fn() => EnhancedPhoneDatabase::load());

        $container->register('phone_restriction.resolver', fn($c) => new CountryResolver(
            $c->get('phone_restriction.enhanced_db'),
        ));

        $container->register('phone_restriction.guard', fn($c) => new SendingPolicyGuard(
            $c->get('phone_restriction.resolver'),
            $c->get('phone_restriction.settings'),
        ));

        $container->register('phone_restriction.db_updater', fn($c) => new DatabaseUpdater(
            $c->get('phone_restriction.settings'),
        ));
    }

    public function boot(ServiceContainer $container): void
    {
        $guardResolver = fn() => $container->get('phone_restriction.guard');

        $container->get('message.dispatcher')->setSendingPolicyGuardResolver($guardResolver);
        $container->get('auth.account_manager')->setSendingPolicyGuardResolver($guardResolver);
        $container->get('mfa.channel.phone')->setSendingPolicyGuardResolver($guardResolver);

        // Deferred to init — Action Scheduler may not be loaded earlier
        add_action('init', fn() => $container->get('phone_restriction.db_updater')->syncCronSchedule());
        add_action(DatabaseUpdater::CRON_HOOK, fn() => $container->get('phone_restriction.db_updater')->handleCron());
    }
}
