<?php

namespace WP_SMS\Admin\Reports\Pages;

use WP_SMS\Admin\Reports\Abstracts\AbstractReportPage;
use WP_SMS\Admin\Reports\Widgets\HealthSnapshotWidget;
use WP_SMS\Admin\Reports\Widgets\JourneyFunnelsWidget;
use WP_SMS\Admin\Reports\Widgets\VolumeOverTimeWidget;
use WP_SMS\Admin\Reports\Widgets\MethodMixWidget;
use WP_SMS\Admin\Reports\Widgets\DeliveryQualityWidget;
use WP_SMS\Admin\Reports\Widgets\GeoHeatmapWidget;
use WP_SMS\Components\Countries;

/**
 * ActivityOverviewReportPage - Main analytics/activity report page.
 * 
 * Displays comprehensive dashboard with KPIs, charts, and visualizations.
 */
class ActivityOverviewReportPage extends AbstractReportPage
{
    /**
     * Widget instances.
     * 
     * @var array
     */
    protected $widgetInstances = [];

    /**
     * Constructor.
     */
    public function __construct()
    {
        // Initialize widget instances
        $this->widgetInstances = [
            'health_snapshot' => new HealthSnapshotWidget(),
            'journey_funnels' => new JourneyFunnelsWidget(),
            'volume_over_time' => new VolumeOverTimeWidget(),
            'method_mix' => new MethodMixWidget(),
            'delivery_quality' => new DeliveryQualityWidget(),
            'geo_heatmap' => new GeoHeatmapWidget(),
        ];
    }

    /**
     * @inheritDoc
     */
    public function getSlug()
    {
        return 'activity-overview';
    }

    /**
     * @inheritDoc
     */
    public function getLabel()
    {
        return __('Activity Overview', 'wp-sms');
    }

    /**
     * @inheritDoc
     */
    public function getDescription()
    {
        return __('Comprehensive analytics dashboard for authentication and 2FA activity', 'wp-sms');
    }

    /**
     * @inheritDoc
     */
    public function getFilters()
    {
        return [
            [
                'key' => 'date_range',
                'label' => __('Date Range', 'wp-sms'),
                'type' => 'date-range',
                'default' => 'last_7d',
                'presets' => [
                    'today' => __('Today', 'wp-sms'),
                    'last_7d' => __('Last 7 Days', 'wp-sms'),
                    'last_30d' => __('Last 30 Days', 'wp-sms'),
                    'custom' => __('Custom Range', 'wp-sms'),
                ],
            ],
            [
                'key' => 'flow_type',
                'label' => __('Flow Type', 'wp-sms'),
                'type' => 'radio',
                'options' => [
                    'all' => __('Both', 'wp-sms'),
                    'login' => __('Log-in', 'wp-sms'),
                    'registration' => __('Registration', 'wp-sms'),
                ],
                'default' => 'login',
            ],
            [
                'key' => 'channel',
                'label' => __('Auth Channel', 'wp-sms'),
                'type' => 'multi-select',
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
                'type' => 'select',
                'options' => [
                    'all' => __('All Methods', 'wp-sms'),
                    'sms_otp' => __('SMS OTP', 'wp-sms'),
                    'email_otp' => __('Email OTP', 'wp-sms'),
                    'totp' => __('TOTP', 'wp-sms'),
                ],
                'default' => 'all',
            ],
            [
                'key' => 'country',
                'label' => __('Country', 'wp-sms'),
                'type' => 'select',
                'searchable' => true,
                'options' => $this->getCountries(),
            ],
            [
                'key' => 'wp_role',
                'label' => __('WP Role', 'wp-sms'),
                'type' => 'multi-select',
                'options' => $this->getWpRoles(),
            ],
        ];
    }

    /**
     * @inheritDoc
     */
    public function getWidgets()
    {
        return [
            [
                'id' => 'health_snapshot',
                'type' => 'kpi',
                'label' => __('Health Snapshot', 'wp-sms'),
                'layout' => ['row' => 1, 'col' => 1, 'span' => 12],
            ],
            [
                'id' => 'journey_funnels',
                'type' => 'funnel',
                'label' => __('Journey Funnels', 'wp-sms'),
                'layout' => ['row' => 2, 'col' => 1, 'span' => 12],
            ],
            [
                'id' => 'volume_over_time',
                'type' => 'chart',
                'label' => __('Volume Over Time', 'wp-sms'),
                'layout' => ['row' => 3, 'col' => 1, 'span' => 12],
            ],
            [
                'id' => 'method_mix',
                'type' => 'chart',
                'label' => __('Method Mix', 'wp-sms'),
                'layout' => ['row' => 4, 'col' => 1, 'span' => 6],
            ],
            [
                'id' => 'delivery_quality',
                'type' => 'chart',
                'label' => __('Delivery Quality', 'wp-sms'),
                'layout' => ['row' => 4, 'col' => 7, 'span' => 6],
            ],
            [
                'id' => 'geo_heatmap',
                'type' => 'map',
                'label' => __('Geographic Distribution', 'wp-sms'),
                'layout' => ['row' => 5, 'col' => 1, 'span' => 12],
            ],
        ];
    }

    /**
     * @inheritDoc
     */
    public function getWidgetData($filters)
    {
        $data = [];

        foreach ($this->widgetInstances as $id => $widget) {
            $data[$id] = $widget->getData($filters);
        }

        return $data;
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
     * Get countries for filter.
     * 
     * @return array
     */
    protected function getCountries()
    {
        return wp_sms_countries()->getCountryNamesByCode();
    }
}

