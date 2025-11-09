<?php

namespace WP_SMS\Admin\Reports\Widgets;

use WP_SMS\Admin\Reports\Abstracts\AbstractWidget;
use WP_SMS\Services\OTP\Models\AuthEventModel;

/**
 * HealthSnapshotWidget - Displays 6 key performance indicators.
 */
class HealthSnapshotWidget extends AbstractWidget
{
    /**
     * @inheritDoc
     */
    public function getType()
    {
        return 'kpi';
    }

    /**
     * @inheritDoc
     */
    public function getLabel()
    {
        return __('Health Snapshot', 'wp-sms');
    }

    /**
     * @inheritDoc
     */
    public function getData($filters)
    {
        $query = $this->buildBaseQuery($filters);

        // Get all events
        $events = $query->get();

        // Calculate KPIs
        $attempts = count($events);
        $succeeded = 0;
        $failed = 0;
        $totalDuration = 0;
        $durationCount = 0;
        $lockedAccounts = [];

        $lockThreshold = 5; // Can be made configurable

        foreach ($events as $event) {
            if ($event['result'] === 'allow') {
                $succeeded++;
            } elseif ($event['result'] === 'deny') {
                $failed++;
            }

            // Track locked accounts (attempt_count >= threshold)
            if (isset($event['attempt_count']) && $event['attempt_count'] >= $lockThreshold && !empty($event['user_id'])) {
                $lockedAccounts[$event['user_id']] = true;
            }
        }

        // Calculate delivery success (simplified - based on vendor_status)
        $delivered = 0;
        $deliveryTotal = 0;
        foreach ($events as $event) {
            if (!empty($event['vendor_status'])) {
                $deliveryTotal++;
                if (stripos($event['vendor_status'], 'delivered') !== false || 
                    stripos($event['vendor_status'], 'sent') !== false ||
                    stripos($event['vendor_status'], 'queued') !== false) {
                    $delivered++;
                }
            }
        }

        $successRate = ($succeeded + $failed) > 0 ? round(($succeeded / ($succeeded + $failed)) * 100, 1) : 0;
        $deliveryRate = $deliveryTotal > 0 ? round(($delivered / $deliveryTotal) * 100, 1) : 0;

        return [
            'kpis' => [
                [
                    'key' => 'attempts',
                    'label' => __('Attempts', 'wp-sms'),
                    'value' => $attempts,
                    'format' => 'number',
                ],
                [
                    'key' => 'succeeded',
                    'label' => __('Succeeded', 'wp-sms'),
                    'value' => $succeeded,
                    'format' => 'number',
                ],
                [
                    'key' => 'success_rate',
                    'label' => __('Success Rate', 'wp-sms'),
                    'value' => $successRate,
                    'format' => 'percentage',
                ],
                [
                    'key' => 'avg_2fa_duration',
                    'label' => __('Avg 2FA Duration', 'wp-sms'),
                    'value' => 0,  // Would need timestamp comparison
                    'format' => 'seconds',
                ],
                [
                    'key' => 'accounts_locked',
                    'label' => __('Accounts Locked', 'wp-sms'),
                    'value' => count($lockedAccounts),
                    'format' => 'number',
                ],
                [
                    'key' => 'delivery_success',
                    'label' => __('Delivery Success', 'wp-sms'),
                    'value' => $deliveryRate,
                    'format' => 'percentage',
                ],
            ],
        ];
    }

    /**
     * Build base query with filters applied.
     * 
     * @param array $filters
     * @return mixed QueryBuilder instance
     */
    protected function buildBaseQuery($filters)
    {
        $query = AuthEventModel::query();

        // Apply date range
        if (!empty($filters['date_range'])) {
            $range = $this->parseDateRange($filters['date_range']);
            if ($range) {
                $query->whereBetween('timestamp_utc', $range['start'], $range['end']);
            }
        }

        // Apply channel filter
        if (!empty($filters['channel']) && is_array($filters['channel'])) {
            $query->whereIn('channel', $filters['channel']);
        }

        // Apply country filter
        if (!empty($filters['country'])) {
            $query->where('geo_country', $filters['country']);
        }

        // Apply WP role filter
        if (!empty($filters['wp_role']) && is_array($filters['wp_role'])) {
            $query->whereIn('wp_role', $filters['wp_role']);
        }

        return $query;
    }

    /**
     * Parse date range (same as in AuthenticationEventLogPage).
     * 
     * @param mixed $dateRange
     * @return array|null
     */
    protected function parseDateRange($dateRange)
    {
        if (is_array($dateRange)) {
            if (isset($dateRange['start']) && isset($dateRange['end'])) {
                return [
                    'start' => gmdate('Y-m-d H:i:s', strtotime($dateRange['start'])),
                    'end' => gmdate('Y-m-d H:i:s', strtotime($dateRange['end'])),
                ];
            }
            return null;
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

