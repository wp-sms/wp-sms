<?php

namespace WP_SMS\RestEndpoints\Endpoints\V1\Logs;

use WP_SMS\RestEndpoints\Abstracts\AbstractSettingsEndpoint;
use WP_SMS\Admin\Logs\LogsPageProvider;
use WP_REST_Request;
use WP_REST_Response;

/**
 * LogsDataEndpoint - Get paginated log data.
 * 
 * Route: GET /wpsms/v1/logs/:slug/data
 */
class LogsDataEndpoint extends AbstractSettingsEndpoint
{
    /**
     * @inheritDoc
     */
    public static function register()
    {
        register_rest_route('wpsms/v1', '/logs/(?P<slug>[a-zA-Z0-9_-]+)/data', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'handle'],
            'permission_callback' => [__CLASS__, 'permissions_check'],
            'args' => [
                'slug' => [
                    'required' => true,
                    'type' => 'string',
                    'description' => __('Log page slug', 'wp-sms'),
                ],
                'page' => [
                    'type' => 'integer',
                    'default' => 1,
                    'minimum' => 1,
                    'description' => __('Page number', 'wp-sms'),
                ],
                'perPage' => [
                    'type' => 'integer',
                    'default' => 50,
                    'minimum' => 1,
                    'maximum' => 1000,
                    'description' => __('Items per page', 'wp-sms'),
                ],
                'filters' => [
                    'type' => 'string',
                    'default' => '{}',
                    'description' => __('JSON-encoded filters', 'wp-sms'),
                ],
                'sorts' => [
                    'type' => 'string',
                    'default' => '[]',
                    'description' => __('JSON-encoded sort configuration', 'wp-sms'),
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
        $page = $request->get_param('page');
        $perPage = $request->get_param('perPage');
        $filtersJson = $request->get_param('filters');
        $sortsJson = $request->get_param('sorts');

        // Parse JSON parameters
        $filters = json_decode($filtersJson, true);
        if (!is_array($filters)) {
            $filters = [];
        }

        $sorts = json_decode($sortsJson, true);
        if (!is_array($sorts)) {
            $sorts = [];
        }

        $provider = LogsPageProvider::instance();
        $logPage = $provider->getPage($slug);

        if (!$logPage) {
            return self::error(
                sprintf(__('Log page "%s" not found', 'wp-sms'), $slug),
                404
            );
        }

        if (!$logPage->canView()) {
            return self::error(
                __('You do not have permission to view this log', 'wp-sms'),
                403
            );
        }

        try {
            $data = $logPage->getData($filters, $sorts, $page, $perPage);
            return self::success($data);
        } catch (\Exception $e) {
            return self::error(
                sprintf(__('Error fetching log data: %s', 'wp-sms'), $e->getMessage()),
                500
            );
        }
    }
}

