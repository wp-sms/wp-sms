<?php

/**
 * WP SMS version class
 *
 * @category   class
 * @package    WP_SMS
 */
class WP_SMS_Version {
	public $options;

	/**
	 * WP_SMS_Version constructor.
	 */
	public function __construct() {
		global $wpsms_option;
		$this->options = $wpsms_option;

		// Check pro pack is enabled
		include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
		if ( is_plugin_active( 'wp-sms-pro/wp-sms-pro.php' ) ) {
			add_action( 'wp_sms_pro_after_setting_logo', array( $this, 'pro_setting_title' ) );
		} else {
			if ( ! is_admin() ) {
				return;
			}

			add_filter( 'plugin_row_meta', array( $this, 'pro_meta_links' ), 10, 2 );
			add_action( 'admin_enqueue_scripts', array( $this, 'pro_admin_script' ) );
			add_action( 'wp_sms_pro_after_setting_logo', array( $this, 'pro_setting_title_not_activated' ) );
		}
	}

	/**
	 * @param $links
	 * @param $file
	 *
	 * @return array
	 */
	public function pro_meta_links( $links, $file ) {
		if ( $file == 'wp-sms/wp-sms.php' ) {
			$links[] = sprintf( __( '<b><a href="%s" target="_blank" class="wpsms-plugin-meta-link" title="Get professional package!">Get professional package!</a></b>', 'wp-sms' ), WP_SMS_SITE . '/purchase' );
		}

		return $links;
	}

	/**
	 * @return string
	 * @internal param $string
	 */
	public function pro_setting_title() {
		echo sprintf( __( '<p>WP-SMS-Pro v%s</p>', 'wp-sms' ), WP_SMS_PRO_VERSION );
	}

	/**
	 * @param $string
	 *
	 * @return string
	 */
	public function pro_setting_title_not_activated( $string ) {
		$html = '<p class="wpsms-error-notice">Requires Pro Pack version!</p>';

		if ( file_exists( WP_PLUGIN_DIR . '/wp-sms-pro/wp-sms-pro.php' ) ) {
			$html .= '<a style="margin-bottom: 8px; font-weight: normal;" href="plugins.php" class="button button-primary">' . __( 'Active WP-SMS-Pro', 'wp-sms' ) . '</a>';
		} else {
			$html .= '<a style="margin-bottom: 8px; font-weight: normal;" target="_blank" href="http://wordpresssmsplugin.com/purchase/" class="button button-primary">' . __( 'Buy Professional Pack', 'wp-sms' ) . '</a>';
		}

		echo $html;
	}

	/**
	 * Load script
	 */
	public function pro_admin_script() {
		wp_enqueue_script( 'wpsms-pro-admin-js', WP_SMS_DIR_PLUGIN . 'assets/js/pro-pack.js', true, '1.0.0' );
	}
}

new WP_SMS_Version();