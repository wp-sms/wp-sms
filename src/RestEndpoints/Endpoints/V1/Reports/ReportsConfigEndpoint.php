<?php

namespace WP_SMS\RestEndpoints\Endpoints\V1\Reports;

use WP_SMS\RestEndpoints\Abstracts\AbstractSettingsEndpoint;
use WP_SMS\Admin\Reports\ReportsPageProvider;
use WP_REST_Request;
use WP_REST_Response;

/**
 * ReportsConfigEndpoint - Get configuration for a specific report page.
 * 
 * Route: GET /wpsms/v1/reports/:slug/config
 */
class ReportsConfigEndpoint extends AbstractSettingsEndpoint
{
    /**
     * @inheritDoc
     */
    public static function register()
    {
        register_rest_route('wpsms/v1', '/reports/(?P<slug>[a-zA-Z0-9_-]+)/config', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'handle'],
            'permission_callback' => [__CLASS__, 'permissions_check'],
            'args' => [
                'slug' => [
                    'required' => true,
                    'type' => 'string',
                    'description' => __('Report page slug', 'wp-sms'),
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
        $provider = ReportsPageProvider::instance();

        $page = $provider->getPage($slug);

        if (!$page) {
            return self::error(
                sprintf(__('Report page "%s" not found', 'wp-sms'), $slug),
                404
            );
        }

        if (!$page->canView()) {
            return self::error(
                __('You do not have permission to view this report', 'wp-sms'),
                403
            );
        }

        return self::success([
            'slug' => $page->getSlug(),
            'label' => $page->getLabel(),
            'description' => $page->getDescription(),
            'filters' => $page->getFilters(),
            'widgets' => $page->getWidgets(),
        ]);
    }
}

