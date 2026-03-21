<?php

namespace WSms\Flow\Engine;

use WSms\Dependencies\Psr\Log\LoggerInterface;
use WSms\Enums\ExecutionStatus;
use WSms\Event\Contracts\EventDispatcherInterface;
use WSms\Event\Events\FlowCompletedEvent;
use WSms\Flow\Action\ActionRegistry;
use WSms\Flow\Contracts\ConditionEvaluatorInterface;
use WSms\Integration\PayloadSchemas;
use WSms\Flow\Storage\FlowExecutionRepository;
use WSms\Log\FlowLogger;
use WSms\Queue\Contracts\QueueInterface;
use WSms\Queue\Job\ExecuteFlowStepJob;

defined('ABSPATH') || exit;

class FlowExecutor
{
    public function __construct(
        private readonly QueueInterface $queue,
        private readonly ConditionEvaluatorInterface $conditionEvaluator,
        private readonly FlowExecutionRepository $executionRepository,
        private readonly PayloadResolver $payloadResolver,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ActionRegistry $actionRegistry,
        private readonly FlowLogger $flowLogger,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function execute(string $executionId, array $steps, ExecutionContext $context): void
    {
        $this->executionRepository->updateStatus($executionId, ExecutionStatus::Running->value);

        $this->expandEntities($context);

        foreach ($steps as $step) {
            $this->executeNode($step, $context, $executionId);
        }
    }

    public function executeNode(array $node, ExecutionContext $context, string $executionId): void
    {
        $type = $node['type'] ?? '';
        $nodeId = $node['id'] ?? 'unknown';

        try {
            match ($type) {
                'action'         => $this->executeAction($node, $context, $executionId),
                'condition'      => $this->executeCondition($node, $context, $executionId),
                'parallel'       => $this->executeParallel($node, $context, $executionId),
                'delay'          => $this->executeDelay($node, $context, $executionId),
                'wait_for_event' => $this->executeWaitForEvent($node, $context, $executionId),
                '_branch'        => $this->executeBranch($node, $context, $executionId),
                default          => $this->logger->warning("Unknown node type: {$type}", ['node_id' => $nodeId]),
            };
        } catch (\Throwable $e) {
            $this->flowLogger->logStepError($executionId, $nodeId, $type, $e->getMessage());
            $this->executionRepository->setError($executionId, "Node {$nodeId} failed: {$e->getMessage()}");
        }
    }

    private function executeAction(array $node, ExecutionContext $context, string $executionId): void
    {
        $nodeId = $node['id'];
        $actionId = $node['action'];
        $resolverData = $context->getResolverData();
        $config = $this->payloadResolver->resolveConfig($node['config'] ?? [], $resolverData);

        $this->flowLogger->logStepStart($executionId, $nodeId, 'action', [
            'action'          => $actionId,
            'resolved_config' => $this->redactSensitive($config),
        ]);

        $action = $this->actionRegistry->get($actionId);
        if (!$action) {
            $this->flowLogger->logStepError($executionId, $nodeId, 'action', "Unknown action: {$actionId}");
            return;
        }

        $errorHandling = $node['onError'] ?? ['behavior' => 'stop'];
        $maxAttempts = ($errorHandling['behavior'] === 'retry')
            ? max(1, (int) ($errorHandling['maxRetries'] ?? 3))
            : 1;
        $retryInterval = (int) ($errorHandling['retryIntervalSecs'] ?? 30);

        $lastError = null;
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $result = $action->execute($resolverData, $config);

            if ($result->success) {
                if ($result->output) {
                    $context->setActionOutput($actionId, $result->output);
                }
                $this->flowLogger->logStepComplete($executionId, $nodeId, 'action', $result->output);
                return;
            }

            $lastError = $result->error ?? 'Action failed';

            if ($attempt < $maxAttempts) {
                $this->flowLogger->logStepRetry($executionId, $nodeId, 'action', $attempt, $maxAttempts, $lastError);
                sleep($retryInterval);
            }
        }

        $this->flowLogger->logStepError($executionId, $nodeId, 'action', $lastError);

        $shouldStop = match ($errorHandling['behavior']) {
            'stop' => true,
            'continue' => false,
            'retry' => !($errorHandling['continueOnExhausted'] ?? false),
            default => true,
        };

        if ($shouldStop) {
            throw new \RuntimeException("Action {$actionId} failed: {$lastError}");
        }
    }

    private function executeCondition(array $node, ExecutionContext $context, string $executionId): void
    {
        $nodeId = $node['id'];
        $expression = $node['expression'];
        $rules = $node['rules'] ?? [];
        $resolverData = $context->getResolverData();

        $evaluatedVars = [];
        foreach ($rules as $rule) {
            $field = $rule['field'] ?? '';
            if ($field) {
                $evaluatedVars[$field] = $this->resolveFieldValue($field, $resolverData);
            }
        }

        $input = ['expression' => $expression];
        if (!empty($evaluatedVars)) {
            $input['variables'] = $evaluatedVars;
        }

        $this->flowLogger->logStepStart($executionId, $nodeId, 'condition', $input);

        $result = $this->conditionEvaluator->evaluate($expression, $resolverData);

        $this->flowLogger->logStepComplete($executionId, $nodeId, 'condition', [
            'result' => $result,
            'branch' => $result ? 'then' : 'else',
        ]);

        $branch = $result ? ($node['then'] ?? []) : ($node['else'] ?? []);

        foreach ($branch as $step) {
            $this->executeNode($step, $context, $executionId);
        }
    }

    private function executeParallel(array $node, ExecutionContext $context, string $executionId): void
    {
        $nodeId = $node['id'];
        $branches = $node['branches'] ?? [];

        $this->flowLogger->logStepStart($executionId, $nodeId, 'parallel', ['branch_count' => count($branches)]);

        // Each branch gets a frozen snapshot of current state.
        $contextData = $context->toArray();

        // Dispatch one job per branch (not per step) so steps within a branch
        // execute sequentially while branches run concurrently.
        foreach ($branches as $index => $branch) {
            if (empty($branch)) {
                continue;
            }
            // Wrap the branch as a sequential step list inside a synthetic container node.
            $branchNode = [
                'id'    => $nodeId . '_branch_' . $index,
                'type'  => '_branch',
                'steps' => $branch,
            ];
            $this->queue->dispatch(new ExecuteFlowStepJob($executionId, $branchNode, $contextData));
        }

        $this->flowLogger->logStepComplete($executionId, $nodeId, 'parallel');
    }

    private function executeDelay(array $node, ExecutionContext $context, string $executionId): void
    {
        $nodeId = $node['id'];
        $duration = (int) ($node['duration'] ?? 0);

        $this->flowLogger->logStepStart($executionId, $nodeId, 'delay', ['duration' => $duration]);

        $thenSteps = $node['then'] ?? [];

        if (empty($thenSteps)) {
            return;
        }

        $runAt = new \DateTimeImmutable('+' . $duration . ' seconds');
        $contextData = $context->toArray();

        foreach ($thenSteps as $step) {
            $this->queue->schedule(new ExecuteFlowStepJob($executionId, $step, $contextData), $runAt);
        }

        $this->flowLogger->logStepComplete($executionId, $nodeId, 'delay', ['scheduled_at' => $runAt->format('Y-m-d H:i:s')]);
    }

    private function executeWaitForEvent(array $node, ExecutionContext $context, string $executionId): void
    {
        $nodeId = $node['id'];

        $this->flowLogger->logStepStart($executionId, $nodeId, 'wait_for_event', [
            'event'   => $node['event'],
            'timeout' => $node['timeout'] ?? 86400,
        ]);

        $this->executionRepository->setWaiting(
            $executionId,
            $node['event'],
            $node['match'] ?? '',
            $nodeId,
            $context->toArray(),
            (int) ($node['timeout'] ?? 86400),
            $node['timeout_action'] ?? 'cancel',
        );
    }

    public function resumeFromWait(string $executionId, array $node, ExecutionContext $context, array $eventData): void
    {
        $this->executionRepository->clearWaitState($executionId);

        $context->mergePayload(['event' => $eventData]);

        $thenSteps = $node['then'] ?? [];
        foreach ($thenSteps as $step) {
            $this->executeNode($step, $context, $executionId);
        }
    }

    private function executeBranch(array $node, ExecutionContext $context, string $executionId): void
    {
        foreach ($node['steps'] ?? [] as $step) {
            $this->executeNode($step, $context, $executionId);
        }
    }

    private function redactSensitive(array $config): array
    {
        $sensitiveKeys = ['authorization', 'x-api-key', 'api_key', 'api_secret', 'secret', 'token', 'password', 'auth_token'];
        $result = [];
        foreach ($config as $key => $value) {
            if (is_array($value)) {
                $result[$key] = $this->redactSensitive($value);
            } elseif (is_string($key) && in_array(strtolower($key), $sensitiveKeys, true)) {
                $result[$key] = '***REDACTED***';
            } else {
                $result[$key] = $value;
            }
        }
        return $result;
    }

    private function resolveFieldValue(string $dotPath, array $payload): mixed
    {
        $current = $payload;
        foreach (explode('.', $dotPath) as $part) {
            if (!is_array($current) || !array_key_exists($part, $current)) {
                return null;
            }
            $current = $current[$part];
        }
        return $current;
    }

    private function expandEntities(ExecutionContext $context): void
    {
        $payload = $context->getPayload();
        $additions = [];

        if (isset($payload['user_id']) && !isset($payload['user'])) {
            $user = get_userdata((int) $payload['user_id']);
            if ($user) {
                $additions['user'] = PayloadSchemas::extractWpUser($user);
            }
        }

        if (isset($payload['author_id']) && !isset($payload['author'])) {
            $user = get_userdata((int) $payload['author_id']);
            if ($user) {
                $additions['author'] = PayloadSchemas::extractWpUser($user, ['email', 'phone', 'display_name']);
            }
        }

        if (isset($payload['post_id']) && !isset($payload['post'])) {
            $post = get_post((int) $payload['post_id']);
            if ($post) {
                $additions['post'] = PayloadSchemas::extractPost($post);
            }
        }

        if ($additions) {
            $context->mergePayload($additions);
        }
    }

    public function markCompleted(string $executionId, string $flowId): void
    {
        $this->executionRepository->updateStatus($executionId, ExecutionStatus::Completed->value);
        $this->eventDispatcher->dispatch(new FlowCompletedEvent($flowId, $executionId, ExecutionStatus::Completed->value));
    }
}
