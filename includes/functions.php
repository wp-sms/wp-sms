<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function wp_subscribes() {
	echo 'This function is deprecated and will add in future.';
}

function wps_get_group_by_id($group_id = null) {
	global $wpdb, $table_prefix;
	
	$result = $wpdb->get_row($wpdb->prepare("SELECT name FROM {$table_prefix}sms_subscribes_group WHERE ID = %d", $group_id));
	
	if($result)
		return $result->name;
}

function wps_get_total_subscribe($group_id = null) {
	global $wpdb, $table_prefix;
	
	if($group_id) {
		$result = $wpdb->query($wpdb->prepare("SELECT name FROM {$table_prefix}sms_subscribes WHERE group_ID = %d", $group_id));
	} else {
		$result = $wpdb->query("SELECT name FROM {$table_prefix}sms_subscribes");
	}
	
	if($result)
		return $result;
}