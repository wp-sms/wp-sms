<?php

/**
 * WP SMS quform
 *
 * @category   class
 * @package    WP_SMS
 * @version    1.0
 */
class WP_SMS_Quform {
	static function get_field( $form_id ) {
		if ( ! $form_id ) {
			return;
		}

		if ( ! function_exists( 'iphorm_get_all_forms' ) ) {
			return;
		}

		$fields = iphorm_get_all_forms();

		if ( ! $fields ) {
			return;
		}

		foreach ( $fields as $field ) {
			if ( $field['id'] == $form_id ) {
				if ( $field['elements'] ) {
					foreach ( $field['elements'] as $element ) {
						$option_field[ $element['id'] ] = $element['label'];
					}

					return $option_field;
				}
			}
		}

		return;
	}
}

new WP_SMS_Quform();