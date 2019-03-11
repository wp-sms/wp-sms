<?php

namespace WP_SMS\Api\V1;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

/**
 * @category   class
 * @package    WP_SMS_Api
 * @version    1.0
 */
class Credit extends \WP_SMS\RestApi {

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
		register_rest_route( $this->namespace . '/v1', '/credit', array(
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'subscribers_callback' ),
				'permission_callback' => array( $this, 'get_item_permissions_check' ),
			)
		) );
	}

	/**
	 * @param WP_REST_Request $request
	 *
	 * @return \WP_REST_Response
	 */
	public function subscribers_callback( \WP_REST_Request $request ) {
		// Get parameters from request
		$params = $request->get_params();

		$result   = self::getCredit();

		return self::response( $result );
	}

	/**
	 * Check user permission
	 *
	 * @param $request
	 *
	 * @return bool
	 */
	public function get_item_permissions_check( $request ) {
		return current_user_can( 'wpsms_setting' );
	}
}

new Credit();