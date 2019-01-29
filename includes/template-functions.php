<?php

use WP_SMS\Newsletter;
use WP_SMS\Option;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

if ( ! function_exists( 'wp_subscribes' ) ) {
	function wp_subscribes() {
		Newsletter::loadNewsLetter();
	}
}

/**
 * @param $option_name
 * @param bool $pro
 * @param string $setting_name
 *
 * @return string
 */
function wp_sms_get_option($option_name, $pro = false, $setting_name = '') {
	return Option::getOption($option_name, $pro, $setting_name);
}

/**
 * @param $to
 * @param $msg $pro
 * @param $isflash
 *
 * @return string | WP_Error
 */
function wp_sms_send( $to, $msg, $isflash = false ) {
	global $sms;
	$sms->isflash = $isflash;
	$sms->to  = array( $to );
	$sms->msg = $msg;
	return $sms->SendSMS();
}