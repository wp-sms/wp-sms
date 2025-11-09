<?php

namespace WP_SMS\RestEndpoints\Endpoints\V1\Reports;

use WP_SMS\RestEndpoints\Abstracts\AbstractSettingsEndpoint;
use WP_SMS\Admin\Reports\ReportsPageProvider;
use WP_REST_Request;
use WP_REST_Response;

/**
 * ReportsDataEndpoint - Get widget data for a report page.
 * 
 * Route: GET /wpsms/v1/reports/:slug/data
 */
class ReportsDataEndpoint extends AbstractSettingsEndpoint
{
    /**
     * @inheritDoc
     */
    public static function register()
    {
        register_rest_route('wpsms/v1', '/reports/(?P<slug>[a-zA-Z0-9_-]+)/data', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'handle'],
            'permission_callback' => [__CLASS__, 'permissions_check'],
            'args' => [
                'slug' => [
                    'required' => true,
                    'type' => 'string',
                    'description' => __('Report page slug', 'wp-sms'),
                ],
                'filters' => [
                    'type' => 'string',
                    'default' => '{}',
                    'description' => __('JSON-encoded filters', 'wp-sms'),
                ],
            ],
        ]);
    }

    /**
     * Handle GET request.
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public static function handle(WP_REST_Request $request)
    {
        $slug = $request->get_param('slug');
        $filtersJson = $request->get_param('filters');

        // Parse JSON parameters
        $filters = json_decode($filtersJson, true);
        if (!is_array($filters)) {
            $filters = [];
        }

        $provider = ReportsPageProvider::instance();
        $reportPage = $provider->getPage($slug);

        if (!$reportPage) {
            return self::error(
                sprintf(__('Report page "%s" not found', 'wp-sms'), $slug),
                404
            );
        }

        if (!$reportPage->canView()) {
            return self::error(
                __('You do not have permission to view this report', 'wp-sms'),
                403
            );
        }

        try {
            $data = $reportPage->getWidgetData($filters);
            return self::success($data);
        } catch (\Exception $e) {
            return self::error(
                sprintf(__('Error fetching report data: %s', 'wp-sms'), $e->getMessage()),
                500
            );
        }
    }
}

