<?php
	include_once("../../../../../wp-load.php");

	$mobile	= trim($_REQUEST['mobile']);
	$activation = trim($_REQUEST['activation']);
	
	if(!$mobile) {
		echo json_encode(array('status' => 'error', 'response' => __('Mobile number is missing!', 'wp-sms')));
		return;
	}
	
	if(!$activation) {
		echo json_encode(array('status' => 'error', 'response' => __('Please enter the activation code!', 'wp-sms')));
		return;
	}
	
	$check_mobile = $wpdb->get_row($wpdb->prepare("SELECT * FROM `{$table_prefix}sms_subscribes` WHERE `mobile` = '%s'", $mobile));
	if($activation != $check_mobile->activate_key) {
		echo json_encode(array('status' => 'error', 'response' => __('Activation code is wrong!', 'wp-sms')));
		return;
	}
	
	$result = $wpdb->update("{$table_prefix}sms_subscribes", array('status' => '1'), array('mobile' => $mobile) );
	if($result) {
		do_action('wps_add_subscriber', $check_mobile->name, $mobile);
		echo json_encode(array('status' => 'success', 'response' => __('Your subscription was successful!', 'wp-sms')));
		return;
	}