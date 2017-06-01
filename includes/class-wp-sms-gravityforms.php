<?php

/**
 * WP SMS gravityforms
 *
 * @category   class
 * @package    WP_SMS
 * @version    1.0
 */
class WP_SMS_Gravityforms {
	static function get_field( $form_id ) {
		if ( ! $form_id ) {
			return;
		}

		if ( ! class_exists( 'RGFormsModel' ) ) {
			return;
		}

		$fields       = RGFormsModel::get_form_meta( $form_id );
		$option_field = '';

		if ( $fields ) {
			foreach ( $fields['fields'] as $field ) {
				$option_field[ $field['id'] ] = $field['label'];
			}

			return $option_field;
		}
	}
}

new WP_SMS_Gravityforms();