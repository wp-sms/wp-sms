<?php

namespace WP_SMS\Admin\Reports\Widgets;

use WP_SMS\Admin\Reports\Abstracts\AbstractWidget;
use WP_SMS\Services\OTP\Models\AuthEventModel;

/**
 * MethodMixWidget - Pie chart showing primary 2FA method distribution.
 */
class MethodMixWidget extends AbstractWidget
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
        return __('Method Mix', 'wp-sms');
    }

    /**
     * @inheritDoc
     */
    public function getData($filters)
    {
        $query = $this->buildBaseQuery($filters);

        // Group by channel
        $query->select([
            'channel',
            'COUNT(*) as count',
        ]);
        $query->groupBy('channel');

        $results = $query->get();

        $labels = [];
        $data = [];
        $colors = [
            'sms' => '#3b82f6',
            'email' => '#8b5cf6',
            'whatsapp' => '#10b981',
            'social' => '#f59e0b',
        ];
        $backgroundColors = [];

        foreach ($results as $row) {
            $channel = $row['channel'] ?: 'unknown';
            $labels[] = ucfirst($channel);
            $data[] = (int) $row['count'];
            $backgroundColors[] = isset($colors[$channel]) ? $colors[$channel] : '#6b7280';
        }

        return [
            'labels' => $labels,
            'data' => $data,
            'colors' => $backgroundColors,
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

        // Filter only verified events for 2FA method analysis
        $query->where('event_type', 'verified');

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

