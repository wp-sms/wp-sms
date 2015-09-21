<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function wp_subscribes($description = null, $show_group = true) {
	global $wpdb, $table_prefix;
	if(!$description)
		$description = __('Enter your information for SMS Subscribe', 'wp-sms');
	
	$get_group_result = $wpdb->get_results("SELECT * FROM `{$table_prefix}sms_subscribes_group`");
	include_once dirname( __FILE__ ) . "/templates/wp-sms-subscribe-form.php";
}
add_shortcode('subscribe', 'wp_subscribes');

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