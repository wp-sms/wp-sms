<?php

namespace WSms\Integration\Schedule\Triggers;

use WSms\Flow\Contracts\AbstractTrigger;
use WSms\Flow\Contracts\FlowRepositoryInterface;
use WSms\Flow\Engine\FlowRunner;

defined('ABSPATH') || exit;

abstract class AbstractScheduleTrigger extends AbstractTrigger
{
    public const HOOK_PREFIX = 'wsms_schedule_flow_';

    public function __construct(
        protected readonly FlowRepositoryInterface $flowRepository,
        protected readonly FlowRunner $flowRunner,
    ) {
    }

    public function getGroup(): string
    {
        return __('Schedule', 'wp-sms');
    }

    public function subscribe(callable $callback): void
    {
        // Schedule triggers are wired via Action Scheduler hooks per-flow,
        // not via a single global hook. See ScheduleIntegration::registerScheduleHooks().
    }

    /**
     * Build the Action Scheduler hook name for a flow.
     */
    public static function hookName(string $flowId): string
    {
        return self::HOOK_PREFIX . $flowId;
    }

    /**
     * Unschedule all pending AS jobs for a flow.
     */
    public static function unscheduleFlow(string $flowId): void
    {
        if (!function_exists('as_unschedule_all_actions')) {
            return;
        }

        as_unschedule_all_actions(self::hookName($flowId));
    }

    abstract public function scheduleFlow(string $flowId, array $triggerConfig): void;
}
