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

        // Quick send endpoint for new settings UI
        register_rest_route($this->namespace . '/v1', '/send/quick', array(
            array(
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => array($this, 'quickSendCallback'),
                'permission_callback' => function () {
                    return current_user_can('wpsms_sendsms');
                },
            )
        ));

        // Recipient count endpoint for new settings UI
        register_rest_route($this->namespace . '/v1', '/send/count', array(
            array(
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => array($this, 'getRecipientCountCallback'),
                'permission_callback' => function () {
                    return current_user_can('wpsms_sendsms');
                },
            )
        ));

        // User search endpoint for recipient selector
        register_rest_route($this->namespace . '/v1', '/users/search', array(
            array(
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => array($this, 'searchUsersCallback'),
                'permission_callback' => function () {
                    return current_user_can('wpsms_sendsms');
                },
                'args'                => [
                    'search' => ['required' => false, 'type' => 'string'],
                    'per_page' => ['required' => false, 'type' => 'integer', 'default' => 20],
                ],
            )
        ));

        // Note: /outbox endpoint moved to class-wpsms-api-outbox.php
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
                    throw new Exception(esc_html__('WooCommerce or WP-SMS Pro is not enabled', 'wp-sms'));
                }

                break;

            /**
             * BuddyPress users
             */
            case 'bp-users':

                if (class_exists('BuddyPress') and class_exists('WP_SMS\Pro\BuddyPress')) {
                    $recipients = \WP_SMS\Pro\BuddyPress::getTotalMobileNumbers();
                } else {
                    throw new Exception(esc_html__('BuddyPress or WP SMS Pro is not enabled', 'wp-sms'));
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
     * Quick send callback for new settings UI
     * Simplified send endpoint that accepts groups, roles, and numbers directly
     * Also supports scheduling and repeating messages (add-on)
     *
     * @param WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function quickSendCallback(WP_REST_Request $request)
    {
        try {
            $recipients = $request->get_param('recipients');
            $message = $request->get_param('message');
            $flash = $request->get_param('flash') ?? false;
            $mediaUrl = $request->get_param('media_url') ?? '';
            $from = $request->get_param('from') ?? '';
            $schedule = $request->get_param('schedule');
            $repeat = $request->get_param('repeat');

            if (empty($message)) {
                throw new Exception(esc_html__('The message body can not be empty.', 'wp-sms'));
            }

            $recipientNumbers = [];

            // Get numbers from groups (subscribers)
            if (!empty($recipients['groups']) && is_array($recipients['groups'])) {
                $groupNumbers = Newsletter::getSubscribers($recipients['groups'], true);
                $recipientNumbers = array_merge($recipientNumbers, $groupNumbers);
            }

            // Get numbers from roles
            if (!empty($recipients['roles']) && is_array($recipients['roles'])) {
                foreach ($recipients['roles'] as $roleId) {
                    $roleNumbers = Helper::getUsersMobileNumbers(array($roleId));
                    $recipientNumbers = array_merge($recipientNumbers, $roleNumbers);
                }
            }

            // Get numbers from specific users
            if (!empty($recipients['users']) && is_array($recipients['users'])) {
                $userNumbers = Helper::getUsersMobileNumbers(false, $recipients['users']);
                $recipientNumbers = array_merge($recipientNumbers, $userNumbers);
            }

            // Add direct numbers
            if (!empty($recipients['numbers']) && is_array($recipients['numbers'])) {
                $recipientNumbers = array_merge($recipientNumbers, $recipients['numbers']);
            }

            // Allow add-ons to add recipient numbers (e.g., WooCommerce, BuddyPress)
            $recipientNumbers = apply_filters('wpsms_api_recipient_numbers', $recipientNumbers, $recipients, []);

            // Remove duplicates
            $recipientNumbers = array_unique($recipientNumbers);

            if (count($recipientNumbers) === 0) {
                throw new Exception(esc_html__('Could not find any mobile numbers.', 'wp-sms'));
            }

            // Make URLs shorter
            $message = Helper::makeUrlsShorter($message);

            // Get sender
            $sender = !empty($from) ? $from : Gateway::from();

            // Prepare media URLs
            $mediaUrls = !empty($mediaUrl) ? [$mediaUrl] : [];

            /*
             * Repeating SMS (add-on)
             */
            if (!empty($schedule) && !empty($repeat) && class_exists('WP_SMS\Pro\RepeatingMessages')) {
                $startDate = new DateTime(get_gmt_from_date($schedule));
                $endDate = !empty($repeat['endDate']) ? (new DateTime(get_gmt_from_date($repeat['endDate']))) : null;
                $intervalValue = isset($repeat['interval']) ? intval($repeat['interval']) : 1;
                $intervalUnit = isset($repeat['unit']) ? $repeat['unit'] : 'day';

                if ($startDate->getTimestamp() < time()) {
                    return self::response(esc_html__('Selected start date must be in future', 'wp-sms'), 400);
                }

                if (isset($endDate) && $endDate->getTimestamp() < $startDate->getTimestamp()) {
                    return self::response(esc_html__('Selected end date must be after start date', 'wp-sms'), 400);
                }

                RepeatingMessages::add(
                    $startDate,
                    $endDate,
                    $intervalValue,
                    $intervalUnit,
                    $sender,
                    $message,
                    $recipientNumbers,
                    $mediaUrls
                );

                return self::response(esc_html__('Repeating SMS is scheduled successfully!', 'wp-sms'), 200, [
                    'recipient_count' => count($recipientNumbers),
                    'credit' => Gateway::credit()
                ]);
            }

            /**
             * Scheduled SMS (add-on)
             */
            if (!empty($schedule) && class_exists('WP_SMS\Pro\Scheduled')) {
                if ((new DateTime(get_gmt_from_date($schedule)))->getTimestamp() < time()) {
                    return self::response(esc_html__('Selected start date must be in future', 'wp-sms'), 400);
                }

                Scheduled::add(
                    $schedule,
                    $sender,
                    $message,
                    $recipientNumbers,
                    true,
                    $mediaUrls
                );

                return self::response(esc_html__('SMS scheduled successfully!', 'wp-sms'), 200, [
                    'recipient_count' => count($recipientNumbers),
                    'credit' => Gateway::credit()
                ]);
            }

            // Send SMS immediately
            $notification = NotificationFactory::getHandler(null, null);
            $response = $notification->send(
                $message,
                $recipientNumbers,
                $mediaUrls,
                $flash,
                $sender
            );

            if (is_wp_error($response)) {
                throw new Exception($response->get_error_message());
            }

            return self::response(esc_html__('Successfully sent SMS!', 'wp-sms'), 200, [
                'recipient_count' => count($recipientNumbers),
                'credit' => Gateway::credit()
            ]);
        } catch (\Throwable $e) {
            return self::response($e->getMessage(), 400);
        }
    }

    /**
     * Get recipient count for new settings UI
     *
     * @param WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function getRecipientCountCallback(WP_REST_Request $request)
    {
        try {
            $recipients = $request->get_param('recipients');
            $counts = [
                'groups' => 0,
                'roles' => 0,
                'users' => 0,
                'numbers' => 0,
                'total' => 0
            ];

            $allNumbers = [];

            // Count from groups (subscribers)
            if (!empty($recipients['groups']) && is_array($recipients['groups'])) {
                $groupNumbers = Newsletter::getSubscribers($recipients['groups'], true);
                $counts['groups'] = count($groupNumbers);
                $allNumbers = array_merge($allNumbers, $groupNumbers);
            }

            // Count from roles
            if (!empty($recipients['roles']) && is_array($recipients['roles'])) {
                $roleNumbers = [];
                foreach ($recipients['roles'] as $roleId) {
                    $numbers = Helper::getUsersMobileNumbers(array($roleId));
                    $roleNumbers = array_merge($roleNumbers, $numbers);
                }
                $counts['roles'] = count($roleNumbers);
                $allNumbers = array_merge($allNumbers, $roleNumbers);
            }

            // Count from specific users
            if (!empty($recipients['users']) && is_array($recipients['users'])) {
                $userNumbers = Helper::getUsersMobileNumbers(false, $recipients['users']);
                $counts['users'] = count($userNumbers);
                $allNumbers = array_merge($allNumbers, $userNumbers);
            }

            // Count direct numbers
            if (!empty($recipients['numbers']) && is_array($recipients['numbers'])) {
                $counts['numbers'] = count($recipients['numbers']);
                $allNumbers = array_merge($allNumbers, $recipients['numbers']);
            }

            /**
             * Filter to add recipient numbers from add-ons (e.g., WooCommerce, BuddyPress)
             *
             * @param array $allNumbers Current collected numbers
             * @param array $recipients Recipients request data
             * @param array $counts Current counts array (pass by reference via filter)
             */
            $allNumbers = apply_filters('wpsms_api_recipient_numbers', $allNumbers, $recipients, $counts);

            // Total unique count
            $counts['total'] = count(array_unique($allNumbers));

            return self::response('', 200, $counts);
        } catch (\Throwable $e) {
            return self::response($e->getMessage(), 400);
        }
    }

    /**
     * Search users for recipient selector
     * Returns users with their mobile numbers
     *
     * @param WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function searchUsersCallback(WP_REST_Request $request)
    {
        try {
            $search = $request->get_param('search');
            $perPage = $request->get_param('per_page') ?? 20;
            $mobileFieldKey = Helper::getUserMobileFieldName();

            $args = [
                'number' => $perPage,
                'orderby' => 'display_name',
                'order' => 'ASC',
            ];

            // Add search if provided
            if (!empty($search)) {
                // Check if search is numeric (user ID search)
                if (is_numeric($search)) {
                    $args['include'] = [intval($search)];
                } else {
                    $args['search'] = '*' . $search . '*';
                    $args['search_columns'] = ['user_login', 'user_email', 'display_name', 'user_nicename'];
                }
            }

            $userQuery = new \WP_User_Query($args);
            $users = $userQuery->get_results();

            $results = [];
            foreach ($users as $user) {
                $mobile = get_user_meta($user->ID, $mobileFieldKey, true);
                $results[] = [
                    'id' => $user->ID,
                    'name' => $user->display_name,
                    'email' => $user->user_email,
                    'mobile' => $mobile ?: null,
                    'hasMobile' => !empty($mobile),
                ];
            }

            return self::response('', 200, ['users' => $results]);
        } catch (\Throwable $e) {
            return self::response($e->getMessage(), 400);
        }
    }
}

new SendSmsApi();
