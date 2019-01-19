<?php

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