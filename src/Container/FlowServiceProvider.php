<?php

namespace WSms\Container;

use WSms\Flow\Action\ActionRegistry;
use WSms\Flow\Action\HttpRequestAction;
use WSms\Flow\Action\SendMessageAction;
use WSms\Flow\Action\SetUserRoleAction;
use WSms\Flow\Action\UpdateUserMetaAction;
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
        $actions = $container->get('flow.actions');
        $actions->register(new SendMessageAction(
            $container->get('message.dispatcher'),
        ));
        $actions->register(new HttpRequestAction());
        $actions->register(new UpdateUserMetaAction());
        $actions->register(new SetUserRoleAction());

        // Register job handlers
        $processor = $container->get('queue.processor');
        $processor->registerHandler('execute_flow_step', function (array $payload) use ($container) {
            $executor = $container->get('flow.executor');
            $executor->executeNode($payload['node'], $payload['payload'], $payload['execution_id']);
        });

        // Subscribe active triggers
        $container->get('flow.runner')->subscribeActiveTriggers();
    }
}
