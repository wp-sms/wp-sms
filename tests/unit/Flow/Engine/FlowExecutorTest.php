<?php

namespace WSms\Tests\Unit\Flow\Engine;

use PHPUnit\Framework\TestCase;
use WSms\Event\EventDispatcher;
use WSms\Flow\Action\ActionRegistry;
use WSms\Flow\Contracts\ActionInterface;
use WSms\Flow\Contracts\ActionResult;
use WSms\Flow\Contracts\ConditionEvaluatorInterface;
use WSms\Flow\Engine\ExecutionContext;
use WSms\Flow\Engine\FlowExecutor;
use WSms\Flow\Engine\PayloadResolver;
use WSms\Flow\Storage\FlowExecutionRepository;
use WSms\Log\FlowLogger;
use WSms\Log\WpLogger;
use WSms\Messaging\Template\MustacheEngine;
use WSms\Queue\Contracts\QueueInterface;
use WSms\Queue\Contracts\JobInterface;

class FlowExecutorTest extends TestCase
{
    private FlowExecutor $executor;
    private ActionRegistry $actionRegistry;
    private array $dispatched = [];

    protected function setUp(): void
    {
        $GLOBALS['_test_do_action_calls'] = [];

        $queue = new class($this->dispatched) implements QueueInterface {
            public function __construct(private array &$dispatched) {}
            public function dispatch(JobInterface $job): string {
                $this->dispatched[] = $job;
                return 'job-id';
            }
            public function schedule(JobInterface $job, \DateTimeInterface $runAt): string {
                $this->dispatched[] = ['job' => $job, 'at' => $runAt];
                return 'scheduled-job-id';
            }
            public function cancel(string $jobId): bool { return true; }
        };

        $conditionEvaluator = new class implements ConditionEvaluatorInterface {
            public function evaluate(string $expression, array $payload): bool {
                return (bool) eval("return {$expression};");
            }
            public function validate(string $expression): bool { return true; }
        };

        $executionRepo = $this->createMock(FlowExecutionRepository::class);
        $eventDispatcher = new EventDispatcher();
        $this->actionRegistry = new ActionRegistry();
        $logger = new WpLogger();
        $flowLogger = $this->createMock(FlowLogger::class);
        $payloadResolver = new PayloadResolver(new MustacheEngine());

        $this->executor = new FlowExecutor(
            $queue,
            $conditionEvaluator,
            $executionRepo,
            $payloadResolver,
            $eventDispatcher,
            $this->actionRegistry,
            $flowLogger,
            $logger,
        );
    }

    public function testExecuteActionNode(): void
    {
        $executed = false;

        $this->actionRegistry->register($this->makeAction('test_action', function (array $payload, array $config) use (&$executed) {
            $executed = true;
            return ActionResult::success(['done' => true]);
        }));

        $context = new ExecutionContext(['data' => 'value']);
        $this->executor->executeNode([
            'id'     => 'step_1',
            'type'   => 'action',
            'action' => 'test_action',
            'config' => [],
        ], $context, 'exec-1');

        $this->assertTrue($executed);
    }

    public function testExecuteConditionThenBranch(): void
    {
        $executed = false;

        $this->actionRegistry->register($this->makeAction('then_action', function () use (&$executed) {
            $executed = true;
            return ActionResult::success();
        }));

        $context = new ExecutionContext([]);
        $this->executor->executeNode([
            'id'         => 'cond_1',
            'type'       => 'condition',
            'expression' => 'true',
            'then'       => [
                ['id' => 'then_1', 'type' => 'action', 'action' => 'then_action', 'config' => []],
            ],
            'else'       => [],
        ], $context, 'exec-1');

        $this->assertTrue($executed);
    }

    public function testExecuteConditionElseBranch(): void
    {
        $executed = false;

        $this->actionRegistry->register($this->makeAction('else_action', function () use (&$executed) {
            $executed = true;
            return ActionResult::success();
        }));

        $context = new ExecutionContext([]);
        $this->executor->executeNode([
            'id'         => 'cond_1',
            'type'       => 'condition',
            'expression' => 'false',
            'then'       => [],
            'else'       => [
                ['id' => 'else_1', 'type' => 'action', 'action' => 'else_action', 'config' => []],
            ],
        ], $context, 'exec-1');

        $this->assertTrue($executed);
    }

    public function testExecuteParallelDispatchesToQueue(): void
    {
        $context = new ExecutionContext([]);
        $this->executor->executeNode([
            'id'       => 'par_1',
            'type'     => 'parallel',
            'branches' => [
                [['id' => 'a', 'type' => 'action', 'action' => 'test', 'config' => []]],
                [['id' => 'b', 'type' => 'action', 'action' => 'test', 'config' => []]],
            ],
        ], $context, 'exec-1');

        $this->assertCount(2, $this->dispatched);
    }

    public function testExecuteDelaySchedulesToQueue(): void
    {
        $context = new ExecutionContext([]);
        $this->executor->executeNode([
            'id'       => 'delay_1',
            'type'     => 'delay',
            'duration' => 300,
            'then'     => [
                ['id' => 'after_delay', 'type' => 'action', 'action' => 'test', 'config' => []],
            ],
        ], $context, 'exec-1');

        $this->assertCount(1, $this->dispatched);
        $this->assertArrayHasKey('at', $this->dispatched[0]);
    }

    public function testTemplateResolutionInActionConfig(): void
    {
        $receivedConfig = [];

        $this->actionRegistry->register($this->makeAction('capture', function (array $payload, array $config) use (&$receivedConfig) {
            $receivedConfig = $config;
            return ActionResult::success();
        }));

        $context = new ExecutionContext(['user' => ['email' => 'a@b.com', 'name' => 'Alice']]);
        $this->executor->executeNode([
            'id'     => 'step_1',
            'type'   => 'action',
            'action' => 'capture',
            'config' => [
                'to'   => '{{user.email}}',
                'body' => 'Hello {{user.name}}!',
            ],
        ], $context, 'exec-1');

        $this->assertSame('a@b.com', $receivedConfig['to']);
        $this->assertSame('Hello Alice!', $receivedConfig['body']);
    }

    public function testActionStepLogsResolvedConfig(): void
    {
        $loggedInputs = [];

        $flowLogger = $this->createMock(FlowLogger::class);
        $flowLogger->method('logStepStart')->willReturnCallback(
            function ($execId, $nodeId, $type, $input) use (&$loggedInputs) {
                $loggedInputs[] = $input;
            }
        );

        $executor = $this->createExecutorWithLogger($flowLogger);

        $this->actionRegistry->register($this->makeAction('log_test', function () {
            return ActionResult::success();
        }));

        $context = new ExecutionContext([]);
        $executor->executeNode([
            'id'     => 'step_1',
            'type'   => 'action',
            'action' => 'log_test',
            'config' => ['to' => '+1234567890', 'body' => 'Hello'],
        ], $context, 'exec-1');

        $this->assertArrayHasKey('resolved_config', $loggedInputs[0]);
        $this->assertSame('+1234567890', $loggedInputs[0]['resolved_config']['to']);
        $this->assertSame('Hello', $loggedInputs[0]['resolved_config']['body']);
    }

    public function testRedactSensitiveKeys(): void
    {
        $loggedInputs = [];

        $flowLogger = $this->createMock(FlowLogger::class);
        $flowLogger->method('logStepStart')->willReturnCallback(
            function ($execId, $nodeId, $type, $input) use (&$loggedInputs) {
                $loggedInputs[] = $input;
            }
        );

        $executor = $this->createExecutorWithLogger($flowLogger);

        $this->actionRegistry->register($this->makeAction('redact_test', function () {
            return ActionResult::success();
        }));

        $context = new ExecutionContext([]);
        $executor->executeNode([
            'id'     => 'step_1',
            'type'   => 'action',
            'action' => 'redact_test',
            'config' => [
                'url'     => 'https://example.com',
                'headers' => [
                    'Authorization' => 'Bearer secret-token',
                    'Content-Type'  => 'application/json',
                ],
                'api_key' => 'my-key-123',
                'body'    => 'safe content',
            ],
        ], $context, 'exec-1');

        $config = $loggedInputs[0]['resolved_config'];
        $this->assertSame('https://example.com', $config['url']);
        $this->assertSame('***REDACTED***', $config['headers']['Authorization']);
        $this->assertSame('application/json', $config['headers']['Content-Type']);
        $this->assertSame('***REDACTED***', $config['api_key']);
        $this->assertSame('safe content', $config['body']);
    }

    public function testConditionStepLogsEvaluatedVariables(): void
    {
        $loggedInputs = [];

        $flowLogger = $this->createMock(FlowLogger::class);
        $flowLogger->method('logStepStart')->willReturnCallback(
            function ($execId, $nodeId, $type, $input) use (&$loggedInputs) {
                $loggedInputs[] = $input;
            }
        );

        $executor = $this->createExecutorWithLogger($flowLogger);

        $context = new ExecutionContext(['user' => ['role' => 'subscriber', 'email' => 'a@b.com']]);
        $executor->executeNode([
            'id'         => 'cond_1',
            'type'       => 'condition',
            'expression' => 'true',
            'rules'      => [
                ['field' => 'user.role', 'operator' => 'equals', 'value' => 'admin'],
            ],
            'then'       => [],
            'else'       => [],
        ], $context, 'exec-1');

        $this->assertArrayHasKey('variables', $loggedInputs[0]);
        $this->assertSame('subscriber', $loggedInputs[0]['variables']['user.role']);
    }

    public function testConditionLogsResultWithBranch(): void
    {
        $loggedOutputs = [];

        $flowLogger = $this->createMock(FlowLogger::class);
        $flowLogger->method('logStepComplete')->willReturnCallback(
            function ($execId, $nodeId, $type, $output) use (&$loggedOutputs) {
                $loggedOutputs[] = $output;
            }
        );

        $executor = $this->createExecutorWithLogger($flowLogger);

        $context = new ExecutionContext([]);
        $executor->executeNode([
            'id'         => 'cond_1',
            'type'       => 'condition',
            'expression' => 'false',
            'then'       => [],
            'else'       => [],
        ], $context, 'exec-1');

        $this->assertFalse($loggedOutputs[0]['result']);
        $this->assertSame('else', $loggedOutputs[0]['branch']);
    }

    public function testResolveFieldValueHandlesMissingPaths(): void
    {
        $loggedInputs = [];

        $flowLogger = $this->createMock(FlowLogger::class);
        $flowLogger->method('logStepStart')->willReturnCallback(
            function ($execId, $nodeId, $type, $input) use (&$loggedInputs) {
                $loggedInputs[] = $input;
            }
        );

        $executor = $this->createExecutorWithLogger($flowLogger);

        $context = new ExecutionContext(['user' => ['role' => 'admin']]);
        $executor->executeNode([
            'id'         => 'cond_1',
            'type'       => 'condition',
            'expression' => 'true',
            'rules'      => [
                ['field' => 'user.nonexistent.deep', 'operator' => 'equals', 'value' => 'x'],
            ],
            'then'       => [],
            'else'       => [],
        ], $context, 'exec-1');

        $this->assertNull($loggedInputs[0]['variables']['user.nonexistent.deep']);
    }

    // --- Action Output Chaining Tests ---

    public function testSequentialChainingStoresOutputInContext(): void
    {
        $receivedPayload = [];

        $this->actionRegistry->register($this->makeAction('action_a', function () {
            return ActionResult::success(['result' => 'from_a', 'count' => 42]);
        }));

        $this->actionRegistry->register($this->makeAction('action_b', function (array $payload) use (&$receivedPayload) {
            $receivedPayload = $payload;
            return ActionResult::success(['result' => 'from_b']);
        }));

        $context = new ExecutionContext(['trigger_data' => 'value']);
        $steps = [
            ['id' => 'step_1', 'type' => 'action', 'action' => 'action_a', 'config' => []],
            ['id' => 'step_2', 'type' => 'action', 'action' => 'action_b', 'config' => []],
        ];

        $this->executor->execute('exec-1', $steps, $context);

        // Action B receives resolver data with actions merged in
        $this->assertArrayHasKey('actions', $receivedPayload);
        $this->assertSame('from_a', $receivedPayload['actions']['action_a']['result']);
        $this->assertSame(42, $receivedPayload['actions']['action_a']['count']);

        // Original payload stays clean
        $this->assertArrayNotHasKey('actions', $context->getPayload());

        // getResolverData() merges both outputs
        $resolverData = $context->getResolverData();
        $this->assertSame('from_a', $resolverData['actions']['action_a']['result']);
        $this->assertSame('from_b', $resolverData['actions']['action_b']['result']);
    }

    public function testConditionCanReadActionOutput(): void
    {
        $this->actionRegistry->register($this->makeAction('http_request', function () {
            return ActionResult::success(['http_status' => 200, 'body' => ['ok' => true]]);
        }));

        $thenExecuted = false;
        $this->actionRegistry->register($this->makeAction('then_action', function () use (&$thenExecuted) {
            $thenExecuted = true;
            return ActionResult::success();
        }));

        $context = new ExecutionContext([]);
        $steps = [
            ['id' => 'step_1', 'type' => 'action', 'action' => 'http_request', 'config' => []],
            [
                'id' => 'cond_1',
                'type' => 'condition',
                'expression' => '$payload["actions"]["http_request"]["http_status"] == 200',
                'then' => [
                    ['id' => 'then_1', 'type' => 'action', 'action' => 'then_action', 'config' => []],
                ],
                'else' => [],
            ],
        ];

        $this->executor->execute('exec-1', $steps, $context);

        $this->assertTrue($thenExecuted);
    }

    public function testDuplicateActionTypeOverwritesOutput(): void
    {
        $receivedPayload = [];

        $this->actionRegistry->register($this->makeAction('send_message', function (array $payload) use (&$receivedPayload) {
            $receivedPayload = $payload;
            return ActionResult::success(['status' => 'sent', 'provider_id' => 'msg_' . ($payload['actions']['send_message']['provider_id'] ?? 'first')]);
        }));

        $context = new ExecutionContext([]);
        $steps = [
            ['id' => 'step_1', 'type' => 'action', 'action' => 'send_message', 'config' => []],
            ['id' => 'step_2', 'type' => 'action', 'action' => 'send_message', 'config' => []],
        ];

        $this->executor->execute('exec-1', $steps, $context);

        // Second call should see the first output
        $this->assertSame('sent', $receivedPayload['actions']['send_message']['status']);
    }

    public function testFailedActionDoesNotMergeOutput(): void
    {
        $receivedPayload = [];

        $this->actionRegistry->register($this->makeAction('failing_action', function () {
            return ActionResult::failure('Something went wrong');
        }));

        $this->actionRegistry->register($this->makeAction('next_action', function (array $payload) use (&$receivedPayload) {
            $receivedPayload = $payload;
            return ActionResult::success();
        }));

        $context = new ExecutionContext([]);
        $steps = [
            [
                'id' => 'step_1',
                'type' => 'action',
                'action' => 'failing_action',
                'config' => [],
                'onError' => ['behavior' => 'continue'],
            ],
            ['id' => 'step_2', 'type' => 'action', 'action' => 'next_action', 'config' => []],
        ];

        $this->executor->execute('exec-1', $steps, $context);

        $this->assertArrayNotHasKey('failing_action', $receivedPayload['actions'] ?? []);
    }

    public function testContinueOnErrorPreservesEarlierOutputs(): void
    {
        $receivedPayload = [];

        $this->actionRegistry->register($this->makeAction('good_action', function () {
            return ActionResult::success(['data' => 'good']);
        }));

        $this->actionRegistry->register($this->makeAction('bad_action', function () {
            return ActionResult::failure('Oops');
        }));

        $this->actionRegistry->register($this->makeAction('final_action', function (array $payload) use (&$receivedPayload) {
            $receivedPayload = $payload;
            return ActionResult::success();
        }));

        $context = new ExecutionContext([]);
        $steps = [
            ['id' => 'step_1', 'type' => 'action', 'action' => 'good_action', 'config' => []],
            [
                'id' => 'step_2',
                'type' => 'action',
                'action' => 'bad_action',
                'config' => [],
                'onError' => ['behavior' => 'continue'],
            ],
            ['id' => 'step_3', 'type' => 'action', 'action' => 'final_action', 'config' => []],
        ];

        $this->executor->execute('exec-1', $steps, $context);

        $this->assertSame('good', $receivedPayload['actions']['good_action']['data']);
        $this->assertArrayNotHasKey('bad_action', $receivedPayload['actions']);
    }

    public function testDelaySnapshotIncludesAccumulatedOutputs(): void
    {
        $this->actionRegistry->register($this->makeAction('before_delay', function () {
            return ActionResult::success(['data' => 'pre_delay']);
        }));

        $context = new ExecutionContext([]);
        $steps = [
            ['id' => 'step_1', 'type' => 'action', 'action' => 'before_delay', 'config' => []],
            [
                'id' => 'delay_1',
                'type' => 'delay',
                'duration' => 60,
                'then' => [
                    ['id' => 'after_delay', 'type' => 'action', 'action' => 'test', 'config' => []],
                ],
            ],
        ];

        $this->executor->execute('exec-1', $steps, $context);

        // The delay step dispatches a job with a serialized context snapshot
        $this->assertCount(1, $this->dispatched);
        $job = $this->dispatched[0]['job'];
        $jobPayload = $job->getPayload();

        // Context is now under 'context' key with explicit structure
        $contextData = $jobPayload['context'];
        $this->assertSame('pre_delay', $contextData['action_outputs']['before_delay']['data']);
    }

    public function testEmptyOutputNotMergedIntoPayload(): void
    {
        $receivedPayload = [];

        $this->actionRegistry->register($this->makeAction('empty_output', function () {
            return ActionResult::success([]);
        }));

        $this->actionRegistry->register($this->makeAction('checker', function (array $payload) use (&$receivedPayload) {
            $receivedPayload = $payload;
            return ActionResult::success();
        }));

        $context = new ExecutionContext([]);
        $steps = [
            ['id' => 'step_1', 'type' => 'action', 'action' => 'empty_output', 'config' => []],
            ['id' => 'step_2', 'type' => 'action', 'action' => 'checker', 'config' => []],
        ];

        $this->executor->execute('exec-1', $steps, $context);

        // Empty output should not be merged
        $this->assertArrayNotHasKey('empty_output', $receivedPayload['actions'] ?? []);
    }

    // --- ExecutionContext Serialization Tests ---

    public function testContextToArrayAndFromArrayRoundTrip(): void
    {
        $context = new ExecutionContext(['trigger' => 'data', 'user_id' => 5]);
        $context->setActionOutput('http_request', ['status' => 200, 'body' => ['ok' => true]]);
        $context->setActionOutput('send_message', ['provider_id' => 'msg_123']);

        $serialized = $context->toArray();
        $restored = ExecutionContext::fromArray($serialized);

        $this->assertSame($context->getPayload(), $restored->getPayload());
        $this->assertSame($context->getResolverData(), $restored->getResolverData());
    }

    public function testContextResolverDataMergesActionsOnTheFly(): void
    {
        $context = new ExecutionContext(['trigger' => 'value']);

        // Before any action output
        $this->assertArrayNotHasKey('actions', $context->getResolverData());

        // After setting output
        $context->setActionOutput('action_a', ['result' => 'ok']);
        $resolverData = $context->getResolverData();
        $this->assertSame('value', $resolverData['trigger']);
        $this->assertSame('ok', $resolverData['actions']['action_a']['result']);

        // Payload stays clean
        $this->assertArrayNotHasKey('actions', $context->getPayload());
    }

    public function testParallelBranchesGetIsolatedSnapshots(): void
    {
        $this->actionRegistry->register($this->makeAction('before_parallel', function () {
            return ActionResult::success(['data' => 'shared']);
        }));

        $context = new ExecutionContext(['trigger' => 'data']);
        $steps = [
            ['id' => 'step_1', 'type' => 'action', 'action' => 'before_parallel', 'config' => []],
            [
                'id' => 'par_1',
                'type' => 'parallel',
                'branches' => [
                    [['id' => 'a', 'type' => 'action', 'action' => 'test', 'config' => []]],
                    [['id' => 'b', 'type' => 'action', 'action' => 'test', 'config' => []]],
                ],
            ],
        ];

        $this->executor->execute('exec-1', $steps, $context);

        // Both parallel jobs should have the same snapshot with pre-parallel outputs
        $this->assertCount(2, $this->dispatched);
        $job1Context = $this->dispatched[0]->getPayload()['context'];
        $job2Context = $this->dispatched[1]->getPayload()['context'];

        $this->assertSame($job1Context, $job2Context);
        $this->assertSame('shared', $job1Context['action_outputs']['before_parallel']['data']);
    }

    // --- Helpers ---

    private function makeAction(string $id, \Closure $execute): ActionInterface
    {
        return new class($id, $execute) implements ActionInterface {
            public function __construct(private string $id, private \Closure $fn) {}
            public function getId(): string { return $this->id; }
            public function getName(): string { return $this->id; }
            public function getDescription(): string { return ''; }
            public function getGroup(): string { return 'Test'; }
            public function getConfigSchema(): array { return []; }
            public function getConfigOptions(string $fieldKey, array $context = []): array { return []; }
            public function getPlaceholders(string $triggerType): array { return []; }
            public function getOutputSchema(): array { return []; }
            public function execute(array $payload, array $config): ActionResult {
                return ($this->fn)($payload, $config);
            }
        };
    }

    private function createExecutorWithLogger(FlowLogger $flowLogger): FlowExecutor
    {
        $queue = new class implements QueueInterface {
            public function dispatch(JobInterface $job): string { return 'job-id'; }
            public function schedule(JobInterface $job, \DateTimeInterface $runAt): string { return 'scheduled-job-id'; }
            public function cancel(string $jobId): bool { return true; }
        };

        $conditionEvaluator = new class implements ConditionEvaluatorInterface {
            public function evaluate(string $expression, array $payload): bool {
                return (bool) eval("return {$expression};");
            }
            public function validate(string $expression): bool { return true; }
        };

        return new FlowExecutor(
            $queue,
            $conditionEvaluator,
            $this->createMock(FlowExecutionRepository::class),
            new PayloadResolver(new MustacheEngine()),
            new EventDispatcher(),
            $this->actionRegistry,
            $flowLogger,
            new WpLogger(),
        );
    }
}
