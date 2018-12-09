<?php

// Set namespace class
namespace WP_SMS;

class Admin {

	/**
	 * WP SMS gateway object
	 *
	 * @var string
	 */
	public $sms;

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

		global $wpdb, $wpsms_option, $sms;

		$this->db        = $wpdb;
		$this->tb_prefix = $wpdb->prefix;
		$this->options   = $wpsms_option;
		$this->sms       = $sms;
		$this->init();

		// Add Actions
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_assets' ) );
		add_action( 'admin_bar_menu', array( $this, 'admin_bar' ) );
		add_action( 'dashboard_glance_items', array( $this, 'dashboard_glance' ) );
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'wpmu_new_blog', array( $this, 'add_table_on_create_blog' ), 10, 1 );

		// Add Filters
		add_filter( 'plugin_row_meta', array( $this, 'meta_links' ), 0, 2 );
		add_filter( 'wpmu_drop_tables', array( $this, 'remove_table_on_delete_blog' ) );

	}

	/**
	 * Include admin assets
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
	 * Admin bar plugin
	 */
	public function admin_bar() {
		global $wp_admin_bar;
		/* TODO: Not working!
		if ( is_super_admin() && is_admin_bar_showing() ) {
			if ( get_option( 'wp_last_credit' ) && isset( $this->options['account_credit_in_menu'] ) ) {
				$wp_admin_bar->add_menu( array(
					'id'    => 'wp-credit-sms',
					'title' => '<span class="ab-icon"></span>' . get_option( 'wp_last_credit' ),
					'href'  => WP_SMS_ADMIN_URL . '/admin.php?page=wp-sms-settings',
				) );
			}*/

		$wp_admin_bar->add_menu( array(
			'id'     => 'wp-send-sms',
			'parent' => 'new-content',
			'title'  => __( 'SMS', 'wp-sms' ),
			'href'   => WP_SMS_ADMIN_URL . '/admin.php?page=wp-sms'
		) );
	}


	/**
	 * Dashboard glance plugin
	 */
	public function dashboard_glance() {

		$subscribe = $this->db->get_var( "SELECT COUNT(*) FROM {$this->tb_prefix}sms_subscribes" );
		echo "<li class='wpsms-subscribe-count'><a href='" . WP_SMS_ADMIN_URL . "admin.php?page=wp-sms-subscribers'>" . sprintf( __( '%s Subscriber', 'wp-sms' ), $subscribe ) . "</a></li>";
		echo "<li class='wpsms-credit-count'><a href='" . WP_SMS_ADMIN_URL . "admin.php?page=wp-sms-settings&tab=web-service'>" . sprintf( __( '%s SMS Credit', 'wp-sms' ), get_option( 'wp_last_credit' ) ) . "</a></li>";
	}

	/**
	 * Administrator admin_menu
	 */
	public
	function admin_menu() {
		add_menu_page( __( 'SMS', 'wp-sms' ), __( 'SMS', 'wp-sms' ), 'wpsms_sendsms', 'wp-sms', array( SMS_Send::class, 'send_page' ), 'dashicons-email-alt' );
		add_submenu_page( 'wp-sms', __( 'Send SMS', 'wp-sms' ), __( 'Send SMS', 'wp-sms' ), 'wpsms_sendsms', 'wp-sms', array( SMS_Send::class, 'send_page' ) );
		add_submenu_page( 'wp-sms', __( 'Outbox', 'wp-sms' ), __( 'Outbox', 'wp-sms' ), 'wpsms_outbox', 'wp-sms-outbox', array( Outbox::class, 'outbox_page' ) );
		add_submenu_page( 'wp-sms', __( 'Subscribers', 'wp-sms' ), __( 'Subscribers', 'wp-sms' ), 'wpsms_subscribers', 'wp-sms-subscribers', array( Subscribers::class, 'subscribe_page' ) );
		add_submenu_page( 'wp-sms', __( 'Groups', 'wp-sms' ), __( 'Groups', 'wp-sms' ), 'wpsms_subscribers', 'wp-sms-subscribers-group', array( Groups::class, 'groups_page' ) );

		// Check GDPR compliance for Privacy menu
		if ( isset( $this->options['gdpr_compliance'] ) and $this->options['gdpr_compliance'] == 1 ) {
			add_submenu_page( 'wp-sms', __( 'Privacy', 'wp-sms' ), __( 'Privacy', 'wp-sms' ), 'manage_options', 'wp-sms-subscribers-privacy', array( Privacy::class, 'privacy_page' ) );
		}
	}

	/**
	 * Administrator add Meta Links
	 *
	 * @param $links
	 * @param $file
	 *
	 * @return array
	 */
	public
	function meta_links(
		$links, $file
	) {
		if ( $file == 'wp-sms/wp-sms.php' ) {
			$rate_url = 'http://wordpress.org/support/view/plugin-reviews/wp-sms?rate=5#postform';
			$links[]  = '<a href="' . $rate_url . '" target="_blank" class="wpsms-plugin-meta-link" title="' . __( 'Click here to rate and review this plugin on WordPress.org', 'wp-sms' ) . '">' . __( 'Rate this plugin', 'wp-sms' ) . '</a>';

			$newsletter_url = WP_SMS_SITE . '/newsletter';
			$links[]        = '<a href="' . $newsletter_url . '" target="_blank" class="wpsms-plugin-meta-link" title="' . __( 'Click here to rate and review this plugin on WordPress.org', 'wp-sms' ) . '">' . __( 'Subscribe to our Email Newsletter', 'wp-sms' ) . '</a>';
		}

		return $links;
	}

	/**
	 * Adding new capability in the plugin
	 */
	public
	function add_cap() {
		// Get administrator role
		$role = get_role( 'administrator' );

		$role->add_cap( 'wpsms_sendsms' );
		$role->add_cap( 'wpsms_outbox' );
		$role->add_cap( 'wpsms_subscribers' );
		$role->add_cap( 'wpsms_setting' );
	}

	/**
	 * Initial plugin
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
	 * Admin newsletter
	 */
	public function admin_newsletter() {
		include_once WP_SMS_DIR . 'includes/templates/admin-newsletter.php';
	}

	/**
	 * Creating plugin tables
	 */
	static function install( $network_wide ) {
		global $wp_sms_db_version;

		include_once WP_SMS_DIR . 'includes/install.php';
		$install = new Install();
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
	public
	function add_table_on_create_blog(
		$blog_id
	) {
		if ( is_plugin_active_for_network( 'wp-sms/wp-sms.php' ) ) {
			switch_to_blog( $blog_id );

			include_once WP_SMS_DIR . 'includes/install.php';

			$install = new Install();
			$install->table_sql();

			restore_current_blog();
		}
	}

	/**
	 * Remove Table On Delete Blog Wordpress
	 */
	public function remove_table_on_delete_blog( $tables ) {
		foreach ( array( 'sms_subscribes', 'sms_subscribes_group', 'sms_send' ) as $tbl ) {
			$tables[] = $this->tb_prefix . $tbl;
		}

		return $tables;
	}
}

new Admin();