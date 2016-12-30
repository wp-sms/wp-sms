<?php
/**
 * WP SMS features class
 * 
 * @category   class
 * @package    WP_SMS
 * @version    1.0
 */

class WP_SMS_Features {

	public $sms;
	public $date;
	public $options;

	public function __construct() {
		global $wpsms_option, $sms, $wp_version;

		$this->sms = $sms;
		$this->date = WP_SMS_CURRENT_DATE;
		$this->options = $wpsms_option;

		if( isset($this->options['add_mobile_field']) ) {
			add_action('user_new_form', array(&$this, 'add_mobile_field_to_newuser_form'));
			add_filter('user_contactmethods', array(&$this, 'add_mobile_field_to_profile_form'));
			add_action('register_form', array(&$this, 'add_mobile_field_to_register_form'));
			add_filter('registration_errors', array(&$this, 'registration_errors'), 10, 3);
			add_action('user_register', array(&$this, 'save_register'));
		}
	}

	public function add_mobile_field_to_newuser_form(){
		include_once dirname( __FILE__ ) . "/templates/wp-sms-mobile-field.php";
	}

	public function add_mobile_field_to_profile_form($fields) {
		$fields['mobile'] = __('Mobile', 'wp-sms');
		return $fields;
	}

	public function add_mobile_field_to_register_form() {
		$mobile = ( isset( $_POST['mobile'] ) ) ? $_POST['mobile']: '';
		include_once dirname( __FILE__ ) . "/templates/wp-sms-mobile-field-register.php";
	}

	public function registration_errors($errors, $sanitized_user_login, $user_email) {
		if ( empty( $_POST['mobile'] ) )
		$errors->add( 'first_name_error', __('<strong>ERROR</strong>: You must include a mobile number.', 'wp-sms') );
		return $errors;
	}

	public function save_register($user_id) {
		if ( isset( $_POST['mobile'] ) ) {
			update_user_meta($user_id, 'mobile', $_POST['mobile']);
		}
	}

}

new WP_SMS_Features();