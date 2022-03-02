<?php

namespace WP_SMS\Admin\Widget\Widgets;

use WP_SMS\Admin\Widget\AbstractWidget;
use WP_SMS\Helper;
use WPSmsTwoWay\Models\IncomingMessage;

class StatsWidget extends AbstractWidget
{
    protected $id = 'wp-sms-stats';
    protected $name = 'WP SMS Stats';
    protected $version = '1.0';

    /**
     * @var array
     */
    private $data = [];

    protected function onInit()
    {
        add_action('plugins_loaded', function () {
            $this->fetchSentSmsData();
            $this->fetchReceivedSmsData();
        }, 11);
    }

    protected function assets()
    {
        wp_register_script('wp-sms-chartjs', Helper::getPluginAssetUrl('js/chart.min.js'), [], '3.7.1');
        wp_enqueue_script('wp-sms-widgets-script', Helper::getPluginAssetUrl('js/widgets/stats.js'), ['wp-sms-chartjs']);
    }

    public function render()
    {
        dump('hi');
    }

    private function fetchSentSmsData()
    {
        //select json_unquote(json_extract(`action_status`, '$."success"')) as `actionSuccess`, count(*) as count from `wp_sms_two_way_incoming_messages` where `received_at` between ? and ? and `wp_sms_two_way_incoming_messages`.`deleted_at` is null group by `actionSuccess
    }

    private function fetchReceivedSmsData()
    {
        // Check if wp-sms-two-way is active
        if (!function_exists('WPSmsTwoWay') || !WPSmsTwoWay()->getPlugin()->isBooted()) {
            return;
        }

        $DB = get_class(WPSmsTwoWay()->getPlugin()->database());

        /**
         * @param string[] $timeRange ['beginning', 'ending']
         * @param string   $interval
         * @link https://www.php.net/manual/en/datetime.formats.relative.php
         */
        $getResults = function (array $timeRange, string $interval, string $format = 'Y-m-d') use ($DB) {
            $begin = new \DateTime($timeRange[0]);
            $end   = new \DateTime($timeRange[1]);

            // for ($time = $begin ; $time >= $end  ; $time->modify($interval)) {);
            // }

            $time = $begin ;
            do {
                $receivedMessages[$time->format($format)] = IncomingMessage::whereBetween('received_at', [(clone $time)->modify($interval)->getTimeStamp(), $time->getTimestamp()])
                    ->select('action_status->success as actionSuccess', $DB::raw('count(*) as count'))
                    ->groupBy('actionSuccess')
                    ->get()
                    ->groupBy(function (&$item) {
                        switch ($item['actionSuccess']) {
                            case 'true':
                                return 'successful';
                            case 'false':
                                return 'failed';
                            case null:
                                return 'plain';

                        }
                    })
                    ->toArray();
                $time->modify($interval);
            } while ($time >= $end);

            return $receivedMessages;
        };

        //! format arg must generate unique labels
        $receivedMessages['last_7_days']   = $getResults(['now', '-6 days'], '-1 day', 'd D');
        $receivedMessages['last_30_days']  = $getResults(['now', '-29 day'], '-1 day', 'd M');
        $receivedMessages['this_year']     = $getResults(['now', 'first day of this year'], '-1 month', 'M');
        $receivedMessages['last_12_month'] = $getResults(['now', '-12 months'], '-1 month', 'M');

        dump($receivedMessages);
    }
}
