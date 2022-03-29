<?php

namespace WP_SMS\Admin\Widget\Widgets;

use WP_SMS\Admin\Widget\AbstractWidget;
use WP_SMS\Helper;
use DateTime;
use DateInterval;
use DatePeriod;

class StatsWidget extends AbstractWidget
{
    protected $id = 'wp-sms-stats-widget';
    protected $name = 'WP SMS Stats';
    protected $version = '1.0';

    /**
     * Preparations before rendering
     *
     * @return void
     */
    protected function prepare()
    {
        wp_register_script('wp-sms-chartjs', Helper::getPluginAssetUrl('js/chart.min.js'), [], '3.7.1');
        wp_enqueue_script('wp-sms-dashboard-widget-stats-script', Helper::getPluginAssetUrl('js/admin-dashboard-stats-widget.js'), ['wp-sms-chartjs'], $this->version);
        wp_localize_script('wp-sms-dashboard-widget-stats-script', 'WPSmsStatsData', apply_filters('wp_sms_stats_widget_data', $this->getLocalizationData()));
    }

    /**
     * Render the widget
     *
     * @return void
     */
    public function render()
    {
        echo Helper::loadTemplate('admin/dashboard-widget.php');
    }

    /**
     * Get widget's dashboard script localization data
     *
     * @return void
     */
    private function getLocalizationData()
    {
        $widgetData['localization'] = [
            'successful'        => __('Successful', 'wp-sms'),
            'failed'            => __('Failed', 'wp-sms'),
            'plain'             => __('Plain', 'wp-sms'),
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

            for ($i = 0; $i < sizeof($dates)-1 ; $i++) {
                $firstDate  = $dates[$i];
                $secondDate = $dates[$i+1];

                $label = $firstDate->format($format);

                $query   = $wpdb->prepare("select `status`,count(*) as count from `{$wpdb->prefix}sms_send` where `date` between DATE(%s) and DATE(%s) group by `status`", $firstDate->format('Y-m-d'), $secondDate->format('Y-m-d'));
                $results = $wpdb->get_results($query);

                foreach ($results as $key => $result) {
                    $results[$result->status] = $result->count;
                    unset($results[$key]);
                }

                $datasets['successful'][$label] = $results['success'] ?? 0;
                $datasets['failure'][$label] = $results['error'] ?? 0;
            }

            return $datasets;
        };

        $sentMessages['last_7_days'] = $getResults(
            new DatePeriod(new DateTime('tomorrow'), DateInterval::createFromDateString('-1 day'), 7),
            'd D'
        );
        $sentMessages['last_30_days'] = $getResults(
            new DatePeriod(new DateTime('tomorrow'), DateInterval::createFromDateString('-1 day'), 30),
            'd M'
        );
        $sentMessages['this_year'] = $getResults(
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
