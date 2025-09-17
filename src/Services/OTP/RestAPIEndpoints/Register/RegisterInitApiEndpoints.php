<?php

namespace WP_SMS\Services\OTP\RestAPIEndpoints\Register;

use WP_REST_Server;
use WP_REST_Response;
use WP_REST_Request;
use WP_Error;
use WP_SMS\Services\OTP\RestAPIEndpoints\Abstracts\RestAPIEndpointsAbstract;
use WP_SMS\Services\OTP\Helpers\ChannelSettingsHelper;

class RegisterInitApiEndpoints extends RestAPIEndpointsAbstract
{

    /**
     * Register REST API routes for OTP endpoints.
     */
    public function registerRoutes(): void
    {
        // Register init endpoint
        register_rest_route('wpsms/v1', '/register/init', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'initRegister'],
            'permission_callback' => '__return_true',
        ]);
        
    }

    /**
     * init registration endpoint - returns channel settings and policies
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function initRegister(WP_REST_Request $request)
    {
        try {
            // Get all channel settings using the helper class
            $channelSettings = ChannelSettingsHelper::getAllChannelSettings();
            
            // Return the channel settings response
            return new WP_REST_Response($channelSettings, 200);
            
        } catch (\Exception $e) {
            // Log the error for debugging
            error_log('[WP-SMS] Error in initRegister: ' . $e->getMessage());
            
            // Return a generic error response
            return new WP_REST_Response([
                'error' => 'Internal server error',
                'message' => 'Unable to retrieve channel settings'
            ], 500);
        }
    }

}
