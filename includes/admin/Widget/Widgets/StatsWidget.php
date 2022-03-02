<?php

namespace WP_SMS\Admin\Widget\Widgets;

use WP_SMS\Admin\Widget\AbstractWidget;
use WP_SMS\Helper;
use WPSmsTwoWay\Models\IncomingMessage;
use DatePeriod;
use DateInterval;
use DateTime;

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
         * @param \DatePeriod $period
         * @param string $format
         */
        $getResults = function (DatePeriod $period, string $format) use ($DB) {
            foreach ($period as $number => $date) {
                $receivedMessages[$date->format($format)] = IncomingMessage::whereBetween('received_at', [(clone $date)->add($period->getDateInterval())->getTimeStamp() ,$date->getTimestamp()])
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
            }

            return $receivedMessages;
        };

        $now = new DateTime();

        $receivedMessages['last_7_days'] = $getResults(
            new DatePeriod($now, DateInterval::createFromDateString('-1 day'), 7),
            'd D'
        );
        $receivedMessages['last_30_days'] = $getResults(
            new DatePeriod($now, DateInterval::createFromDateString('-1 day'), 30),
            'd M'
        );
        $receivedMessages['this_year'] = $getResults(
            new DatePeriod(new DateTime('first day of jan'), DateInterval::createFromDateString('1 day'), $now),
            'M'
        );
        $receivedMessages['last_12_month'] = $getResults(
            new DatePeriod($now, DateInterval::createFromDateString('-1 month'), 12),
            'M'
        );

        dump($receivedMessages);
    }
}
