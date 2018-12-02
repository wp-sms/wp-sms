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
	 * Subscribe ajax handler
	 */
	public static function Subscribe() {
		global $wpsms_option, $sms, $wpdb, $table_prefix;

		// Check nonce
		$nonce = $_POST['nonce'];
		if ( ! wp_verify_nonce( $nonce, 'wpsms-nonce' ) ) {
			// Stop executing script
			die ( 'Busted!' );
		}

		$name   = trim( $_POST['name'] );
		$mobile = trim( $_POST['mobile'] );
		$group  = trim( $_POST['group'] );
		$type   = $_POST['type'];

		if ( ! $name or ! $mobile ) {
			// Return response
			echo json_encode( array(
				'status'   => 'error',
				'response' => __( 'Please complete all fields', 'wp-sms' )
			) );

			// Stop executing script
			die();
		}

		if ( preg_match( WP_SMS_MOBILE_REGEX, $mobile ) == false ) {
			// Return response
			echo json_encode( array(
				'status'   => 'error',
				'response' => __( 'Please enter a valid mobile number', 'wp-sms' )
			) );

			// Stop executing script
			die();
		}

		if ( isset( $wpsms_option['mobile_terms_maximum'] ) AND $wpsms_option['mobile_terms_maximum'] ) {
			if ( strlen( $mobile ) > $wpsms_option['mobile_terms_maximum'] ) {
				// Return response
				echo json_encode( array(
					'status'   => 'error',
					'response' => sprintf( __( 'Your mobile number should be less than %s digits', 'wp-sms' ), $wpsms_option['mobile_terms_maximum'] )
				) );

				// Stop executing script
				die();
			}
		}

		if ( isset( $wpsms_option['mobile_terms_minimum'] ) AND $wpsms_option['mobile_terms_minimum'] ) {
			if ( strlen( $mobile ) < $wpsms_option['mobile_terms_minimum'] ) {
				// Return response
				echo json_encode( array(
					'status'   => 'error',
					'response' => sprintf( __( 'Your mobile number should be greater than %s digits', 'wp-sms' ), $wpsms_option['mobile_terms_minimum'] )
				) );

				// Stop executing script
				die();
			}
		}

		if ( $type == 'subscribe' ) {
			if ( isset( $wpsms_option['newsletter_form_verify'] ) AND $wpsms_option['newsletter_form_verify'] AND $wpsms_option['gateway_name'] ) {

				// Check gateway setting
				if ( ! $wpsms_option['gateway_name'] ) {
					// Return response
					echo json_encode( array(
						'status'   => 'error',
						'response' => __( 'Service provider is not available for send activate key to your mobile. Please contact with site.', 'wp-sms' )
					) );

					// Stop executing script
					die();
				}
				$key = rand( 1000, 9999 );
				// Add subscribe to database
				//todo $result = $this->subscribe->add_subscriber( $name, $mobile, $group, '0', $key );

				if ( $result['result'] == 'error' ) {
					// Return response
					echo json_encode( array( 'status' => 'error', 'response' => $result['message'] ) );

					// Stop executing script
					die();
				} else {

					$sms->to  = array( $mobile );
					$sms->msg = __( 'Your activation code', 'wp-sms' ) . ': ' . $key;
					$sms->SendSMS();
				}

				// Return response
				echo json_encode( array(
					'status'   => 'success',
					'response' => __( 'You will join the newsletter, Activation code sent to your mobile.', 'wp-sms' ),
					'action'   => 'activation'
				) );

				// Stop executing script
				die();

			} else {

				// Add subscribe to database
				//todo $result = $this->subscribe->add_subscriber( $name, $mobile, $group, '1' );

				if ( $result['result'] == 'error' ) {
					// Return response
					echo json_encode( array( 'status' => 'error', 'response' => $result['message'] ) );

					// Stop executing script
					die();
				}
				// Stop executing script
				die();
			}
		} else if ( $type == 'unsubscribe' ) {
			// Delete subscriber
			//todo $result = $this->subscribe->delete_subscriber_by_number( $mobile, $group );

			// Check result
			if ( $result['result'] == 'error' ) {
				// Return response
				echo json_encode( array( 'status' => 'error', 'response' => $result['message'] ) );

				// Stop executing script
				die();
			}

			// Return response
			echo json_encode( array(
				'status'   => 'success',
				'response' => __( 'Your subscription was canceled.', 'wp-sms' )
			) );

			// Stop executing script
			die();
		}

		// Stop executing script
		die();
	}

	/**
	 * Activation ajax handler
	 */
	public static function unSubscribe() {
		global $wpsms_option, $sms, $wpdb, $table_prefix;
		// Check nonce
		$nonce = $_POST['nonce'];
		if ( ! wp_verify_nonce( $nonce, 'wpsms-nonce' ) ) {
			// Stop executing script
			die ( 'Busted!' );
		}


		$mobile     = trim( $_POST['mobile'] );
		$name       = trim( $_POST['name'] );
		$activation = trim( $_POST['activation'] );

		if ( ! $mobile ) {
			// Return response
			echo json_encode( array( 'status' => 'error', 'response' => __( 'Mobile number is missing!', 'wp-sms' ) ) );

			// Stop executing script
			die();
		}

		if ( ! $activation ) {
			// Return response
			echo json_encode( array(
				'status'   => 'error',
				'response' => __( 'Please enter the activation code!', 'wp-sms' )
			) );

			// Stop executing script
			die();
		}

		$check_mobile = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table_prefix}sms_subscribes` WHERE `mobile` = '%s'", $mobile ) );

		if ( $activation != $check_mobile->activate_key ) {
			// Return response
			echo json_encode( array( 'status' => 'error', 'response' => __( 'Activation code is wrong!', 'wp-sms' ) ) );

			// Stop executing script
			die();
		}

		$result = $wpdb->update( "{$table_prefix}sms_subscribes", array( 'status' => '1' ), array( 'mobile' => $mobile ) );

		if ( $result ) {
			// Return response
			echo json_encode( array(
				'status'   => 'success',
				'response' => __( 'Your subscription was successful!', 'wp-sms' )
			) );
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
			// Stop executing script
			die();
		}
	}
}

new WP_SMS_Newsletter();