<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Get WP SMS Option values
$wps_options = get_option('wpsms');
if(empty($wps_options['wpsms_wc_no_tt'])) $wps_options['wpsms_wc_no_tt'] = '';
if(empty($wps_options['wpsms_wc_no_stats'])) $wps_options['wpsms_wc_no_stats'] = '';

function wps_woc_options() {
	global $wps_options;
	include_once dirname( __FILE__ ) . '/options.php';
}
add_action('wp_sms_notification_page', 'wps_woc_options');

// Woocommerce Hooks
function wps_wcc_new_order($order_id){
	global $sms, $wps_options;
	$order = new WC_Order($order_id);
	
	if(!$get_mobile)
		return;
	
	$sms->to = array(get_option('wp_admin_mobile'));
	$string = $wps_options['wpsms_wc_no_tt'];
	$template_vars = array(
		'order_id'			=> $order_id,
		'status'			=> $order->get_status(),
		'order_name'		=> $order->get_order_number()
	);
	$final_message = preg_replace('/%(.*?)%/ime', "\$template_vars['$1']", $string);
	$sms->msg = $final_message;
	$sms->SendSMS();
}
if($wps_options['wpsms_wc_no_stats'])
	add_action('woocommerce_new_order', 'wps_wcc_new_order');