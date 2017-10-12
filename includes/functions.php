<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'initial_gateway' ) ) {
	/**
	 * Initial gateway
	 * @return mixed
	 */
	function initial_gateway() {
		global $wpsms_option;

		// Include default gateway
		include_once dirname( __FILE__ ) . '/class-wp-sms.php';
		include_once dirname( __FILE__ ) . '/gateways/default.class.php';

		// Using default gateway if does not set gateway in the setting
		if ( empty( $wpsms_option['gateway_name'] ) ) {
			return new Default_Gateway;
		}

		if ( is_file( dirname( __FILE__ ) . '/gateways/' . $wpsms_option['gateway_name'] . '.class.php' ) ) {
			include_once dirname( __FILE__ ) . '/gateways/' . $wpsms_option['gateway_name'] . '.class.php';
		} else if ( is_file( WP_PLUGIN_DIR . '/wp-sms-pro/includes/gateways/' . $wpsms_option['gateway_name'] . '.class.php' ) ) {
			include_once( WP_PLUGIN_DIR . '/wp-sms-pro/includes/gateways/' . $wpsms_option['gateway_name'] . '.class.php' );
		} else {
			return new Default_Gateway;
		}

		// Create object from the gateway class
		if ( $wpsms_option['gateway_name'] == 'default' ) {
			$sms = new Default_Gateway();
		} else {
			$sms = new $wpsms_option['gateway_name'];
		}

		// Set username and password
		$sms->username = $wpsms_option['gateway_username'];
		$sms->password = $wpsms_option['gateway_password'];

		// Set api key
		if ( $sms->has_key && $wpsms_option['gateway_key'] ) {
			$sms->has_key = $wpsms_option['gateway_key'];
		}

		// Show gateway help configuration in gateway page
		if ( $sms->help ) {
			add_action( 'wp_sms_after_gateway', function () {
				echo '<p class="description">' . $sms->help . '</p>';
			} );
		}

		// Check unit credit gateway
		if ( $sms->unitrial == true ) {
			$sms->unit = __( 'Credit', 'wp-sms' );
		} else {
			$sms->unit = __( 'SMS', 'wp-sms' );
		}

		// Set sender id
		if ( ! $sms->from ) {
			$sms->from = $wpsms_option['gateway_sender_id'];
		}

		// Unset gateway key field if not available in the current gateway class.
		add_filter( 'wp_sms_gateway_settings', function ( $filter ) {
			global $sms;

			if ( ! $sms->has_key ) {
				unset( $filter['gateway_key'] );
			}

			return $filter;
		} );

		// Return gateway object
		return $sms;
	}
}

if ( ! function_exists( 'wp_subscribes' ) ) {
	function wp_subscribes() {
		_e( 'This function is deprecated and will be added in future.', 'wp-sms' );
	}
}

if ( ! function_exists( 'wps_get_group_by_id' ) ) {
	function wps_get_group_by_id( $group_id = null ) {
		global $wpdb, $table_prefix;

		$result = $wpdb->get_row( $wpdb->prepare( "SELECT name FROM {$table_prefix}sms_subscribes_group WHERE ID = %d", $group_id ) );

		if ( $result ) {
			return $result->name;
		}
	}
}

if ( ! function_exists( 'wps_get_total_subscribe' ) ) {
	function wps_get_total_subscribe( $group_id = null ) {
		global $wpdb, $table_prefix;

		if ( $group_id ) {
			$result = $wpdb->query( $wpdb->prepare( "SELECT name FROM {$table_prefix}sms_subscribes WHERE group_ID = %d", $group_id ) );
		} else {
			$result = $wpdb->query( "SELECT name FROM {$table_prefix}sms_subscribes" );
		}

		if ( $result ) {
			return $result;
		}
	}
}
