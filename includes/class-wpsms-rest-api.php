<?php

namespace WP_SMS;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

/**
 * WP SMS RestApi class
 *
 * @category   class
 * @package    WP_SMS
 * @version    4.0
 */
class RestApi {

	/**
	 * SMS object
	 * @var object
	 */
	public $sms;

	/**
	 * Options
	 *
	 * @var string
	 */
	protected $option;

	/**
	 * Wordpress Database
	 *
	 * @var string
	 */
	protected $db;

	/**
	 * Wordpress Table prefix
	 *
	 * @var string
	 */
	protected $tb_prefix;

	/**
	 * Name space
	 * @var string
	 */
	public $namespace;

	/**
	 * WP_SMS_RestApi constructor.
	 */
	public function __construct() {
		global $sms, $wpdb;

		$this->sms       = $sms;
		$this->options   = Option::getOptions();
		$this->db        = $wpdb;
		$this->tb_prefix = $wpdb->prefix;
		$this->namespace = 'wpsms';
	}

	/**
	 * @param $message
	 * @param int $status
	 *
	 * @return WP_REST_Response
	 */
	public static function response( $message, $status = 200 ) {
		if ( $status == 200 ) {
			$output = array(
				'message' => $message,
				'error'   => array(),
			);
		} else {
			$output = array(
				'error' => array(
					'code'    => $status,
					'message' => $message,
				),
			);
		}

		return new \WP_REST_Response( $output, $status );
	}

	/**
	 * @param $name
	 * @param $mobile
	 * @param null $group
	 *
	 * @return array|string
	 */
	public static function subscribe( $name, $mobile, $group ) {
		global $sms;

		if ( empty( $name ) OR empty( $mobile ) ) {
			return new \WP_Error( 'subscribe', __( 'The name and mobile number must be valued!', 'wp-sms' ) );
		}

		$check_group = \WP_SMS\Newsletter::getGroup( $group );

		if ( ! isset( $check_group ) AND empty( $check_group ) ) {
			return new \WP_Error( 'subscribe', __( 'The group number is not valid!', 'wp-sms' ) );
		}

		if ( preg_match( WP_SMS_MOBILE_REGEX, $mobile ) == false ) {
			// Return response
			return new \WP_Error( 'subscribe', __( 'Please enter a valid mobile number', 'wp-sms' ) );
		}

		$max_number = Option::getOption( 'mobile_terms_maximum' );

		if ( $max_number ) {
			if ( strlen( $mobile ) > $max_number ) {
				// Return response
				return new \WP_Error( 'subscribe', sprintf( __( 'Your mobile number should be less than %s digits', 'wp-sms' ), $max_number ) );
			}
		}
		$min_number = Option::getOption( 'mobile_terms_minimum' );
		if ( $min_number ) {
			if ( strlen( $mobile ) < $min_number ) {
				// Return response
				return new \WP_Error( 'subscribe', sprintf( __( 'Your mobile number should be greater than %s digits', 'wp-sms' ), $min_number ) );
			}
		}

		$gateway_name = Option::getOption( 'gateway_name' );

		if ( Option::getOption( 'newsletter_form_verify' ) AND $gateway_name ) {
			// Check gateway setting
			if ( ! $gateway_name ) {
				// Return response
				return new \WP_Error( 'subscribe', __( 'Service provider is not available for send activate key to your mobile. Please contact with site.', 'wp-sms' ) );
			}

			$key = rand( 1000, 9999 );

			// Add subscribe to database
			$result = \WP_SMS\Newsletter::addSubscriber( $name, $mobile, $group, '0', $key );

			if ( $result['result'] == 'error' ) {
				// Return response
				return new \WP_Error( 'subscribe', $result['message'] );
			} else {

				$sms->to  = array( $mobile );
				$sms->msg = __( 'Your activation code', 'wp-sms' ) . ': ' . $key;
				$sms->SendSMS();
			}

			// Return response
			return __( 'You will join the newsletter, Activation code sent to your mobile.', 'wp-sms' );

		} else {

			// Add subscribe to database
			$result = \WP_SMS\Newsletter::addSubscriber( $name, $mobile, $group, '1' );

			if ( $result['result'] == 'error' ) {
				// Return response
				return new \WP_Error( 'subscribe', $result['message'] );
			}

			return __( 'Your number has been successfully subscribed.', 'wp-sms' );
		}
	}

	/**
	 * @param $name
	 * @param $mobile
	 * @param null $group
	 *
	 * @return array|string
	 */
	public static function unSubscribe( $name, $mobile, $group ) {

		if ( empty( $name ) OR empty( $mobile ) ) {
			return new \WP_Error( 'unsubscribe', __( 'The name and mobile number must be valued!', 'wp-sms' ) );
		}

		$check_group = \WP_SMS\Newsletter::getGroup( $group );

		if ( ! isset( $check_group ) AND empty( $check_group ) ) {
			return new \WP_Error( 'unsubscribe', __( 'The group number is not valid!', 'wp-sms' ) );
		}

		if ( preg_match( WP_SMS_MOBILE_REGEX, $mobile ) == false ) {
			// Return response
			return new \WP_Error( 'unsubscribe', __( 'Please enter a valid mobile number', 'wp-sms' ) );
		}

		$max_number = Option::getOption( 'mobile_terms_maximum' );

		if ( $max_number ) {
			if ( strlen( $mobile ) > $max_number ) {
				// Return response
				return new \WP_Error( 'unsubscribe', sprintf( __( 'Your mobile number should be less than %s digits', 'wp-sms' ), $max_number ) );

			}
		}

		$max_number = Option::getOption( 'mobile_terms_minimum' );

		if ( $max_number ) {
			if ( strlen( $mobile ) < $max_number ) {
				// Return response
				return new \WP_Error( 'unsubscribe', sprintf( __( 'Your mobile number should be greater than %s digits', 'wp-sms' ), $max_number ) );
			}
		}
		// Delete subscriber
		$result = \WP_SMS\Newsletter::deleteSubscriberByNumber( $mobile, $group );

		// Check result
		if ( $result['result'] == 'error' ) {
			// Return response
			return new \WP_Error( 'unsubscribe', $result['message'] );
		}

		return __( 'Your subscription was canceled.', 'wp-sms' );
	}

	/**
	 * @param $mobile
	 * @param $name
	 * @param $activation
	 *
	 * @return array|string
	 */
	public static function verifySubscriber( $name, $mobile, $activation, $group ) {
		global $sms, $wpdb;

		if ( empty( $name ) OR empty( $mobile ) OR empty( $activation ) ) {
			return new \WP_Error( 'unsubscribe', __( 'The required parameters must be valued!', 'wp-sms' ) );
		}

		// Check the mobile number is string or integer
		if ( strpos( $mobile, '+' ) !== false ) {
			$db_prepare = $wpdb->prepare( "SELECT * FROM `{$wpdb->prefix}sms_subscribes` WHERE `mobile` = %s AND `status` = %d AND group_ID = %d", $mobile, 0, $group );
		} else {
			$db_prepare = $wpdb->prepare( "SELECT * FROM `{$wpdb->prefix}sms_subscribes` WHERE `mobile` = %d AND `status` = %d AND group_ID = %d", $mobile, 0, $group );
		}

		$check_mobile = $wpdb->get_row( $db_prepare );

		if ( isset( $check_mobile ) ) {

			if ( $activation != $check_mobile->activate_key ) {
				// Return response
				return new \WP_Error( 'verify_subscriber', __( 'Activation code is wrong!', 'wp-sms' ) );
			}

			// Check the mobile number is string or integer
			if ( strpos( $mobile, '+' ) !== false ) {
				$result = $wpdb->update( "{$wpdb->prefix}sms_subscribes", array( 'status' => '1' ), array( 'mobile' => $mobile, 'group_ID' => $group ), array( '%d', '%d' ), array( '%s' ) );
			} else {
				$result = $wpdb->update( "{$wpdb->prefix}sms_subscribes", array( 'status' => '1' ), array( 'mobile' => $mobile, 'group_ID' => $group ), array( '%d', '%d' ), array( '%d' ) );
			}

			if ( $result ) {
				// Send welcome message
				if ( Option::getOption( 'newsletter_form_welcome' ) ) {
					$template_vars = array(
						'%subscribe_name%'   => $name,
						'%subscribe_mobile%' => $mobile,
					);
					$text          = Option::getOption( 'newsletter_form_welcome_text' );
					$message       = str_replace( array_keys( $template_vars ), array_values( $template_vars ), $text );

					$sms->to  = array( $mobile );
					$sms->msg = $message;
					$sms->SendSMS();
				}

				// Return response
				return __( 'Your subscription was successful!', 'wp-sms' );
			}
		}

		return new \WP_Error( 'verify_subscriber', __( 'Not found the number!', 'wp-sms' ) );
	}
}

new RestApi();