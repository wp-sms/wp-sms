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

	/**
	 * WP SMS subscribe object
	 *
	 * @var string
	 */
	public $subscribe;

	public function __construct() {
		global $wpsms_option, $sms, $wpdb, $table_prefix;

		$this->sms       = $sms;
		$this->options   = $wpsms_option;
		$this->db        = $wpdb;
		$this->tb_prefix = $table_prefix;
		$this->subscribe = new WP_SMS_Subscriptions();

		// Load scripts
		add_action( 'wp_enqueue_scripts', array( &$this, 'load_script' ) );

		// Subscribe ajax action
		add_action( 'wp_ajax_subscribe_ajax_action', array( &$this, 'subscribe_ajax_action_handler' ) );
		add_action( 'wp_ajax_nopriv_subscribe_ajax_action', array( &$this, 'subscribe_ajax_action_handler' ) );

		// Subscribe activation action
		add_action( 'wp_ajax_activation_ajax_action', array( &$this, 'activation_ajax_action_handler' ) );
		add_action( 'wp_ajax_nopriv_activation_ajax_action', array( &$this, 'activation_ajax_action_handler' ) );
	}

	/**
	 * Include front table
	 *
	 * @param  Not param
	 */
	public function load_script() {
		// jQuery will be included automatically
		wp_enqueue_script( 'ajax-script', WP_SMS_DIR_PLUGIN . 'assets/js/script.js', array( 'jquery' ), 1.0 );

		// Ajax params
		wp_localize_script( 'ajax-script', 'ajax_object', array(
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'wpsms-nonce' )
		) );
	}

	/**
	 * Subscribe ajax handler
	 */
	public function subscribe_ajax_action_handler() {
		// Check nonce
		$nonce = $_POST['nonce'];
		if ( ! wp_verify_nonce( $nonce, 'wpsms-nonce' ) ) {
			// Stop executing script
			die ( 'Busted!' );
		}

		// Get widget option
		$get_widget     = get_option( 'widget_wpsms_widget' );
		$widget_options = $get_widget[ $_POST['widget_id'] ];

		// Check current widget
		if ( ! isset( $widget_options ) ) {
			// Return response
			echo json_encode( array( 'status'   => 'error',
			                         'response' => __( 'Params does not found! please refresh the current page!', 'wp-sms' )
			) );

			// Stop executing script
			die();
		}

		$name   = trim( $_POST['name'] );
		$mobile = trim( $_POST['mobile'] );
		$group  = trim( $_POST['group'] );
		$type   = $_POST['type'];

		if ( ! $name or ! $mobile ) {
			// Return response
			echo json_encode( array( 'status'   => 'error',
			                         'response' => __( 'Please complete all fields', 'wp-sms' )
			) );

			// Stop executing script
			die();
		}

		if ( preg_match( WP_SMS_MOBILE_REGEX, $mobile ) == false ) {
			// Return response
			echo json_encode( array( 'status'   => 'error',
			                         'response' => __( 'Please enter a valid mobile number', 'wp-sms' )
			) );

			// Stop executing script
			die();
		}

		if ( $widget_options['mobile_number_terms'] ) {
			if ( $widget_options['mobile_field_max'] ) {
				if ( strlen( $mobile ) > $widget_options['mobile_field_max'] ) {
					// Return response
					echo json_encode( array( 'status'   => 'error',
					                         'response' => sprintf( __( 'Your mobile number should be less than %s digits', 'wp-sms' ), $widget_options['mobile_field_max'] )
					) );

					// Stop executing script
					die();
				}
			}

			if ( $widget_options['mobile_field_min'] ) {
				if ( strlen( $mobile ) < $widget_options['mobile_field_min'] ) {
					// Return response
					echo json_encode( array( 'status'   => 'error',
					                         'response' => sprintf( __( 'Your mobile number should be greater than %s digits', 'wp-sms' ), $widget_options['mobile_field_min'] )
					) );

					// Stop executing script
					die();
				}
			}
		}

		if ( $type == 'subscribe' ) {
			if ( $widget_options['send_activation_code'] and $this->options['gateway_name'] ) {

				// Check gateway setting
				if ( ! $this->options['gateway_name'] ) {
					// Return response
					echo json_encode( array( 'status'   => 'error',
					                         'response' => __( 'Service provider is not available for send activate key to your mobile. Please contact with site.', 'wp-sms' )
					) );

					// Stop executing script
					die();
				}

				$key            = rand( 1000, 9999 );
				$this->sms->to  = array( $mobile );
				$this->sms->msg = __( 'Your activation code', 'wp-sms' ) . ': ' . $key;
				$this->sms->SendSMS();

				// Add subscribe to database
				$result = $this->subscribe->add_subscriber( $name, $mobile, $group, '0', $key );

				if ( $result['result'] == 'error' ) {
					// Return response
					echo json_encode( array( 'status' => 'error', 'response' => $result['message'] ) );

					// Stop executing script
					die();
				}

				// Return response
				echo json_encode( array( 'status'   => 'success',
				                         'response' => __( 'You will join the newsletter, Activation code sent to your mobile.', 'wp-sms' ),
				                         'action'   => 'activation'
				) );

				// Stop executing script
				die();

			} else {

				// Add subscribe to database
				$result = $this->subscribe->add_subscriber( $name, $mobile, $group, '1' );

				if ( $result['result'] == 'error' ) {
					// Return response
					echo json_encode( array( 'status' => 'error', 'response' => $result['message'] ) );

					// Stop executing script
					die();
				}

				// Send welcome message
				if ( $widget_options['send_welcome_sms'] ) {
					$template_vars = array(
						'%subscribe_name%'   => $name,
						'%subscribe_mobile%' => $mobile,
					);

					$message = str_replace( array_keys( $template_vars ), array_values( $template_vars ), $widget_options['welcome_sms_template'] );

					$this->sms->to  = array( $mobile );
					$this->sms->msg = $message;
					$this->sms->SendSMS();
				}

				// Return response
				echo json_encode( array( 'status'   => 'success',
				                         'response' => __( 'You will join the newsletter', 'wp-sms' )
				) );

				// Stop executing script
				die();
			}
		} else if ( $type == 'unsubscribe' ) {
			// Delete subscriber
			$result = $this->subscribe->delete_subscriber_by_number( $mobile, $group );

			// Check result
			if ( $result['result'] == 'error' ) {
				// Return response
				echo json_encode( array( 'status' => 'error', 'response' => $result['message'] ) );

				// Stop executing script
				die();
			}

			// Return response
			echo json_encode( array( 'status'   => 'success',
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
	public function activation_ajax_action_handler() {
		// Check nonce
		$nonce = $_POST['nonce'];
		if ( ! wp_verify_nonce( $nonce, 'wpsms-nonce' ) ) {
			// Stop executing script
			die ( 'Busted!' );
		}

		// Get widget option
		$get_widget     = get_option( 'widget_wpsms_widget' );
		$widget_options = $get_widget[ $_POST['widget_id'] ];

		// Check current widget
		if ( ! isset( $widget_options ) ) {
			// Return response
			echo json_encode( array( 'status'   => 'error',
			                         'response' => __( 'Params does not found! please refresh the current page!', 'wp-sms' )
			) );

			// Stop executing script
			die();
		}

		$mobile     = trim( $_POST['mobile'] );
		$activation = trim( $_POST['activation'] );

		if ( ! $mobile ) {
			// Return response
			echo json_encode( array( 'status' => 'error', 'response' => __( 'Mobile number is missing!', 'wp-sms' ) ) );

			// Stop executing script
			die();
		}

		if ( ! $activation ) {
			// Return response
			echo json_encode( array( 'status'   => 'error',
			                         'response' => __( 'Please enter the activation code!', 'wp-sms' )
			) );

			// Stop executing script
			die();
		}

		$check_mobile = $this->db->get_row( $this->db->prepare( "SELECT * FROM `{$this->tb_prefix}sms_subscribes` WHERE `mobile` = '%s'", $mobile ) );

		if ( $activation != $check_mobile->activate_key ) {
			// Return response
			echo json_encode( array( 'status' => 'error', 'response' => __( 'Activation code is wrong!', 'wp-sms' ) ) );

			// Stop executing script
			die();
		}

		$result = $this->db->update( "{$this->tb_prefix}sms_subscribes", array( 'status' => '1' ), array( 'mobile' => $mobile ) );

		if ( $result ) {
			// Return response
			echo json_encode( array( 'status'   => 'success',
			                         'response' => __( 'Your subscription was successful!', 'wp-sms' )
			) );

			// Stop executing script
			die();
		}
	}
}

new WP_SMS_Newsletter();