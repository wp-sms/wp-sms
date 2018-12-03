<?php

/**
 * WP SMS RestApi class
 *
 * @category   class
 * @package    WP_SMS
 * @version    4.0
 */
class WP_SMS_RestApi {

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
		global $wpsms_option, $sms, $wpdb, $table_prefix;

		$this->sms       = $sms;
		$this->options   = $wpsms_option;
		$this->db        = $wpdb;
		$this->tb_prefix = $table_prefix;
		$this->namespace = 'wpsms';
	}

	/**
	 * @param WP_REST_Request $request
	 *
	 * @return WP_Error|WP_REST_Response
	 */
	public function subscribe_api( WP_REST_Request $request ) {
		// Get parameters from request
		$params = $request->get_params();

		if ( empty( $params['name'] ) ) {
			return self::response( new WP_Error( 'subscribe', __( 'Name must be valued!', 'wp-sms' ) ), 400 );
		}

		if ( empty( $params['mobile'] ) ) {
			return self::response( new WP_Error( 'subscribe', __( 'Mobile number must be valued!', 'wp-sms' ), 400 ) );
		}

		$group_id = isset ( $params['group_id'] ) ? $params['group_id'] : 1;


		$result = WP_SMS_Api_Newsletter_V1::Subscribe( $params['name'], $params['mobile'], $group_id );

		if ( is_wp_error( $result ) ) {
			return self::response( $result, 400 );
		}

		return self::response( $result );
	}

	/**
	 * @param WP_REST_Request $request
	 *
	 * @return WP_Error|WP_REST_Response
	 */
	public function unsubscribe_api( WP_REST_Request $request ) {
		// Get parameters from request
		$params = $request->get_params();

		if ( empty( $params['name'] ) ) {
			return self::response( new WP_Error( 'unsubscribe', __( 'Name must be valued!', 'wp-sms' ) ), 400 );
		}

		if ( empty( $params['mobile'] ) ) {
			return self::response( new WP_Error( 'unsubscribe', __( 'Mobile number must be valued!', 'wp-sms' ), 400 ) );
		}

		$group_id = isset ( $params['group_id'] ) ? $params['group_id'] : 1;

		$result = WP_SMS_Api_Newsletter_V1::unSubscribe( $params['name'], $params['mobile'], $group_id );

		if ( is_wp_error( $result ) ) {
			return self::response( $result, 400 );
		}

		return self::response( $result );
	}

	/**
	 * @param WP_REST_Request $request
	 *
	 * @return WP_Error|WP_REST_Response
	 */
	public function verify_subscriber_api( WP_REST_Request $request ) {
		// Get parameters from request
		$params = $request->get_params();

		if ( empty( $params['name'] ) ) {
			return self::response( new WP_Error( 'verify_subscriber', __( 'Name must be valued!', 'wp-sms' ) ), 400 );
		}

		if ( empty( $params['mobile'] ) ) {
			return self::response( new WP_Error( 'verify_subscriber', __( 'Mobile number must be valued!', 'wp-sms' ), 400 ) );
		}

		if ( empty( $params['activation'] ) ) {
			return self::response( new WP_Error( 'verify_subscriber', __( 'Activation must be valued!', 'wp-sms' ), 400 ) );
		}

		$group_id = isset ( $params['group_id'] ) ? $params['group_id'] : 1;

		$result = WP_SMS_Api_Newsletter_V1::verifySubscriber( $params['name'], $params['mobile'], $params['activation'], $group_id );

		if ( is_wp_error( $result ) ) {
			return self::response( $result, 400 );
		}

		return self::response( $result );
	}

	/**
	 * @param $message
	 * @param int $status
	 *
	 * @return WP_REST_Response
	 */
	public static function response( $message, $status = 200 ) {
		return new WP_REST_Response( $message, $status );
	}

}

new WP_SMS_RestApi();