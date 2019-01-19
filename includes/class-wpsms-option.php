<?php

namespace WP_SMS;

/**
 * Class Option
 * @package WP_SMS
 */
class Option {
	/**
	 * Get the whole Plugin Options
	 *
	 * @param string $setting_name
	 * @param bool $pro
	 *
	 * @return mixed|void
	 */
	public static function getOptions( $pro = false, $setting_name = '' ) {
		if ( ! $setting_name ) {
			if ( $pro ) {
				global $wpsms_pro_option;

				return $wpsms_pro_option;
			}

			global $wpsms_option;

			return $wpsms_option;
		}

		return get_option( $setting_name );
	}


	/**
	 * Get the only Option that we want
	 *
	 * @param $option_name
	 * @param string $setting_name
	 * @param bool $pro
	 *
	 * @return string
	 */
	public static function getOption( $option_name, $pro = false, $setting_name = '' ) {
		if ( ! $setting_name ) {
			if ( $pro ) {
				global $wpsms_pro_option;

				return isset( $wpsms_pro_option[ $option_name ] ) ? $wpsms_pro_option[ $option_name ] : '';
			}

			global $wpsms_option;

			return isset( $wpsms_option[ $option_name ] ) ? $wpsms_option[ $option_name ] : '';
		}
		$options = self::getOptions( $setting_name );

		return isset( $options[ $option_name ] ) ? $options[ $option_name ] : '';

	}

	/**
	 * Add an option
	 *
	 * @param $option_name
	 * @param $option_value
	 */
	public static function addOption( $option_name, $option_value ) {
		add_option( $option_name, $option_value );
	}

	/**
	 * Update Option
	 *
	 * @param $setting_name
	 * @param $option_values
	 * @param bool $autoload
	 */
	public static function updateOption( $setting_name, $option_values, $autoload = true ) {
		if ( $autoload ) {
			update_option( $setting_name, $option_values );
		} else {
			update_option( $setting_name, $option_values, false );
		}
	}
}

new Option();