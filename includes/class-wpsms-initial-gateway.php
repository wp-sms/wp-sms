<?php

namespace WP_SMS\Initial;

class Gateway {

	public static function initial() {
		global $wpsms_option;

		// Set the default_gateway class
		$class_name = '\\WP_SMS\\Gateway\\Default_Gateway';
		// Include default gateway
		include_once WP_SMS_DIR . 'includes/class-wpsms-gateway.php';
		include_once WP_SMS_DIR . 'includes/gateways/class-wpsms-gateway-default.php';

		// Using default gateway if does not set gateway in the setting
		if ( empty( $wpsms_option['gateway_name'] ) ) {
			return new $class_name();
		}

		// TODO : need to change Class names on WP-SMS-PRO
		if ( is_file( WP_SMS_DIR . 'includes/gateways/class-wpsms-gateway-' . $wpsms_option['gateway_name'] . '.php' ) ) {
			include_once WP_SMS_DIR . 'includes/gateways/class-wpsms-gateway-' . $wpsms_option['gateway_name'] . '.php';
		} else if ( is_file( WP_PLUGIN_DIR . '/wp-sms-pro/includes/gateways/' . $wpsms_option['gateway_name'] . '.class.php' ) ) {
			include_once( WP_PLUGIN_DIR . '/wp-sms-pro/includes/gateways/' . $wpsms_option['gateway_name'] . '.class.php' );
		} else {
			return new $class_name();
		}

		// Create object from the gateway class
		if ( $wpsms_option['gateway_name'] == 'default' ) {
			$sms = new $class_name();
		} else {
			$class_name = '\\WP_SMS\\Gateway\\' . $wpsms_option['gateway_name'];
			$sms        = new $class_name();
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
				echo ' < p class="description" > ' . $sms->help . '</p > ';
			} );
		}

		// Check unit credit gateway
		if ( $sms->unitrial == true ) {
			$sms->unit = __( 'Credit', 'wp - sms' );
		} else {
			$sms->unit = __( 'SMS', 'wp - sms' );
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