<?php

namespace WP_SMS\RestEndpoints\Endpoints\V1\Logs;

use WP_SMS\RestEndpoints\Abstracts\AbstractSettingsEndpoint;
use WP_SMS\Admin\Logs\LogsPageProvider;
use WP_REST_Request;
use WP_REST_Response;

/**
 * LogsRowEndpoint - Get single row details for side drawer.
 * 
 * Route: GET /wpsms/v1/logs/:slug/row/:id
 */
class LogsRowEndpoint extends AbstractSettingsEndpoint
{
    /**
     * @inheritDoc
     */
    public static function register()
    {
        register_rest_route('wpsms/v1', '/logs/(?P<slug>[a-zA-Z0-9_-]+)/row/(?P<id>[0-9]+)', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'handle'],
            'permission_callback' => [__CLASS__, 'permissions_check'],
            'args' => [
                'slug' => [
                    'required' => true,
                    'type' => 'string',
                    'description' => __('Log page slug', 'wp-sms'),
                ],
                'id' => [
                    'required' => true,
                    'type' => 'integer',
                    'description' => __('Row ID', 'wp-sms'),
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
        $id = $request->get_param('id');

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
            $row = $logPage->getRow($id);

            if (!$row) {
                return self::error(
                    sprintf(__('Row with ID %d not found', 'wp-sms'), $id),
                    404
                );
            }

            return self::success($row);
        } catch (\Exception $e) {
            return self::error(
                sprintf(__('Error fetching row data: %s', 'wp-sms'), $e->getMessage()),
                500
            );
        }
    }
}

