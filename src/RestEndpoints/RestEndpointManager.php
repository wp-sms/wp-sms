<?php

namespace WP_SMS\RestEndpoints;

use WP_SMS\RestEndpoints\Endpoints\V1\Settings\GetSchemaEndpoint;
use WP_SMS\RestEndpoints\Endpoints\V1\Settings\GetSettingsEndpoint;
use WP_SMS\RestEndpoints\Endpoints\V1\Settings\SaveSettingsEndpoint;
use WP_SMS\RestEndpoints\Endpoints\V1\Gateways\GetGatewayFieldsEndpoint;

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

            // Future: OTP, Import, Logs, etc. can be registered here too
        });
    }
}
