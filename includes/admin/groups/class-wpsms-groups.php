<?php

namespace WP_SMS;

// Groups page class
class Groups {


	/**
	 * Get Group By ID
	 *
	 * @param $group_id
	 *
	 * @return Object|null
	 */
	public static function getGroup( $group_id ) {
		global $wpdb;

		$result = $wpdb->get_row( $wpdb->prepare( "SELECT name FROM {$wpdb->prefix}sms_subscribes_group WHERE ID = %d", $group_id ) );

		if ( $result ) {
			return $result;
		}

		return null;
	}

	/**
	 * Subscribe groups admin page
	 */
	public function render_page() {

		//Add groups
		if ( isset( $_POST['wp_add_group'] ) ) {
			$result = Newsletter::addGroup( $_POST['wp_group_name'] );
			echo Admin\Helper::notice( $result['message'], $result['result'] );
		}

		// Manage groups
		if ( isset( $_POST['wp_update_group'] ) ) {
			$result = Newsletter::updateGroup( $_POST['group_id'], $_POST['wp_group_name'] );
			echo Admin\Helper::notice( $result['message'], $result['result'] );
		}

		include_once WP_SMS_DIR . '/includes/admin/groups/class-wpsms-groups-table.php';

		//Create an instance of our package class...
		$list_table = new Subscribers_Groups_List_Table();

		//Fetch, prepare, sort, and filter our data...
		$list_table->prepare_items();

		include_once WP_SMS_DIR . "includes/admin/groups/groups.php";
	}
}