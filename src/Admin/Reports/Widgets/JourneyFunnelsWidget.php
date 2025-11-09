<?php

namespace WP_SMS\Admin\Reports\Widgets;

use WP_SMS\Admin\Reports\Abstracts\AbstractWidget;
use WP_SMS\Services\OTP\Models\AuthEventModel;

/**
 * JourneyFunnelsWidget - Displays login and registration funnels.
 */
class JourneyFunnelsWidget extends AbstractWidget
{
    /**
     * @inheritDoc
     */
    public function getType()
    {
        return 'funnel';
    }

    /**
     * @inheritDoc
     */
    public function getLabel()
    {
        return __('Journey Funnels', 'wp-sms');
    }

    /**
     * @inheritDoc
     */
    public function getData($filters)
    {
        $query = $this->buildBaseQuery($filters);
        $events = $query->get();

        // Count events by type and result
        $requested = 0;
        $sent = 0;
        $verified = 0;
        $allowed = 0;
        $denied = 0;

        foreach ($events as $event) {
            switch ($event['event_type']) {
                case 'requested':
                    $requested++;
                    break;
                case 'sent':
                    $sent++;
                    break;
                case 'verified':
                    $verified++;
                    break;
            }

            if ($event['result'] === 'allow') {
                $allowed++;
            } elseif ($event['result'] === 'deny') {
                $denied++;
            }
        }

        // Calculate dropoff percentages
        $stages = [
            ['label' => __('Requested', 'wp-sms'), 'count' => $requested],
            ['label' => __('Sent', 'wp-sms'), 'count' => $sent],
            ['label' => __('Verified', 'wp-sms'), 'count' => $verified],
            ['label' => __('Allowed', 'wp-sms'), 'count' => $allowed],
        ];

        // Calculate dropoff
        for ($i = 0; $i < count($stages) - 1; $i++) {
            $current = $stages[$i]['count'];
            $next = $stages[$i + 1]['count'];
            $dropoff = $current > 0 ? round((($current - $next) / $current) * 100, 1) : 0;
            $stages[$i]['dropoffPercentage'] = $dropoff;
        }

        return [
            'loginFunnel' => [
                'stages' => $stages,
            ],
            // Registration funnel would be similar but with different event types
            'registrationFunnel' => [
                'stages' => [],
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

        if (!empty($filters['channel']) && is_array($filters['channel'])) {
            $query->whereIn('channel', $filters['channel']);
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

