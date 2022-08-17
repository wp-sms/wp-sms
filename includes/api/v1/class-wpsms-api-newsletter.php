<?php

namespace WP_SMS\Api\V1;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_SMS\Option;
use WP_SMS\RestApi;

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

/**
 * @category   class
 * @package    WP_SMS_Api
 * @version    1.0
 *
 */
class Newsletter extends RestApi
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
        register_rest_route($this->namespace . '/v1', '/newsletter', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array($this, 'subscribe_callback'),
            'args'                => array(
                'name'     => array(
                    'required' => true,
                ),
                'mobile'   => array(
                    'required' => true,
                ),
                'group_id' => array(
                    'required' => false,
                ),
            ),
            'permission_callback' => '__return_true'
        ));

        register_rest_route($this->namespace . '/v1', '/newsletter/unsubscribe', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array($this, 'unsubscribe_callback'),
            'args'                => array(
                'name'   => array(
                    'required' => true,
                ),
                'mobile' => array(
                    'required' => true,
                ),
            ),
            'permission_callback' => '__return_true'
        ));

        register_rest_route($this->namespace . '/v1', '/newsletter/verify', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array($this, 'verify_subscriber_callback'),
            'args'                => array(
                'name'       => array(
                    'required' => true,
                ),
                'mobile'     => array(
                    'required' => true,
                ),
                'activation' => array(
                    'required' => true,
                ),
            ),
            'permission_callback' => '__return_true'
        ));
    }

    /**
     * @param WP_REST_Request $request
     *
     * @return WP_REST_Response
     */
    public function subscribe_callback(WP_REST_Request $request)
    {
        // Get parameters from request
        $params = $request->get_params();
        $number = self::convertNumber($params['mobile']);

        $group_id       = isset($params['group_id']) ? $params['group_id'] : false;
        $allowed_groups = Option::getOption('newsletter_form_specified_groups');

        if ($group_id && $allowed_groups && !in_array($group_id, $allowed_groups)) {
            return self::response(__('Not allowed.', 'wp-sms'), 400);
        }

        $result = self::subscribe($params['name'], $number, $group_id);

        if (is_wp_error($result)) {
            return self::response($result->get_error_message(), 400);
        }

        return self::response($result);
    }

    /**
     * @param WP_REST_Request $request
     *
     * @return WP_REST_Response
     */
    public function unsubscribe_callback(WP_REST_Request $request)
    {
        // Get parameters from request
        $params = $request->get_params();
        $number = self::convertNumber($params['mobile']);

        $group_id = isset($params['group_id']) ? $params['group_id'] : 0;
        $result   = self::unSubscribe($params['name'], $number, $group_id);

        if (is_wp_error($result)) {
            return self::response($result->get_error_message(), 400);
        }

        return self::response(__('Your mobile number has been successfully unsubscribed.', 'wp-sms'));
    }

    /**
     * @param WP_REST_Request $request
     *
     * @return WP_REST_Response
     */
    public function verify_subscriber_callback(WP_REST_Request $request)
    {
        // Get parameters from request
        $params = $request->get_params();
        $number = self::convertNumber($params['mobile']);

        $group_id = isset($params['group_id']) ? $params['group_id'] : 0;
        $result   = self::verifySubscriber($params['name'], $number, $params['activation'], $group_id);

        if (is_wp_error($result)) {
            return self::response($result->get_error_message(), 400);
        }

        return self::response(__('Your mobile number has been successfully subscribed.', 'wp-sms'));
    }
}

new Newsletter();
