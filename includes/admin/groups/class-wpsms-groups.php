<?php

namespace WP_SMS;

// Groups page class
class Groups {

	/**
	 * Subscribe groups admin page
	 *
	 * @param  Not param
	 */
	public static function groups_page() {
		$subscriber = new Newsletter();
		//Add groups
		if ( isset( $_POST['wp_add_group'] ) ) {
			$result = $subscriber->add_group( $_POST['wp_group_name'] );
			echo Admin\Helper::notice( $result['message'], $result['result'] );
		}
		// Manage groups
		if ( isset( $_POST['wp_update_group'] ) ) {
			$result = $subscriber->update_group( $_POST['group_id'], $_POST['wp_group_name'] );
			echo Admin\Helper::notice( $result['message'], $result['result'] );
		}

		include_once WP_SMS_DIR . '/includes/admin/groups/class-wpsms-groups-table.php';

		//Create an instance of our package class...
		$list_table = new Subscribers_Groups_List_Table();

		//Fetch, prepare, sort, and filter our data...
		$list_table->prepare_items();

		include_once WP_SMS_DIR . "includes/admin/subscribers/groups.php";
	}
}

new Groups();