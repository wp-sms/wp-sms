<?php

namespace WP_SMS;

/**
 * Class Option
 * @package WP_SMS
 */
class Option {
	/**
	 * Get the whole Plugin Options
	 * @return mixed|void
	 */
	public static function getOptions() {
		global $wpsms_option;

		return $wpsms_option;
	}

	/**
	 * Get the only Option that we want
	 *
	 * @param $option_name
	 *
	 * @return string
	 */
	public static function getOption( $option_name ) {
		global $wpsms_option;

		return isset( $wpsms_option[ $option_name ] ) ? $wpsms_option[ $option_name ] : '';
	}

	/**
	 * Get the whole Plugin Options Pro
	 * @return mixed|void
	 */
	public static function getOptionsPro() {
		global $wpsms_pro_option;

		return $wpsms_pro_option;
	}

	/**
	 * Get the only Option Pro that we want
	 *
	 * @param $option_name
	 *
	 * @return string
	 */
	public static function getOptionPro( $option_name ) {
		global $wpsms_pro_option;

		return isset( $wpsms_pro_option[ $option_name ] ) ? $wpsms_pro_option[ $option_name ] : '';
	}
}

new Option();