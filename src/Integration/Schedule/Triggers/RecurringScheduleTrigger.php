<?php

namespace WSms\Integration\Schedule\Triggers;

defined('ABSPATH') || exit;

class RecurringScheduleTrigger extends AbstractScheduleTrigger
{
    public function getId(): string
    {
        return 'schedule.recurring';
    }

    public function getName(): string
    {
        return __('Recurring Schedule', 'wp-sms');
    }

    public function getDescription(): string
    {
        return __('Run this flow on a recurring schedule (hourly, daily, weekly, monthly)', 'wp-sms');
    }

    public function getFilterSchema(): array
    {
        return [
            'frequency' => [
                'type'        => 'string',
                'label'       => __('Frequency', 'wp-sms'),
                'description' => __('How often this flow should run', 'wp-sms'),
                'enum'        => ['hourly', 'daily', 'weekly', 'monthly'],
                'enumLabels'  => [
                    'hourly'  => __('Every hour', 'wp-sms'),
                    'daily'   => __('Every day', 'wp-sms'),
                    'weekly'  => __('Every week', 'wp-sms'),
                    'monthly' => __('Every month', 'wp-sms'),
                ],
                'default' => 'daily',
            ],
            'time' => [
                'type'           => 'string',
                'format'         => 'time',
                'label'          => __('Time', 'wp-sms'),
                'description'    => __('Time of day to run', 'wp-sms'),
                'default'        => '09:00',
                'displayOptions' => [
                    'hide' => ['frequency' => ['hourly']],
                ],
            ],
            'day' => [
                'type'           => 'string',
                'label'          => __('Day of Week', 'wp-sms'),
                'enum'           => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'],
                'enumLabels'     => [
                    'monday'    => __('Monday', 'wp-sms'),
                    'tuesday'   => __('Tuesday', 'wp-sms'),
                    'wednesday' => __('Wednesday', 'wp-sms'),
                    'thursday'  => __('Thursday', 'wp-sms'),
                    'friday'    => __('Friday', 'wp-sms'),
                    'saturday'  => __('Saturday', 'wp-sms'),
                    'sunday'    => __('Sunday', 'wp-sms'),
                ],
                'default'        => 'monday',
                'displayOptions' => [
                    'show' => ['frequency' => ['weekly']],
                ],
            ],
        ];
    }

    public function getPayloadSchema(): array
    {
        return [
            'run_count' => [
                'type'        => 'integer',
                'label'       => __('Run Count', 'wp-sms'),
                'description' => __('Number of times this scheduled flow has run', 'wp-sms'),
                'example'     => 5,
            ],
            'scheduled_time' => [
                'type'        => 'string',
                'label'       => __('Scheduled Time', 'wp-sms'),
                'description' => __('The time this execution was scheduled for', 'wp-sms'),
                'example'     => '2026-03-20 09:00:00',
            ],
        ];
    }

    public function scheduleFlow(string $flowId, array $triggerConfig): void
    {
        $hook = self::hookName($flowId);
        $runner = $this->flowRunner;

        // Register the hook handler
        add_action($hook, function () use ($flowId, $runner) {
            $runCount = (int) get_option("wsms_flow_{$flowId}_run_count", 0);
            $runCount++;
            update_option("wsms_flow_{$flowId}_run_count", $runCount, false);

            $runner->runSingleFlow($flowId, [
                'run_count'      => $runCount,
                'scheduled_time' => current_time('Y-m-d H:i:s'),
            ]);
        });

        // Calculate interval in seconds
        $interval = $this->calculateInterval($triggerConfig);
        if ($interval <= 0) {
            return;
        }

        // Calculate first run timestamp
        $firstRun = $this->calculateFirstRun($triggerConfig);

        // Schedule the recurring action (idempotent — skip if already scheduled)
        if (function_exists('as_schedule_recurring_action') && !as_has_scheduled_action($hook, [], 'wsms')) {
            as_schedule_recurring_action($firstRun, $interval, $hook, [], 'wsms');
        }
    }

    private function calculateInterval(array $config): int
    {
        return match ($config['frequency'] ?? 'daily') {
            'hourly'  => HOUR_IN_SECONDS,
            'daily'   => DAY_IN_SECONDS,
            'weekly'  => WEEK_IN_SECONDS,
            'monthly' => 30 * DAY_IN_SECONDS,
            default   => DAY_IN_SECONDS,
        };
    }

    private function calculateFirstRun(array $config): int
    {
        $tz = wp_timezone();
        $now = new \DateTimeImmutable('now', $tz);
        $time = $config['time'] ?? '09:00';
        $frequency = $config['frequency'] ?? 'daily';

        // Build the target time for today
        $target = \DateTimeImmutable::createFromFormat('Y-m-d H:i', $now->format('Y-m-d') . ' ' . $time, $tz);

        if (!$target) {
            // Fallback: next hour
            return $now->modify('+1 hour')->getTimestamp();
        }

        if ($frequency === 'weekly' && isset($config['day'])) {
            // Find the next occurrence of the specified day
            $dayOfWeek = $config['day']; // e.g., 'monday'
            $target = new \DateTimeImmutable("next {$dayOfWeek} {$time}", $tz);
            if ($target <= $now) {
                $target = $target->modify('+1 week');
            }
            return $target->getTimestamp();
        }

        // For hourly/daily/monthly: if target is in the past today, schedule for next interval
        if ($target <= $now) {
            $interval = $this->calculateInterval($config);
            $target = $target->modify("+{$interval} seconds");
        }

        return $target->getTimestamp();
    }
}
