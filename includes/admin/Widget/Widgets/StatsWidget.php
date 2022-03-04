<?php

namespace WP_SMS\Admin\Widget\Widgets;

use WP_SMS\Admin\Widget\AbstractWidget;
use WP_SMS\Helper;

class StatsWidget extends AbstractWidget
{
    protected $id = 'wp-sms-stats';
    protected $name = 'WP SMS Stats';
    protected $version = '1.0';

    /**
     * @var array
     */
    private $data = [];

    protected function prepare()
    {
        $this->fetchData();
        wp_register_script('wp-sms-chartjs', Helper::getPluginAssetUrl('js/chart.min.js'), [], '3.7.1');
        wp_enqueue_script('wp-sms-dashboard-widget-stats', Helper::getPluginAssetUrl('js/admin-dashboard-stats-widget.js'), ['wp-sms-chartjs']);
        wp_localize_script('wp-sms-dashboard-widget-stats', 'WPSmsStatsData', apply_filters('wp_sms_stats_widget_data', $this->data));
    }

    public function render()
    {
        echo "<h1>chart</h1><div class='wp-sms-widget stats-widget'>
            <canvas width='400' height='400'></canvas>
        </div>";
    }

    private function fetchData()
    {
        //select json_unquote(json_extract(`action_status`, '$."success"')) as `actionSuccess`, count(*) as count from `wp_sms_two_way_incoming_messages` where `received_at` between ? and ? and `wp_sms_two_way_incoming_messages`.`deleted_at` is null group by `actionSuccess
    }
}
