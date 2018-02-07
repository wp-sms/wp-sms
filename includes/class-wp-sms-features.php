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

	protected $db;
	protected $tb_prefix;

	/**
	 * WP_SMS_Features constructor.
	 */
	public function __construct() {
		global $wpsms_option, $sms, $wpdb, $table_prefix;

		$this->sms       = $sms;
		$this->db        = $wpdb;
		$this->tb_prefix = $table_prefix;
		$this->date      = WP_SMS_CURRENT_DATE;
		$this->options   = $wpsms_option;

		if ( isset( $this->options['add_mobile_field'] ) ) {
			add_action( 'user_new_form', array( $this, 'add_mobile_field_to_newuser_form' ) );
			add_filter( 'user_contactmethods', array( $this, 'add_mobile_field_to_profile_form' ) );
			add_action( 'register_form', array( $this, 'add_mobile_field_to_register_form' ) );
			add_filter( 'registration_errors', array( $this, 'registration_errors' ), 10, 3 );
			add_action( 'user_register', array( $this, 'save_register' ) );

			add_action( 'user_register', array( $this, 'check_admin_duplicate_number' ) );
			add_action( 'profile_update', array( $this, 'check_admin_duplicate_number' ) );
		}
	}

	/**
	 * @param $mobile_number
	 * @param null $user_id
	 *
	 * @return bool
	 */
	private function check_mobile_number( $mobile_number, $user_id = null ) {
		if ( $user_id ) {
			$result = $this->db->get_results( "SELECT * from `{$this->tb_prefix}usermeta` WHERE meta_key = 'mobile' AND meta_value = '{$mobile_number}' AND user_id != '{$user_id}'" );
		} else {
			$result = $this->db->get_results( "SELECT * from `{$this->tb_prefix}usermeta` WHERE meta_key = 'mobile' AND meta_value = '{$mobile_number}'" );
		}

		if ( $result ) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * @param $user_id
	 */
	private function delete_user_mobile( $user_id ) {
		$this->db->delete(
			$this->tb_prefix . "usermeta",
			array(
				'user_id'  => $user_id,
				'meta_key' => 'mobile',
			)
		);
	}

	/**
	 * @param $user_id
	 */
	public function check_admin_duplicate_number( $user_id ) {
		// Get user mobile
		$user_mobile = get_user_meta( $user_id, 'mobile', true );

		if ( empty( $user_mobile ) ) {
			return;
		}

		// Delete user mobile
		if ( $this->check_mobile_number( $user_mobile, $user_id ) ) {
			$this->delete_user_mobile( $user_id );
		}
	}

	public function add_mobile_field_to_newuser_form() {
		include_once dirname( __FILE__ ) . "/templates/wp-sms-mobile-field.php";
	}

	/**
	 * @param $fields
	 *
	 * @return mixed
	 */
	public function add_mobile_field_to_profile_form( $fields ) {
		$fields['mobile'] = __( 'Mobile', 'wp-sms' );

		return $fields;
	}

	public function add_mobile_field_to_register_form() {
		$mobile = ( isset( $_POST['mobile'] ) ) ? $_POST['mobile'] : '';
		include_once dirname( __FILE__ ) . "/templates/wp-sms-mobile-field-register.php";
	}

	/**
	 * @param $errors
	 * @param $sanitized_user_login
	 * @param $user_email
	 *
	 * @return mixed
	 */
	public function registration_errors( $errors, $sanitized_user_login, $user_email ) {
		if ( empty( $_POST['mobile'] ) ) {
			$errors->add( 'first_name_error', __( '<strong>ERROR</strong>: You must include a mobile number.', 'wp-sms' ) );
		}

		if ( $this->check_mobile_number( $_POST['mobile'] ) ) {
			$errors->add( 'duplicate_mobile_number', __( '<strong>ERROR</strong>: This mobile is already registered, please choose another one.', 'wp-sms' ) );
		}

		return $errors;
	}

	/**
	 * @param $user_id
	 */
	public function save_register( $user_id ) {
		if ( isset( $_POST['mobile'] ) ) {
			update_user_meta( $user_id, 'mobile', $_POST['mobile'] );
		}
	}

}

new WP_SMS_Features();