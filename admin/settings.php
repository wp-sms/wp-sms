<?php

/**
 * Setting class
 * 
 * @package WP_SMS
 */
if ( !class_exists('WP_SMS_Settings' ) ):
class WP_SMS_Settings {

	private $settings_api;

	/**
	 * Options
	 * @var array
	 */
	public $options = array();

	public function __construct() {
		$this->settings_api = new WP_SMS_Settings_API;

		add_action( 'admin_init', array($this, 'admin_init') );
		add_action( 'admin_menu', array($this, 'admin_menu') );

		// Set options
		$this->options['wpsms_general'] = get_option('wpsms_general');
		$this->options['wpsms_gateway'] = get_option('wpsms_gateway');
		$this->options['wpsms_features'] = get_option('wpsms_features');
		$this->options['wpsms_notifications'] = get_option('wpsms_notifications');

		/**
		 * wpsms_options action
		 *
		 * @since 4.0.0
		 * @param array $wpsms_options
		 */
		do_action('wpsms_options', $this->options);

		// Set global option variable
		$GLOBALS['wp_sms_options'] = $this->options;
	}

	public function admin_init() {
		//set the settings
		$this->settings_api->set_sections( $this->get_settings_sections() );
		$this->settings_api->set_fields( $this->get_settings_fields() );

		//initialize settings
		$this->settings_api->admin_init();
	}

	public function admin_menu() {
		add_submenu_page( 'wp-sms', __('Setting', 'wp-sms'), __('Setting', 'wp-sms'), 'wpsms_setting', 'wp-sms-setting', array($this, 'plugin_page'));
	}

	public function get_settings_sections() {
		$sections = array(
			array(
				'id'    => 'wpsms_general',
				'title' => __( 'General', 'wp-sms' )
			),
			array(
				'id'    => 'wpsms_gateway',
				'title' => __( 'Gateways', 'wp-sms' )
			),
			array(
				'id'    => 'wpsms_features',
				'title' => __( 'Features', 'wp-sms' )
			),
			array(
				'id'    => 'wpsms_notifications',
				'title' => __( 'Notifications', 'wp-sms' )
			)
		);

		return $sections;
	}

	/**
	 * Returns all the settings fields
	 *
	 * @return array settings fields
	 */
	public function get_settings_fields() {

		// General fields
		$settings_fields['wpsms_general'] = array(
			array(
				'name'    => 'mobile_number',
				'label'   => __( 'Mobile Number', 'wp-sms' ),
				'desc'    => __( 'Your mobile number', 'wp-sms' ),
				'type'    => 'text',
				'default' => '',
			),
			array(
				'name'    => 'country_code',
				'label'   => __( 'Mobile country code', 'wp-sms' ),
				'desc'    => __( 'Your mobile country code', 'wp-sms' ),
				'type'    => 'text',
				'default' => '',
			),
		);

		// Gateways fields
		$settings_fields['wpsms_gateway']['sms_gateway'] = array(
			'name'    => 'gateway',
			'label'   => __( 'SMS Gateway', 'wp-sms' ),
			'desc'    => __( 'Please select your sms gateway', 'wp-sms' ),
			'type'    => 'select',
			'default' => 'no',
			'options' => array(),
		);

		// Check gateway exists
		if( isset($this->options['wpsms_gateway']['gateway']) and $this->options['wpsms_gateway']['gateway'] != 'none' ) {

			// Gateways field (username)
			$settings_fields['wpsms_gateway']['username'] = array(
				'name'    => 'gateway_username',
				'label'   => __( 'Gateway username', 'wp-sms' ),
				'desc'    => __( 'Please enter gateway username', 'wp-sms' ),
				'type'    => 'text',
				'default' => '',
			);

			// Gateways field (password)
			$settings_fields['wpsms_gateway']['password'] = array(
				'name'    => 'gateway_password',
				'label'   => __( 'Gateway password', 'wp-sms' ),
				'desc'    => __( 'Please enter gateway password', 'wp-sms' ),
				'type'    => 'text',
				'default' => '',
			);

			// Gateways field (api_key)
			$settings_fields['wpsms_gateway']['api_key'] = array(
				'name'    => 'gateway_api_key',
				'label'   => __( 'Gateway API key', 'wp-sms' ),
				'desc'    => __( 'Please enter  gateway api key', 'wp-sms' ),
				'type'    => 'text',
				'default' => '',
			);

			// Gateways field (sender_id)
			$settings_fields['wpsms_gateway']['sender_id'] = array(
				'name'    => 'sender_id',
				'label'   => __( 'Gateway sender id', 'wp-sms' ),
				'desc'    => __( 'Please enter gateway sender ID', 'wp-sms' ),
				'type'    => 'text',
				'default' => '',
			);

		}

		// Features fields
		$settings_fields['wpsms_features'] = array(
			array(
				'name'    => 'add_mobile_field',
				'label'   => __( 'Add mobile field', 'wp-sms' ),
				'desc'    => __( 'Add mobile field to users profile', 'wp-sms' ),
				'type'    => 'checkbox',
				'default' => '',
			),
			array(
				'name'    => 'enable_sms_login',
				'label'   => __( 'Login with sms', 'wp-sms' ),
				'desc'    => __( 'Login to wordpress profile with sms', 'wp-sms' ),
				'type'    => 'checkbox',
				'default' => '',
				'disable' => true,
				'premium' => true,
			),
		);

		// Notifications fields
		$settings_fields['wpsms_notifications'] = array(
			array(
				'name'    => 'subscribers_newpost_header', // Header
				'label'   => __( 'Published new posts', 'wp-sms' ),
				'type'    => 'header',
			),
			array(
				'name'    => 'subscribers_newpost',
				'label'   => __( 'Status', 'wp-sms' ),
				'desc'    => __( 'Send a sms to subscribers When published new posts', 'wp-sms' ),
				'type'    => 'checkbox2',
				'default' => '',
			),
			array(
				'name'    => 'subscribers_newpost_template',
				'label'   => __( 'Text template', 'wp-sms' ),
				'desc'    => $this->render_input_data( array('post_title', 'post_url', 'post_date') ),
				'placeholder' => __( 'Enter the contents of the sms message', 'wp-sms' ),
				'type'        => 'textarea'
			),
			array(
				'name'    => 'wordpress_newversion_header', // Header
				'label'   => __( 'The new release of WordPress', 'wp-sms' ),
				'type'    => 'header',
			),
			array(
				'name'    => 'wordpress_newversion',
				'label'   => __( 'Status', 'wp-sms' ),
				'desc'    => __( 'Send a sms to you When the new release of WordPress', 'wp-sms' ),
				'type'    => 'checkbox2',
				'default' => '',
			),
			array(
				'name'    => 'register_newuser_header', // Header
				'label'   => __( 'Register a new user', 'wp-sms' ),
				'type'    => 'header',
			),
			array(
				'name'    => 'register_newuser',
				'label'   => __( 'Status', 'wp-sms' ),
				'desc'    => __( 'Send a sms to you and user when register on wordpress', 'wp-sms' ),
				'type'    => 'checkbox2',
				'default' => '',
			),
			array(
				'name'    => 'register_newuser_admin_template',
				'label'   => __( 'Text template for admin', 'wp-sms' ),
				'desc'    => $this->render_input_data( array('user_login', 'user_email', 'register_date') ),
				'placeholder' => __( 'Enter the contents of the sms message', 'wp-sms' ),
				'type'        => 'textarea'
			),
			array(
				'name'    => 'register_newuser_user_template',
				'label'   => __( 'Text template for user', 'wp-sms' ),
				'desc'    => $this->render_input_data( array('user_login', 'user_email', 'register_date') ),
				'placeholder' => __( 'Enter the contents of the sms message', 'wp-sms' ),
				'type'        => 'textarea'
			),
			array(
				'name'    => 'insert_newcomment_header', // Header
				'label'   => __( 'New comment', 'wp-sms' ),
				'type'    => 'header',
			),
			array(
				'name'    => 'insert_newcomment',
				'label'   => __( 'Status', 'wp-sms' ),
				'desc'    => __( 'Send a sms to you When get a new comment', 'wp-sms' ),
				'type'    => 'checkbox2',
				'default' => '',
			),
			array(
				'name'    => 'insert_newcomment_template',
				'label'   => __( 'Text template', 'wp-sms' ),
				'desc'    => $this->render_input_data( array('comment_author', 'comment_author_email', 'comment_author_url', 'comment_author_IP', 'comment_date', 'comment_content') ),
				'placeholder' => __( 'Enter the contents of the sms message', 'wp-sms' ),
				'type'        => 'textarea'
			),
			array(
				'name'    => 'user_login_header', // Header
				'label'   => __( 'User login', 'wp-sms' ),
				'type'    => 'header',
			),
			array(
				'name'    => 'user_login',
				'label'   => __( 'Status', 'wp-sms' ),
				'desc'    => __( 'Send a sms to you When user is login', 'wp-sms' ),
				'type'    => 'checkbox2',
				'default' => '',
			),
			array(
				'name'    => 'user_login_template',
				'label'   => __( 'Text template', 'wp-sms' ),
				'desc'    => $this->render_input_data( array('username_login', 'display_name') ),
				'placeholder' => __( 'Enter the contents of the sms message', 'wp-sms' ),
				'type'        => 'textarea'
			),
		);

		/**
		 * wpsms_settings_fields filter
		 *
		 * @since 4.0.0
		 * @param array $wpsms_settings_fields
		 */
		$settings_fields = apply_filters('wpsms_settings_fields', $settings_fields);

		return $settings_fields;
	}

	public function plugin_page() {
		echo '<div class="wrap">';

		$this->settings_api->show_navigation();
		$this->settings_api->show_forms();

		echo '</div>';
	}

	/**
	 * Get all the pages
	 *
	 * @return array page names with key value pairs
	 */
	public function get_pages() {
		$pages = get_pages();
		$pages_options = array();
		if ( $pages ) {
			foreach ($pages as $page) {
				$pages_options[$page->ID] = $page->post_title;
			}
		}

		return $pages_options;
	}

	/**
	 * Rendering input data
	 * 
	 * @param  array $inputs Input data
	 */
	private function render_input_data($inputs) {

		foreach ($inputs as $value) {
			switch ($value) {
				case 'post_title': $data[] = __('Post title', 'wp-sms') . ' : ' . '<code>%post_title%</code>'; break;
				case 'post_url': $data[] = __('Post URL', 'wp-sms') . ' : ' . '<code>%post_url%</code>'; break;
				case 'post_date': $data[] = __('Post date', 'wp-sms') . ' : ' . '<code>%post_date%</code>'; break;
				case 'user_login': $data[] = __('Username', 'wp-sms') . ' : ' . '<code>%user_login%</code>'; break;
				case 'user_email': $data[] = __('User email', 'wp-sms') . ' : ' . '<code>%user_email%</code>'; break;
				case 'register_date': $data[] = __('Register date', 'wp-sms') . ' : ' . '<code>%register_date%</code>'; break;
				case 'comment_author': $data[] = __('Comment author', 'wp-sms') . ' : ' . '<code>%comment_author%</code>'; break;
				case 'comment_author_email': $data[] = __('Comment author email', 'wp-sms') . ' : ' . '<code>%comment_author_email%</code>'; break;
				case 'comment_author_url': $data[] = __('Comment author url', 'wp-sms') . ' : ' . '<code>%comment_author_url%</code>'; break;
				case 'comment_author_IP': $data[] = __('Comment author IP', 'wp-sms') . ' : ' . '<code>%comment_author_IP%</code>'; break;
				case 'comment_date': $data[] = __('Comment date', 'wp-sms') . ' : ' . '<code>%comment_date%</code>'; break;
				case 'comment_content': $data[] = __('Comment content', 'wp-sms') . ' : ' . '<code>%comment_content%</code>'; break;
				case 'username_login': $data[] = __('Username', 'wp-sms') . ' : ' . '<code>%username_login%</code>'; break;
				case 'display_name': $data[] = __('Display name', 'wp-sms') . ' : ' . '<code>%display_name%</code>'; break;
				default: $data[] = ''; break;
			}
		}

		return sprintf(__('Input data: %s', 'wp-sms'), implode( ', ', $data ));
	}

}
endif;
