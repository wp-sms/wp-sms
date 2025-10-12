<?php

namespace WP_SMS\Admin\Reports\Widgets;

use WP_SMS\Admin\Reports\Abstracts\AbstractWidget;
use WP_SMS\Services\OTP\Models\AuthEventModel;

/**
 * DeliveryQualityWidget - Grouped bar chart showing delivery quality by channel.
 */
class DeliveryQualityWidget extends AbstractWidget
{
    /**
     * @inheritDoc
     */
    public function getType()
    {
        return 'chart';
    }

    /**
     * @inheritDoc
     */
    public function getLabel()
    {
        return __('Delivery Quality by Channel', 'wp-sms');
    }

    /**
     * @inheritDoc
     */
    public function getData($filters)
    {
        $query = $this->buildBaseQuery($filters);
        $events = $query->get();

        // Analyze delivery status by channel
        $channelStats = [];

        foreach ($events as $event) {
            $channel = $event['channel'] ?: 'unknown';
            $status = strtolower($event['vendor_status'] ?: 'empty');

            if (!isset($channelStats[$channel])) {
                $channelStats[$channel] = [
                    'delivered' => 0,
                    'gateway_error' => 0,
                    'user_timeout' => 0,
                    'total' => 0,
                ];
            }

            $channelStats[$channel]['total']++;

            // Classify vendor status
            if (stripos($status, 'delivered') !== false || 
                stripos($status, 'sent') !== false ||
                stripos($status, 'queued') !== false) {
                $channelStats[$channel]['delivered']++;
            } elseif (stripos($status, 'error') !== false || 
                      stripos($status, 'failed') !== false) {
                $channelStats[$channel]['gateway_error']++;
            } elseif (stripos($status, 'timeout') !== false || 
                      stripos($status, 'undelivered') !== false) {
                $channelStats[$channel]['user_timeout']++;
            }
        }

        // Calculate percentages and build Chart.js format
        $labels = array_keys($channelStats);
        $deliveredData = [];
        $gatewayErrorData = [];
        $userTimeoutData = [];

        foreach ($channelStats as $channel => $stats) {
            $total = $stats['total'];
            $deliveredData[] = $total > 0 ? round(($stats['delivered'] / $total) * 100, 1) : 0;
            $gatewayErrorData[] = $total > 0 ? round(($stats['gateway_error'] / $total) * 100, 1) : 0;
            $userTimeoutData[] = $total > 0 ? round(($stats['user_timeout'] / $total) * 100, 1) : 0;
        }

        return [
            'labels' => array_map('ucfirst', $labels),
            'datasets' => [
                [
                    'label' => __('Delivered %', 'wp-sms'),
                    'data' => $deliveredData,
                    'backgroundColor' => '#10b981',
                ],
                [
                    'label' => __('Gateway Error %', 'wp-sms'),
                    'data' => $gatewayErrorData,
                    'backgroundColor' => '#ef4444',
                ],
                [
                    'label' => __('User Timeout %', 'wp-sms'),
                    'data' => $userTimeoutData,
                    'backgroundColor' => '#f59e0b',
                ],
            ],
        ];
    }

    /**
     * Build base query with filters.
     * 
     * @param array $filters
     * @return mixed
     */
    protected function buildBaseQuery($filters)
    {
        $query = AuthEventModel::query();

        if (!empty($filters['date_range'])) {
            $range = $this->parseDateRange($filters['date_range']);
            if ($range) {
                $query->whereBetween('timestamp_utc', $range['start'], $range['end']);
            }
        }

        // Only events that were sent (have vendor status)
        $query->whereNotNull('vendor_status');

        return $query;
    }

    /**
     * Parse date range.
     * 
     * @param mixed $dateRange
     * @return array|null
     */
    protected function parseDateRange($dateRange)
    {
        if (is_array($dateRange) && isset($dateRange['start']) && isset($dateRange['end'])) {
            return [
                'start' => gmdate('Y-m-d H:i:s', strtotime($dateRange['start'])),
                'end' => gmdate('Y-m-d H:i:s', strtotime($dateRange['end'])),
            ];
        }

        $now = time();
        $start = null;

        switch ($dateRange) {
            case 'today':
                $start = strtotime('today');
                break;
            case 'last_7d':
                $start = $now - (7 * 86400);
                break;
            case 'last_30d':
                $start = $now - (30 * 86400);
                break;
            default:
                return null;
        }

        return [
            'start' => gmdate('Y-m-d H:i:s', $start),
            'end' => gmdate('Y-m-d H:i:s', $now),
        ];
    }
}

