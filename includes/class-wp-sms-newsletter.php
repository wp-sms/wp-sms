<?php

/**
 * WP SMS newsletter class
 *
 * @category   class
 * @package    WP_SMS
 * @version    1.0
 */
class WP_SMS_Newsletter {

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


	public function __construct() {


		// Load scripts
		add_action( 'wp_enqueue_scripts', array( &$this, 'load_script' ) );

		// Subscribe ajax action
		add_action( 'wp_ajax_subscribe_ajax_action', array( &$this, 'subscribe_ajax_action_handler' ) );

		// Subscribe activation action
		add_action( 'wp_ajax_activation_ajax_action', array( &$this, 'activation_ajax_action_handler' ) );
	}

	/**
	 * Include front table
	 *
	 * @param  Not param
	 */
	public function load_script() {
		// jQuery will be included automatically
		wp_enqueue_script( 'ajax-script', WP_SMS_DIR_PLUGIN . 'assets/js/script.js', array( 'jquery' ), 1.1 );

		// Ajax params
		wp_localize_script( 'ajax-script', 'ajax_object', array(
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'wpsms-nonce' )
		) );
	}


	/**
	 * @param WP_SMS_Subscriptions $subscriptions
	 * @param $nonce
	 * @param $name
	 * @param $mobile
	 * @param null $group
	 *
	 * @return array
	 */
	public static function Subscribe( WP_SMS_Subscriptions $subscriptions, $name, $mobile, $group = null ) {
		global $wpsms_option, $sms;

		$errors  = array();
		$success = array();

		if ( ! $name or ! $mobile ) {
			// Return response
			$errors['result']  = 'error';
			$errors['message'] = __( 'Please complete all fields', 'wp-sms' );
			$errors['status']  = 400;

			return $errors;
		}

		if ( preg_match( WP_SMS_MOBILE_REGEX, $mobile ) == false ) {
			// Return response
			$errors['result']  = 'error';
			$errors['message'] = __( 'Please enter a valid mobile number', 'wp-sms' );
			$errors['status']  = 400;

			return $errors;
		}

		if ( isset( $wpsms_option['mobile_terms_maximum'] ) AND $wpsms_option['mobile_terms_maximum'] ) {
			if ( strlen( $mobile ) > $wpsms_option['mobile_terms_maximum'] ) {
				// Return response
				$errors['result']  = 'error';
				$errors['message'] = sprintf( __( 'Your mobile number should be less than %s digits', 'wp-sms' ), $wpsms_option['mobile_terms_maximum'] );
				$errors['status']  = 400;

				return $errors;
			}
		}

		if ( isset( $wpsms_option['mobile_terms_minimum'] ) AND $wpsms_option['mobile_terms_minimum'] ) {
			if ( strlen( $mobile ) < $wpsms_option['mobile_terms_minimum'] ) {
				// Return response
				$errors['result']  = 'error';
				$errors['message'] = sprintf( __( 'Your mobile number should be greater than %s digits', 'wp-sms' ), $wpsms_option['mobile_terms_minimum'] );
				$errors['status']  = 400;

				return $errors;
			}
		}

		if ( isset( $wpsms_option['newsletter_form_verify'] ) AND $wpsms_option['newsletter_form_verify'] AND $wpsms_option['gateway_name'] ) {

			// Check gateway setting
			if ( ! $wpsms_option['gateway_name'] ) {
				// Return response
				$errors['result']  = 'error';
				$errors['message'] = __( 'Service provider is not available for send activate key to your mobile. Please contact with site.', 'wp-sms' );
				$errors['status']  = 400;

				return $errors;
			}
			$key = rand( 1000, 9999 );
			// Add subscribe to database
			$result = $subscriptions->add_subscriber( $name, $mobile, $group, '0', $key );

			if ( $result['result'] == 'error' ) {
				// Return response
				$errors['result']  = 'error';
				$errors['message'] = $result['message'];
				$errors['status']  = 400;

				return $errors;
			} else {

				$sms->to  = array( $mobile );
				$sms->msg = __( 'Your activation code', 'wp-sms' ) . ': ' . $key;
				$sms->SendSMS();
			}

			// Return response
			$success['result']  = 'success';
			$success['message'] = __( 'You will join the newsletter, Activation code sent to your mobile.', 'wp-sms' );
			$success['action']  = 'activation';
			$success['status']  = 400;

			return $success;

		} else {

			// Add subscribe to database
			$result = $subscriptions->add_subscriber( $name, $mobile, $group, '1' );

			if ( $result['result'] == 'error' ) {
				// Return response
				$errors['result']  = 'error';
				$errors['message'] = $result['message'];
				$errors['status']  = 400;

				return $errors;
			} else {
				$success['result']  = 'success';
				$success['message'] = $result['message'];
				$success['status']  = 400;

				return $success;
			}
		}
	}

	/**
	 * @param WP_SMS_Subscriptions $subscriptions
	 * @param $nonce
	 * @param $name
	 * @param $mobile
	 * @param null $group
	 *
	 * @return array
	 */
	public static function unSubscribe( WP_SMS_Subscriptions $subscriptions, $name, $mobile, $group = null ) {
		global $wpsms_option;

		$errors = array();
		$errors = array();

		if ( ! $name or ! $mobile ) {
			// Return response
			$errors['result']  = 'error';
			$errors['message'] = __( 'Please complete all fields', 'wp-sms' );
			$errors['status']  = 400;

			return $errors;
		}

		if ( preg_match( WP_SMS_MOBILE_REGEX, $mobile ) == false ) {
			// Return response
			$errors['result']  = 'error';
			$errors['message'] = __( 'Please enter a valid mobile number', 'wp-sms' );
			$errors['status']  = 400;

			return $errors;
		}

		if ( isset( $wpsms_option['mobile_terms_maximum'] ) AND $wpsms_option['mobile_terms_maximum'] ) {
			if ( strlen( $mobile ) > $wpsms_option['mobile_terms_maximum'] ) {
				// Return response
				$errors['result']  = 'error';
				$errors['message'] = sprintf( __( 'Your mobile number should be less than %s digits', 'wp-sms' ), $wpsms_option['mobile_terms_maximum'] );
				$errors['status']  = 400;

				return $errors;
			}
		}

		if ( isset( $wpsms_option['mobile_terms_minimum'] ) AND $wpsms_option['mobile_terms_minimum'] ) {
			if ( strlen( $mobile ) < $wpsms_option['mobile_terms_minimum'] ) {
				// Return response
				$errors['result']  = 'error';
				$errors['message'] = sprintf( __( 'Your mobile number should be greater than %s digits', 'wp-sms' ), $wpsms_option['mobile_terms_minimum'] );
				$errors['status']  = 400;

				return $errors;
			}
		}
		// Delete subscriber
		$result = $subscriptions->delete_subscriber_by_number( $mobile, $group );

		// Check result
		if ( $result['result'] == 'error' ) {
			// Return response
			$errors['result']  = 'error';
			$errors['message'] = $result['message'];
			$errors['status']  = 400;

			return $errors;
		}

		// Return response
		$success['result']  = 'success';
		$success['message'] = __( 'Your subscription was canceled.', 'wp-sms' );
		$success['status']  = 400;

		return $success;
	}


	public static function verifySubscriber( $mobile, $name, $activation ) {
		global $wpsms_option, $sms, $wpdb, $table_prefix;

		$errors  = array();
		$success = array();

		if ( ! $mobile ) {
			// Return response
			$errors['result']  = 'error';
			$errors['message'] = __( 'Mobile number is missing!', 'wp-sms' );
			$errors['status']  = 400;

			return $errors;
		}

		if ( ! $activation ) {
			// Return response
			$errors['result']  = 'error';
			$errors['message'] = __( 'Please enter the activation code!', 'wp-sms' );
			$errors['status']  = 400;

			return $errors;
		}

		$check_mobile = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table_prefix}sms_subscribes` WHERE `mobile` = '%s'", $mobile ) );

		if ( $activation != $check_mobile->activate_key ) {
			// Return response
			$errors['result']  = 'error';
			$errors['message'] = __( 'Activation code is wrong!', 'wp-sms' );
			$errors['status']  = 400;

			return $errors;
		}

		$result = $wpdb->update( "{$table_prefix}sms_subscribes", array( 'status' => '1' ), array( 'mobile' => $mobile ) );

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
			$success['result']  = 'success';
			$success['message'] = __( 'Your subscription was successful!', 'wp-sms' );
			$success['status']  = 400;

			return $success;
		}
	}
}

new WP_SMS_Newsletter();