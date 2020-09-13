<?php

namespace WP_SMS\Api\V1;

use WP_SMS\RestApi;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

/**
 * @category   class
 * @package    WP_SMS_Api
 * @version    1.0
 */
class Send {

	/**
	 * Register API class route
	 *
	 * @param $nameSpace
	 * @param $route
	 */
	public static function registerRoute( $nameSpace, $route ) {

		// SMS Newsletter
		register_rest_route( $nameSpace, $route, array(
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( self::class, 'send_callback' ),
				'args'                => array(
					'to'      => array(
						'required' => true,
					),
					'msg'     => array(
						'required' => true,
					),
					'isflash' => array(
						'required' => false,
					)
				),
				'permission_callback' => array( self::class, 'get_item_permissions_check' ),
			)
		) );
	}

	/**
	 * @param WP_REST_Request $request
	 *
	 * @return \WP_REST_Response
	 */
	static function send_callback( \WP_REST_Request $request ) {
		// Get parameters from request
		$params = $request->get_params();

		$to      = isset ( $params['to'] ) ? $params['to'] : '';
		$msg     = isset ( $params['msg'] ) ? $params['msg'] : '';
		$isflash = isset ( $params['isflash'] ) ? $params['isflash'] : '';
		$result  = RestApi::sendSMS( $to, $msg, $isflash );

		if ( is_wp_error( $result ) ) {
			return RestApi::response( $result->get_error_message(), 400 );
		}

		return RestApi::response( $result );
	}

	/**
	 * Check user permission
	 *
	 * @param $request
	 *
	 * @return bool
	 */
	static function get_item_permissions_check( $request ) {
		return current_user_can( 'wpsms_sendsms' );
	}
}