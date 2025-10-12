<?php

namespace WP_SMS\RestEndpoints;

use WP_SMS\RestEndpoints\Endpoints\V1\Settings\GetSchemaEndpoint;
use WP_SMS\RestEndpoints\Endpoints\V1\Settings\GetSettingsEndpoint;
use WP_SMS\RestEndpoints\Endpoints\V1\Settings\SaveSettingsEndpoint;
use WP_SMS\RestEndpoints\Endpoints\V1\Gateways\GetGatewayFieldsEndpoint;

// Logs endpoints
use WP_SMS\RestEndpoints\Endpoints\V1\Logs\LogsIndexEndpoint;
use WP_SMS\RestEndpoints\Endpoints\V1\Logs\LogsConfigEndpoint;
use WP_SMS\RestEndpoints\Endpoints\V1\Logs\LogsDataEndpoint;
use WP_SMS\RestEndpoints\Endpoints\V1\Logs\LogsRowEndpoint;

// Reports endpoints
use WP_SMS\RestEndpoints\Endpoints\V1\Reports\ReportsIndexEndpoint;
use WP_SMS\RestEndpoints\Endpoints\V1\Reports\ReportsConfigEndpoint;
use WP_SMS\RestEndpoints\Endpoints\V1\Reports\ReportsDataEndpoint;

// Providers
use WP_SMS\Admin\Logs\LogsPageProvider;
use WP_SMS\Admin\Logs\Pages\AuthenticationEventLogPage;
use WP_SMS\Admin\Reports\ReportsPageProvider;
use WP_SMS\Admin\Reports\Pages\ActivityOverviewReportPage;

/**
 * Manages registration of all WP-SMS REST API endpoints.
 */
class RestEndpointManager
{
    /**
     * Register all REST endpoints on rest_api_init.
     */
    public function init()
    {
        add_action('rest_api_init', function () {
            // Settings API v1
            GetSchemaEndpoint::register();
            GetSettingsEndpoint::register();
            SaveSettingsEndpoint::register();
            
            // Gateways API v1
            GetGatewayFieldsEndpoint::register();

            // Logs API v1
            LogsIndexEndpoint::register();
            LogsConfigEndpoint::register();
            LogsDataEndpoint::register();
            LogsRowEndpoint::register();

            // Reports API v1
            ReportsIndexEndpoint::register();
            ReportsConfigEndpoint::register();
            ReportsDataEndpoint::register();
        });
    }
}
