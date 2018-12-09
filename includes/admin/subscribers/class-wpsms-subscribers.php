<?php

namespace WP_SMS;

// Subscribers page class
class Subscribers {

	/**
	 * Subscribe admin page
	 */
	public static function subscribe_page() {
		$subscriber = new Newsletter();
		// Add subscriber page

		if ( isset( $_POST['wp_add_subscribe'] ) ) {
			$result = $subscriber->add_subscriber( $_POST['wp_subscribe_name'], $_POST['wp_subscribe_mobile'], $_POST['wpsms_group_name'] );
			echo Admin\Helper::notice( $result['message'], $result['result'] );
		}

		// Edit subscriber page
		if ( isset( $_POST['wp_update_subscribe'] ) ) {
			$result = $subscriber->update_subscriber( $_POST['ID'], $_POST['wp_subscribe_name'], $_POST['wp_subscribe_mobile'], $_POST['wpsms_group_name'], $_POST['wpsms_subscribe_status'] );
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

new Subscribers();