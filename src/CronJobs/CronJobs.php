<?php

namespace WP_SMS\CronJobs;

use WP_SMS\Option;
use WP_SMS\Utils\GenerateReport;

class CronJobs
{
    public function init()
    {
        add_action('init', [$this, 'registerSendReportCronJob']);
        add_action('wp_sms_admin_email_report', [$this, 'generateReport']);
    }

    public function registerSendReportCronJob()
    {
        if (isset($this->options['report_wpsms_statistics']) && !$this->options['report_wpsms_statistics']) {
            return;
        }

        // Get the current time and day of the week
        $now         = current_time('timestamp');
        $day_of_week = date('w', $now);

        // Get the WordPress option for the first day of the week
        $first_day_option = get_option('start_of_week');

        // Calculate the delay to the next occurrence of the first day of the week
        if ($day_of_week !== $first_day_option) {
            $days_until_first_day = ($first_day_option - $day_of_week + 7) % 7;
        } else {
            // If today is the first day of the week, schedule it for the next occurrence
            $days_until_first_day = 0;
        }

        // Calculate the delay in seconds
        $delay = $days_until_first_day * 24 * 60 * 60;

        // Schedule the cron job with the calculated delay
        if (!wp_next_scheduled('wp_sms_admin_email_report')) {
            wp_schedule_event(time() + $delay, 'weekly', 'wp_sms_admin_email_report');
        }
    }

    public function generateReport()
    {
        $generateReport = new GenerateReport();
        $generateReport->generate();
    }
}
