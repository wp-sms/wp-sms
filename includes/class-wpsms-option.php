<?php

namespace WP_SMS;

class Option {

	//Get the whole Plugin Options
	public static function getOptions() {
		global $wpsms_option;

		return $wpsms_option;
	}

	// Get the only Option that we want
	public static function getOption( $option_name ) {
		global $wpsms_option;

		return isset( $wpsms_option[ $option_name ] ) ? $wpsms_option[ $option_name ] : '';
	}
}

new Option();