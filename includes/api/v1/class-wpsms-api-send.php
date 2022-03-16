<?php

namespace WP_SMS\Api\V1;

use Exception;
use WP_REST_Request;
use WP_SMS\Gateway;
use WP_SMS\Helper;
use WP_SMS\Pro\Scheduled;

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
		'sender'     => array('required' => true, 'type' => 'string'),
		'recipients' => array('required' => true, 'type' => 'string', 'enum' => ['subscribers', 'users', 'wc-customers', 'bp-users', 'numbers']),
		'group_ids'  => array('required' => false, 'type' => 'array'),
		'role_ids'   => array('required' => false, 'type' => 'array'),
		'numbers'    => array('required' => false, 'type' => 'array', 'format' => 'uri'),
		'message'    => array('required' => true, 'type' => 'string'),
		'flash'      => array('required' => false, 'type' => 'boolean'),
		'media_urls' => array('required' => false, 'type' => 'array'),
		'schedule'   => array('required' => false, 'type' => 'string', 'format' => 'date-time')
	];

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
		register_rest_route($this->namespace . '/v1', '/send', array(
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array($this, 'send_callback'),
				'args'                => $this->sendSmsArguments,
				'permission_callback' => array($this, 'get_item_permissions_check'),
			)
		));
	}

	/**
	 * @param WP_REST_Request $request
	 *
	 * @return \WP_REST_Response
	 */
	public function send_callback(WP_REST_Request $request)
	{
		try {

			$recipientNumbers = $this->getRecipientsFromRequest($request);

			$mediaUrls = array_filter($mediaUrls);

			/*
			 * Scheduled SMS
			 */
			if ($request->has_param('schedule')) {
				Scheduled::add(
					$request->get_param('schedule'),
					$request->get_param('sender'),
					$request->get_param('message'),
					$recipientNumbers,
					true,
					$mediaUrls
				);

				return self::response('SMS Scheduled Successfully!');
			}

            /**
             * Make shorter the URLs in the message
             */
            $message = Helper::makeUrlsShorter($request->get_param('message'));

			/*
			 * Regular SMS
			 */
			$response = wp_sms_send(
				$recipientNumbers,
				$message,
				$request->get_param('flash'),
				$request->get_param('sender'),
				$mediaUrls
			);

			if (is_wp_error($response)) {
				throw new Exception($response->get_error_message());
			}

			return self::response('Successfully send SMS!', 200, [
				'balance' => Gateway::credit()
			]);

		} catch (Exception $e) {
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

				if (!$request->get_param('group_ids')) {
					throw new Exception(__('Parameter group_ids is required', 'wp-sms'));
				}

				$recipients = \WP_SMS\Newsletter::getSubscribers($request->get_param('group_ids'), true);
				break;

			/**
			 * Users
			 */
			case 'users':

                if (!$request->get_param('role_ids')) {
					throw new Exception(__('Parameter role_ids is required', 'wp-sms'));
				}

				$recipients = Helper::getUsersMobileNumbers($request->get_param('role_ids'));
				break;

			/**
			 * WooCommerce customers
			 */
			case 'wc-customers':

				if (class_exists('woocommerce') and class_exists('WP_SMS\Pro\WooCommerce\Helper')) {
					$recipients = \WP_SMS\Pro\WooCommerce\Helper::getCustomersNumbers();
				} else {
					throw new Exception(__('WooCommerce or WP-SMS Pro is not enabled', 'wp-sms-pro'));
				}

				break;

			/**
			 * BuddyPress users
			 */
			case 'bp-users':

				if (class_exists('BuddyPress') and class_exists('WP_SMS\Pro\BuddyPress')) {
					$recipients = \WP_SMS\Pro\BuddyPress::getTotalMobileNumbers();
				} else {
					throw new Exception(__('BuddyPress or WP-SMS Pro is not enabled', 'wp-sms-pro'));
				}

				break;

			/**
			 * Numbers
			 */
			case 'numbers':

				if (!$request->get_param('numbers')) {
					throw new Exception(__('Parameter numbers is required', 'wp-sms'));
				}

				$recipients = $request->get_param('numbers');
				break;
		}

		return apply_filters('wp_sms_api_recipients_numbers', $recipients, $request->get_param('recipients'));
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

new SendSmsApi();
