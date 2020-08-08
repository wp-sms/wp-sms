<?php

namespace WP_SMS;

use WP_SMS\Admin\Helper;
use WP_SMS\Pro\Scheduled;

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
	 * @param Not param
	 */
	public function render_page() {
		$get_group_result = $this->db->get_results( "SELECT * FROM `{$this->db->prefix}sms_subscribes_group`" );
		$get_users_mobile = $this->db->get_col( "SELECT `meta_value` FROM `{$this->db->prefix}usermeta` WHERE `meta_key` = 'mobile' AND `meta_value` != '' " );
		// Check if WooCommerce & Pro version enabled
		$wcSendEnable = false;
		if ( Version::pro_is_active() and class_exists( 'woocommerce' ) ) {
			$getTotalWcUsers = $this->getUsersList( 'customer', true );
			$wcSendEnable = true;
		}

		$mobile_field = Option::getOption( 'add_mobile_field' );

		//Get User Mobile List by Role
		if ( ! empty( $mobile_field ) and $mobile_field == 1 ) {
			$wpsms_list_of_role = array();
			foreach ( wp_roles()->role_names as $key_item => $val_item ) {
				$wpsms_list_of_role[ $key_item ] = array(
					"name"  => $val_item,
					"count" => $this->getUsersList( $key_item, true )
				);
			}
		}

		$gateway_name = Option::getOption( 'gateway_name' );
		$credit       = get_option( 'wpsms_gateway_credit' );

		/*if ( $gateway_name && ! $credit ) {
			echo '<br><div class="update-nag">' . __( 'You should have sufficient funds for sending sms in the account', 'wp-sms' ) . '</div>';

			return;
		} else if ( ! $gateway_name ) {
			echo '<br><div class="update-nag">' . __( 'You should choose and configuration your gateway in the Setting page', 'wp-sms' ) . '</div>';

			return;
		}*/

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
					$numbers = $_POST['wp_get_number'];
					if ( strpos( $numbers, ',' ) !== false ) {
						$this->sms->to = explode( ",", $_POST['wp_get_number'] );
					} else {
						$this->sms->to = explode( "\n", str_replace( "\r", "", $numbers ) );
					}
				} else if ( $_POST['wp_send_to'] == "wp_role" ) {
					$to   = array();
					$list = $this->getUsersList( $_POST['wpsms_group_role'] );
					foreach ( $list as $user ) {
						$to[] = $user->mobile;
					}
					$this->sms->to = $to;
				} else if ( $_POST['wp_send_to'] == "wc_users" ) {
					$to   = array();
					$list = $this->getUsersList( 'customer' );
					foreach ( $list as $user ) {
						$to[] = $user->mobile;
					}
					$this->sms->to = $to;
				}

				$this->sms->from = $_POST['wp_get_sender'];
				$this->sms->msg  = $_POST['wp_get_message'];

				if ( isset( $_POST['wp_flash'] ) and $_POST['wp_flash'] == 'true' ) {
					$this->sms->isflash = true;
				} else {
					$this->sms->isflash = false;
				}

				if ( isset( $_POST['wpsms_scheduled'] ) and isset( $_POST['schedule_status'] ) and $_POST['schedule_status'] and $_POST['wpsms_scheduled'] ) {
					$response = Scheduled::add( $_POST['wpsms_scheduled'], $this->sms->from, $this->sms->msg, $this->sms->to );
				} else {

					// Send sms
					if ( empty( $this->sms->to ) ) {
						$response = new \WP_Error( 'error', __( 'The selected user list is empty, please select another valid users list from send to option.', 'wp-sms' ) );
					} else {
						$response = $this->sms->SendSMS();
					}
				}

				if ( is_wp_error( $response ) ) {
					if ( is_array( $response->get_error_message() ) ) {
						$response = print_r( $response->get_error_message(), 1 );
					} else {
						$response = $response->get_error_message();
					}

					echo "<div class='error'><p>" . sprintf( __( '<strong>SMS was not delivered! results received:</strong> %s', 'wp-sms' ), $response ) . "</p></div>";
				} else {
					echo "<div class='updated'><p>" . __( 'The SMS sent successfully', 'wp-sms' ) . "</p></div>";
					$credit = Gateway::credit();
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
	public static function getQueryUserMobile( $user_query ) {
		global $wpdb;

		$user_query->query_fields .= ', m1.meta_value AS mobile ';
		$user_query->query_from   .= " JOIN {$wpdb->usermeta} m1 ON (m1.user_id = {$wpdb->users}.ID AND m1.meta_key = 'mobile') ";

		return $user_query;
	}

	/**
	 * Get WooCommerce customers
	 *
	 * @param string $role
	 * @param bool $count
	 *
	 * @return array|int
	 */
	public function getUsersList( $role, $count = false ) {
		add_action( 'pre_user_query', array( SMS_Send::class, 'getQueryUserMobile' ) );

		$args = array(
			'meta_query' => array(
				array(
					'key'     => 'mobile',
					'value'   => '',
					'compare' => '!=',
				),
			),
			'role'       => $role,
			'fields'     => $count ? 'ID' : 'all'
		);

		$customers = get_users( $args );

		remove_action( 'pre_user_query', array( SMS_Send::class, 'getQueryUserMobile' ) );

		if ( $count ) {
			return count( $customers );
		}

		return $customers;
	}
}

new SMS_Send();