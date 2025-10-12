<?php

namespace WP_SMS\Admin\Reports\Widgets;

use WP_SMS\Admin\Reports\Abstracts\AbstractWidget;
use WP_SMS\Services\OTP\Models\AuthEventModel;

/**
 * GeoHeatmapWidget - World map data showing attempts and success by country.
 */
class GeoHeatmapWidget extends AbstractWidget
{
    /**
     * @inheritDoc
     */
    public function getType()
    {
        return 'map';
    }

    /**
     * @inheritDoc
     */
    public function getLabel()
    {
        return __('Geographic Distribution', 'wp-sms');
    }

    /**
     * @inheritDoc
     */
    public function getData($filters)
    {
        $query = $this->buildBaseQuery($filters);
        $events = $query->get();

        // Aggregate by country
        $countryStats = [];

        foreach ($events as $event) {
            $country = $event['geo_country'] ?: 'UNKNOWN';
            $channel = $event['channel'] ?: 'unknown';

            if (!isset($countryStats[$country])) {
                $countryStats[$country] = [
                    'attempts' => 0,
                    'success' => 0,
                    'channels' => [],
                ];
            }

            $countryStats[$country]['attempts']++;

            if ($event['result'] === 'allow') {
                $countryStats[$country]['success']++;
            }

            if (!isset($countryStats[$country]['channels'][$channel])) {
                $countryStats[$country]['channels'][$channel] = 0;
            }
            $countryStats[$country]['channels'][$channel]++;
        }

        // Format for frontend
        $countries = [];

        foreach ($countryStats as $code => $stats) {
            $attempts = $stats['attempts'];
            $success = $stats['success'];
            $successRate = $attempts > 0 ? round(($success / $attempts) * 100, 1) : 0;

            // Find top channel
            $topChannel = 'unknown';
            $topChannelCount = 0;
            foreach ($stats['channels'] as $channel => $count) {
                if ($count > $topChannelCount) {
                    $topChannel = $channel;
                    $topChannelCount = $count;
                }
            }
            $topChannelPercentage = $attempts > 0 ? round(($topChannelCount / $attempts) * 100, 1) : 0;

            $countries[] = [
                'code' => $code,
                'attempts' => $attempts,
                'success' => $success,
                'successRate' => $successRate,
                'avgDuration' => 0,  // Would need timestamp analysis
                'topChannel' => ucfirst($topChannel),
                'topChannelPercentage' => $topChannelPercentage,
            ];
        }

        // Sort by attempts descending
        usort($countries, function ($a, $b) {
            return $b['attempts'] - $a['attempts'];
        });

        return [
            'countries' => $countries,
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

