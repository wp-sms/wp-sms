<?php

namespace WP_SMS\RestEndpoints\Endpoints\V1\Reports;

use WP_SMS\RestEndpoints\Abstracts\AbstractSettingsEndpoint;
use WP_SMS\Admin\Reports\ReportsPageProvider;
use WP_REST_Request;
use WP_REST_Response;

/**
 * ReportsIndexEndpoint - List all available report pages.
 * 
 * Route: GET /wpsms/v1/reports
 */
class ReportsIndexEndpoint extends AbstractSettingsEndpoint
{
    /**
     * @inheritDoc
     */
    public static function register()
    {
        register_rest_route('wpsms/v1', '/reports', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'handle'],
            'permission_callback' => [__CLASS__, 'permissions_check'],
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
        $provider = ReportsPageProvider::instance();
        $pages = $provider->getPagesList();

        return self::success($pages);
    }
}

