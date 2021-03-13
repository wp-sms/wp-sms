<?php

namespace WP_SMS\Api\V1;

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

/**
 * @category   class
 * @package    WP_SMS_Api
 * @version    1.0
 */
class Send extends \WP_SMS\RestApi
{

    public function __construct()
    {
        // Register routes
        add_action('rest_api_init', array($this, 'register_routes'));

        parent::__construct();
    }

    /**
     * Register routes
     */
    public function register_routes()
    {

        // SMS Newsletter
        register_rest_route($this->namespace . '/v1', '/send', array(
            array(
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => array($this, 'send_callback'),
                'args'                => array(
                    'to'      => array(
                        'required' => true,
                    ),
                    'msg'     => array(
                        'required' => true,
                    ),
                    'isflash' => array(
                        'required' => false,
                    )
                ),
                'permission_callback' => array($this, 'get_item_permissions_check'),
            )
        ));
    }

    /**
     * @param WP_REST_Request $request
     *
     * @return \WP_REST_Response
     */
    public function send_callback(\WP_REST_Request $request)
    {
        // Get parameters from request
        $params = $request->get_params();

        $to      = isset ($params['to']) ? $params['to'] : '';
        $msg     = isset ($params['msg']) ? $params['msg'] : '';
        $isflash = isset ($params['isflash']) ? $params['isflash'] : '';
        $result  = self::sendSMS($to, $msg, $isflash);

        if (is_wp_error($result)) {
            return self::response($result->get_error_message(), 400);
        }

        return self::response($result);
    }

    /**
     * Check user permission
     *
     * @param $request
     *
     * @return bool
     */
    public function get_item_permissions_check($request)
    {
        return current_user_can('wpsms_sendsms');
    }
}

new Send();