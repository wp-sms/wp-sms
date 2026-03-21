<?php

namespace WSms\Container;

use WSms\Flow\Action\ActionRegistry;
use WSms\Flow\Condition\ExpressionLanguageEvaluator;
use WSms\Flow\Engine\FlowExecutor;
use WSms\Flow\Engine\FlowRunner;
use WSms\Flow\Engine\PayloadResolver;
use WSms\Flow\Storage\FlowExecutionRepository;
use WSms\Flow\Storage\FlowRepository;
use WSms\Flow\Trigger\TriggerRegistry;

defined('ABSPATH') || exit;

class FlowServiceProvider implements ServiceProvider
{
    public function register(ServiceContainer $container): void
    {
        $container->register('flow.triggers', fn() => new TriggerRegistry());
        $container->register('flow.actions', fn() => new ActionRegistry());
        $container->register('flow.conditions', fn() => new ExpressionLanguageEvaluator());
        $container->register('flow.repository', fn() => new FlowRepository());
        $container->register('flow.execution_repository', fn() => new FlowExecutionRepository());

        $container->register('flow.payload_resolver', fn($c) => new PayloadResolver(
            $c->get('template.engine'),
        ));

        $container->register('flow.executor', fn($c) => new FlowExecutor(
            $c->get('queue'),
            $c->get('flow.conditions'),
            $c->get('flow.execution_repository'),
            $c->get('flow.payload_resolver'),
            $c->get('event.dispatcher'),
            $c->get('flow.actions'),
            $c->get('log.flow'),
            $c->get('log.app'),
        ));

        $container->register('flow.runner', fn($c) => new FlowRunner(
            $c->get('flow.triggers'),
            $c->get('flow.repository'),
            $c->get('flow.executor'),
            $c->get('flow.execution_repository'),
            $c->get('event.dispatcher'),
            $c->get('log.app'),
        ));
    }

    public function boot(ServiceContainer $container): void
    {
        // Register job handlers
        $processor = $container->get('queue.processor');
        $processor->registerHandler('execute_flow_step', function (array $payload) use ($container) {
            $executor = $container->get('flow.executor');
            $executor->executeNode($payload['node'], $payload['payload'], $payload['execution_id']);
        });

        $processor->registerHandler('send_message', function (array $payload) use ($container) {
            $registry = $container->get('gateway.registry');
            $logger = $container->get('log.message');

            if (in_array($payload['channel'], ['sms', 'whatsapp'], true)) {
                $restriction = $container->get('phone_restriction.guard')->check($payload['recipient'], 'messaging');

                if (!$restriction->allowed) {
                    $logger->updateStatus($payload['log_id'], 'blocked', $restriction->message);
                    return;
                }
            }

            $gateway = $registry->get($payload['gateway_id']);

            if (!$gateway) {
                $logger->updateStatus($payload['log_id'], 'failed', 'Gateway not found');
                return;
            }

            $message = new \WSms\Messaging\Message\Message(
                $payload['channel'],
                $payload['recipient'],
                $payload['body'],
                $payload['execution_id'] ?? null,
                $payload['meta'] ?? [],
            );

            $result = $gateway->send($message);
            $logger->updateStatus(
                $payload['log_id'],
                $result->success ? $result->status : 'failed',
                $result->error,
                $result->providerId,
            );
        });

        // Subscribe active triggers
        $container->get('flow.runner')->subscribeActiveTriggers();
    }
}
