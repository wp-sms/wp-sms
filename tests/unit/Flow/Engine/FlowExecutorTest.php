<?php

namespace WSms\Tests\Unit\Flow\Engine;

use PHPUnit\Framework\TestCase;
use WSms\Event\EventDispatcher;
use WSms\Flow\Action\ActionRegistry;
use WSms\Flow\Contracts\ActionInterface;
use WSms\Flow\Contracts\ActionResult;
use WSms\Flow\Contracts\ConditionEvaluatorInterface;
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

        $this->actionRegistry->register(new class($executed) implements ActionInterface {
            public function __construct(private bool &$executed) {}
            public function getId(): string { return 'test_action'; }
            public function getName(): string { return 'Test'; }
            public function getGroup(): string { return 'Test'; }
            public function getConfigSchema(): array { return []; }
            public function getConfigOptions(string $fieldKey): array { return []; }
            public function execute(array $payload, array $config): ActionResult {
                $this->executed = true;
                return ActionResult::success(['done' => true]);
            }
        });

        $this->executor->executeNode([
            'id'     => 'step_1',
            'type'   => 'action',
            'action' => 'test_action',
            'config' => [],
        ], ['data' => 'value'], 'exec-1');

        $this->assertTrue($executed);
    }

    public function testExecuteConditionThenBranch(): void
    {
        $executed = false;

        $this->actionRegistry->register(new class($executed) implements ActionInterface {
            public function __construct(private bool &$executed) {}
            public function getId(): string { return 'then_action'; }
            public function getName(): string { return 'Then'; }
            public function getGroup(): string { return 'Test'; }
            public function getConfigSchema(): array { return []; }
            public function getConfigOptions(string $fieldKey): array { return []; }
            public function execute(array $payload, array $config): ActionResult {
                $this->executed = true;
                return ActionResult::success();
            }
        });

        $this->executor->executeNode([
            'id'         => 'cond_1',
            'type'       => 'condition',
            'expression' => 'true',
            'then'       => [
                ['id' => 'then_1', 'type' => 'action', 'action' => 'then_action', 'config' => []],
            ],
            'else'       => [],
        ], [], 'exec-1');

        $this->assertTrue($executed);
    }

    public function testExecuteConditionElseBranch(): void
    {
        $executed = false;

        $this->actionRegistry->register(new class($executed) implements ActionInterface {
            public function __construct(private bool &$executed) {}
            public function getId(): string { return 'else_action'; }
            public function getName(): string { return 'Else'; }
            public function getGroup(): string { return 'Test'; }
            public function getConfigSchema(): array { return []; }
            public function getConfigOptions(string $fieldKey): array { return []; }
            public function execute(array $payload, array $config): ActionResult {
                $this->executed = true;
                return ActionResult::success();
            }
        });

        $this->executor->executeNode([
            'id'         => 'cond_1',
            'type'       => 'condition',
            'expression' => 'false',
            'then'       => [],
            'else'       => [
                ['id' => 'else_1', 'type' => 'action', 'action' => 'else_action', 'config' => []],
            ],
        ], [], 'exec-1');

        $this->assertTrue($executed);
    }

    public function testExecuteParallelDispatchesToQueue(): void
    {
        $this->executor->executeNode([
            'id'       => 'par_1',
            'type'     => 'parallel',
            'branches' => [
                [['id' => 'a', 'type' => 'action', 'action' => 'test', 'config' => []]],
                [['id' => 'b', 'type' => 'action', 'action' => 'test', 'config' => []]],
            ],
        ], [], 'exec-1');

        $this->assertCount(2, $this->dispatched);
    }

    public function testExecuteDelaySchedulesToQueue(): void
    {
        $this->executor->executeNode([
            'id'       => 'delay_1',
            'type'     => 'delay',
            'duration' => 300,
            'then'     => [
                ['id' => 'after_delay', 'type' => 'action', 'action' => 'test', 'config' => []],
            ],
        ], [], 'exec-1');

        $this->assertCount(1, $this->dispatched);
        $this->assertArrayHasKey('at', $this->dispatched[0]);
    }

    public function testTemplateResolutionInActionConfig(): void
    {
        $receivedConfig = [];

        $this->actionRegistry->register(new class($receivedConfig) implements ActionInterface {
            public function __construct(private array &$receivedConfig) {}
            public function getId(): string { return 'capture'; }
            public function getName(): string { return 'Capture'; }
            public function getGroup(): string { return 'Test'; }
            public function getConfigSchema(): array { return []; }
            public function getConfigOptions(string $fieldKey): array { return []; }
            public function execute(array $payload, array $config): ActionResult {
                $this->receivedConfig = $config;
                return ActionResult::success();
            }
        });

        $this->executor->executeNode([
            'id'     => 'step_1',
            'type'   => 'action',
            'action' => 'capture',
            'config' => [
                'to'   => '{{user.email}}',
                'body' => 'Hello {{user.name}}!',
            ],
        ], ['user' => ['email' => 'a@b.com', 'name' => 'Alice']], 'exec-1');

        $this->assertSame('a@b.com', $receivedConfig['to']);
        $this->assertSame('Hello Alice!', $receivedConfig['body']);
    }
}
