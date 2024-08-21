<?php

namespace WP_SMS\Api\V1;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_SMS\Helper;
use WP_SMS\Option;
use WP_SMS\RestApi;
use WP_SMS\Services\Subscriber\SubscriberUtil;

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
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array($this, 'subscribe_callback'),
                'args'                => array(
                    'name'          => array(
                        'required' => true,
                    ),
                    'mobile'        => array(
                        'required' => true,
                    ),
                    'group_id'      => array(
                        'required'          => false,
                        'type'              => array('integer', 'array'),
                        'validate_callback' => array($this, 'validate_group_callback')
                    ),
                    'custom_fields' => array(
                        'required' => false
                    )
                ),
                'permission_callback' => function () {
                    return current_user_can('wpsms_subscribers');
                },
            ),
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array($this, 'get_subscribers_callback'),
                'args'                => array(
                    'group_id' => array(
                        'required' => false,
                    )
                ),
                'permission_callback' => function () {
                    return current_user_can('wpsms_subscribers');
                },
            )
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
                )
            ),
            'permission_callback' => function () {
                return current_user_can('wpsms_subscribers');
            },
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
                )
            ),
            'permission_callback' => function () {
                return current_user_can('wpsms_subscribers');
            },
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
        $params         = $request->get_params();
        $number         = $params['mobile'];
        $customFields   = $request->get_param('custom_fields');
        $group_id       = $request->get_param('group_id');
        $groups_enabled = Option::getOption('newsletter_form_groups');

        //  If admin enabled groups and user did not select any group, then return error
        if ($groups_enabled && !$group_id) {
            return self::response(esc_html__('Please select a specific group.', 'wp-sms'), 400);
        }

        $result = SubscriberUtil::subscribe($params['name'], $number, $group_id, $customFields);

        if (is_wp_error($result)) {
            return self::response($result->get_error_message(), 400);
        }

        return self::response($result);
    }

    /**
     * Validates group_id parameter
     */
    public function validate_group_callback($group_id)
    {
        $groupIds = is_array($group_id) ? $group_id : array($group_id);

        foreach ($groupIds as $groupId) {
            if (!\WP_SMS\Newsletter::getGroup($groupId)) {
                // translators: %s: Group ID
                return new \WP_Error('subscribe', sprintf(esc_html__('Group ID #%s is not valid', 'wp-sms'), $groupId));
            }
        }

        return true;
    }

    /**
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function get_subscribers_callback(WP_REST_Request $request)
    {
        return \WP_SMS\Newsletter::getSubscribers(false, false, [
            'name',
            'mobile'
        ]);
    }

    /**
     * @param WP_REST_Request $request
     *
     * @return WP_REST_Response
     */
    public function unsubscribe_callback(WP_REST_Request $request)
    {
        // Get parameters from request
        $params         = $request->get_params();
        $number         = Helper::convertNumber($params['mobile']);
        $group_id       = isset($params['group_id']) ? $params['group_id'] : 0;
        $groups_enabled = Option::getOption('newsletter_form_groups');

        //  If admin enabled groups and user did not select any group, then return error
        if ($groups_enabled && !$group_id) {
            return self::response(esc_html__('Please select a specific group.', 'wp-sms'), 400);
        }

        $groupIds = is_array($group_id) ? $group_id : array($group_id);

        foreach ($groupIds as $groupId) {
            $result = SubscriberUtil::unSubscribe($params['name'], $number, $groupId);

            if (is_wp_error($result)) {
                return self::response($result->get_error_message(), 400);
            }
        }

        return self::response(esc_html__('Your mobile number has been successfully unsubscribed.', 'wp-sms'));
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
        $number = Helper::convertNumber($params['mobile']);

        $group_id = isset($params['group_id']) ? $params['group_id'] : 0;
        $groupIds = is_array($group_id) ? $group_id : array($group_id);

        foreach ($groupIds as $groupId) {

            // Remove additional space and make compatible with auto-fill
            $activation = trim($params['activation']);

            // Add subscribe to database
            $result = SubscriberUtil::verifySubscriber($params['name'], $number, $activation, $groupId);

            if (is_wp_error($result)) {
                return self::response($result->get_error_message(), 400);
            }
        }

        return self::response(esc_html__('Your mobile number has been successfully subscribed.', 'wp-sms'));
    }
}

new Newsletter();
