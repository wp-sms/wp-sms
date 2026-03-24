<?php

namespace WSms\Container;

use WSms\Flow\Action\ActionRegistry;
use WSms\Flow\Condition\ExpressionLanguageEvaluator;
use WSms\Flow\Engine\ExecutionContext;
use WSms\Flow\Engine\FlowExecutor;
use WSms\Flow\Engine\FlowRunner;
use WSms\Flow\Engine\PayloadResolver;
use WSms\Flow\Storage\FlowExecutionRepository;
use WSms\Flow\Storage\FlowRepository;
use WSms\Flow\Trigger\TriggerRegistry;
use WSms\Database\Connection;

defined('ABSPATH') || exit;

class FlowServiceProvider implements ServiceProvider
{
    public function register(ServiceContainer $container): void
    {
        $container->register('flow.triggers', fn() => new TriggerRegistry());
        $container->register('flow.actions', fn() => new ActionRegistry());
        $container->register('flow.conditions', fn() => new ExpressionLanguageEvaluator());
        $container->register('flow.repository', fn($c) => new FlowRepository($c->get(Connection::class)));
        $container->register('flow.execution_repository', fn($c) => new FlowExecutionRepository($c->get(Connection::class)));

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
            $c->get('contact.repository'),
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
            $context = ExecutionContext::fromArray($payload['context']);
            $executor->executeNode($payload['node'], $context, $payload['execution_id']);
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
                array_merge($payload['meta'] ?? [], ['log_id' => $payload['log_id'] ?? null]),
            );

            $result = $gateway->send($message);

            if ($result->retryable) {
                throw new \RuntimeException("Transient delivery failure: {$result->error}");
            }

            $logger->updateStatus(
                $payload['log_id'],
                $result->success ? $result->status : 'failed',
                $result->error,
                $result->providerId,
            );
        });

        // Mark message log as failed when all retries are exhausted
        add_action('wsms_job_failed', function (array $args, \Throwable $e) use ($container) {
            if (($args['type'] ?? '') !== 'send_message') {
                return;
            }

            $logId = $args['payload']['log_id'] ?? null;
            if ($logId) {
                $container->get('log.message')->updateStatus($logId, 'failed', $e->getMessage());
            }
        }, 10, 2);

        // Subscribe active triggers
        $container->get('flow.runner')->subscribeActiveTriggers();
    }
}
