<?php

namespace WP_SMS\Admin\Widget\Widgets;

use WP_SMS\Admin\Widget\AbstractWidget;
use WP_SMS\Helper;

class StatsWidget extends AbstractWidget
{
    protected $id = 'wp-sms-stats';
    protected $name = 'WP SMS Stats';
    protected $version = '1.0';

    protected function prepare()
    {
        wp_register_script('wp-sms-chartjs', Helper::getPluginAssetUrl('js/chart.min.js'), [], '3.7.1');
        wp_enqueue_script('wp-sms-dashboard-widget-stats', Helper::getPluginAssetUrl('js/admin-dashboard-stats-widget.js'), ['wp-sms-chartjs']);
        wp_localize_script('wp-sms-dashboard-widget-stats', 'WPSmsStatsData', apply_filters('wp_sms_stats_widget_data', $this->fetchSentMessagesStats()));
    }

    public function render()
    {
        echo Helper::loadTemplate('admin-dashboard-widget.php', [
            'foo' => 'bar'
        ]);
    }

    private function fetchSentMessagesStats()
    {
        return [
            'send-messages-stats' => [],
        ];
    }
}
