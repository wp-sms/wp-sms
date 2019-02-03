<?php

namespace WP_SMS;

// Subscribers page class
class Subscribers {


	/**
	 * Get Total Subscribers with Group ID
	 *
	 * @param null $group_id
	 *
	 * @return Object|null
	 */
	public static function getTotal( $group_id = null ) {
		global $wpdb;

		if ( $group_id ) {
			$result = $wpdb->query( $wpdb->prepare( "SELECT name FROM {$wpdb->prefix}sms_subscribes WHERE group_ID = %d", $group_id ) );
		} else {
			$result = $wpdb->query( "SELECT name FROM {$wpdb->prefix}sms_subscribes" );
		}

		if ( $result ) {
			return $result;
		}
		return null;
	}

	/**
	 * Subscribe admin page
	 */
	public function render_page() {

		// Add subscriber
		if ( isset( $_POST['wp_add_subscribe'] ) ) {
			$result = Newsletter::addSubscriber( $_POST['wp_subscribe_name'], $_POST['wp_subscribe_mobile'], $_POST['wpsms_group_name'] );
			echo Admin\Helper::notice( $result['message'], $result['result'] );
		}

		// Edit subscriber page
		if ( isset( $_POST['wp_update_subscribe'] ) ) {
			$result = Newsletter::updateSubscriber( $_POST['ID'], $_POST['wp_subscribe_name'], $_POST['wp_subscribe_mobile'], $_POST['wpsms_group_name'], $_POST['wpsms_subscribe_status'] );
			echo Admin\Helper::notice( $result['message'], $result['result'] );
		}

		// Import subscriber page
		if ( isset( $_POST['wps_import'] ) ) {
			include_once WP_SMS_DIR . "includes/admin/import.php";
		}

		include_once WP_SMS_DIR . 'includes/admin/subscribers/class-wpsms-subscribers-table.php';

		//Create an instance of our package class...
		$list_table = new Subscribers_List_Table();

		//Fetch, prepare, sort, and filter our data...
		$list_table->prepare_items();

		include_once WP_SMS_DIR . "includes/admin/subscribers/subscribers.php";
	}
}