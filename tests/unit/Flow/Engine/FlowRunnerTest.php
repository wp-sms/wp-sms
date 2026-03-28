<?php

namespace WSms\Tests\Unit\Flow\Engine;

use PHPUnit\Framework\TestCase;
use WSms\Dependencies\Psr\Log\LoggerInterface;
use WSms\Enums\ExecutionStatus;
use WSms\Event\Contracts\EventDispatcherInterface;
use WSms\Flow\Contracts\Flow;
use WSms\Flow\Contracts\FlowRepositoryInterface;
use WSms\Flow\Engine\ExecutionContext;
use WSms\Flow\Engine\FlowExecutor;
use WSms\Flow\Engine\FlowRunner;
use WSms\Flow\Storage\FlowExecutionRepository;
use WSms\Flow\Trigger\TriggerRegistry;

class FlowRunnerTest extends TestCase
{
    private FlowRunner $runner;
    private FlowExecutor $flowExecutor;
    private FlowExecutionRepository $executionRepository;
    private FlowRepositoryInterface $flowRepository;

    protected function setUp(): void
    {
        $GLOBALS['_test_do_action_calls'] = [];

        $this->flowExecutor = $this->createMock(FlowExecutor::class);
        $this->executionRepository = $this->createMock(FlowExecutionRepository::class);
        $this->flowRepository = $this->createMock(FlowRepositoryInterface::class);

        $this->executionRepository->method('create')->willReturn('exec-1');

        $flow = new Flow(
            id: 'flow-1',
            name: 'Test Flow',
            triggerType: 'wordpress.user_register',
            triggerConfig: [],
            steps: [],
            status: 'active',
            publishedSteps: [
                ['id' => 's1', 'type' => 'action', 'action' => 'test', 'config' => []],
            ],
        );

        $this->flowRepository->method('findByTrigger')
            ->with('wordpress.user_register')
            ->willReturn([$flow]);

        $this->runner = new FlowRunner(
            $this->createMock(TriggerRegistry::class),
            $this->flowRepository,
            $this->flowExecutor,
            $this->executionRepository,
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(LoggerInterface::class),
        );
    }

    public function testSuccessfulFlowIsMarkedCompleted(): void
    {
        $this->flowExecutor->expects($this->once())->method('execute');

        $this->executionRepository->method('find')
            ->with('exec-1')
            ->willReturn(['status' => ExecutionStatus::Running->value]);

        $this->flowExecutor->expects($this->once())
            ->method('markCompleted')
            ->with('exec-1', 'flow-1');

        $this->runner->onTriggerFired('wordpress.user_register', ['user_id' => 1]);
    }

    public function testFlowWithDeferredWorkNotMarkedCompleted(): void
    {
        $this->flowExecutor->expects($this->once())
            ->method('execute')
            ->willReturnCallback(function ($execId, $steps, ExecutionContext $context) {
                $context->markDeferredWork();
            });

        $this->executionRepository->method('find')
            ->with('exec-1')
            ->willReturn(['status' => ExecutionStatus::Running->value]);

        $this->flowExecutor->expects($this->never())->method('markCompleted');

        $this->runner->onTriggerFired('wordpress.user_register', ['user_id' => 1]);
    }

    public function testFailedFlowNotMarkedCompleted(): void
    {
        $this->flowExecutor->expects($this->once())->method('execute');

        $this->executionRepository->method('find')
            ->with('exec-1')
            ->willReturn(['status' => ExecutionStatus::Failed->value]);

        $this->flowExecutor->expects($this->never())->method('markCompleted');

        $this->runner->onTriggerFired('wordpress.user_register', ['user_id' => 1]);
    }

    public function testWaitingFlowNotMarkedCompleted(): void
    {
        $this->flowExecutor->expects($this->once())->method('execute');

        $this->executionRepository->method('find')
            ->with('exec-1')
            ->willReturn(['status' => ExecutionStatus::Waiting->value]);

        $this->flowExecutor->expects($this->never())->method('markCompleted');

        $this->runner->onTriggerFired('wordpress.user_register', ['user_id' => 1]);
    }

    public function testExecutionExceptionSetsError(): void
    {
        $this->flowExecutor->expects($this->once())
            ->method('execute')
            ->willThrowException(new \RuntimeException('DB connection failed'));

        $this->executionRepository->expects($this->once())
            ->method('setError')
            ->with('exec-1', 'DB connection failed');

        $this->flowExecutor->expects($this->never())->method('markCompleted');

        $this->runner->onTriggerFired('wordpress.user_register', ['user_id' => 1]);
    }
}
