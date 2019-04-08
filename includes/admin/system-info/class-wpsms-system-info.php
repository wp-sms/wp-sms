<?php

namespace WP_SMS;

class SystemInfo {

	/**
	 * System info admin page
	 */
	public function render_page() {
		include_once "system-info.php";
		// Export log file
		if ( isset( $_POST['wpsms_download_info'] ) ) {
			$style="
				<style>
				#adminmenumain {display: none;}
				#wpadminbar {display: none;}
				#screen-meta-links {display: none;}
				</style>
			";
			echo $style;
			header( "Content-type: application/text" );
			header( "Content-Disposition: attachment; filename=" . date( 'Y-d-m H:i:s' ) . ".html" );
			header( "Pragma: no-cache" );
			header( "Expires: 0" );
			exit;
		}
	}

	/**
	 * Load system info page assets
	 */
	public function system_info_assets() {
		wp_enqueue_style( 'wpsms-system-info-css', WP_SMS_URL . 'assets/css/system-info.css', true, WP_SMS_VERSION );

	}

	/**
	 * Get WordPress information
	 *
	 * @return array
	 */
	public static function getWordpressInfo() {
		$information = array();

		// Check multisite
		if ( is_multisite() ) {
			$information['Multisite'][ [ 'status' ] ] = 'Enabled';
		} else {
			$information['Multisite']['status'] = 'Disabled';
		}
		$information['Multisite']['desc'] = 'Check WP multisite.';

		// Get version
		$information['Version']['status'] = get_bloginfo( 'version' );

		// Get language
		$information['Language']['status'] = get_bloginfo( 'language' );

		// Get active theme
		$information['Active Theme']['status'] = wp_get_theme();

		// Get ABSPATH
		$information['ABSPATH']['status'] = ABSPATH;

		// Get remote post status
		$remote = wp_remote_post( 'https://google.com' );
		if ( is_wp_error( $remote ) ) {
			$information['Remote Post status']['status'] = $remote->get_error_message();
		} else {
			$information['Remote Post status']['status'] = 'OK!';
		}

		// Get WP_DEBUG
		$wp_debug = WP_DEBUG;
		if ( $wp_debug ) {
			$wp_debug = 'True';
		} else {
			$wp_debug = 'False';
		}
		$information['WP_DEBUG']['status'] = $wp_debug;

		// Get activated plugins
		$active_plugins = get_option( 'active_plugins' );
		$all_plugins    = get_plugins();
		$final          = array();
		foreach ( $active_plugins as $p ) {
			if ( isset( $all_plugins[ $p ] ) ) {
				$final[] = $all_plugins[ $p ]['Name'];
			}
		}

		$information['Active Plugins']['status'] = implode( '<br>', $final );

		return $information;
	}

	/**
	 * Get PHP information
	 */
	public static function getPHPInfo() {
		$information = array();

		// Get PHP version
		$information['Version']['status'] = phpversion();

		// Check cURL enabled or not
		if ( function_exists( 'curl_version' ) ) {
			$information['cURL']['status'] = 'Enabled';
		} else {
			$information['cURL']['status'] = 'Disabled';
		}

		// Check fsockopen enabled or not
		if ( function_exists( 'fsockopen' ) ) {
			$information['fsockopen']['status'] = 'Enabled';
		} else {
			$information['fsockopen']['status'] = 'Disabled';
		}

		// Check SOAP Client enabled or not
		if ( class_exists( "SOAPClient" ) ) {
			$information['SOAP Client']['status'] = 'Enabled';
		} else {
			$information['SOAP Client']['status'] = 'Enabled';
		}

		return $information;
	}
}

new SystemInfo();