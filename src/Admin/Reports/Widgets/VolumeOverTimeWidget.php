<?php

namespace WP_SMS\Admin\Reports\Widgets;

use WP_SMS\Admin\Reports\Abstracts\AbstractWidget;
use WP_SMS\Services\OTP\Models\AuthEventModel;

/**
 * VolumeOverTimeWidget - Stacked area chart showing attempts over time by channel.
 */
class VolumeOverTimeWidget extends AbstractWidget
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
        return __('Volume Over Time', 'wp-sms');
    }

    /**
     * @inheritDoc
     */
    public function getData($filters)
    {
        $query = $this->buildBaseQuery($filters);
        
        // Select date and channel with count
        $query->select([
            'DATE(timestamp_utc) as date',
            'channel',
            'COUNT(*) as count',
        ]);
        
        $query->groupBy(['DATE(timestamp_utc)', 'channel']);
        $query->orderBy('date', 'ASC');

        $results = $query->get();

        // Transform into Chart.js format
        $dateMap = [];
        $channelMap = [];

        foreach ($results as $row) {
            $date = $row['date'];
            $channel = $row['channel'] ?: 'unknown';
            $count = (int) $row['count'];

            if (!isset($dateMap[$date])) {
                $dateMap[$date] = [];
            }
            $dateMap[$date][$channel] = $count;
            $channelMap[$channel] = true;
        }

        // Build labels and datasets
        $labels = array_keys($dateMap);
        $channels = array_keys($channelMap);
        $datasets = [];

        $colors = [
            'sms' => '#3b82f6',
            'email' => '#8b5cf6',
            'whatsapp' => '#10b981',
            'social' => '#f59e0b',
            'unknown' => '#6b7280',
        ];

        foreach ($channels as $channel) {
            $data = [];
            foreach ($labels as $date) {
                $data[] = isset($dateMap[$date][$channel]) ? $dateMap[$date][$channel] : 0;
            }

            $datasets[] = [
                'label' => ucfirst($channel),
                'data' => $data,
                'backgroundColor' => isset($colors[$channel]) ? $colors[$channel] : '#6b7280',
                'borderColor' => isset($colors[$channel]) ? $colors[$channel] : '#6b7280',
            ];
        }

        return [
            'labels' => $labels,
            'datasets' => $datasets,
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

