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
class Subscribers extends \WP_SMS\RestApi {

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
		register_rest_route( $this->namespace . '/v1', '/subscribers', array(
			array(
				'methods'  => \WP_REST_Server::READABLE,
				'callback' => array( $this, 'subscribers' ),
				'args'     => array(
					'page'     => array(
						'required' => false,
					),
					'group_id' => array(
						'required' => false,
					),
					'number'   => array(
						'required' => false,
					),
					'search'   => array(
						'required' => false,
					)
				),
			)
		) );
	}

	/**
	 * @param WP_REST_Request $request
	 *
	 * @return \WP_REST_Response
	 */
	public function subscribers_callback( \WP_REST_Request $request ) {
		if ( ! current_user_can( 'wpsms_subscribers' ) ) {
			return self::response( new \WP_Error( 'permission_denied', __( 'Activation code is wrong!', 'wp-sms' ) ), 400 );
		}
		// Get parameters from request
		$params = $request->get_params();

		$page     = isset ( $params['page'] ) ? $params['page'] : '';
		$group_id = isset ( $params['group_id'] ) ? $params['group_id'] : '';
		$mobile   = isset ( $params['mobile'] ) ? $params['mobile'] : '';
		$search   = isset ( $params['search'] ) ? $params['search'] : '';
		$result   = self::getSubscribers( $page, $group_id, $mobile, $search );

		if ( is_wp_error( $result ) ) {
			return self::response( $result->get_error_message(), 400 );
		}

		return self::response( $result );
	}
}

new Newsletter();