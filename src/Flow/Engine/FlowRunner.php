<?php

namespace WSms\Flow\Engine;

use WSms\Dependencies\Psr\Log\LoggerInterface;
use WSms\Event\Contracts\EventDispatcherInterface;
use WSms\Event\Events\FlowStartedEvent;
use WSms\Flow\Contracts\FlowRepositoryInterface;
use WSms\Flow\Storage\FlowExecutionRepository;
use WSms\Flow\Trigger\TriggerRegistry;

defined('ABSPATH') || exit;

class FlowRunner
{
    public function __construct(
        private readonly TriggerRegistry $triggerRegistry,
        private readonly FlowRepositoryInterface $flowRepository,
        private readonly FlowExecutor $flowExecutor,
        private readonly FlowExecutionRepository $executionRepository,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function subscribeActiveTriggers(): void
    {
        foreach ($this->triggerRegistry->all() as $trigger) {
            $trigger->subscribe(function (array $payload) use ($trigger) {
                $this->onTriggerFired($trigger->getId(), $payload);
            });
        }
    }

    public function onTriggerFired(string $triggerType, array $payload): void
    {
        $flows = $this->flowRepository->findByTrigger($triggerType);

        if (empty($flows)) {
            return;
        }

        foreach ($flows as $flow) {
            $steps = $flow->getPublishedSteps();

            if (empty($steps)) {
                $this->logger->debug("Flow {$flow->getId()} has no published steps, skipping.");
                continue;
            }

            $executionId = $this->executionRepository->create($flow->getId(), $payload);

            $this->eventDispatcher->dispatch(new FlowStartedEvent(
                $flow->getId(),
                $executionId,
                $triggerType,
                $payload,
            ));

            try {
                // Don't mark completed here — async steps (delay, parallel) may
                // still be pending in the queue. Completion is tracked by checking
                // whether all dispatched steps have finished.
                $this->flowExecutor->execute($executionId, $steps, $payload);
            } catch (\Throwable $e) {
                $this->logger->error("Flow execution failed: {$e->getMessage()}", [
                    'flow_id'      => $flow->getId(),
                    'execution_id' => $executionId,
                ]);
                $this->executionRepository->setError($executionId, $e->getMessage());
            }
        }
    }
}
