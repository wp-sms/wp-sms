<?php

namespace WP_SMS\Api\V1;

use Exception;
use WP_REST_Request;
use DateTime;
use DateInterval;
use WP_SMS\Gateway;
use WP_SMS\Helper;
use WP_SMS\Newsletter;
use WP_SMS\Notification\NotificationFactory;
use WP_SMS\Pro\Scheduled;
use WP_SMS\Pro\RepeatingMessages;

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

/**
 * @category   class
 * @package    WP_SMS_Api
 * @version    1.0
 */
class SendSmsApi extends \WP_SMS\RestApi
{
    private $sendSmsArguments = [
        'sender'               => array('required' => true, 'type' => 'string'),
        'recipients'           => array('required' => true, 'type' => 'string', 'enum' => ['subscribers', 'users', 'roles', 'wc-customers', 'bp-users', 'numbers']),
        'group_ids'            => array('required' => false, 'type' => 'array'),
        'role_ids'             => array('required' => false, 'type' => 'array'),
        'numbers'              => array('required' => false, 'type' => 'array', 'format' => 'uri'),
        'message'              => array('required' => true, 'type' => 'string'),
        'flash'                => array('required' => false, 'type' => 'boolean'),
        'media_urls'           => array('required' => false, 'type' => 'array'),
        'schedule'             => array('required' => false, 'type' => 'string', 'format' => 'date-time'),
        'repeat'               => array('required' => false, 'type' => 'object'),
        'notification_handler' => array('required' => false, 'type' => 'string', 'enum' => ['WooCommerceOrderNotification', 'WooCommerceAdminOrderNotification', 'WooCommerceProductNotification', 'WooCommerceCouponNotification', 'WooCommerceCustomerNotification', 'WordPressPostNotification', 'WordPressUserNotification', 'AwesomeSupportTicketNotification', 'WordPressCommentNotification', 'SubscriberNotification']),
        'handler_id'           => array('required' => false, 'type' => 'int'),
    ];

    public function __construct()
    {
        // Register routes
        add_action('rest_api_init', array($this, 'register_routes'));

        $this->sendSmsArguments = apply_filters('wp_sms_api_send_sms_arguments', $this->sendSmsArguments);

        parent::__construct();
    }

    /**
     * Register routes
     */
    public function register_routes()
    {
        register_rest_route($this->namespace . '/v1', '/send', array(
            array(
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => array($this, 'sendSmsCallback'),
                'args'                => $this->sendSmsArguments,
                'permission_callback' => function () {
                    return current_user_can('wpsms_sendsms');
                },
            )
        ));

        // @todo, this can be moved to a separate class
        register_rest_route($this->namespace . '/v1', '/outbox', array(
            array(
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => array($this, 'getOutboxCallback'),
                'permission_callback' => function () {
                    return current_user_can('wpsms_outbox');
                },
            )
        ));
    }

    /**
     * @param WP_REST_Request $request
     *
     * @return \WP_REST_Response
     */
    public function sendSmsCallback(WP_REST_Request $request)
    {
        try {
            $recipientNumbers = $this->getRecipientsFromRequest($request);
            $mediaUrls        = [];

            if ($request->get_param('media_urls') and is_array($request->get_param('media_urls'))) {
                $mediaUrls = array_filter($request->get_param('media_urls'));
            }

            if (count($recipientNumbers) === 0) {
                throw new Exception(esc_html__('Could not find any mobile numbers.', 'wp-sms'));
            }

            if (!$request->get_param('message')) {
                throw new Exception(esc_html__('The message body can not be empty.', 'wp-sms'));
            }

            /**
             * Make shorter the URLs in the message
             */
            $message = Helper::makeUrlsShorter($request->get_param('message'));

            /**
             * Filter to modify message
             */
            $message = apply_filters('wp_sms_api_message_content', $message, $request->get_params());

            /*
             * Repeating SMS
             */
            if ($request->has_param('schedule') && $request->has_param('repeat')) {
                $data      = $request->get_param('repeat');
                $startDate = new DateTime(get_gmt_from_date($request->get_param('schedule')));
                $endDate   = isset($data['endDate']) ? (new DateTime(get_gmt_from_date($data['endDate']))) : null;
                $interval  = $data['interval'];

                if ($startDate->getTimestamp() < time()) {
                    return self::response(esc_html__('Selected start date must be in future', 'wp-sms'), 400);
                }

                if (isset($endDate) && $endDate->getTimestamp() < $startDate->getTimestamp()) {
                    return self::response(esc_html__('Selected end date must be after start date', 'wp-sms'), 400);
                }

                RepeatingMessages::add(
                    $startDate,
                    $endDate,
                    $interval['value'],
                    $interval['unit'],
                    $request->get_param('sender'),
                    $message,
                    $recipientNumbers,
                    $mediaUrls
                );

                return self::response(esc_html__('Repeating SMS is scheduled successfully!', 'wp-sms'));
            }

            /**
             * Scheduled SMS
             */
            if ($request->has_param('schedule')) {
                if ((new DateTime(get_gmt_from_date($request->get_param('schedule'))))->getTimestamp() < time()) {
                    return self::response(esc_html__('Selected start date must be in future', 'wp-sms'), 400);
                }

                Scheduled::add(
                    $request->get_param('schedule'),
                    $request->get_param('sender'),
                    $message,
                    $recipientNumbers,
                    true,
                    $mediaUrls
                );
                return self::response('SMS Scheduled Successfully!');
            }

            /*
             * Regular SMS
             */
            $notificationHandler   = $request->get_param('notification_handler');
            $notificationHandlerId = $request->get_param('handler_id');

            $notification = NotificationFactory::getHandler($notificationHandler, $notificationHandlerId);
            $response     = $notification->send(
                $message,
                $recipientNumbers,
                $mediaUrls,
                $request->get_param('flash'),
                $request->get_param('sender')
            );

            if (is_wp_error($response)) {
                throw new Exception($response->get_error_message());
            }

            $response = apply_filters('wp_sms_send_sms_response', esc_html__('Successfully send SMS!', 'wp-sms'));
            return self::response($response, 200, [
                'balance' => Gateway::credit()
            ]);
        } catch (\Throwable $e) {
            return self::response($e->getMessage(), 400);
        }
    }

    /**
     * @throws Exception
     */
    private function getRecipientsFromRequest(WP_REST_Request $request)
    {
        $recipients = [];

        switch ($request->get_param('recipients')) {
            /**
             * Subscribers
             */
            case 'subscribers':

                $group_ids = $request->get_param('group_ids');
                $groups    = Newsletter::getGroups();

                // Check there is group or not
                if ($groups) {
                    if (!$request->get_param('group_ids')) {
                        throw new Exception(esc_html__('Parameter group_ids is required', 'wp-sms'));
                    }

                    // Check group validity
                    foreach ($group_ids as $group_id) {
                        if (!Newsletter::getGroup($group_id)) {
                            // translators: %s: Group ID
                            $group_validity_error[] = sprintf(esc_html__('The group ID %s is not valid', 'wp-sms'), $group_id);
                        }
                    }

                    if (isset($group_validity_error) && !empty($group_validity_error)) {
                        throw new Exception(esc_html($group_validity_error));
                    }
                }

                $recipients = Newsletter::getSubscribers($group_ids, true);
                break;

            /**
             * Roles
             */
            case 'roles':

                if (!$request->get_param('role_ids')) {
                    throw new Exception(esc_html__('Parameter role_ids is required', 'wp-sms'));
                }

                $recipients = array();
                $roleIds    = $request->get_param('role_ids');

                foreach ($roleIds as $roleId) {
                    $mobileNumbers = Helper::getUsersMobileNumbers(array($roleId));
                    $recipients    = array_merge($recipients, $mobileNumbers);
                }

                break;

            /**
             * Users
             */
            case 'users':

                if (!$request->get_param('users')) {
                    throw new Exception(esc_html__('Parameter users is required', 'wp-sms'));
                }

                $recipients = Helper::getUsersMobileNumbers(false, $request->get_param('users'));
                break;

            /**
             * WooCommerce customers
             */
            case 'wc-customers':

                if (class_exists('woocommerce')) {
                    $recipients = \WP_SMS\Helper::getWooCommerceCustomersNumbers();
                } else {
                    throw new Exception(esc_html__('WooCommerce or WP-SMS Pro is not enabled', 'wp-sms-pro'));
                }

                break;

            /**
             * BuddyPress users
             */
            case 'bp-users':

                if (class_exists('BuddyPress') and class_exists('WP_SMS\Pro\BuddyPress')) {
                    $recipients = \WP_SMS\Pro\BuddyPress::getTotalMobileNumbers();
                } else {
                    throw new Exception(esc_html__('BuddyPress or WP SMS Pro is not enabled', 'wp-sms-pro'));
                }

                break;

            /**
             * Numbers
             */
            case 'numbers':

                if (!$request->get_param('numbers')) {
                    throw new Exception(esc_html__('Parameter numbers is required', 'wp-sms'));
                }

                $recipients = $request->get_param('numbers');
                break;
        }

        // Remove duplicate numbers.
        $recipients = array_unique($recipients);

        return apply_filters('wp_sms_api_recipients_numbers', $recipients, $request->get_param('recipients'), $request);
    }

    /**
     * @param WP_REST_Request $request
     * @return array|object|\stdClass[]|null
     * @todo support pagination and filter
     */
    public function getOutboxCallback(WP_REST_Request $request)
    {
        $query = "SELECT * FROM `{$this->tb_prefix}sms_send`";

        return $this->db->get_results($query, ARRAY_A);
    }
}

new SendSmsApi();
