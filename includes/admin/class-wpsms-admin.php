<?php

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

		global $wpdb, $sms;

		$this->db        = $wpdb;
		$this->tb_prefix = $wpdb->prefix;
		$this->options   = Option::getOptions();
		$this->sms       = $sms;
		$this->init();

		// Add Actions
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_assets' ) );
		add_action( 'admin_bar_menu', array( $this, 'admin_bar' ) );
		add_action( 'dashboard_glance_items', array( $this, 'dashboard_glance' ) );
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );

		// Add Filters
		add_filter( 'plugin_row_meta', array( $this, 'meta_links' ), 0, 2 );
	}

	/**
	 * Include admin assets
	 */
	public function admin_assets() {

		//Register admin-bar.css for whole admin area
		wp_register_style( 'wpsms-admin-bar-css', WP_SMS_URL . 'assets/css/admin-bar.css', true, WP_SMS_VERSION );
		wp_enqueue_style( 'wpsms-admin-bar-css' );

		if ( stristr( get_current_screen()->id, "wp-sms" ) ) {
			wp_register_style( 'wpsms-admin-css', WP_SMS_URL . 'assets/css/admin.css', true, WP_SMS_VERSION );
			wp_enqueue_style( 'wpsms-admin-css' );

			wp_enqueue_style( 'wpsms-chosen-css', WP_SMS_URL . 'assets/css/chosen.min.css', true, WP_SMS_VERSION );
			wp_enqueue_script( 'wpsms-chosen-js', WP_SMS_URL . 'assets/js/chosen.jquery.min.js', true, WP_SMS_VERSION );
			wp_enqueue_script( 'wpsms-word-and-character-counter-js', WP_SMS_URL . 'assets/js/jquery.word-and-character-counter.min.js', true, WP_SMS_VERSION );
			wp_enqueue_script( 'wpsms-admin-js', WP_SMS_URL . 'assets/js/admin.js', true, WP_SMS_VERSION );
		}
	}

	/**
	 * Admin bar plugin
	 */
	public function admin_bar() {
		global $wp_admin_bar;
		if ( is_super_admin() && is_admin_bar_showing() ) {
			if ( get_option( 'wp_last_credit' ) && isset( $this->options['account_credit_in_menu'] ) ) {
				$wp_admin_bar->add_menu( array(
					'id'    => 'wp-credit-sms',
					'title' => '<span class="ab-icon"></span>' . get_option( 'wp_last_credit' ),
					'href'  => WP_SMS_ADMIN_URL . '/admin.php?page=wp-sms-settings'
				) );
			}
		}

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
	public function admin_menu() {
		add_menu_page( __( 'SMS', 'wp-sms' ), __( 'SMS', 'wp-sms' ), 'wpsms_sendsms', 'wp-sms', array( $this, 'send_sms_callback' ), 'dashicons-email-alt' );
		add_submenu_page( 'wp-sms', __( 'Send SMS', 'wp-sms' ), __( 'Send SMS', 'wp-sms' ), 'wpsms_sendsms', 'wp-sms', array( $this, 'send_sms_callback' ) );
		add_submenu_page( 'wp-sms', __( 'Outbox', 'wp-sms' ), __( 'Outbox', 'wp-sms' ), 'wpsms_outbox', 'wp-sms-outbox', array( $this, 'outbox_callback' ) );
		add_submenu_page( 'wp-sms', __( 'Subscribers', 'wp-sms' ), __( 'Subscribers', 'wp-sms' ), 'wpsms_subscribers', 'wp-sms-subscribers', array( $this, 'subscribers_callback' ) );
		add_submenu_page( 'wp-sms', __( 'Groups', 'wp-sms' ), __( 'Groups', 'wp-sms' ), 'wpsms_subscribers', 'wp-sms-subscribers-group', array( $this, 'groups_callback' ) );

		// Check GDPR compliance for Privacy menu
		if ( isset( $this->options['gdpr_compliance'] ) and $this->options['gdpr_compliance'] == 1 ) {
			add_submenu_page( 'wp-sms', __( 'Privacy', 'wp-sms' ), __( 'Privacy', 'wp-sms' ), 'manage_options', 'wp-sms-subscribers-privacy', array( $this, 'privacy_callback' ) );
		}
	}

	/**
	 * Callback send sms page.
	 */
	public function send_sms_callback() {
		$page = new SMS_Send();

		$page->render_page();
	}

	/**
	 * Callback outbox page.
	 */
	public function outbox_callback() {
		$page = new Outbox();

		$page->render_page();
	}

	/**
	 * Callback subscribers page.
	 */
	public function subscribers_callback() {
		$page = new Subscribers();

		$page->render_page();
	}

	/**
	 * Callback subscribers page.
	 */
	public function groups_callback() {
		$page = new Groups();

		$page->render_page();
	}

	/**
	 * Callback subscribers page.
	 */
	public function privacy_callback() {
		$page = new Privacy();

		$page->render_page();
	}

	/**
	 * Administrator add Meta Links
	 *
	 * @param $links
	 * @param $file
	 *
	 * @return array
	 */
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
	 * Adding new capability in the plugin
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
}

new Admin();