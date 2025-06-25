<?php

namespace WP_SMS\RestEndpoints;

use WP_SMS\RestEndpoints\Endpoints\V1\Settings\GetSchemaEndpoint;
use WP_SMS\RestEndpoints\Endpoints\V1\Settings\GetSettingsEndpoint;
use WP_SMS\RestEndpoints\Endpoints\V1\Settings\SaveSettingsEndpoint;

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

            // Future: OTP, Import, Logs, etc. can be registered here too
        });
    }
}
