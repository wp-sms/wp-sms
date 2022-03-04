<?php

namespace WP_SMS\Admin\Widget\Widgets;

use WP_SMS\Admin\Widget\AbstractWidget;
use WP_SMS\Helper;

class StatsWidget extends AbstractWidget
{
    protected $id = 'wp-sms-stats';
    protected $name = 'WP SMS Stats';
    protected $version = '1.0';

    public function __construct()
    {
        // @todo, filter doesn't work in this structure, please fix it!
        add_filter('wp_sms_stats_widget_data', [$this, 'addReceivedMessagesToStatsWidget']);
    }

    /**
     * @var array
     */
    private $data = [];

    protected function prepare()
    {
        wp_register_script('wp-sms-chartjs', Helper::getPluginAssetUrl('js/chart.min.js'), [], '3.7.1');
        wp_enqueue_script('wp-sms-dashboard-widget-stats', Helper::getPluginAssetUrl('js/admin-dashboard-stats-widget.js'), ['wp-sms-chartjs']);
        wp_localize_script('wp-sms-dashboard-widget-stats', 'WPSmsWidgetsStats', apply_filters('wp_sms_stats_widget_data', $this->data));
    }

    // todo, rename html classes and build the html according to the screenshot on the task
    public function render()
    {
        echo Helper::loadTemplate('admin-dashboard-widget.php', [
            'foo' => 'bar'
        ]);
    }

    // @todo, should be dynamic
    public function addReceivedMessagesToStatsWidget($data)
    {
        $data['send-messages-stats'] = [
            'last_7_days'   => [

            ],
            'last_30_days'  => [

            ],
            'this_year'     => [

            ],
            'last_12_month' => [

            ],
        ];

        return $data;
    }
}
