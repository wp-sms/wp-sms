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
class Subscribers extends \WP_SMS\RestApi
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
        register_rest_route($this->namespace . '/v1', '/subscribers', array(
            array(
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => array($this, 'subscribers_callback'),
                'args'                => array(
                    'page'     => array(
                        'required' => false,
                    ),
                    'group_id' => array(
                        'required' => false,
                    ),
                    'number'   => array(
                        'required' => false,
                    ),
                    'search'   => array(
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
    public function subscribers_callback(\WP_REST_Request $request)
    {
        // Get parameters from request
        $params = $request->get_params();

        $page     = isset ($params['page']) ? $params['page'] : '';
        $group_id = isset ($params['group_id']) ? $params['group_id'] : '';
        $mobile   = isset ($params['mobile']) ? $params['mobile'] : '';
        $search   = isset ($params['search']) ? $params['search'] : '';
        $result   = self::getSubscribers($page, $group_id, $mobile, $search);

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
        return current_user_can('wpsms_subscribers');
    }
}

new Subscribers();