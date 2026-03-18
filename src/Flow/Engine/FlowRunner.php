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
            if (!$this->matchesTriggerFilters($flow->getTriggerConfig(), $payload)) {
                $this->logger->debug("Flow {$flow->getId()} skipped: trigger filters do not match payload.");
                continue;
            }

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

    /**
     * Check if the flow's trigger_config filters match the event payload.
     *
     * Each key in $filters is compared against the corresponding payload value.
     * Empty/unset filter values are treated as "match all".
     * Array filter values use in_array (multi-select).
     */
    public function matchesTriggerFilters(array $filters, array $payload): bool
    {
        foreach ($filters as $key => $filterValue) {
            if ($filterValue === '' || $filterValue === null || $filterValue === []) {
                continue;
            }

            if (!array_key_exists($key, $payload)) {
                return false;
            }

            $payloadValue = $payload[$key];

            // Payload value is an array (e.g., changed_fields) — check containment
            if (is_array($payloadValue) && !is_array($filterValue)) {
                if (!in_array((string) $filterValue, array_map('strval', $payloadValue), true)) {
                    return false;
                }
                continue;
            }

            $payloadStr = (string) $payloadValue;

            if (is_array($filterValue)) {
                // Multi-select: payload value must be in the filter array
                if (!in_array($payloadStr, array_map('strval', $filterValue), true)) {
                    return false;
                }
            } else {
                // String comparison handles int/string mismatches safely (e.g., product_id 55 vs '55')
                if ($payloadStr !== (string) $filterValue) {
                    return false;
                }
            }
        }

        return true;
    }
}
