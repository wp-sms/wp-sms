<?php

namespace WP_SMS\Api;

use WP_SMS\RestApi;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

class Router_Manager extends RestApi {

	private $version;

	public function __construct() {
		// Register routes
		add_action( 'rest_api_init', array( $this, 'registerRoutes' ) );
		$this->version   = 'v1';
		$this->namespace .= $this->version;
		parent::__construct();
	}

	/**
	 * Register routes
	 */
	public function registerRoutes() {
		$this->registerRouteEndpoints( '/send' );
	}

	/**
	 * Register API class endpoints
	 *
	 * @param $route
	 */
	private function registerRouteEndpoints( $route ) {

		// Set class name to include
		$className = str_replace( '/', '', $route );

		include( $this->version . '/class-wpsms-api-' . $className . '.php' );

		$className = '\\WP_SMS\\Api\\' . $this->version . '\\' . ucfirst( $className );
		$className::registerRoute( $this->namespace, $route );
	}
}

new Router_Manager();