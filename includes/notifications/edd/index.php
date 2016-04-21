<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Get WP SMS Option values
$wps_options = get_option('wpsms');
if(empty($wps_options['wpsms_edd_no_tt'])) $wps_options['wpsms_edd_no_tt'] = '';
if(empty($wps_options['wpsms_edd_no_stats'])) $wps_options['wpsms_edd_no_stats'] = '';

function wps_edd_options() {
	global $wps_options;
	include_once dirname( __FILE__ ) . '/options.php';
}
add_action('wp_sms_notification_page', 'wps_edd_options');

// Easy Digital Downloads Hooks
function wps_edd_new_order() {
	global $sms, $wps_options;
	$sms->to = array(get_option('wp_admin_mobile'));
	$sms->msg = $wps_options['wpsms_edd_no_tt'];
	$sms->SendSMS();
}
if($wps_options['wpsms_edd_no_stats'])
	add_action('edd_complete_purchase', 'wps_edd_new_order');