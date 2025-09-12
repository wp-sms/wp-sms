<?php

namespace WP_SMS\Services\OTP\RestAPIEndpoints\Register;

use WP_REST_Server;
use WP_REST_Response;
use WP_REST_Request;
use WP_Error;
use WP_SMS\Services\OTP\AuthChannel\OTP\OtpService;
use WP_SMS\Services\OTP\Models\OtpSessionModel;
use WP_SMS\Services\OTP\Models\AuthEventModel;
use WP_SMS\Services\OTP\Security\RateLimiter;
use WP_SMS\Services\OTP\Helpers\ChannelSettingsHelper;
use WP_SMS\Utils\DateUtils;

class RegisterApiEndpoints
{
    /** @var OtpService */
    protected $otpService;
    /** @var RateLimiter */
    protected $rateLimiter;

    public function __construct()
    {
        $this->otpService = new OtpService();
        $this->rateLimiter = new RateLimiter();
    }

     /**
     * Initialize the service
     */
    public function init()
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    /**
     * Register REST API routes for OTP endpoints.
     */
    public function registerRoutes()
    {
        register_rest_route('wpsms/v1', '/register/init', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'startRegister'],
            'permission_callback' => '__return_true',
        ]);

        
    }

    /**
     * Start registration endpoint - returns channel settings and policies
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function startRegister(WP_REST_Request $request)
    {
        try {
            // Get all channel settings using the helper class
            $channelSettings = ChannelSettingsHelper::getAllChannelSettings();
            
            // Return the channel settings response
            return new WP_REST_Response($channelSettings, 200);
            
        } catch (\Exception $e) {
            // Log the error for debugging
            error_log('[WP-SMS] Error in startRegister: ' . $e->getMessage());
            
            // Return a generic error response
            return new WP_REST_Response([
                'error' => 'Internal server error',
                'message' => 'Unable to retrieve channel settings'
            ], 500);
        }
    }

}
