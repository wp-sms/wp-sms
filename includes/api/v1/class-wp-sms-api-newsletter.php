<?php

/**
 * @category   class
 * @package    WP_SMS_Api
 * @version    1.0
 */
class WP_SMS_Api_Newsletter_V1 extends WP_SMS_RestApi {
	/**
	 * WP_SMS_Api_Newsletter_V1 constructor.
	 */
	public function __construct() {

		// Register routes
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );

		parent::__construct();
	}

	/**
	 * Register routes
	 */
	public function register_routes() {

		// SMS Newsletter
		register_rest_route( $this->namespace . '/v1', '/newsletter', array(
			array(
				'methods'  => WP_REST_Server::CREATABLE,
				'callback' => array( $this, 'subscribe_api' ),
				'args'     => array(
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
			),
			array(
				'methods'  => WP_REST_Server::DELETABLE,
				'callback' => array( $this, 'unsubscribe_api' ),
				'args'     => array(
					'name'   => array(
						'required' => true,
					),
					'mobile' => array(
						'required' => true,
					),
				),
			),
			array(
				'methods'  => WP_REST_Server::EDITABLE,
				'callback' => array( $this, 'verify_subscriber_api' ),
				'args'     => array(
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
			)
		) );

	}

	/**
	 * @param $name
	 * @param $mobile
	 * @param null $group
	 *
	 * @return array|string
	 */
	public static function Subscribe( $name, $mobile, $group ) {

		global $wpdb, $table_prefix, $wpsms_option, $sms;

		$db_prepare  = $wpdb->prepare( "SELECT * FROM `{$table_prefix}sms_subscribes_group` WHERE `ID` = %d", $group );
		$check_group = $wpdb->get_row( $db_prepare );

		if ( ! isset( $check_group ) AND empty($check_group) ) {
			return new WP_Error( 'subscribe', __( 'The group number is not valid!', 'wp-sms' ) );
		}

		if ( preg_match( WP_SMS_MOBILE_REGEX, $mobile ) == false ) {
			// Return response
			return new WP_Error( 'subscribe', __( 'Please enter a valid mobile number', 'wp-sms' ) );
		}

		if ( isset( $wpsms_option['mobile_terms_maximum'] ) AND $wpsms_option['mobile_terms_maximum'] ) {
			if ( strlen( $mobile ) > $wpsms_option['mobile_terms_maximum'] ) {
				// Return response
				return new WP_Error( 'subscribe', sprintf( __( 'Your mobile number should be less than %s digits', 'wp-sms' ), $wpsms_option['mobile_terms_maximum'] ) );
			}
		}

		if ( isset( $wpsms_option['mobile_terms_minimum'] ) AND $wpsms_option['mobile_terms_minimum'] ) {
			if ( strlen( $mobile ) < $wpsms_option['mobile_terms_minimum'] ) {
				// Return response
				return new WP_Error( 'subscribe', sprintf( __( 'Your mobile number should be greater than %s digits', 'wp-sms' ), $wpsms_option['mobile_terms_minimum'] ) );
			}
		}

		if ( isset( $wpsms_option['newsletter_form_verify'] ) AND $wpsms_option['newsletter_form_verify'] AND $wpsms_option['gateway_name'] ) {

			// Check gateway setting
			if ( ! $wpsms_option['gateway_name'] ) {
				// Return response
				return new WP_Error( 'subscribe', __( 'Service provider is not available for send activate key to your mobile. Please contact with site.', 'wp-sms' ) );
			}
			$key = rand( 1000, 9999 );
			// Add subscribe to database
			$result = WP_SMS_Newsletter::addSubscriber( $name, $mobile, $group, '0', $key );

			if ( $result['result'] == 'error' ) {
				// Return response
				return new WP_Error( 'subscribe', $result['message'] );
			} else {

				$sms->to  = array( $mobile );
				$sms->msg = __( 'Your activation code', 'wp-sms' ) . ': ' . $key;
				$sms->SendSMS();
			}

			// Return response
			return __( 'You will join the newsletter, Activation code sent to your mobile.', 'wp-sms' );

		} else {

			// Add subscribe to database
			$result = WP_SMS_Newsletter::addSubscriber( $name, $mobile, $group, '1' );

			if ( $result['result'] == 'error' ) {
				// Return response
				return new WP_Error( 'subscribe', $result['message'] );
			} else {
				return __( 'Your subscription was successful!', 'wp-sms' );
			}
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
		global $wpdb, $table_prefix, $wpsms_option;

		$db_prepare  = $wpdb->prepare( "SELECT * FROM `{$table_prefix}sms_subscribes_group` WHERE `ID` = %d", $group );
		$check_group = $wpdb->get_row( $db_prepare );

		if ( ! isset( $check_group ) AND empty($check_group) ) {
			return new WP_Error( 'unsubscribe', __( 'The group number is not valid!', 'wp-sms' ) );
		}

		if ( preg_match( WP_SMS_MOBILE_REGEX, $mobile ) == false ) {
			// Return response
			return new WP_Error( 'unsubscribe', __( 'Please enter a valid mobile number', 'wp-sms' ) );
		}

		if ( isset( $wpsms_option['mobile_terms_maximum'] ) AND $wpsms_option['mobile_terms_maximum'] ) {
			if ( strlen( $mobile ) > $wpsms_option['mobile_terms_maximum'] ) {
				// Return response
				return new WP_Error( 'unsubscribe', sprintf( __( 'Your mobile number should be less than %s digits', 'wp-sms' ), $wpsms_option['mobile_terms_maximum'] ) );

			}
		}

		if ( isset( $wpsms_option['mobile_terms_minimum'] ) AND $wpsms_option['mobile_terms_minimum'] ) {
			if ( strlen( $mobile ) < $wpsms_option['mobile_terms_minimum'] ) {
				// Return response
				return new WP_Error( 'unsubscribe', sprintf( __( 'Your mobile number should be greater than %s digits', 'wp-sms' ), $wpsms_option['mobile_terms_minimum'] ) );
			}
		}
		// Delete subscriber
		$result = WP_SMS_Newsletter::deleteSubscriberByNumber( $mobile, $group );

		// Check result
		if ( $result['result'] == 'error' ) {
			// Return response
			return new WP_Error( 'unsubscribe', $result['message'] );
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
		global $wpsms_option, $sms, $wpdb, $table_prefix;

		// Check the mobile number is string or integer
		if ( strpos( $mobile, '+' ) !== false ) {
			$db_prepare = $wpdb->prepare( "SELECT * FROM `{$table_prefix}sms_subscribes` WHERE `mobile` = %s AND `status` = %d AND group_ID = %d", $mobile, 0, $group );
		} else {
			$db_prepare = $wpdb->prepare( "SELECT * FROM `{$table_prefix}sms_subscribes` WHERE `mobile` = %d AND `status` = %d AND group_ID = %d", $mobile, 0, $group );
		}

		$check_mobile = $wpdb->get_row( $db_prepare );

		if ( isset( $check_mobile ) ) {

			if ( $activation != $check_mobile->activate_key ) {
				// Return response
				return new WP_Error( 'verify_subscriber', __( 'Activation code is wrong!', 'wp-sms' ) );
			}

			// Check the mobile number is string or integer
			if ( strpos( $mobile, '+' ) !== false ) {
				$result = $wpdb->update( "{$table_prefix}sms_subscribes", array( 'status' => '1' ), array( 'mobile' => $mobile, 'group_ID' => $group ), array( '%d', '%d' ), array( '%s' ) );
			} else {
				$result = $wpdb->update( "{$table_prefix}sms_subscribes", array( 'status' => '1' ), array( 'mobile' => $mobile, 'group_ID' => $group ), array( '%d', '%d' ), array( '%d' ) );
			}

			if ( $result ) {
				// Send welcome message
				if ( isset( $wpsms_option['newsletter_form_welcome'] ) AND $wpsms_option['newsletter_form_welcome'] ) {
					$template_vars = array(
						'%subscribe_name%'   => $name,
						'%subscribe_mobile%' => $mobile,
					);
					$text          = isset( $wpsms_option['newsletter_form_welcome_text'] ) ? $wpsms_option['newsletter_form_welcome_text'] : '';
					$message       = str_replace( array_keys( $template_vars ), array_values( $template_vars ), $text );

					$sms->to  = array( $mobile );
					$sms->msg = $message;
					$sms->SendSMS();
				}

				// Return response
				return __( 'Your subscription was successful!', 'wp-sms' );
			}
		}

		return new WP_Error( 'verify_subscriber', __( 'Not found the number!', 'wp-sms' ) );
	}
}

new WP_SMS_Api_Newsletter_V1();