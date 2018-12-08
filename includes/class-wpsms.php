<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

/**
 * Get plugin options
 */
$wpsms_option = get_option( 'wpsms_settings' );

/**
 * Initial gateway
 */
include_once WP_SMS_DIR . 'includes/functions.php';
$sms = initial_gateway();


class WP_SMS {

	/**
	 * Plugin instance.
	 *
	 * @see get_instance()
	 * @type object
	 */
	protected static $instance = null;

	/**
	 * Wordpress Admin url
	 *
	 * @var string
	 */
	public $admin_url = WP_SMS_ADMIN_URL;

	/**
	 * WP SMS gateway object
	 *
	 * @var string
	 */
	public $sms;

	/**
	 * WP SMS subscribe object
	 *
	 * @var string
	 */
	public $subscribe;

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
	 * Options
	 *
	 * @var string
	 */
	protected $options;


	public function __construct() {
		/*
		 * Plugin Loaded Action
		 */
		add_action( 'plugins_loaded', array( $this, 'plugin_setup' ) );

		/**
		 * Install plugin
		 */
		register_activation_hook( __FILE__, array( $this, 'install' ) );

		/**
		 * Upgrade plugin
		 */
		register_activation_hook( __FILE__, array( $this, 'upgrade' ) );
	}

	/**
	 * Constructors plugin Setup
	 *
	 * @param  Not param
	 */
	public function plugin_setup() {
		global $wpdb, $table_prefix, $wpsms_option, $sms;

		$this->db        = $wpdb;
		$this->tb_prefix = $table_prefix;
		$this->options   = $wpsms_option;

		// Load text domain
		add_action( 'init', array( $this, 'load_textdomain' ) );

		$this->includes();
		$this->sms = $sms;
		$this->init();
		$this->subscribe = new WP_SMS\Newsletter;

		add_action( 'admin_enqueue_scripts', array( $this, 'admin_assets' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'front_assets' ) );

		add_action( 'admin_bar_menu', array( $this, 'adminbar' ) );
		add_action( 'dashboard_glance_items', array( $this, 'dashboard_glance' ) );
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_filter( 'plugin_row_meta', array( $this, 'meta_links' ), 0, 2 );
		add_action( 'widgets_init', array( $this, 'register_widget' ) );

		// WordPress Multisite
		add_action( 'wpmu_new_blog', array( $this, 'add_table_on_create_blog' ), 10, 1 );
		add_filter( 'wpmu_drop_tables', array( $this, 'remove_table_on_delete_blog' ) );

		add_filter( 'wp_sms_to', array( $this, 'modify_bulk_send' ) );

	}

	/**
	 * Load plugin textdomain.
	 *
	 * @since 1.0.0
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'wp-sms', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	}

	/**
	 * Creating plugin tables
	 *
	 * @param  Not param
	 */
	static function install( $network_wide ) {
		global $wp_sms_db_version;

		include_once WP_SMS_DIR . 'includes/install.php';
		$install = new WP_SMS_INSTALL;
		$install->create_table( $network_wide );

		add_option( 'wp_sms_db_version', WP_SMS_VERSION );

		// Delete notification new wp_version option
		delete_option( 'wp_notification_new_wp_version' );
	}


	/**
	 * Upgrade plugin requirements if needed
	 */
	static function upgrade() {
		include_once WP_SMS_DIR . 'includes/upgrade.php';
	}

	/**
	 * Creating Table for New Blog in wordpress
	 */
	public function add_table_on_create_blog( $blog_id ) {
		if ( is_plugin_active_for_network( 'wp-sms/wp-sms.php' ) ) {
			switch_to_blog( $blog_id );

			include_once WP_SMS_DIR . 'includes/install.php';

			$install = new WP_SMS_INSTALL;
			$install->table_sql();

			restore_current_blog();
		}
	}

	/**
	 * Remove Table On Delete Blog Wordpress
	 */
	public function remove_table_on_delete_blog( $tables ) {
		global $wpdb;
		foreach ( array( 'sms_subscribes', 'sms_subscribes_group', 'sms_send' ) as $tbl ) {
			$tables[] = $wpdb->prefix . $tbl;
		}

		return $tables;
	}

	/**
	 * Adding new capability in the plugin
	 *
	 * @param  Not param
	 */
	public function add_cap() {
		// Get administrator role
		$role = get_role( 'administrator' );

		$role->add_cap( 'wpsms_sendsms' );
		$role->add_cap( 'wpsms_outbox' );
		$role->add_cap( 'wpsms_subscribers' );
		$role->add_cap( 'wpsms_setting' );
	}

	/**
	 * Includes plugin files
	 *
	 * @param  Not param
	 */
	public function includes() {
		if ( is_admin() ) {
			// Admin classes.
			require_once WP_SMS_DIR . 'includes/admin/class-wpsms-privacy.php';
			require_once WP_SMS_DIR . 'includes/admin/class-wpsms-version.php';

			// Groups class.
			require_once WP_SMS_DIR . 'includes/admin/groups/class-wpsms-groups-table-edit.php';

			// Send class.
			require_once WP_SMS_DIR . 'includes/admin/send/class-wpsms-send.php';

			// Settings classes.
			require_once WP_SMS_DIR . 'includes/admin/settings/class-wpsms-settings.php';
			require_once WP_SMS_DIR . 'includes/admin/settings/class-wpsms-settings-pro.php';

			// Subscribers class.
			require_once WP_SMS_DIR . 'includes/admin/subscribers/class-wpsms-subscribers-table-edit.php';
		}

		// Utility classes.
		require_once WP_SMS_DIR . 'includes/class-wpsms-gateway.php';
		require_once WP_SMS_DIR . 'includes/class-wpsms-features.php';
		require_once WP_SMS_DIR . 'includes/class-wpsms-notifications.php';
		require_once WP_SMS_DIR . 'includes/class-wpsms-integrations.php';
		require_once WP_SMS_DIR . 'includes/class-wpsms-gravityforms.php';
		require_once WP_SMS_DIR . 'includes/class-wpsms-quform.php';
		require_once WP_SMS_DIR . 'includes/class-wpsms-newsletter.php';
		require_once WP_SMS_DIR . 'includes/class-wpsms-widget.php';
		require_once WP_SMS_DIR . 'includes/class-wpsms-rest-api.php';
		require_once WP_SMS_DIR . 'includes/class-wpsms-shortcode.php';

		// API class.
		require_once WP_SMS_DIR . 'includes/api/v1/class-wpsms-api-newsletter.php';
	}

	/**
	 * Initial plugin
	 *
	 * @param  Not param
	 */
	private function init() {
		if ( isset( $_GET['action'] ) ) {
			if ( $_GET['action'] == 'wpsms-hide-newsletter' ) {
				update_option( 'wpsms_hide_newsletter', true );
			}
		}

		if ( ! get_option( 'wpsms_hide_newsletter' ) ) {
			add_action( 'wp_sms_settings_page', array( $this, 'admin_newsletter' ) );
		}

		// Check exists require function
		if ( ! function_exists( 'wp_get_current_user' ) ) {
			include( ABSPATH . "wp-includes/pluggable.php" );
		}

		// Add plugin caps to admin role
		if ( is_admin() and is_super_admin() ) {
			$this->add_cap();
		}
	}

	/**
	 * Include admin assets
	 *
	 * @param  Not param
	 */
	public function admin_assets() {
		if ( stristr( get_current_screen()->id, "wp-sms" ) ) {
			wp_register_style( 'wpsms-admin-css', WP_SMS_URL . 'assets/css/admin.css', true, '1.3' );
			wp_enqueue_style( 'wpsms-admin-css' );

			wp_enqueue_style( 'wpsms-chosen-css', WP_SMS_URL . 'assets/css/chosen.min.css', true, '1.2.0' );
			wp_enqueue_script( 'wpsms-chosen-js', WP_SMS_URL . 'assets/js/chosen.jquery.min.js', true, '1.2.0' );
			wp_enqueue_script( 'wpsms-word-and-character-counter-js', WP_SMS_URL . 'assets/js/jquery.word-and-character-counter.min.js', true, '2.5.0' );
			wp_enqueue_script( 'wpsms-admin-js', WP_SMS_URL . 'assets/js/admin.js', true, '1.2.0' );
		}
	}

	/**
	 * Include front table
	 *
	 * @param  Not param
	 */
	public function front_assets() {
		global $wpsms_option;

		// Check if Disable Style in frontend is active or not
		if ( empty( $wpsms_option['disable_style_in_front'] ) or ( isset( $wpsms_option['disable_style_in_front'] ) and ! $wpsms_option['disable_style_in_front'] ) ) {
			wp_register_style( 'wpsms-subscribe', WP_SMS_URL . 'assets/css/subscribe.css', true, '1.1' );
			wp_enqueue_style( 'wpsms-subscribe' );
		}
	}

	/**
	 * Show Admin Wordpress Ui Notice
	 *
	 * @param string $text where Show Text Notification
	 * @param string $model Type Of Model from list : error / warning / success / info
	 * @param boolean $close_button Check Show close Button Or false for not
	 * @param  boolean $echo Check Echo or return in function
	 * @param string $style_extra add extra Css Style To Code
	 *
	 * @author Mehrshad Darzi
	 * @return string Wordpress html Notice code
	 */
	public static function wp_admin_notice( $text, $model = "info", $close_button = true, $echo = true, $style_extra = 'padding:12px;' ) {
		$text = '
        <div class="notice notice-' . $model . '' . ( $close_button === true ? " is-dismissible" : "" ) . '">
           <div style="' . $style_extra . '">' . $text . '</div>
        </div>
        ';
		if ( $echo ) {
			echo $text;
		} else {
			return $text;
		}
	}

	/**
	 * Admin bar plugin
	 *
	 * @param  Not param
	 */
	public function adminbar() {
		global $wp_admin_bar, $wpsms_option;

		if ( is_super_admin() && is_admin_bar_showing() ) {
			if ( get_option( 'wp_last_credit' ) && isset( $wpsms_option['account_credit_in_menu'] ) ) {
				$wp_admin_bar->add_menu( array(
					'id'    => 'wp-credit-sms',
					'title' => '<span class="ab-icon"></span>' . get_option( 'wp_last_credit' ),
					'href'  => $this->admin_url . '/admin.php?page=wp-sms-settings',
				) );
			}

			$wp_admin_bar->add_menu( array(
				'id'     => 'wp-send-sms',
				'parent' => 'new-content',
				'title'  => __( 'SMS', 'wp-sms' ),
				'href'   => $this->admin_url . '/admin.php?page=wp-sms'
			) );
		}
	}

	/**
	 * Dashboard glance plugin
	 *
	 * @param  Not param
	 */
	public function dashboard_glance() {
		$subscribe = $this->db->get_var( "SELECT COUNT(*) FROM {$this->tb_prefix}sms_subscribes" );
		echo "<li class='wpsms-subscribe-count'><a href='" . $this->admin_url . "admin.php?page=wp-sms-subscribers'>" . sprintf( __( '%s Subscriber', 'wp-sms' ), $subscribe ) . "</a></li>";
		echo "<li class='wpsms-credit-count'><a href='" . $this->admin_url . "admin.php?page=wp-sms-settings&tab=web-service'>" . sprintf( __( '%s SMS Credit', 'wp-sms' ), get_option( 'wp_last_credit' ) ) . "</a></li>";
	}

	/**
	 * Administrator admin_menu
	 *
	 * @param  Not param
	 */
	public function admin_menu() {
		global $wpsms_option;
		add_menu_page( __( 'SMS', 'wp-sms' ), __( 'SMS', 'wp-sms' ), 'wpsms_sendsms', 'wp-sms', array(
			WP_SMS_Send::class,
			'send_page'
		), 'dashicons-email-alt' );
		add_submenu_page( 'wp-sms', __( 'Send SMS', 'wp-sms' ), __( 'Send SMS', 'wp-sms' ), 'wpsms_sendsms', 'wp-sms', array(
			WP_SMS_Send::class,
			'send_page'
		) );
		add_submenu_page( 'wp-sms', __( 'Outbox', 'wp-sms' ), __( 'Outbox', 'wp-sms' ), 'wpsms_outbox', 'wp-sms-outbox', array(
			$this,
			'outbox_page'
		) );
		add_submenu_page( 'wp-sms', __( 'Subscribers', 'wp-sms' ), __( 'Subscribers', 'wp-sms' ), 'wpsms_subscribers', 'wp-sms-subscribers', array(
			$this,
			'subscribe_page'
		) );
		add_submenu_page( 'wp-sms', __( 'Groups', 'wp-sms' ), __( 'Groups', 'wp-sms' ), 'wpsms_subscribers', 'wp-sms-subscribers-group', array(
			$this,
			'groups_page'
		) );
		if ( isset( $wpsms_option['gdpr_compliance'] ) and $wpsms_option['gdpr_compliance'] == 1 ) {
			add_submenu_page( 'wp-sms', __( 'Privacy', 'wp-sms' ), __( 'Privacy', 'wp-sms' ), 'manage_options', 'wp-sms-subscribers-privacy', array(
				$this,
				'privacy_page'
			) );
		}
	}

	public function meta_links( $links, $file ) {
		if ( $file == 'wp-sms/wp-sms.php' ) {
			$rate_url = 'http://wordpress.org/support/view/plugin-reviews/wp-sms?rate=5#postform';
			$links[]  = '<a href="' . $rate_url . '" target="_blank" class="wpsms-plugin-meta-link" title="' . __( 'Click here to rate and review this plugin on WordPress.org', 'wp-sms' ) . '">' . __( 'Rate this plugin', 'wp-sms' ) . '</a>';

			$newsletter_url = WP_SMS_SITE . '/newsletter';
			$links[]        = '<a href="' . $newsletter_url . '" target="_blank" class="wpsms-plugin-meta-link" title="' . __( 'Click here to rate and review this plugin on WordPress.org', 'wp-sms' ) . '">' . __( 'Subscribe to our Email Newsletter', 'wp-sms' ) . '</a>';
		}

		return $links;
	}

	/**
	 * Register widget
	 */
	public function register_widget() {
		register_widget( '\WP_SMS\Widget' );
	}

	/**
	 * Modify destination number
	 *
	 * @param  array $to
	 *
	 * @return array/string
	 */
	public function modify_bulk_send( $to ) {
		if ( ! $this->sms->bulk_send ) {
			return array( $to[0] );
		}

		return $to;
	}


	/**
	 * Outbox sms admin page
	 *
	 * @param  Not param
	 */
	public function outbox_page() {
		include_once WP_SMS_DIR . 'includes/admin/outbox/class-wpsms-outbox.php';

		//Create an instance of our package class...
		$list_table = new WP_SMS_Outbox_List_Table();

		//Fetch, prepare, sort, and filter our data...
		$list_table->prepare_items();

		include_once WP_SMS_DIR . "includes/admin/outbox/outbox.php";
	}

	/**
	 * Subscribe admin page
	 *
	 * @param  Not param
	 */
	public function subscribe_page() {

		// Add subscriber page

		if ( isset( $_POST['wp_add_subscribe'] ) ) {
			$result = $this->subscribe->add_subscriber( $_POST['wp_subscribe_name'], $_POST['wp_subscribe_mobile'], $_POST['wpsms_group_name'] );
			echo $this->notice_result( $result['result'], $result['message'] );
		}

		// Edit subscriber page
		if ( isset( $_POST['wp_update_subscribe'] ) ) {
			$result = $this->subscribe->update_subscriber( $_POST['ID'], $_POST['wp_subscribe_name'], $_POST['wp_subscribe_mobile'], $_POST['wpsms_group_name'], $_POST['wpsms_subscribe_status'] );
			echo $this->notice_result( $result['result'], $result['message'] );
		}

		// Import subscriber page
		if ( isset( $_POST['wps_import'] ) ) {
			include_once WP_SMS_DIR . "includes/admin/import.php";
		}

		include_once WP_SMS_DIR . 'includes/admin/subscribers/class-wpsms-subscribers-table.php';

		//Create an instance of our package class...
		$list_table = new WP_SMS_Subscribers_List_Table();

		//Fetch, prepare, sort, and filter our data...
		$list_table->prepare_items();

		include_once WP_SMS_DIR . "includes/admin/subscribers/subscribers.php";
	}

	/**
	 * Subscribe groups admin page
	 *
	 * @param  Not param
	 */
	public function groups_page() {

		//Add groups
		if ( isset( $_POST['wp_add_group'] ) ) {
			$result = $this->subscribe->add_group( $_POST['wp_group_name'] );
			echo $this->notice_result( $result['result'], $result['message'] );
		}
		// Manage groups
		if ( isset( $_POST['wp_update_group'] ) ) {
			$result = $this->subscribe->update_group( $_POST['group_id'], $_POST['wp_group_name'] );
			echo $this->notice_result( $result['result'], $result['message'] );
		}

		include_once WP_SMS_DIR . '/includes/admin/groups/class-wpsms-groups-table.php';

		//Create an instance of our package class...
		$list_table = new WP_SMS_Subscribers_Groups_List_Table();

		//Fetch, prepare, sort, and filter our data...
		$list_table->prepare_items();

		include_once WP_SMS_DIR . "includes/admin/subscribers/groups.php";
	}

	/**
	 * Privacy admin page
	 *
	 * @param  Not param
	 */
	public function privacy_page() {
		WP_SMS_Privacy::get()->show_page_privacy();
	}

	/**
	 * Show message notice in admin
	 *
	 * @param $result
	 * @param $message
	 *
	 * @return string|void
	 * @internal param param $Not
	 */
	public function notice_result( $result, $message ) {
		if ( empty( $result ) ) {
			return;
		}

		if ( $result == 'error' ) {
			return '<div class="updated settings-error notice error is-dismissible"><p><strong>' . $message . '</strong></p><button class="notice-dismiss" type="button"><span class="screen-reader-text">' . __( 'Close', 'wp-sms' ) . '</span></button></div>';
		}

		if ( $result == 'update' ) {
			return '<div class="updated settings-update notice is-dismissible"><p><strong>' . $message . '</strong></p><button class="notice-dismiss" type="button"><span class="screen-reader-text">' . __( 'Close', 'wp-sms' ) . '</span></button></div>';
		}
	}

	/**
	 * Admin newsletter
	 *
	 * @param  Not param
	 */
	public function admin_newsletter() {
		include_once WP_SMS_DIR . 'includes/templates/admin-newsletter.php';
	}

	public static function loadNewsLetter( $widget_id = null, $instance = null ) {
		global $wpdb, $table_prefix, $wpsms_option;
		$get_group_result = $wpdb->get_results( "SELECT * FROM `{$table_prefix}sms_subscribes_group`" );

		include_once WP_SMS_DIR . "includes/templates/subscribe-form.php";
	}

}
