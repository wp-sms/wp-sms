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
		if ( $this->pro_is_active() ) {
			add_action( 'wp_sms_pro_after_setting_logo', array( $this, 'pro_setting_title' ) );
		} else {
			add_filter( 'plugin_row_meta', array( $this, 'pro_meta_links' ), 10, 2 );
			add_action( 'admin_enqueue_scripts', array( $this, 'pro_admin_script' ) );
			add_action( 'wp_sms_pro_after_setting_logo', array( $this, 'pro_setting_title_pro_not_activated' ) );
			add_action( 'wp_sms_after_setting_logo', array( $this, 'setting_title_pro_not_activated' ) );
			add_filter( 'wpsms_gateway_list', array( $this, 'pro_gateways' ) );
		}
	}

	/**
	 * Check pro pack is enabled
	 * @return bool
	 */
	private function pro_is_active() {
		include_once( ABSPATH . 'wp-admin/includes/plugin.php' );

		if ( is_plugin_active( 'wp-sms-pro/wp-sms-pro.php' ) ) {
			return true;
		}
	}

	/**
	 * Check pro pack is exists
	 * @return bool
	 */
	private function pro_is_exists() {
		if ( file_exists( WP_PLUGIN_DIR . '/wp-sms-pro/wp-sms-pro.php' ) ) {
			return true;
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
			$links[] = sprintf( __( '<b><a href="%s" target="_blank" class="wpsms-plugin-meta-link wp-sms-pro" title="Get professional package!">Get professional package!</a></b>', 'wp-sms' ), WP_SMS_SITE . '/purchase' );
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
	 * @return string
	 * @internal param $string
	 */
	public function pro_setting_title_pro_not_activated() {
		$html = '<p class="wpsms-error-notice">' . __( 'Requires Pro Pack version!', 'wp-sms' ) . '</p>';

		if ( $this->pro_is_exists() ) {
			$html .= '<a style="margin-bottom: 8px; font-weight: normal;" href="plugins.php" class="button button-primary">' . __( 'Active WP-SMS-Pro', 'wp-sms' ) . '</a>';
		} else {
			$html .= '<a style="margin-bottom: 8px; font-weight: normal;" target="_blank" href="http://wp-sms-pro.com/purchase/" class="button button-primary">' . __( 'Buy Professional Pack', 'wp-sms' ) . '</a>';
		}

		echo $html;
	}

	public function setting_title_pro_not_activated() {
		if ( ! $this->pro_is_exists() ) {
			$html = '<a style="margin: 10px 0; font-weight: normal;" target="_blank" href="http://wp-sms-pro.com/purchase/" class="button button-primary">' . __( 'Buy Professional Pack', 'wp-sms' ) . '</a>';
			echo $html;
		}
	}

	/**
	 * Load script
	 */
	public function pro_admin_script() {
		wp_enqueue_script( 'wpsms-pro-admin-js', WP_SMS_DIR_PLUGIN . 'assets/js/pro-pack.js', true, '1.0.0' );
	}

	/**
	 * @param $gateways
	 *
	 * @return mixed
	 */
	public function pro_gateways( $gateways ) {
		$gateways['pro_pack_gateways'] = array(
			'twilio'           => 'twilio.com',
			'plivo'            => 'plivo.com',
			'clickatell'       => 'clickatell.com',
			'bulksms'          => 'bulksms.com',
			'infobip'          => 'infobip.com',
			'nexmo'            => 'nexmo.com',
			'clockworksms'     => 'clockworksms.com',
			'messagebird'      => 'messagebird.com',
			'clicksend'        => 'clicksend.com',
			'smsapicom'        => 'smsapi.com',
			'dsms'             => 'dsms.in',
			'esms'             => 'esms.vn',
			'isms'             => 'isms.com.my',
			'dot4all'          => 'sms4marketing.it',
			'magicdeal4u'      => 'magicdeal4u.com',
			'mobily'           => 'mobily.ws',
			'moceansms'        => 'moceansms.com',
			'msg91'            => 'msg91.com',
			'msg360'           => 'msg360.in',
			'livesms'          => 'livesms.eu',
			'ozioma'           => 'ozioma.net',
			'pswin'            => 'pswin.com',
			'ra'               => 'ra.sa',
			'smsfactor'        => 'smsfactor.com',
			'textmarketer'     => 'textmarketer.co.uk',
			'smslive247'       => 'smslive247.com',
			'sendsms247'       => 'sendsms247.com',
			'ssdindia'         => 'ssdindia.com',
			'viensms'          => 'viensms.com',
			'vsms'             => 'vsms.club',
			'websms'           => 'websms.at',
			'smstrade'         => 'smstrade.de',
			'bulksmshyderabad' => 'bulksmshyderabad.co.in',
			'yamamah'          => 'yamamah.com',
			'cellsynt'         => 'cellsynt.net',
			'cmtelecom'        => 'cmtelecom.com',
		);

		return $gateways;
	}
}

new WP_SMS_Version();