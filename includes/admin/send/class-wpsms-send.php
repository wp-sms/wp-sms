<?php

namespace WP_SMS;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

/**
 * Class Send SMS Page
 */
class SMS_Send {

	public $sms;
	protected $db;
	protected $tb_prefix;
	protected $options;

	public function __construct() {
		global $wpdb, $sms;

		$this->db        = $wpdb;
		$this->tb_prefix = $wpdb->prefix;
		$this->sms       = $sms;
		$this->options   = Option::getOptions();
	}

	/**
	 * Sending sms admin page
	 *
	 * @param  Not param
	 */
	public function render_page() {
		$get_group_result = $this->db->get_results( "SELECT * FROM `{$this->db->prefix}sms_subscribes_group`" );
		$get_users_mobile = $this->db->get_col( "SELECT `meta_value` FROM `{$this->db->prefix}usermeta` WHERE `meta_key` = 'mobile'" );

		$mobile_field = Option::getOption( 'add_mobile_field' );

		//Get User Mobile List by Role
		if ( ! empty( $mobile_field ) AND $mobile_field == 1 ) {
			$wpsms_list_of_role = array();
			foreach ( wp_roles()->role_names as $key_item => $val_item ) {
				$wpsms_list_of_role[ $key_item ] = array(
					"name"  => $val_item,
					"count" => count( get_users( array(
						'meta_key'     => 'mobile',
						'meta_value'   => '',
						'meta_compare' => '!=',
						'role'         => $key_item,
						'fields'       => 'ID'
					) ) )
				);
			}
		}

		$gateway_name = Option::getOption( 'gateway_name' );
		$credit = $this->sms->GetCredit();

		if ( $gateway_name && ! $credit ) {
			echo '<br><div class="update-nag">' . __( 'You should have sufficient funds for sending sms in the account', 'wp-sms' ) . '</div>';

			return;
		} else if ( ! $gateway_name ) {
			echo '<br><div class="update-nag">' . __( 'You should choose and configuration your gateway in the Setting page', 'wp-sms' ) . '</div>';

			return;
		}

		if ( isset( $_POST['SendSMS'] ) ) {
			if ( $_POST['wp_get_message'] ) {
				if ( $_POST['wp_send_to'] == "wp_subscribe_username" ) {
					if ( $_POST['wpsms_group_name'] == 'all' ) {
						$this->sms->to = $this->db->get_col( "SELECT mobile FROM {$this->db->prefix}sms_subscribes WHERE `status` = '1'" );
					} else {
						$this->sms->to = $this->db->get_col( "SELECT mobile FROM {$this->db->prefix}sms_subscribes WHERE `status` = '1' AND `group_ID` = '" . $_POST['wpsms_group_name'] . "'" );
					}
				} else if ( $_POST['wp_send_to'] == "wp_users" ) {
					$this->sms->to = $get_users_mobile;
				} else if ( $_POST['wp_send_to'] == "wp_tellephone" ) {
					$this->sms->to = explode( ",", $_POST['wp_get_number'] );
				} else if ( $_POST['wp_send_to'] == "wp_role" ) {
					$to = array();
					add_action( 'pre_user_query', array( SMS_Send::class, 'get_query_user_mobile' ) );
					$list = get_users( array(
						'meta_key'     => 'mobile',
						'meta_value'   => '',
						'meta_compare' => '!=',
						'role'         => $_POST['wpsms_group_role'],
						'fields'       => 'all'
					) );
					remove_action( 'pre_user_query', array( SMS_Send::class, 'get_query_user_mobile' ) );
					foreach ( $list as $user ) {
						$to[] = $user->mobile;
					}
					$this->sms->to = $to;
				}

				$this->sms->from = $_POST['wp_get_sender'];
				$this->sms->msg  = $_POST['wp_get_message'];

				if ( isset( $_POST['wp_flash'] ) ) {
					$this->sms->isflash = true;
				} else {
					$this->sms->isflash = false;
				}

				// Send sms
				$response = $this->sms->SendSMS();

				if ( is_wp_error( $response ) ) {
					if ( is_array( $response->get_error_message() ) ) {
						$response = print_r( $response->get_error_message(), 1 );
					} else {
						$response = $response->get_error_message();
					}

					echo "<div class='error'><p>" . sprintf( __( '<strong>SMS was not delivered! results received:</strong> %s', 'wp-sms' ), $response ) . "</p></div>";
				} else {
					echo "<div class='updated'><p>" . __( 'The SMS sent successfully', 'wp-sms' ) . "</p></div>";
					if ( ! is_object( $credit ) ) {
						update_option( 'wp_last_credit', $credit );
					}
				}
			} else {
				echo "<div class='error'><p>" . __( 'Please enter your SMS message.', 'wp-sms' ) . "</p></div>";
			}
		}

		include_once WP_SMS_DIR . "includes/admin/send/send-sms.php";
	}

	/**
	 * Custom Query for Get All User Mobile in special Role
	 */
	public static function get_query_user_mobile( $user_query ) {
		global $wpdb;

		$user_query->query_fields .= ', m1.meta_value AS mobile ';
		$user_query->query_from   .= " JOIN {$wpdb->usermeta} m1 ON (m1.user_id = {$wpdb->users}.ID AND m1.meta_key = 'mobile') ";

		return $user_query;
	}

}

new SMS_Send();