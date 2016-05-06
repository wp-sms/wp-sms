<?php
if ( ! defined( 'ABSPATH' ) ) exit;

global $wps_options;

function wp_sms_subscribe_meta_box() {
	add_meta_box('subscribe-meta-box', __('SMS', 'wp-sms'), 'wp_sms_subscribe_post', 'post', 'normal', 'high');
}

if(isset($wps_options['wp_subscribes_send']))
	add_action('add_meta_boxes', 'wp_sms_subscribe_meta_box');

function wp_sms_subscribe_post($post) {
	global $wpdb, $table_prefix, $sms;
	$get_group_result = $wpdb->get_results("SELECT * FROM `{$table_prefix}sms_subscribes_group`");
	$username_active = $wpdb->query("SELECT * FROM {$table_prefix}sms_subscribes WHERE status = '1'");
	include_once dirname( __FILE__ ) . "/includes/templates/wp-sms-meta-box.php";
}

function wp_sms_subscribe_send($wp_sms_new_status = NULL, $wp_sms_old_status = NULL, $post = NULL) {
	if($_REQUEST['wps_send_subscribe'] == 'yes') {
		if ( 'publish' == $wp_sms_new_status && 'publish' != $wp_sms_old_status ) {
			
			global $wpdb, $table_prefix, $sms, $wps_options;
			
			if($_REQUEST['wps_subscribe_group'] == 'all') {
				$sms->to = $wpdb->get_col("SELECT mobile FROM {$table_prefix}sms_subscribes");
			} else {
				$sms->to = $wpdb->get_col("SELECT mobile FROM {$table_prefix}sms_subscribes WHERE group_ID = '{$_REQUEST['wps_subscribe_group']}'");
			}
			
			$template_vars = array(
				'title_post' => get_the_title($post->ID),
				'url_post' => wp_get_shortlink($post->ID),
				'date_post' => get_post_time('Y-m-d', true, $post->ID, true)
			);
			
			$sms->msg = preg_replace('/%(.*?)%/ime', "\$template_vars['$1']", $_REQUEST['wps_custom_text']);
			
			$sms->SendSMS();
		}
	}
	return $post;
}
if(isset($wps_options['wp_subscribes_send']))
	add_action('transition_post_status', 'wp_sms_subscribe_send', 10, 3);

function wp_sms_register_new_subscribe($name, $mobile) {
	global $sms;
	
	$string = get_option('wp_subscribes_text_send');
	
	$template_vars = array(
		'subscribe_name'	=> $name,
		'subscribe_mobile'	=> $mobile
	);
	
	$final_message = preg_replace('/%(.*?)%/ime', "\$template_vars['$1']", $string);
	
	$sms->to = array($mobile);
	$sms->msg = $final_message;
	
	$sms->SendSMS();
}
if(get_option('wp_subscribes_send_sms'))
	add_action('wps_add_subscriber', 'wp_sms_register_new_subscribe', 10, 2);

function wps_add_user_to_newsletter() {
	$subscribe = new WP_SMS_Subscriptions();
	$subscribe->add_subscriber($_POST['user_login'], $_POST['mobile']);
}
if(get_option('wps_add_user_to_newsletter'))
	add_action('user_register', 'wps_add_user_to_newsletter');