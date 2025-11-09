<?php

namespace WP_SMS\Services\OTP\RestAPIEndpoints\Login;

use WP_REST_Server;
use WP_REST_Response;
use WP_REST_Request;
use WP_Error;
use WP_SMS\Services\OTP\RestAPIEndpoints\Abstracts\RestAPIEndpointsAbstract;
use WP_SMS\Services\OTP\Helpers\ChannelSettingsHelper;

class LoginInitApiEndpoints extends RestAPIEndpointsAbstract
{
    /**
     * Register REST API routes for login endpoints
     */
    public function registerRoutes(): void
    {
        register_rest_route('wpsms/v1', '/login/init', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'handleRequest'],
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * Initialize login endpoint - returns channel settings and policies
     */
    public function handleRequest(WP_REST_Request $request)
    {
        try {
            // Get all channel settings for login
            $channelSettings = ChannelSettingsHelper::getAllChannelSettings();
            
            // Return the channel settings response
            return new WP_REST_Response($channelSettings, 200);
            
        } catch (\Exception $e) {
            error_log('[WP-SMS] Error in login init: ' . $e->getMessage());
            
            return new WP_REST_Response([
                'error' => 'Internal server error',
                'message' => 'Unable to retrieve channel settings'
            ], 500);
        }
    }
}

