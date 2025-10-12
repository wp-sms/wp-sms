<?php

namespace WP_SMS\Admin\Logs\Pages;

use WP_SMS\Admin\Logs\Abstracts\AbstractLogPage;
use WP_SMS\Services\OTP\Models\AuthEventModel;

/**
 * AuthenticationEventLogPage - Log page for authentication events.
 * 
 * Displays comprehensive authentication and 2FA event logs with rich filtering.
 */
class AuthenticationEventLogPage extends AbstractLogPage
{
    /**
     * @inheritDoc
     */
    public function getSlug()
    {
        return 'auth-events';
    }

    /**
     * @inheritDoc
     */
    public function getLabel()
    {
        return __('Authentication Events', 'wp-sms');
    }

    /**
     * @inheritDoc
     */
    public function getDescription()
    {
        return __('Complete log of authentication attempts, OTP verifications, and 2FA events', 'wp-sms');
    }

    /**
     * @inheritDoc
     */
    public function getSchema()
    {
        return [
            // Default ON columns (9 core columns)
            [
                'key' => 'timestamp_utc',
                'label' => __('TS (UTC)', 'wp-sms'),
                'sortable' => true,
                'visisble' => true,
            ],
            [
                'key' => 'flow_id',
                'label' => __('Flow ID', 'wp-sms'),
                'sortable' => true,
                'visisble' => true,
            ],
            [
                'key' => 'event_type',
                'label' => __('Event', 'wp-sms'),
                'sortable' => true,
                'visisble' => true,
            ],
            [
                'key' => 'result',
                'label' => __('Result', 'wp-sms'),
                'sortable' => true,
                'visisble' => true,
            ],
            [
                'key' => 'channel',
                'label' => __('Channel', 'wp-sms'),
                'sortable' => true,
                'visisble' => true,
            ],
            [
                'key' => 'geo_country',
                'label' => __('Country', 'wp-sms'),
                'sortable' => true,
                'visisble' => true,
            ],
            [
                'key' => 'user_id',
                'label' => __('User ID', 'wp-sms'),
                'sortable' => true,
                'visisble' => true,
            ],
            [
                'key' => 'vendor_status',
                'label' => __('Vendor Status', 'wp-sms'),
                'sortable' => false,
                'visisble' => true,
            ],
            [
                'key' => 'attempt_count',
                'label' => __('Attempts', 'wp-sms'),
                'sortable' => true,
                'visisble' => true,
            ],

            // Advanced OFF columns (10 additional columns)
            [
                'key' => 'scenario_key',
                'label' => __('Scenario', 'wp-sms'),
                'sortable' => true,
                'visible' => false,
            ],
            [
                'key' => 'wp_role',
                'label' => __('WP Role', 'wp-sms'),
                'sortable' => true,
                'visible' => false,
            ],
            [
                'key' => 'client_ip_masked',
                'label' => __('Client IP', 'wp-sms'),
                'sortable' => false,
                'visible' => false,
            ],
            [
                'key' => 'vendor_sid',
                'label' => __('Vendor SID', 'wp-sms'),
                'sortable' => false,
                'visible' => false,
            ],
            [
                'key' => 'factor_id',
                'label' => __('Factor ID', 'wp-sms'),
                'sortable' => false,
                'visible' => false,
            ],
            [
                'key' => 'device_type',
                'label' => __('Device', 'wp-sms'),
                'sortable' => true,
                'visible' => false,
            ],
            [
                'key' => 'retention_days',
                'label' => __('Retention (d)', 'wp-sms'),
                'sortable' => true,
                'visible' => false,
            ],
            [
                'key' => 'user_agent',
                'label' => __('User Agent', 'wp-sms'),
                'sortable' => false,
                'visible' => false,
            ],
            [
                'key' => 'event_id',
                'label' => __('Event ID', 'wp-sms'),
                'sortable' => false,
                'visible' => false,
            ],
            [
                'key' => 'id',
                'label' => __('ID', 'wp-sms'),
                'sortable' => true,
                'visible' => false,
            ],
        ];
    }

    /**
     * @inheritDoc
     */
    public function getFilters()
    {
        return [
            // Time & Flow group
            [
                'key' => 'date_range',
                'label' => __('Date / Time Range', 'wp-sms'),
                'type' => 'date-range',
                'group' => 'time_flow',
                'default' => 'last_24h',
                'presets' => [
                    'last_1h' => __('Last Hour', 'wp-sms'),
                    'last_24h' => __('Last 24 Hours', 'wp-sms'),
                    'last_7d' => __('Last 7 Days', 'wp-sms'),
                    'last_30d' => __('Last 30 Days', 'wp-sms'),
                    'custom' => __('Custom Range', 'wp-sms'),
                ],
            ],
            [
                'key' => 'flow_type',
                'label' => __('Flow Type', 'wp-sms'),
                'type' => 'radio',
                'group' => 'time_flow',
                'options' => [
                    'all' => __('Both', 'wp-sms'),
                    'login' => __('Log-in', 'wp-sms'),
                    'registration' => __('Registration', 'wp-sms'),
                ],
                'default' => 'all',
            ],
            [
                'key' => 'scenario',
                'label' => __('Scenario', 'wp-sms'),
                'type' => 'select',
                'group' => 'time_flow',
                'searchable' => true,
                'options' => [],  // Populated dynamically
            ],

            // User & Role group
            [
                'key' => 'user_id',
                'label' => __('User ID', 'wp-sms'),
                'type' => 'text',
                'group' => 'user_role',
                'placeholder' => __('Comma-separated user IDs', 'wp-sms'),
                'autocomplete' => true,
            ],
            [
                'key' => 'wp_role',
                'label' => __('WP Role', 'wp-sms'),
                'type' => 'multi-select',
                'group' => 'user_role',
                'options' => $this->getWpRoles(),
            ],

            // Channel & Method group
            [
                'key' => 'channel',
                'label' => __('Auth Channel', 'wp-sms'),
                'type' => 'multi-select',
                'group' => 'channel_method',
                'options' => [
                    'sms' => __('SMS', 'wp-sms'),
                    'email' => __('Email', 'wp-sms'),
                    'whatsapp' => __('WhatsApp', 'wp-sms'),
                    'social' => __('Social', 'wp-sms'),
                ],
            ],
            [
                'key' => 'twofa_method',
                'label' => __('2-FA Method', 'wp-sms'),
                'type' => 'multi-select',
                'group' => 'channel_method',
                'options' => [
                    'sms_otp' => __('SMS OTP', 'wp-sms'),
                    'email_otp' => __('Email OTP', 'wp-sms'),
                    'totp' => __('TOTP', 'wp-sms'),
                    'push' => __('Push', 'wp-sms'),
                ],
            ],

            // Outcome group
            [
                'key' => 'event_type',
                'label' => __('Event Type', 'wp-sms'),
                'type' => 'checkbox',
                'group' => 'outcome',
                'options' => [
                    'requested' => __('Requested', 'wp-sms'),
                    'sent' => __('Sent', 'wp-sms'),
                    'verified' => __('Verified', 'wp-sms'),
                    'failed' => __('Failed', 'wp-sms'),
                ],
            ],
            [
                'key' => 'result',
                'label' => __('Result', 'wp-sms'),
                'type' => 'chips',
                'group' => 'outcome',
                'options' => [
                    'allow' => __('Allow', 'wp-sms'),
                    'deny' => __('Deny', 'wp-sms'),
                    'n/a' => __('N/A', 'wp-sms'),
                ],
            ],
            [
                'key' => 'vendor_status_group',
                'label' => __('Vendor Status Group', 'wp-sms'),
                'type' => 'select',
                'group' => 'outcome',
                'options' => [
                    'delivered' => __('Delivered', 'wp-sms'),
                    'soft_fail' => __('Soft-fail', 'wp-sms'),
                    'hard_fail' => __('Hard-fail', 'wp-sms'),
                    'empty' => __('Empty', 'wp-sms'),
                ],
            ],

            // Geo & Network group
            [
                'key' => 'country',
                'label' => __('Country', 'wp-sms'),
                'type' => 'select',
                'group' => 'geo_network',
                'searchable' => true,
                'options' => $this->getCountries(),
            ],
            [
                'key' => 'client_ip',
                'label' => __('Client IP Partial', 'wp-sms'),
                'type' => 'text',
                'group' => 'geo_network',
                'placeholder' => __('e.g., 203.0.113.*', 'wp-sms'),
            ],

            // Security group
            [
                'key' => 'attempt_count_min',
                'label' => __('Attempt Count ≥', 'wp-sms'),
                'type' => 'number',
                'group' => 'security',
                'min' => 1,
                'max' => 100,
            ],

            // Ops group
            [
                'key' => 'vendor_sid',
                'label' => __('Vendor SID', 'wp-sms'),
                'type' => 'text',
                'group' => 'ops',
                'placeholder' => __('Lookup by SID', 'wp-sms'),
            ],

            // Quick Search (global)
            [
                'key' => 'quick_search',
                'label' => __('Quick Search', 'wp-sms'),
                'type' => 'text',
                'group' => 'global',
                'placeholder' => __('Search flow_id, event_id, vendor_sid, IP...', 'wp-sms'),
            ],
        ];
    }

    /**
     * @inheritDoc
     */
    public function getData($filters, $sorts, $page, $perPage)
    {
        $query = AuthEventModel::query();

        // Apply filters
        $this->applyFilters($query, $filters);

        // Apply sorting
        if (!empty($sorts)) {
            $sortMap = [];
            foreach ($sorts as $sort) {
                if (isset($sort['column']) && isset($sort['direction'])) {
                    $sortMap[$sort['column']] = $sort['direction'];
                }
            }
            if (!empty($sortMap)) {
                $query->orderByMultiple($sortMap);
            }
        } else {
            // Default sort by timestamp DESC
            $query->orderBy('timestamp_utc', 'DESC');
        }

        // Paginate
        return $query->paginate($page, $perPage);
    }

    /**
     * @inheritDoc
     */
    public function getRow($id)
    {
        return AuthEventModel::find(['id' => $id]);
    }

    /**
     * Apply filters to query.
     * 
     * @param mixed $query QueryBuilder instance
     * @param array $filters
     * @return void
     */
    protected function applyFilters($query, $filters)
    {
        if (empty($filters)) {
            return;
        }

        // Date range filter
        if (!empty($filters['date_range'])) {
            $range = $this->parseDateRange($filters['date_range']);
            if ($range) {
                $query->whereBetween('timestamp_utc', $range['start'], $range['end']);
            }
        }

        // Flow type filter (requires derived column - skip for now, can be added later)
        // This would need a flow_type column or JOIN

        // User ID filter (comma-separated)
        if (!empty($filters['user_id'])) {
            $userIds = array_map('trim', explode(',', $filters['user_id']));
            $userIds = array_filter($userIds, 'is_numeric');
            if (!empty($userIds)) {
                $query->whereIn('user_id', $userIds);
            }
        }

        // WP Role filter
        if (!empty($filters['wp_role']) && is_array($filters['wp_role'])) {
            $query->whereIn('wp_role', $filters['wp_role']);
        }

        // Channel filter
        if (!empty($filters['channel']) && is_array($filters['channel'])) {
            $query->whereIn('channel', $filters['channel']);
        }

        // Event type filter
        if (!empty($filters['event_type']) && is_array($filters['event_type'])) {
            $query->whereIn('event_type', $filters['event_type']);
        }

        // Result filter
        if (!empty($filters['result']) && is_array($filters['result'])) {
            $query->whereIn('result', $filters['result']);
        }

        // Country filter
        if (!empty($filters['country'])) {
            $query->where('geo_country', $filters['country']);
        }

        // Client IP partial filter (LIKE)
        if (!empty($filters['client_ip'])) {
            $pattern = str_replace('*', '%', $filters['client_ip']);
            $query->whereLike('client_ip_masked', $pattern);
        }

        // Attempt count minimum
        if (isset($filters['attempt_count_min']) && is_numeric($filters['attempt_count_min'])) {
            $query->where('attempt_count', '>=', (int) $filters['attempt_count_min']);
        }

        // Vendor SID filter
        if (!empty($filters['vendor_sid'])) {
            $query->where('vendor_sid', $filters['vendor_sid']);
        }

        // Quick search (searches multiple columns)
        if (!empty($filters['quick_search'])) {
            $searchTerm = '%' . $filters['quick_search'] . '%';
            $query->orWhere([
                ['type' => 'like', 'column' => 'flow_id', 'pattern' => $searchTerm],
                ['type' => 'like', 'column' => 'event_id', 'pattern' => $searchTerm],
                ['type' => 'like', 'column' => 'vendor_sid', 'pattern' => $searchTerm],
                ['type' => 'like', 'column' => 'client_ip_masked', 'pattern' => $searchTerm],
            ]);
        }
    }

    /**
     * Parse date range filter.
     * 
     * @param mixed $dateRange String preset or array with start/end
     * @return array|null ['start' => '...', 'end' => '...']
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

        // Handle presets
        $now = time();
        $start = null;

        switch ($dateRange) {
            case 'last_1h':
                $start = $now - 3600;
                break;
            case 'last_24h':
                $start = $now - 86400;
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

    /**
     * Get WordPress roles for filter.
     * 
     * @return array
     */
    protected function getWpRoles()
    {
        if (!function_exists('wp_roles')) {
            return [];
        }

        $roles = wp_roles();
        $roleNames = [];

        foreach ($roles->roles as $slug => $role) {
            $roleNames[$slug] = $role['name'];
        }

        return $roleNames;
    }

    /**
     * Get countries for filter (abbreviated list).
     * 
     * @return array
     */
    protected function getCountries()
    {
        return [
            'US' => __('United States', 'wp-sms'),
            'GB' => __('United Kingdom', 'wp-sms'),
            'CA' => __('Canada', 'wp-sms'),
            'AU' => __('Australia', 'wp-sms'),
            'DE' => __('Germany', 'wp-sms'),
            'FR' => __('France', 'wp-sms'),
            'IT' => __('Italy', 'wp-sms'),
            'ES' => __('Spain', 'wp-sms'),
            'NL' => __('Netherlands', 'wp-sms'),
            'IN' => __('India', 'wp-sms'),
            'BR' => __('Brazil', 'wp-sms'),
            'MX' => __('Mexico', 'wp-sms'),
            'JP' => __('Japan', 'wp-sms'),
            'CN' => __('China', 'wp-sms'),
            'UNKNOWN' => __('Unknown', 'wp-sms'),
            // More countries can be added as needed
        ];
    }
}

