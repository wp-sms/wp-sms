<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // No direct access allowed ;)

class WP_SMS_Settings {

	public $setting_name;
	public $options = array();

	public function __construct() {
		$this->setting_name = 'wpsms_settings';

		$this->options = get_option( $this->setting_name );

		if ( empty( $this->options ) ) {
			update_option( $this->setting_name, array() );
		}

		add_action( 'admin_menu', array( &$this, 'add_settings_menu' ), 11 );

		if ( isset( $_GET['page'] ) and $_GET['page'] == 'wp-sms-settings' or isset( $_POST['option_page'] ) and $_POST['option_page'] == 'wpsms_settings' ) {
			add_action( 'admin_init', array( &$this, 'register_settings' ) );
		}
	}

	/**
	 * Add WP SMS Professional Package admin page settings
	 * */
	public function add_settings_menu() {
		add_submenu_page( 'wp-sms', __( 'Settings', 'wp-sms' ), __( 'Settings', 'wp-sms' ), 'wpsms_setting', 'wp-sms-settings', array(
			&$this,
			'render_settings'
		) );
	}

	/**
	 * Gets saved settings from WP core
	 *
	 * @since           2.0
	 * @return          array
	 */
	public function get_settings() {
		$settings = get_option( $this->setting_name );
		if ( empty( $settings ) ) {
			update_option( $this->setting_name, array(//'admin_lang'	=>  'enable',
			) );
		}

		return apply_filters( 'wpsms_get_settings', $settings );
	}

	/**
	 * Registers settings in WP core
	 *
	 * @since           2.0
	 * @return          void
	 */
	public function register_settings() {
		if ( false == get_option( $this->setting_name ) ) {
			add_option( $this->setting_name );
		}

		foreach ( $this->get_registered_settings() as $tab => $settings ) {
			add_settings_section(
				'wpsms_settings_' . $tab,
				__return_null(),
				'__return_false',
				'wpsms_settings_' . $tab
			);

			if ( empty( $settings ) ) {
				return;
			}

			foreach ( $settings as $option ) {
				$name = isset( $option['name'] ) ? $option['name'] : '';

				add_settings_field(
					'wpsms_settings[' . $option['id'] . ']',
					$name,
					array( &$this, $option['type'] . '_callback' ),
					'wpsms_settings_' . $tab,
					'wpsms_settings_' . $tab,
					array(
						'id'      => isset( $option['id'] ) ? $option['id'] : null,
						'desc'    => ! empty( $option['desc'] ) ? $option['desc'] : '',
						'name'    => isset( $option['name'] ) ? $option['name'] : null,
						'section' => $tab,
						'size'    => isset( $option['size'] ) ? $option['size'] : null,
						'options' => isset( $option['options'] ) ? $option['options'] : '',
						'std'     => isset( $option['std'] ) ? $option['std'] : ''
					)
				);

				register_setting( $this->setting_name, $this->setting_name, array( &$this, 'settings_sanitize' ) );
			}
		}
	}

	/**
	 * Gets settings tabs
	 *
	 * @since               2.0
	 * @return              array Tabs list
	 */
	public function get_tabs() {
		$tabs = array(
			'general'       => __( 'General', 'wp-sms' ),
			'gateway'       => __( 'Gateway', 'wp-sms' ),
			'feature'       => __( 'Features', 'wp-sms' ),
			'notifications' => __( 'Notifications', 'wp-sms' ),
			'integration'   => __( 'Integration', 'wp-sms' ),
		);

		return $tabs;
	}

	/**
	 * Sanitizes and saves settings after submit
	 *
	 * @since               2.0
	 *
	 * @param               array $input Settings input
	 *
	 * @return              array New settings
	 */
	public function settings_sanitize( $input = array() ) {

		if ( empty( $_POST['_wp_http_referer'] ) ) {
			return $input;
		}

		parse_str( $_POST['_wp_http_referer'], $referrer );

		$settings = $this->get_registered_settings();
		$tab      = isset( $referrer['tab'] ) ? $referrer['tab'] : 'wp';

		$input = $input ? $input : array();
		$input = apply_filters( 'wpsms_settings_' . $tab . '_sanitize', $input );

		// Loop through each setting being saved and pass it through a sanitization filter
		foreach ( $input as $key => $value ) {

			// Get the setting type (checkbox, select, etc)
			$type = isset( $settings[ $tab ][ $key ]['type'] ) ? $settings[ $tab ][ $key ]['type'] : false;

			if ( $type ) {
				// Field type specific filter
				$input[ $key ] = apply_filters( 'wpsms_settings_sanitize_' . $type, $value, $key );
			}

			// General filter
			$input[ $key ] = apply_filters( 'wpsms_settings_sanitize', $value, $key );
		}

		// Loop through the whitelist and unset any that are empty for the tab being saved
		if ( ! empty( $settings[ $tab ] ) ) {
			foreach ( $settings[ $tab ] as $key => $value ) {

				// settings used to have numeric keys, now they have keys that match the option ID. This ensures both methods work
				if ( is_numeric( $key ) ) {
					$key = $value['id'];
				}

				if ( empty( $input[ $key ] ) ) {
					unset( $this->options[ $key ] );
				}

			}
		}

		// Merge our new settings with the existing
		$output = array_merge( $this->options, $input );

		add_settings_error( 'wpsms-notices', '', __( 'Settings updated', 'wp-sms' ), 'updated' );

		return $output;

	}

	/**
	 * Get settings fields
	 *
	 * @since           2.0
	 * @return          array Fields
	 */
	public function get_registered_settings() {
		$options = array(
			'enable'  => __( 'Enable', 'wp-sms' ),
			'disable' => __( 'Disable', 'wp-sms' )
		);

		$settings = apply_filters( 'wp_sms_registered_settings', array(
			// General tab
			'general'       => apply_filters( 'wp_sms_general_settings', array(
				'admin_title'         => array(
					'id'   => 'admin_title',
					'name' => __( 'Mobile', 'wp-sms' ),
					'type' => 'header'
				),
				'admin_mobile_number' => array(
					'id'   => 'admin_mobile_number',
					'name' => __( 'Admin mobile number', 'wp-sms' ),
					'type' => 'text',
					'desc' => __( 'Admin mobile number for get any sms notifications', 'wp-sms' )
				),
				'mobile_county_code'  => array(
					'id'   => 'mobile_county_code',
					'name' => __( 'Mobile country code', 'wp-sms' ),
					'type' => 'text',
					'desc' => __( 'Enter your mobile country code.', 'wp-sms' )
				),
			) ),

			// Gateway tab
			'gateway'       => apply_filters( 'wp_sms_gateway_settings', array(
				// Gateway
				'gayeway_title'             => array(
					'id'   => 'gayeway_title',
					'name' => __( 'Gateway information', 'wp-sms' ),
					'type' => 'header'
				),
				'gateway_name'              => array(
					'id'      => 'gateway_name',
					'name'    => __( 'Gateway name', 'wp-sms' ),
					'type'    => 'advancedselect',
					'options' => WP_SMS_Gateway::gateway(),
					'desc'    => __( 'Please select your gateway.', 'wp-sms' )
				),
				'gateway_help'              => array(
					'id'      => 'gateway_help',
					'name'    => __( 'Gateway description', 'wp-sms' ),
					'type'    => 'html',
					'options' => WP_SMS_Gateway::help(),
				),
				'gateway_username'          => array(
					'id'   => 'gateway_username',
					'name' => __( 'API username', 'wp-sms' ),
					'type' => 'text',
					'desc' => __( 'Enter API username of gateway', 'wp-sms' )
				),
				'gateway_password'          => array(
					'id'   => 'gateway_password',
					'name' => __( 'API password', 'wp-sms' ),
					'type' => 'text',
					'desc' => __( 'Enter API password of gateway', 'wp-sms' )
				),
				'gateway_sender_id'         => array(
					'id'   => 'gateway_sender_id',
					'name' => __( 'Sender number', 'wp-sms' ),
					'type' => 'text',
					'std'  => WP_SMS_Gateway::from(),
					'desc' => __( 'Sender number or sender ID', 'wp-sms' )
				),
				'gateway_key'               => array(
					'id'   => 'gateway_key',
					'name' => __( 'API key', 'wp-sms' ),
					'type' => 'text',
					'desc' => __( 'Enter API key of gateway', 'wp-sms' )
				),
				// Gateway status
				'gateway_status_title'      => array(
					'id'   => 'gateway_status_title',
					'name' => __( 'Gateway status', 'wp-sms' ),
					'type' => 'header'
				),
				'account_credit'            => array(
					'id'      => 'account_credit',
					'name'    => __( 'Status', 'wp-sms' ),
					'type'    => 'html',
					'options' => WP_SMS_Gateway::status(),
				),
				'account_response'          => array(
					'id'      => 'account_response',
					'name'    => __( 'Result request', 'wp-sms' ),
					'type'    => 'html',
					'options' => WP_SMS_Gateway::response(),
				),
				'bulk_send'                 => array(
					'id'      => 'bulk_send',
					'name'    => __( 'Bulk send', 'wp-sms' ),
					'type'    => 'html',
					'options' => WP_SMS_Gateway::bulk_status(),
				),
				// Account credit
				'account_credit_title'      => array(
					'id'   => 'account_credit_title',
					'name' => __( 'Account balance', 'wp-sms' ),
					'type' => 'header'
				),
				'account_credit_in_menu'    => array(
					'id'      => 'account_credit_in_menu',
					'name'    => __( 'Show in admin menu', 'wp-sms' ),
					'type'    => 'checkbox',
					'options' => $options,
					'desc'    => __( 'Show your account credit in admin menu.', 'wp-sms' )
				),
				'account_credit_in_sendsms' => array(
					'id'      => 'account_credit_in_sendsms',
					'name'    => __( 'Show in send SMS page', 'wp-sms' ),
					'type'    => 'checkbox',
					'options' => $options,
					'desc'    => __( 'Show your account credit in send SMS page.', 'wp-sms' )
				),
			) ),
			// Feature tab
			'feature'       => apply_filters( 'wp_sms_feature_settings', array(
				'mobile_field'     => array(
					'id'   => 'mobile_field',
					'name' => __( 'Mobile field', 'wp-sms' ),
					'type' => 'header'
				),
				'add_mobile_field' => array(
					'id'      => 'add_mobile_field',
					'name'    => __( 'Add Mobile number field', 'wp-sms' ),
					'type'    => 'checkbox',
					'options' => $options,
					'desc'    => __( 'Add Mobile number to user profile and register form.', 'wp-sms' )
				),
				'rest_api'         => array(
					'id'   => 'rest_api',
					'name' => __( 'REST API', 'wp-sms' ),
					'type' => 'header'
				),
				'rest_api_status'  => array(
					'id'      => 'rest_api_status',
					'name'    => __( 'Rest api status', 'wp-sms' ),
					'type'    => 'checkbox',
					'options' => $options,
					'desc'    => __( 'Add WP-SMS endpoints to the WP Rest API', 'wp-sms' )
				),
			) ),
			// Notifications tab
			'notifications' => apply_filters( 'wp_sms_notifications_settings', array(
				// Publish new post
				'notif_publish_new_post_title'           => array(
					'id'   => 'notif_publish_new_post_title',
					'name' => __( 'Published new posts', 'wp-sms' ),
					'type' => 'header'
				),
				'notif_publish_new_post'                 => array(
					'id'      => 'notif_publish_new_post',
					'name'    => __( 'Status', 'wp-sms' ),
					'type'    => 'checkbox',
					'options' => $options,
					'desc'    => __( 'Send a sms to subscribers When published new posts.', 'wp-sms' )
				),
				'notif_publish_new_post_template'        => array(
					'id'   => 'notif_publish_new_post_template',
					'name' => __( 'Message body', 'wp-sms' ),
					'type' => 'textarea',
					'desc' => __( 'Enter the contents of the sms message.', 'wp-sms' ) . '<br>' .
					          sprintf(
						          __( 'Post title: %s, Post content: %s, Post url: %s, Post date: %s', 'wp-sms' ),
						          '<code>%post_title%</code>',
						          '<code>%post_content%</code>',
						          '<code>%post_url%</code>',
						          '<code>%post_date%</code>'
					          )
				),
				// Publish new wp version
				'notif_publish_new_wpversion_title'      => array(
					'id'   => 'notif_publish_new_wpversion_title',
					'name' => __( 'The new release of WordPress', 'wp-sms' ),
					'type' => 'header'
				),
				'notif_publish_new_wpversion'            => array(
					'id'      => 'notif_publish_new_wpversion',
					'name'    => __( 'Status', 'wp-sms' ),
					'type'    => 'checkbox',
					'options' => $options,
					'desc'    => __( 'Send a sms to you When the new release of WordPress.', 'wp-sms' )
				),
				// Register new user
				'notif_register_new_user_title'          => array(
					'id'   => 'notif_register_new_user_title',
					'name' => __( 'Register a new user', 'wp-sms' ),
					'type' => 'header'
				),
				'notif_register_new_user'                => array(
					'id'      => 'notif_register_new_user',
					'name'    => __( 'Status', 'wp-sms' ),
					'type'    => 'checkbox',
					'options' => $options,
					'desc'    => __( 'Send a sms to you and user when register on wordpress.', 'wp-sms' )
				),
				'notif_register_new_user_admin_template' => array(
					'id'   => 'notif_register_new_user_admin_template',
					'name' => __( 'Message body for admin', 'wp-sms' ),
					'type' => 'textarea',
					'desc' => __( 'Enter the contents of the sms message.', 'wp-sms' ) . '<br>' .
					          sprintf(
						          __( 'User login: %s, User email: %s, Register date: %s', 'wp-sms' ),
						          '<code>%user_login%</code>',
						          '<code>%user_email%</code>',
						          '<code>%date_register%</code>'
					          )
				),
				'notif_register_new_user_template'       => array(
					'id'   => 'notif_register_new_user_template',
					'name' => __( 'Message body for user', 'wp-sms' ),
					'type' => 'textarea',
					'desc' => __( 'Enter the contents of the sms message.', 'wp-sms' ) . '<br>' .
					          sprintf(
						          __( 'User login: %s, User email: %s, Register date: %s', 'wp-sms' ),
						          '<code>%user_login%</code>',
						          '<code>%user_email%</code>',
						          '<code>%date_register%</code>'
					          )
				),
				// New comment
				'notif_new_comment_title'                => array(
					'id'   => 'notif_new_comment_title',
					'name' => __( 'New comment', 'wp-sms' ),
					'type' => 'header'
				),
				'notif_new_comment'                      => array(
					'id'      => 'notif_new_comment',
					'name'    => __( 'Status', 'wp-sms' ),
					'type'    => 'checkbox',
					'options' => $options,
					'desc'    => __( 'Send a sms to you When get a new comment.', 'wp-sms' )
				),
				'notif_new_comment_template'             => array(
					'id'   => 'notif_new_comment_template',
					'name' => __( 'Message body', 'wp-sms' ),
					'type' => 'textarea',
					'desc' => __( 'Enter the contents of the sms message.', 'wp-sms' ) . '<br>' .
					          sprintf(
						          __( 'Comment author: %s, Author email: %s, Author url: %s, Author IP: %s, Comment date: %s, Comment content: %s', 'wp-sms' ),
						          '<code>%comment_author%</code>',
						          '<code>%comment_author_email%</code>',
						          '<code>%comment_author_url%</code>',
						          '<code>%comment_author_IP%</code>',
						          '<code>%comment_date%</code>',
						          '<code>%comment_content%</code>'
					          )
				),
				// User login
				'notif_user_login_title'                 => array(
					'id'   => 'notif_user_login_title',
					'name' => __( 'User login', 'wp-sms' ),
					'type' => 'header'
				),
				'notif_user_login'                       => array(
					'id'      => 'notif_user_login',
					'name'    => __( 'Status', 'wp-sms' ),
					'type'    => 'checkbox',
					'options' => $options,
					'desc'    => __( 'Send a sms to you When user is login.', 'wp-sms' )
				),
				'notif_user_login_template'              => array(
					'id'   => 'notif_user_login_template',
					'name' => __( 'Message body', 'wp-sms' ),
					'type' => 'textarea',
					'desc' => __( 'Enter the contents of the sms message.', 'wp-sms' ) . '<br>' .
					          sprintf(
						          __( 'Username: %s, Nickname: %s', 'wp-sms' ),
						          '<code>%username_login%</code>',
						          '<code>%display_name%</code>'
					          )
				),
			) ),
			// Integration  tab
			'integration'   => apply_filters( 'wp_sms_integration_settings', array(
				// Contact form 7
				'cf7_title'                    => array(
					'id'   => 'cf7_title',
					'name' => __( 'Contact Form 7', 'wp-sms' ),
					'type' => 'header'
				),
				'cf7_metabox'                  => array(
					'id'      => 'cf7_metabox',
					'name'    => __( 'SMS meta box', 'wp-sms' ),
					'type'    => 'checkbox',
					'options' => $options,
					'desc'    => __( 'Added Wordpress SMS meta box to Contact form 7 plugin when enable this option.', 'wp-sms' )
				),
				// Woocommerce
				'wc_title'                     => array(
					'id'   => 'wc_title',
					'name' => __( 'WooCommerce', 'wp-sms' ),
					'type' => 'header'
				),
				'wc_notif_new_order'           => array(
					'id'      => 'wc_notif_new_order',
					'name'    => __( 'New order', 'wp-sms' ),
					'type'    => 'checkbox',
					'options' => $options,
					'desc'    => __( 'Send a sms to you When get new order.', 'wp-sms' )
				),
				'wc_notif_new_order_template'  => array(
					'id'   => 'wc_notif_new_order_template',
					'name' => __( 'Message body', 'wp-sms' ),
					'type' => 'textarea',
					'desc' => __( 'Enter the contents of the sms message.', 'wp-sms' ) . '<br>' .
					          sprintf(
						          __( 'Order ID: %s, Order status: %s', 'wp-sms' ),
						          '<code>%order_id%</code>',
						          '<code>%status%</code>'
					          )
				),
				// EDD
				'edd_title'                    => array(
					'id'   => 'edd_title',
					'name' => __( 'Easy Digital Downloads', 'wp-sms' ),
					'type' => 'header'
				),
				'edd_notif_new_order'          => array(
					'id'      => 'edd_notif_new_order',
					'name'    => __( 'New order', 'wp-sms' ),
					'type'    => 'checkbox',
					'options' => $options,
					'desc'    => __( 'Send a sms to you When get new order.', 'wp-sms' )
				),
				'edd_notif_new_order_template' => array(
					'id'   => 'edd_notif_new_order_template',
					'name' => __( 'Message body', 'wp-sms' ),
					'type' => 'textarea',
					'desc' => __( 'Enter the contents of the message.', 'wp-telegram-notifications' ) . '<br>' .
					          sprintf(
						          __( 'Customer email: %s, Customer name: %s, Customer last name: %s', 'wp-telegram-notifications' ),
						          '<code>%edd_email%</code>',
						          '<code>%edd_first%</code>',
						          '<code>%edd_last%</code>'
					          )
				),
			) ),
		) );

		return $settings;
	}

	public function header_callback( $args ) {
		echo '<hr/>';
	}

	public function html_callback( $args ) {
		echo $args['options'];
	}

	public function notice_callback( $args ) {
		echo $args['desc'];
	}

	public function checkbox_callback( $args ) {
		$checked = isset( $this->options[ $args['id'] ] ) ? checked( 1, $this->options[ $args['id'] ], false ) : '';
		$html    = '<input type="checkbox" id="wpsms_settings[' . $args['id'] . ']" name="wpsms_settings[' . $args['id'] . ']" value="1" ' . $checked . '/>';
		$html    .= '<label for="wpsms_settings[' . $args['id'] . ']"> ' . __( 'Active', 'wp-sms' ) . '</label>';
		$html    .= '<p class="description"> ' . $args['desc'] . '</p>';

		echo $html;
	}

	public function multicheck_callback( $args ) {
		$html = '';
		foreach ( $args['options'] as $key => $value ) {
			$option_name = $args['id'] . '-' . $key;
			$this->checkbox_callback( array(
				'id'   => $option_name,
				'desc' => $value
			) );
			echo '<br>';
		}

		echo $html;
	}

	public function radio_callback( $args ) {
		foreach ( $args['options'] as $key => $option ) :
			$checked = false;

			if ( isset( $this->options[ $args['id'] ] ) && $this->options[ $args['id'] ] == $key ) {
				$checked = true;
			} elseif ( isset( $args['std'] ) && $args['std'] == $key && ! isset( $this->options[ $args['id'] ] ) ) {
				$checked = true;
			}

			echo '<input name="wpsms_settings[' . $args['id'] . ']"" id="wpsms_settings[' . $args['id'] . '][' . $key . ']" type="radio" value="' . $key . '" ' . checked( true, $checked, false ) . '/>';
			echo '<label for="wpsms_settings[' . $args['id'] . '][' . $key . ']">' . $option . '</label>&nbsp;&nbsp;';
		endforeach;

		echo '<p class="description">' . $args['desc'] . '</p>';
	}

	public function text_callback( $args ) {
		if ( isset( $this->options[ $args['id'] ] ) and $this->options[ $args['id'] ] ) {
			$value = $this->options[ $args['id'] ];
		} else {
			$value = isset( $args['std'] ) ? $args['std'] : '';
		}

		$size = ( isset( $args['size'] ) && ! is_null( $args['size'] ) ) ? $args['size'] : 'regular';
		$html = '<input type="text" class="' . $size . '-text" id="wpsms_settings[' . $args['id'] . ']" name="wpsms_settings[' . $args['id'] . ']" value="' . esc_attr( stripslashes( $value ) ) . '"/>';
		$html .= '<p class="description"> ' . $args['desc'] . '</p>';

		echo $html;
	}

	public function number_callback( $args ) {
		if ( isset( $this->options[ $args['id'] ] ) ) {
			$value = $this->options[ $args['id'] ];
		} else {
			$value = isset( $args['std'] ) ? $args['std'] : '';
		}

		$max  = isset( $args['max'] ) ? $args['max'] : 999999;
		$min  = isset( $args['min'] ) ? $args['min'] : 0;
		$step = isset( $args['step'] ) ? $args['step'] : 1;

		$size = ( isset( $args['size'] ) && ! is_null( $args['size'] ) ) ? $args['size'] : 'regular';
		$html = '<input type="number" step="' . esc_attr( $step ) . '" max="' . esc_attr( $max ) . '" min="' . esc_attr( $min ) . '" class="' . $size . '-text" id="wpsms_settings[' . $args['id'] . ']" name="wpsms_settings[' . $args['id'] . ']" value="' . esc_attr( stripslashes( $value ) ) . '"/>';
		$html .= '<p class="description"> ' . $args['desc'] . '</p>';

		echo $html;
	}

	public function textarea_callback( $args ) {
		if ( isset( $this->options[ $args['id'] ] ) ) {
			$value = $this->options[ $args['id'] ];
		} else {
			$value = isset( $args['std'] ) ? $args['std'] : '';
		}

		$size = ( isset( $args['size'] ) && ! is_null( $args['size'] ) ) ? $args['size'] : 'regular';
		$html = '<textarea class="large-text" cols="50" rows="5" id="wpsms_settings[' . $args['id'] . ']" name="wpsms_settings[' . $args['id'] . ']">' . esc_textarea( stripslashes( $value ) ) . '</textarea>';
		$html .= '<p class="description"> ' . $args['desc'] . '</p>';

		echo $html;
	}

	public function password_callback( $args ) {
		if ( isset( $this->options[ $args['id'] ] ) ) {
			$value = $this->options[ $args['id'] ];
		} else {
			$value = isset( $args['std'] ) ? $args['std'] : '';
		}

		$size = ( isset( $args['size'] ) && ! is_null( $args['size'] ) ) ? $args['size'] : 'regular';
		$html = '<input type="password" class="' . $size . '-text" id="wpsms_settings[' . $args['id'] . ']" name="wpsms_settings[' . $args['id'] . ']" value="' . esc_attr( $value ) . '"/>';
		$html .= '<p class="description"> ' . $args['desc'] . '</p>';

		echo $html;
	}

	public function missing_callback( $args ) {
		echo '&ndash;';

		return false;
	}


	public function select_callback( $args ) {
		if ( isset( $this->options[ $args['id'] ] ) ) {
			$value = $this->options[ $args['id'] ];
		} else {
			$value = isset( $args['std'] ) ? $args['std'] : '';
		}

		$html = '<select id="wpsms_settings[' . $args['id'] . ']" name="wpsms_settings[' . $args['id'] . ']"/>';

		foreach ( $args['options'] as $option => $name ) :
			$selected = selected( $option, $value, false );
			$html     .= '<option value="' . $option . '" ' . $selected . '>' . $name . '</option>';
		endforeach;

		$html .= '</select>';
		$html .= '<p class="description"> ' . $args['desc'] . '</p>';

		echo $html;
	}

	public function advancedselect_callback( $args ) {
		if ( isset( $this->options[ $args['id'] ] ) ) {
			$value = $this->options[ $args['id'] ];
		} else {
			$value = isset( $args['std'] ) ? $args['std'] : '';
		}

		if ( is_rtl() ) {
			$class_name = 'chosen-select chosen-rtl';
		} else {
			$class_name = 'chosen-select';
		}

		$html = '<select class="' . $class_name . '" id="wpsms_settings[' . $args['id'] . ']" name="wpsms_settings[' . $args['id'] . ']"/>';

		foreach ( $args['options'] as $key => $v ) {
			$html .= '<optgroup label="' . ucfirst( str_replace( '_', ' ', $key ) ) . '">';

			foreach ( $v as $option => $name ) :
				$disabled = ( $key == 'pro_pack_gateways' ) ? $disabled = ' disabled' : '';
				$selected = selected( $option, $value, false );
				$html     .= '<option value="' . $option . '" ' . $selected . ' ' . $disabled . '>' . ucfirst( $name ) . '</option>';
			endforeach;

			$html .= '</optgroup>';
		}

		$html .= '</select>';
		$html .= '<p class="description"> ' . $args['desc'] . '</p>';

		echo $html;
	}

	public function color_select_callback( $args ) {
		if ( isset( $this->options[ $args['id'] ] ) ) {
			$value = $this->options[ $args['id'] ];
		} else {
			$value = isset( $args['std'] ) ? $args['std'] : '';
		}

		$html = '<select id="wpsms_settings[' . $args['id'] . ']" name="wpsms_settings[' . $args['id'] . ']"/>';

		foreach ( $args['options'] as $option => $color ) :
			$selected = selected( $option, $value, false );
			$html     .= '<option value="' . $option . '" ' . $selected . '>' . $color['label'] . '</option>';
		endforeach;

		$html .= '</select>';
		$html .= '<p class="description"> ' . $args['desc'] . '</p>';

		echo $html;
	}

	public function rich_editor_callback( $args ) {
		global $wp_version;

		if ( isset( $this->options[ $args['id'] ] ) ) {
			$value = $this->options[ $args['id'] ];
		} else {
			$value = isset( $args['std'] ) ? $args['std'] : '';
		}

		if ( $wp_version >= 3.3 && function_exists( 'wp_editor' ) ) {
			$html = wp_editor( stripslashes( $value ), 'wpsms_settings[' . $args['id'] . ']', array( 'textarea_name' => 'wpsms_settings[' . $args['id'] . ']' ) );
		} else {
			$html = '<textarea class="large-text" rows="10" id="wpsms_settings[' . $args['id'] . ']" name="wpsms_settings[' . $args['id'] . ']">' . esc_textarea( stripslashes( $value ) ) . '</textarea>';
		}

		$html .= '<p class="description"> ' . $args['desc'] . '</p>';

		echo $html;
	}

	public function upload_callback( $args ) {
		if ( isset( $this->options[ $args['id'] ] ) ) {
			$value = $this->options[ $args['id'] ];
		} else {
			$value = isset( $args['std'] ) ? $args['std'] : '';
		}

		$size = ( isset( $args['size'] ) && ! is_null( $args['size'] ) ) ? $args['size'] : 'regular';
		$html = '<input type="text" class="' . $size . '-text wpsms_upload_field" id="wpsms_settings[' . $args['id'] . ']" name="wpsms_settings[' . $args['id'] . ']" value="' . esc_attr( stripslashes( $value ) ) . '"/>';
		$html .= '<span>&nbsp;<input type="button" class="wpsms_settings_upload_button button-secondary" value="' . __( 'Upload File', 'wpsms' ) . '"/></span>';
		$html .= '<p class="description"> ' . $args['desc'] . '</p>';

		echo $html;
	}

	public function color_callback( $args ) {
		if ( isset( $this->options[ $args['id'] ] ) ) {
			$value = $this->options[ $args['id'] ];
		} else {
			$value = isset( $args['std'] ) ? $args['std'] : '';
		}

		$default = isset( $args['std'] ) ? $args['std'] : '';

		$size = ( isset( $args['size'] ) && ! is_null( $args['size'] ) ) ? $args['size'] : 'regular';
		$html = '<input type="text" class="wpsms-color-picker" id="wpsms_settings[' . $args['id'] . ']" name="wpsms_settings[' . $args['id'] . ']" value="' . esc_attr( $value ) . '" data-default-color="' . esc_attr( $default ) . '" />';
		$html .= '<p class="description"> ' . $args['desc'] . '</p>';

		echo $html;
	}

	public function render_settings() {
		$active_tab = isset( $_GET['tab'] ) && array_key_exists( $_GET['tab'], $this->get_tabs() ) ? $_GET['tab'] : 'general';

		ob_start();
		?>
        <div class="wrap wpsms-settings-wrap">
			<?php do_action( 'wp_sms_settings_page' ); ?>
            <h2><?php _e( 'Settings', 'wp-sms' ) ?></h2>
            <div class="wpsms-tab-group">
                <ul class="wpsms-tab">
                    <li id="wpsms-logo">
                        <img src="<?php echo WP_SMS_DIR_PLUGIN; ?>assets/images/logo-250.png"/>
                        <p><?php echo sprintf( __( 'WP-SMS v%s', 'wp-sms' ), WP_SMS_VERSION ); ?></p>
						<?php do_action( 'wp_sms_after_setting_logo' ); ?>
                    </li>
					<?php
					foreach ( $this->get_tabs() as $tab_id => $tab_name ) {

						$tab_url = add_query_arg( array(
							'settings-updated' => false,
							'tab'              => $tab_id
						) );

						$active = $active_tab == $tab_id ? 'active' : '';

						echo '<li><a href="' . esc_url( $tab_url ) . '" title="' . esc_attr( $tab_name ) . '" class="' . $active . '">';
						echo $tab_name;
						echo '</a></li>';
					}
					?>
                </ul>
				<?php echo settings_errors( 'wpsms-notices' ); ?>
                <div class="wpsms-tab-content">
                    <form method="post" action="options.php">
                        <table class="form-table">
							<?php
							settings_fields( $this->setting_name );
							do_settings_fields( 'wpsms_settings_' . $active_tab, 'wpsms_settings_' . $active_tab );
							?>
                        </table>
						<?php submit_button(); ?>
                    </form>
                </div>
            </div>
        </div>
		<?php
		echo ob_get_clean();
	}
}

new WP_SMS_Settings();