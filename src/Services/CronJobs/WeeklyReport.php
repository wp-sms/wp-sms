<?php

namespace WP_SMS\Services\CronJobs;

use WP_SMS\Option;
use WP_SMS\Services\Report\EmailReportGenerator;

class WeeklyReport
{
    public function register()
    {
        add_action('init', [$this, 'registerSendReportCronJob']);
        add_action('wp_sms_admin_email_report', [$this, 'generateReport']);
    }

    public function registerSendReportCronJob()
    {
        if (!Option::getOption('report_wpsms_statistics')) {
            wp_clear_scheduled_hook('wp_sms_admin_email_report');
            return;
        }

        // Get the current time and day of the week
        $now       = current_time('timestamp');
        $dayOfWeek = date('w', $now); // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date	

        // Get the WordPress option for the first day of the week
        $firstDayOption = get_option('start_of_week');

        // Calculate the delay to the next occurrence of the first day of the week
        if ($dayOfWeek !== $firstDayOption) {
            $daysUntilFirstDay = ($firstDayOption - $dayOfWeek + 7) % 7;
        } else {
            // If today is the first day of the week, schedule it for the next occurrence
            $daysUntilFirstDay = 0;
        }

        // Calculate the delay in seconds
        $delay = $daysUntilFirstDay * 24 * 60 * 60;

        // Schedule the cron job with the calculated delay
        if (!wp_next_scheduled('wp_sms_admin_email_report')) {
            if ($delay === 0) $delay = 7 * 24 * 60 * 60;

            wp_schedule_event(time() + $delay, 'weekly', 'wp_sms_admin_email_report');
        }
    }

    public function generateReport()
    {
        $generateReport = new EmailReportGenerator();
        $generateReport->generate();
    }
}
