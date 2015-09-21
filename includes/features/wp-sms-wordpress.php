<?php
if ( ! defined( 'ABSPATH' ) ) exit;
function wps_mobilefield_to_newuser($user){
	include_once dirname( __FILE__ ) . "/../templates/wp-sms-user-field.php";
}

function wps_mobilefield_to_ptofile($fields) {
	$fields['mobile'] = __('Mobile', 'wp-sms');
	return $fields;
}

function wps_register_form() {
	$mobile = ( isset( $_POST['mobile'] ) ) ? $_POST['mobile']: '';
	include_once dirname( __FILE__ ) . "/../templates/wp-sms-user-field-register.php";
}

function wps_registration_errors($errors, $sanitized_user_login, $user_email) {
	if ( empty( $_POST['mobile'] ) )
	$errors->add( 'first_name_error', __('<strong>ERROR</strong>: You must include a mobile number.', 'wp-sms') );
	return $errors;
}

function wps_save_register($user_id) {
	if ( isset( $_POST['mobile'] ) ) {
		update_user_meta($user_id, 'mobile', $_POST['mobile']);
	}
}

if(get_option('wps_add_mobile_field')) {
	add_action('user_new_form', 'wps_mobilefield_to_newuser');
	add_filter('user_contactmethods', 'wps_mobilefield_to_ptofile');
	add_action('register_form','wps_register_form');
	add_filter('registration_errors', 'wps_registration_errors', 10, 3);
	add_action('user_register', 'wps_save_register');
}