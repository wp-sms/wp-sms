<?php

namespace WSms\Container;

use WSms\Webhook\OutboundWebhookDispatcher;
use WSms\Webhook\WebhookHttpClient;
use WSms\Webhook\WebhookRepository;

defined('ABSPATH') || exit;

class WebhookServiceProvider implements ServiceProvider
{
    public function register(ServiceContainer $container): void
    {
        $container->register('webhook.repository', fn() => new WebhookRepository());

        $container->register('webhook.dispatcher', fn($c) => new OutboundWebhookDispatcher(
            $c->get('event.dispatcher'),
            $c->get('webhook.repository'),
            $c->get('queue'),
            $c->get('log.app'),
            $c->get('contact.repository'),
        ));
    }

    public function boot(ServiceContainer $container): void
    {
        $container->get('queue.processor')->registerHandler('deliver_webhook', function (array $payload) use ($container) {
            $repository = $container->get('webhook.repository');

            if (!$repository->find($payload['webhook_id'])) {
                return;
            }

            $result = WebhookHttpClient::send(
                $payload['url'],
                wp_json_encode($payload['payload']),
                $payload['secret'],
                $payload['event'],
                $payload['webhook_id'],
            );

            $response = $result['response'];

            if (is_wp_error($response)) {
                throw new \RuntimeException('Webhook delivery failed: ' . $response->get_error_message());
            }

            if (!WebhookHttpClient::isSuccess($response)) {
                $code = wp_remote_retrieve_response_code($response);
                throw new \RuntimeException("Webhook delivery failed with HTTP {$code}");
            }
        });

        $container->get('webhook.dispatcher')->subscribe();
    }
}
