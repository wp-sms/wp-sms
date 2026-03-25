<?php

namespace WSms\Container;

use WSms\Rest\AccountController;
use WSms\Audit\ReportAggregator;
use WSms\Database\Connection;
use WSms\Rest\AdminController;
use WSms\Rest\AdminUserController;
use WSms\Rest\AuthController;
use WSms\Rest\ContactController;
use WSms\Rest\ContactSourceController;
use WSms\Rest\FlowController;
use WSms\Rest\CampaignController;
use WSms\Rest\GatewayController;
use WSms\Rest\IntegrationController;
use WSms\Rest\MessageLogController;
use WSms\Rest\MfaController;
use WSms\Rest\EnrollmentController;
use WSms\Rest\SocialAuthController;
use WSms\Rest\TelegramController;
use WSms\Rest\GatewayCallbackController;
use WSms\Rest\ListController;
use WSms\Rest\OptOutSettingsController;
use WSms\Rest\PhoneRestrictionController;
use WSms\Rest\TagController;
use WSms\Rest\MessagingButtonController;
use WSms\Rest\RegistrationFormController;
use WSms\Rest\SubscriptionFormController;
use WSms\Rest\SubscriptionFormPublicController;
use WSms\Rest\TemplateCatalogController;
use WSms\Rest\TemplateController;
use WSms\Rest\OutboundWebhookController;
use WSms\Rest\WebhookReceiverController;

defined('ABSPATH') || exit;

class RestServiceProvider implements ServiceProvider
{
    /** {@inheritDoc} */
    public function register(ServiceContainer $container): void
    {
        $container->register('rest.auth', function () use ($container) {
            return new AuthController(
                $container->get('auth.orchestrator'),
                $container->get('auth.rate_limiter'),
                $container->get('auth.policy'),
                $container->get('auth.captcha_guard'),
                $container->get('social.manager'),
                $container->get('mfa.manager'),
                $container->get('auth.form_repository'),
                $container->get('phone_restriction.settings'),
            );
        });

        $container->register('rest.social', function () use ($container) {
            return new SocialAuthController(
                $container->get('social.orchestrator'),
                $container->get('social.manager'),
                $container->get('auth.rate_limiter'),
            );
        });

        $container->register('rest.mfa', function () use ($container) {
            return new MfaController(
                $container->get('auth.orchestrator'),
                $container->get('auth.rate_limiter'),
                $container->get('auth.trusted_devices'),
            );
        });

        $container->register('rest.enrollment', function () use ($container) {
            return new EnrollmentController(
                $container->get('mfa.manager'),
                $container->get('auth.policy'),
                $container->get('auth.field_registry'),
                $container->get('auth.avatar_manager'),
                $container->get('auth.trusted_devices'),
            );
        });

        $container->register('rest.account', function () use ($container) {
            return new AccountController(
                $container->get('auth.account_manager'),
                $container->get('auth.rate_limiter'),
                $container->get('auth.session'),
                $container->get('auth.captcha_guard'),
                $container->get('auth.field_registry'),
                $container->get('auth.avatar_manager'),
                $container->get('auth.form_repository'),
            );
        });

        $container->register('audit.reports', function () use ($container) {
            return new ReportAggregator($container->get(Connection::class));
        });

        $container->register('rest.admin', function () use ($container) {
            return new AdminController(
                $container->get('audit.logger'),
                $container->get('mfa.manager'),
                $container->get('auth.field_registry'),
                $container->get('audit.reports'),
                $container->get('gateway.registry'),
            );
        });

        $container->register('rest.telegram', function () use ($container) {
            return new TelegramController(
                $container->get('mfa.channel.telegram'),
                $container->get('message.dispatcher'),
            );
        });

        $container->register('rest.admin_user', function () use ($container) {
            return new AdminUserController(
                $container->get('audit.logger'),
                $container->get('mfa.manager'),
                $container->get('social.repository'),
                $container->get('auth.lockout'),
                $container->get('auth.account_manager'),
                $container->get('auth.settings'),
                $container->get('template.manager'),
                $container->get('message.dispatcher'),
                $container->get('log.app'),
                $container->get('auth.suspension'),
            );
        });

        // Messaging platform controllers
        $container->register('rest.flows', fn($c) => new FlowController(
            $c->get('flow.repository'),
            $c->get('flow.execution_repository'),
            $c->get('flow.triggers'),
            $c->get('flow.runner'),
        ));
        $container->register('rest.gateways', fn($c) => new GatewayController(
            $c->get('gateway.registry'),
            $c->get('log.message'),
        ));
        $container->register('rest.contact_sources', fn($c) => new ContactSourceController(
            $c->get('contact_source.registry'),
            $c->get('contact_source.manager'),
        ));
        $container->register('rest.contacts', fn($c) => new ContactController(
            $c->get('contact.repository'),
            $c->get('contact.segment_evaluator'),
            $c->get('contact.importer'),
            $c->get('contact.exporter'),
            $c->get('log.message'),
        ));
        $container->register('rest.tags', fn($c) => new TagController(
            $c->get('contact.tag_repository'),
        ));
        $container->register('rest.lists', fn($c) => new ListController(
            $c->get('contact.list_repository'),
            $c->get('contact.segment_evaluator'),
            $c->get('contact.repository'),
        ));
        $container->register('rest.message_logs', fn($c) => new MessageLogController(
            $c->get('log.message'),
        ));
        $container->register('rest.campaigns', fn($c) => new CampaignController(
            $c->get('campaign.repository'),
            $c->get('campaign.dispatcher'),
            $c->get('campaign.audience_resolver'),
            $c->get('log.message'),
            $c->get('message.dispatcher'),
        ));
        $container->register('rest.integrations', fn($c) => new IntegrationController(
            $c->get('integration.registry'),
            $c->get('flow.triggers'),
            $c->get('flow.actions'),
            $c->get('contact.repository'),
            $c->get('queue'),
            $c->has('marketing.suppression_poller') ? $c->get('marketing.suppression_poller') : null,
        ));
        $container->register('rest.gateway_callbacks', fn($c) => new GatewayCallbackController(
            $c->get('gateway.registry'),
            $c->get('log.message'),
            $c->get('auth.rate_limiter'),
            $c->get('campaign.repository'),
            $c->get('messaging.optout_manager'),
            $c->get('messaging.status_propagator'),
        ));
        $container->register('rest.optout_settings', fn() => new OptOutSettingsController());
        $container->register('rest.phone_restriction', fn($c) => new PhoneRestrictionController(
            $c->get('phone_restriction.settings'),
            $c->get('phone_restriction.resolver'),
            $c->get('phone_restriction.guard'),
            $c->get('phone_restriction.db_updater'),
        ));
        $container->register('rest.templates', fn($c) => new TemplateController(
            $c->get('template.manager'),
            $c->get('template.storage'),
            $c->get('auth.settings'),
            $c->get('template.catalog_manager'),
        ));
        $container->register('rest.template_catalog', fn($c) => new TemplateCatalogController(
            $c->get('template.catalog_manager'),
        ));
        $container->register('rest.outbound_webhooks', fn($c) => new OutboundWebhookController(
            $c->get('webhook.repository'),
        ));
        $container->register('rest.webhook_receiver', fn($c) => new WebhookReceiverController(
            $c->get('auth.rate_limiter'),
        ));
        $container->register('rest.registration_forms', fn($c) => new RegistrationFormController(
            $c->get('auth.form_repository'),
            $c->get('auth.field_registry'),
        ));
        $container->register('rest.messaging_button', fn($c) => new MessagingButtonController(
            $c->get('messaging_button.settings'),
            $c->get('messaging_button.handler'),
            $c->get('gateway.registry'),
            $c->get('auth.rate_limiter'),
        ));
        $container->register('rest.subscription_forms', fn($c) => new SubscriptionFormController(
            $c->get('subscription_form.repository'),
        ));
        $container->register('rest.subscription_forms_public', fn($c) => new SubscriptionFormPublicController(
            $c->get('subscription_form.repository'),
            $c->get('subscription_form.handler'),
            $c->get('auth.rate_limiter'),
        ));
    }

    /** {@inheritDoc} */
    public function boot(ServiceContainer $container): void
    {
        add_action('rest_api_init', function () use ($container) {
            $container->get('rest.auth')->registerRoutes();
            $container->get('rest.mfa')->registerRoutes();
            $container->get('rest.enrollment')->registerRoutes();
            $container->get('rest.account')->registerRoutes();
            $container->get('rest.admin')->registerRoutes();
            $container->get('rest.social')->registerRoutes();
            $container->get('rest.telegram')->registerRoutes();
            $container->get('rest.admin_user')->registerRoutes();
            // Messaging platform routes
            $container->get('rest.flows')->registerRoutes();
            $container->get('rest.gateways')->registerRoutes();
            $container->get('rest.contact_sources')->registerRoutes();
            $container->get('rest.contacts')->registerRoutes();
            $container->get('rest.tags')->registerRoutes();
            $container->get('rest.lists')->registerRoutes();
            $container->get('rest.message_logs')->registerRoutes();
            $container->get('rest.campaigns')->registerRoutes();
            $container->get('rest.integrations')->registerRoutes();
            $container->get('rest.gateway_callbacks')->registerRoutes();
            $container->get('rest.optout_settings')->registerRoutes();
            $container->get('rest.phone_restriction')->registerRoutes();
            $container->get('rest.outbound_webhooks')->registerRoutes();
            $container->get('rest.webhook_receiver')->registerRoutes();
            $container->get('rest.messaging_button')->registerRoutes();
            $container->get('rest.registration_forms')->registerRoutes();
            $container->get('rest.subscription_forms')->registerRoutes();
            $container->get('rest.subscription_forms_public')->registerRoutes();
            $container->get('rest.templates')->registerRoutes();
            $container->get('rest.template_catalog')->registerRoutes();
        });
    }
}
