<?php

namespace WP_SMS\Components;

if (!defined('ABSPATH')) exit;

class Event
{
    /**
     * Get a scheduled event.
     *
     * @param string $event The action hook of the event.
     * @return object|false The event object if found, false otherwise.
     */
    public static function get($event)
    {
        return wp_get_scheduled_event($event);
    }

    /**
     * Schedules a WordPress event hook if it is not already scheduled.
     *
     * @param string $hook The action hook of the event.
     * @param int $timestamp The timestamp for when the event should occur.
     * @param string $recurrence How often the event should be repeated.
     * @param mixed $callback The callback of the event.
     *
     * @return void
     */
    public static function schedule($event, $timestamp, $recurrence, $callback = null)
    {
        if (!self::isScheduled($event)) {
            wp_schedule_event($timestamp, $recurrence, $event);
        }

        if ($callback) {
            add_action($event, $callback);
        }
    }


    /**
     * Unschedules a WordPress event hook, if it is scheduled.
     *
     * @param string $event The action hook of the event.
     *
     * @return void
     */
    public static function unschedule($event)
    {
        if (self::isScheduled($event)) {
            wp_unschedule_event(wp_next_scheduled($event), $event);
        }
    }

    /**
     * Checks if a WordPress event hook is scheduled.
     *
     * @param string $event The action hook of the event.
     * @return bool True if the event is scheduled, false otherwise.
     */
    public static function isScheduled($event)
    {
        return wp_next_scheduled($event) ? true : false;
    }

    /**
     * Reschedule an already scheduled event hook.
     *
     * @param string $event
     * @param string $recurrence
     *
     * @return void
     */
    public static function reschedule($event, $recurrence)
    {
        // If not scheduled, return
        if (!self::isScheduled($event)) return;

        // If already scheduled with the same recurrence, return
        if (self::get($event)->schedule === $recurrence) return;

        // unschedule previous event
        self::unschedule($event);

        $schedules = self::getSchedules();

        if (isset($schedules[$recurrence])) {
            $nextRun = $schedules[$recurrence]['next_schedule'];
            self::schedule($event, $nextRun, $recurrence);
        }
    }

    /**
     * Retrieves an array of schedules with their intervals and display names.
     *
     * @return array
     * @throws \Exception
     */
    public static function getSchedules()
    {
        $timestamp = time();
        $timezone  = wp_timezone();
        $datetime  = new \DateTime('@' . $timestamp);
        $datetime->setTimezone($timezone);

        // Determine the day name based on the start of the week setting
        $start_day_name = DateTime::getStartOfWeek();

        // Daily schedule
        $daily = clone $datetime;
        $daily->modify('tomorrow')->setTime(8, 0);

        // Weekly schedule
        $weekly = clone $datetime;
        $weekly->modify("next {$start_day_name}")->setTime(8, 0);

        // BiWeekly schedule
        $biweekly = clone $datetime;
        $biweekly->modify("next {$start_day_name} +1 week")->setTime(8, 0);

        // Monthly schedule
        $monthly = clone $datetime;
        $monthly->modify('first day of next month')->setTime(8, 0);

        $schedules = [
            'daily'    => [
                'interval'      => DAY_IN_SECONDS,
                'display'       => __('Daily', 'wp-sms'),
                'start'         => wp_date('Y-m-d', strtotime("-1 day")),
                'end'           => wp_date('Y-m-d', strtotime("-1 day")),
                'next_schedule' => $daily->getTimestamp()
            ],
            'weekly'   => [
                'interval'      => WEEK_IN_SECONDS,
                'display'       => __('Weekly', 'wp-sms'),
                'start'         => wp_date('Y-m-d', strtotime("-7 days")),
                'end'           => wp_date('Y-m-d', strtotime("-1 day")),
                'next_schedule' => $weekly->getTimestamp()
            ],
            'biweekly' => [
                'interval'      => 2 * WEEK_IN_SECONDS,
                'display'       => __('Bi-Weekly', 'wp-sms'),
                'start'         => wp_date('Y-m-d', strtotime("-14 days")),
                'end'           => wp_date('Y-m-d', strtotime("-1 day")),
                'next_schedule' => $biweekly->getTimestamp()
            ],
            'monthly'  => [
                'interval'      => MONTH_IN_SECONDS,
                'display'       => __('Monthly', 'wp-sms'),
                'start'         => wp_date('Y-m-d', strtotime('First day of previous month')),
                'end'           => wp_date('Y-m-d', strtotime('Last day of previous month')),
                'next_schedule' => $monthly->getTimestamp()
            ]
        ];

        return apply_filters('wp_sms_cron_schedules', $schedules);
    }
}