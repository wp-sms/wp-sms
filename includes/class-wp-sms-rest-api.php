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
	private $namespace;

	public function __construct() {
		global $wpsms_option, $sms, $wpdb, $table_prefix;

		$this->sms           = $sms;
		$this->options       = $wpsms_option;
		$this->db            = $wpdb;
		$this->tb_prefix     = $table_prefix;
		$this->namespace     = 'wpsms';
		$this->subscriptions = new WP_SMS_Subscriptions();

		if ( isset( $this->options['rest_api_status'] ) ) {
			add_action( 'rest_api_init', array( &$this, 'register_routes' ) );
		}
	}

	public function register_routes() {
		register_rest_route( $this->namespace . '/v1', '/subscriber/add', array(
			'methods'  => WP_REST_Server::CREATABLE,
			'callback' => array( &$this, 'add_subscriber' ),
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
		) );
	}

	public function add_subscriber( WP_REST_Request $request ) {
		//get parameters from request
		$params = $request->get_params();

		$data = $this->subscriptions->add_subscriber( $params['name'], $params['mobile'], $params['group_id'] );

		if ( $data ) {
			return new WP_REST_Response( $data, 200 );
		} else {
			return new WP_Error( 'subscriber', __( 'Could not be added', 'wp-sms' ) );
		}
	}
}

new WP_SMS_RestApi();