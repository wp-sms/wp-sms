<?php
	include_once("../../../../../wp-load.php");
	
	$name	= trim($_REQUEST['name']);
	$mobile	= trim($_REQUEST['mobile']);
	$group	= trim($_REQUEST['group']);
	$type	= $_REQUEST['type'];
	
	if(!$name or !$mobile) {
		echo json_encode(array('status' => 'error', 'response' => __('Please complete all fields', 'wp-sms')));
		return;
	}
	
	if(preg_match(WP_SMS_MOBILE_REGEX, $mobile) == false) {
		echo json_encode(array('status' => 'error', 'response' => __('Please enter a valid mobile number', 'wp-sms')));
		return;
	}
	
	if(get_option('wps_mnt_status')) {
		if(get_option('wps_mnt_max')) {
			if(strlen($mobile) > get_option('wps_mnt_max')) {
				echo json_encode(array('status' => 'error', 'response' => __('Your mobile number is high!', 'wp-sms')));
				return;
			}
		}
		
		if(get_option('wps_mnt_min')) {
			if(strlen($mobile) < get_option('wps_mnt_min')) {
				echo json_encode(array('status' => 'error', 'response' => __('Your mobile number is low!', 'wp-sms')));
				return;
			}
		}
	}
	
	global $wpdb, $table_prefix, $sms, $date;
	$check_mobile = $wpdb->query($wpdb->prepare("SELECT * FROM `{$table_prefix}sms_subscribes` WHERE `mobile` = '%s'", $mobile));
	
	if($check_mobile and $type == 'subscribe') {
		echo json_encode(array('status' => 'error', 'response' => __('Phone number is repeated', 'wp-sms')));
		return;
	}
	
	if($type == 'subscribe') {
		
		$get_current_date = date('Y-m-d H:i:s' ,current_time('timestamp',0));

		if(get_option('wp_subscribes_activation') and get_option('wp_webservice')) {
			if(!get_option('wp_webservice')){
				echo json_encode(array('status' => 'error', 'response' => __('Service provider is not available for send activate key to your mobile. Please contact with site.', 'wp-sms')));
				return;
			}
			
			$key = rand(1000, 9999);
			
			$sms->to = array($mobile);
			$sms->msg = __('Your activation code', 'wp-sms') . ': ' . $key;
			$sms->SendSMS();
			
			$check = $wpdb->insert("{$table_prefix}sms_subscribes",
				array(
					'date'			=>	$get_current_date,
					'name'			=>	$name,
					'mobile'		=>	$mobile,
					'status'		=>	'0',
					'activate_key'	=>	$key,
					'group_ID'		=>	$group
				)
			);

			if($check) {
				echo json_encode(array('status' => 'success', 'response' => __('You will join the newsletter, Activation code sent to your mobile.', 'wp-sms'), 'action' => 'activation'));
				return;
			}
		} else {
			$check = $wpdb->insert("{$table_prefix}sms_subscribes",
				array(
					'date'			=>	$get_current_date,
					'name'			=>	$name,
					'mobile'		=>	$mobile,
					'status'		=>	'1',
					'group_ID'		=>	$group
				)
			);
			
			if($check) {
				do_action('wps_add_subscriber', $name, $mobile);
				echo json_encode(array('status' => 'success', 'response' => __('You will join the newsletter', 'wp-sms')));
				return;
			}
		}
		
	} else if($type == 'unsubscribe') {
		
		if(!$check_mobile) {
			echo json_encode(array('status' => 'error', 'response' => __('Not found!', 'wp-sms')));
			return;
		}
		
		$wpdb->delete("{$table_prefix}sms_subscribes", array('mobile' => $mobile) );
		
		echo json_encode(array('status' => 'success', 'response' => __('Your subscription was canceled.', 'wp-sms')));
		return;
	}
