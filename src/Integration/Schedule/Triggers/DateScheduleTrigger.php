<?php

namespace WSms\Integration\Schedule\Triggers;

defined('ABSPATH') || exit;

class DateScheduleTrigger extends AbstractScheduleTrigger
{
    public function getId(): string
    {
        return 'schedule.date';
    }

    public function getName(): string
    {
        return __('Specific Date/Time', 'wp-sms');
    }

    public function getDescription(): string
    {
        return __('Run this flow once at a specific date and time', 'wp-sms');
    }

    public function getPayloadSchema(): array
    {
        return [
            'scheduled_time' => [
                'type'        => 'string',
                'label'       => __('Scheduled Time', 'wp-sms'),
                'description' => __('The date/time this execution was scheduled for', 'wp-sms'),
                'example'     => '2026-03-25 09:00:00',
            ],
        ];
    }

    public function scheduleFlow(string $flowId, array $triggerConfig): void
    {
        $hook = self::hookName($flowId);
        $runner = $this->flowRunner;
        $flowRepo = $this->flowRepository;

        // Register the hook handler
        add_action($hook, function () use ($flowId, $runner, $flowRepo) {
            $runner->runSingleFlow($flowId, [
                'scheduled_time' => current_time('Y-m-d H:i:s'),
            ]);

            // Auto-deactivate after firing
            $flowRepo->updateStatus($flowId, 'inactive');
        });

        // Parse date/time from trigger config
        $dateTime = $triggerConfig['date'] ?? '';
        $time = $triggerConfig['time'] ?? '00:00';

        if (empty($dateTime)) {
            return;
        }

        $tz = wp_timezone();
        $target = \DateTimeImmutable::createFromFormat('Y-m-d H:i', "{$dateTime} {$time}", $tz);

        if (!$target) {
            return;
        }

        // Don't schedule if the date is in the past
        $now = new \DateTimeImmutable('now', $tz);
        if ($target <= $now) {
            return;
        }

        if (function_exists('as_schedule_single_action') && !as_has_scheduled_action($hook, [], 'wsms')) {
            as_schedule_single_action($target->getTimestamp(), $hook, [], 'wsms');
        }
    }
}
