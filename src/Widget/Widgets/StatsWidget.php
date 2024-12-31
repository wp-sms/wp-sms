<?php

namespace WP_SMS\Widget\Widgets;

use WP_SMS\Components\Assets;
use WP_SMS\Widget\AbstractWidget;
use WP_SMS\Helper;
use DateTime;
use DateInterval;
use DatePeriod;

class StatsWidget extends AbstractWidget
{
    protected $id = 'wp-sms-stats-widget';
    protected $name = 'WP SMS Stats';

    protected $capability = 'manage_options';

    /**
     * Preparations before rendering
     *
     * @return void
     */

    /**
     * Render the widget
     *
     * @return void
     */
    public function render()
    {
        echo Helper::loadTemplate('admin/dashboard-widget.php'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    /**
     * Get widget's dashboard script localization data
     *
     * @return void
     */
    public function getLocalizationData()
    {
        // Set a transient key and expiration time for the query results (e.g., 12 hours)
        $transientKey = 'wp_sms_dashboard_send_data';
        $expiration   = HOUR_IN_SECONDS;

        // Check if the query results transient exists
        $sentMessages = get_transient($transientKey);

        if ($sentMessages === false) {
            // Query results don't exist or have expired, so generate the data
            global $wpdb;

            $oneYearAgo = (new DateTime('first day of -11 month'))->format('Y-m-d');
            $tomorrow   = (new DateTime('tomorrow'))->format('Y-m-d');

            $results = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT DATE(`date`) as date, `status`, COUNT(*) as count FROM `{$wpdb->prefix}sms_send` WHERE `date` BETWEEN %s AND %s GROUP BY DATE(`date`), `status` ORDER BY `date`", $oneYearAgo, $tomorrow
                )
            );

            // Initialize datasets with all dates and zero values
            $datasets = [
                'last_7_days'   => ['successful' => [], 'failure' => []],
                'last_30_days'  => ['successful' => [], 'failure' => []],
                'this_year'     => ['successful' => [], 'failure' => []],
                'last_12_month' => ['successful' => [], 'failure' => []]
            ];

            // Initialize last 7 days
            $last7Days = new DatePeriod(new DateTime('-6 days'), new DateInterval('P1D'), new DateTime('tomorrow'));
            foreach ($last7Days as $date) {
                $key                                         = $date->format('d D');
                $datasets['last_7_days']['successful'][$key] = 0;
                $datasets['last_7_days']['failure'][$key]    = 0;
            }

            // Initialize last 30 days
            $last30Days = new DatePeriod(new DateTime('-29 days'), new DateInterval('P1D'), new DateTime('tomorrow'));
            foreach ($last30Days as $date) {
                $key                                          = $date->format('d M');
                $datasets['last_30_days']['successful'][$key] = 0;
                $datasets['last_30_days']['failure'][$key]    = 0;
            }

            // Initialize this year and last 12 months
            $currentYear  = date('Y');
            $last12Months = new DatePeriod(new DateTime('first day of -11 month'), new DateInterval('P1M'), new DateTime('first day of next month'));

            foreach ($last12Months as $date) {
                $key = $date->format('M');

                // Initialize last 12 months data
                $datasets['last_12_month']['successful'][$key] = 0;
                $datasets['last_12_month']['failure'][$key]    = 0;

                // Check if the month belongs to the current year
                if ($date->format('Y') == $currentYear) {
                    // Only initialize this_year data for months that have passed
                    if ($date < new DateTime('first day of this month')) {
                        $datasets['this_year']['successful'][$key] = 0;
                        $datasets['this_year']['failure'][$key]    = 0;
                    }
                }
            }

            $currentYear   = date('Y');
            $sevenDaysAgo  = (new DateTime('-6 days'))->format('Y-m-d');
            $thirtyDaysAgo = (new DateTime('-29 days'))->format('Y-m-d');

            foreach ($results as $row) {
                $date   = new DateTime($row->date);
                $status = $row->status === 'success' ? 'successful' : 'failure';
                $count  = (int)$row->count;

                // Last 7 days
                if ($date >= new DateTime($sevenDaysAgo)) {
                    $key                                    = $date->format('d D');
                    $datasets['last_7_days'][$status][$key] = $count;
                }

                // Last 30 days
                if ($date >= new DateTime($thirtyDaysAgo)) {
                    $key                                     = $date->format('d M');
                    $datasets['last_30_days'][$status][$key] = $count;
                }

                // This year
                if ($date->format('Y') === $currentYear) {
                    $key                                  = $date->format('M');
                    $datasets['this_year'][$status][$key] += $count;
                }

                // Last 12 months
                $key                                      = $date->format('M');
                $datasets['last_12_month'][$status][$key] += $count;
            }

            $sentMessages = $datasets;

            // Store the query results in a transient
            set_transient($transientKey, $sentMessages, $expiration);
        }

        // Localization data is generated every time, not cached
        $widgetData['localization'] = [
            'successful' => esc_html__('Successful', 'wp-sms'),
            'failed'     => esc_html__('Failed', 'wp-sms'),
            'plain'      => esc_html__('Plain', 'wp-sms'),
        ];

        // Add the query results to the widget data
        $widgetData['send-messages-stats'] = $sentMessages;

        return $widgetData;
    }
}
