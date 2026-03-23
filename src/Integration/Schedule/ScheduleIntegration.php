<?php

namespace WSms\Integration\Schedule;

use WSms\Flow\Contracts\FlowRepositoryInterface;
use WSms\Flow\Engine\FlowRunner;
use WSms\Integration\Contracts\IntegrationInterface;
use WSms\Integration\Schedule\Triggers\AbstractScheduleTrigger;
use WSms\Integration\Schedule\Triggers\DateScheduleTrigger;
use WSms\Integration\Schedule\Triggers\RecurringScheduleTrigger;

defined('ABSPATH') || exit;

class ScheduleIntegration implements IntegrationInterface
{
    private RecurringScheduleTrigger $recurringTrigger;
    private DateScheduleTrigger $dateTrigger;

    public function __construct(
        private readonly FlowRepositoryInterface $flowRepository,
        private readonly FlowRunner $flowRunner,
    ) {
        $this->recurringTrigger = new RecurringScheduleTrigger($flowRepository, $flowRunner);
        $this->dateTrigger = new DateScheduleTrigger($flowRepository, $flowRunner);
    }

    public function getId(): string
    {
        return 'wsms_schedule';
    }

    public function getName(): string
    {
        return 'Schedule';
    }

    public function getDescription(): string
    {
        return 'Run flows on a recurring schedule or at a specific date/time.';
    }

    public function getCategory(): string
    {
        return 'automation';
    }

    public function getIcon(): string
    {
        return 'clock';
    }

    public function isAvailable(): bool
    {
        return function_exists('as_schedule_recurring_action');
    }

    public function getAuthType(): string
    {
        return 'none';
    }

    public function getAuthSchema(): array
    {
        return [];
    }

    public function getTriggers(): array
    {
        return [
            $this->recurringTrigger,
            $this->dateTrigger,
        ];
    }

    public function getActions(): array
    {
        return [];
    }

    public function getCapabilities(): array
    {
        return [];
    }

    public function boot(): void
    {
        $this->registerScheduleHooks();
        $this->registerFlowLifecycleHooks();
    }

    /**
     * Register Action Scheduler hooks for all active schedule-based flows.
     */
    private function registerScheduleHooks(): void
    {
        $recurringFlows = $this->flowRepository->findByTrigger('schedule.recurring');
        foreach ($recurringFlows as $flow) {
            $this->recurringTrigger->scheduleFlow($flow->getId(), $flow->getTriggerConfig());
        }

        $dateFlows = $this->flowRepository->findByTrigger('schedule.date');
        foreach ($dateFlows as $flow) {
            $this->dateTrigger->scheduleFlow($flow->getId(), $flow->getTriggerConfig());
        }
    }

    /**
     * Hook into flow lifecycle events to schedule/unschedule AS jobs.
     */
    private function registerFlowLifecycleHooks(): void
    {
        // When a flow is published, schedule its AS job
        add_action('wsms_flow_published', function (string $flowId, string $triggerType, array $triggerConfig) {
            if ($triggerType === 'schedule.recurring') {
                AbstractScheduleTrigger::unscheduleFlow($flowId);
                $this->recurringTrigger->scheduleFlow($flowId, $triggerConfig);
            } elseif ($triggerType === 'schedule.date') {
                AbstractScheduleTrigger::unscheduleFlow($flowId);
                $this->dateTrigger->scheduleFlow($flowId, $triggerConfig);
            }
        }, 10, 3);

        // When a flow is unpublished/deleted, unschedule its AS job
        add_action('wsms_flow_deactivated', function (string $flowId) {
            AbstractScheduleTrigger::unscheduleFlow($flowId);
        });

        add_action('wsms_flow_deleted', function (string $flowId) {
            AbstractScheduleTrigger::unscheduleFlow($flowId);
            delete_option("wsms_flow_{$flowId}_run_count");
        });
    }

    public function connect(array $credentials): array
    {
        return $credentials;
    }

    public function disconnect(): void
    {
    }

    public function isConnected(): bool
    {
        return true;
    }
}
