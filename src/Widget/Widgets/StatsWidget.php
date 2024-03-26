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
        $widgetData['localization'] = [
            'successful' => esc_html__('Successful', 'wp-sms'),
            'failed'     => esc_html__('Failed', 'wp-sms'),
            'plain'      => esc_html__('Plain', 'wp-sms'),
        ];

        /**
         * @param \DatePeriod $period
         * @param string $format
         */
        $getResults = function (DatePeriod $period, string $format) {
            global $wpdb;

            $dates = iterator_to_array($period);
            sort($dates);

            $datasets = [];

            for ($i = 0; $i < sizeof($dates) - 1; $i++) {
                $firstDate  = $dates[$i];
                $secondDate = $dates[$i + 1];

                $label = $firstDate->format($format);

                $results = $wpdb->get_results(
                    $wpdb->prepare("select `status`, count(*) as count from `{$wpdb->prefix}sms_send` where `date` between DATE(%s) and DATE(%s) group by `status`", $firstDate->format('Y-m-d'), $secondDate->format('Y-m-d'))
                );

                foreach ($results as $key => $result) {
                    $results[$result->status] = $result->count;
                    unset($results[$key]);
                }

                $datasets['successful'][$label] = $results['success'] ?? 0;
                $datasets['failure'][$label]    = $results['error'] ?? 0;
            }

            return $datasets;
        };

        $sentMessages['last_7_days']   = $getResults(
            new DatePeriod(new DateTime('tomorrow'), DateInterval::createFromDateString('-1 day'), 7),
            'd D'
        );
        $sentMessages['last_30_days']  = $getResults(
            new DatePeriod(new DateTime('tomorrow'), DateInterval::createFromDateString('-1 day'), 30),
            'd M'
        );
        $sentMessages['this_year']     = $getResults(
            new DatePeriod(new DateTime('first day of jan'), DateInterval::createFromDateString('+1 month'), (new DateTime('first day of next month'))->modify('+1 second')),
            'M'
        );
        $sentMessages['last_12_month'] = $getResults(
            new DatePeriod(new DateTime('first day of -11 month'), DateInterval::createFromDateString('+1 month'), (new DateTime('first day of next month'))->modify('+1 second')),
            'M'
        );

        $widgetData['send-messages-stats'] = $sentMessages;

        return $widgetData;
    }
}
