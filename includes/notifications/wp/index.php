<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Get WP SMS Option values
$wps_options = get_option('wpsms');
if(empty($wps_options['wp_notification_new_wp_version'])) $wps_options['wp_notification_new_wp_version'] = '';
if(empty($wps_options['wp_webservice'])) $wps_options['wp_webservice'] = '';
if(empty($wps_options['wp_last_send_notification'])) $wps_options['wp_last_send_notification'] = '';
if(empty($wps_options['wpsms_narnu_tt'])) $wps_options['wpsms_narnu_tt'] = '';
if(empty($wps_options['wpsms_nrnu_tt'])) $wps_options['wpsms_nrnu_tt'] = '';
if(empty($wps_options['wpsms_nrnu_stats'])) $wps_options['wpsms_nrnu_stats'] = '';
if(empty($wps_options['wpsms_gnc_tt'])) $wps_options['wpsms_gnc_tt'] = '';
if(empty($wps_options['wpsms_gnc_stats'])) $wps_options['wpsms_gnc_stats'] = '';
if(empty($wps_options['wpsms_ul_tt'])) $wps_options['wpsms_ul_tt'] = '';
if(empty($wps_options['wpsms_ul_stats'])) $wps_options['wpsms_ul_stats'] = '';

function wps_wp_options() {
	global $wps_options;
	include_once dirname( __FILE__ ) . '/options.php';
}
add_action('wp_sms_notification_page', 'wps_wp_options');

// Wordpress new version
if($wps_options['wp_notification_new_wp_version'] and $wps_options['wp_webservice']) {
	global $sms;
	$update = get_site_transient('update_core');
	$update = $update->updates;
	
	if($update[1]->current > $wp_version and $sms->GetCredit()) {
		if($wps_options['wp_last_send_notification'] == false) {
			$sms->to = array(get_option('wp_admin_mobile'));
			$sms->msg = sprintf(__('WordPress %s is available! Please update now', 'wp-sms'), $update[1]->current);
			$sms->SendSMS();
			
			update_option('wp_last_send_notification', true);
		}
	} else {
		update_option('wp_last_send_notification', false);
	}
}

// Register new user
function wps_notification_new_user($user_id) {
	global $sms, $date, $wps_options;
	
	$user = get_userdata($user_id);
	$template_vars = array(
		'user_login'	=> $user->user_login,
		'user_email'	=> $user->user_email,
		'date_register'	=> $date,
	);
	
	// Send SMS to admin
	$sms->to = array(get_option('wp_admin_mobile'));
	$string = $wps_options['wpsms_narnu_tt'];
	$final_message = preg_replace('/%(.*?)%/ime', "\$template_vars['$1']", $string);
	$sms->msg = $final_message;
	$sms->SendSMS();
	
	// Send SMS to user register
	$sms->to = array($user->mobile);
	$string = $wps_options['wpsms_nrnu_tt'];
	$final_message = preg_replace('/%(.*?)%/ime', "\$template_vars['$1']", $string);
	$sms->msg = $final_message;
	$sms->SendSMS();
}
if($wps_options['wpsms_nrnu_stats'])
	add_action('user_register', 'wps_notification_new_user', 10, 1);

// New Comment
function wps_notification_new_comment($comment_id, $comment_smsect){
	global $sms, $wps_options;
	
	if($comment_smsect->comment_type == 'order_note')
		return;
	
	if($comment_smsect->comment_type == 'edd_payment_note')
		return;
	
	$sms->to = array(get_option('wp_admin_mobile'));
	$string = $wps_options['wpsms_gnc_tt'];
	$template_vars = array(
		'comment_author'		=> $comment_smsect->comment_author,
		'comment_author_email'	=> $comment_smsect->comment_author_email,
		'comment_author_url'	=> $comment_smsect->comment_author_url,
		'comment_author_IP'		=> $comment_smsect->comment_author_IP,
		'comment_date'			=> $comment_smsect->comment_date,
		'comment_content'		=> $comment_smsect->comment_content
	);
	$final_message = preg_replace('/%(.*?)%/ime', "\$template_vars['$1']", $string);
	$sms->msg = $final_message;
	$sms->SendSMS();
}
if($wps_options['wpsms_gnc_stats'])
	add_action('wp_insert_comment', 'wps_notification_new_comment',99,2);

// User login
function wps_notification_login($username_login, $username){
	global $sms, $wps_options;
	$sms->to = array(get_option('wp_admin_mobile'));
	$string = $wps_options['wpsms_ul_tt'];
	$template_vars = array(
		'username_login'	=> $username->user_login,
		'display_name'	=> $username->display_name
	);
	$final_message = preg_replace('/%(.*?)%/ime', "\$template_vars['$1']", $string);
	$sms->msg = $final_message;
	$sms->SendSMS();
}
if($wps_options['wpsms_ul_stats'])
	add_action('wp_login', 'wps_notification_login', 99, 2);