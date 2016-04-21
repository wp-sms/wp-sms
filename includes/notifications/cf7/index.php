<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Get WP SMS Option values
$wps_options = get_option('wpsms');
if(empty($wps_options['wpsms_add_wpcf7'])) $wps_options['wpsms_add_wpcf7'] = '';

function wps_cf7_options() {
	global $wps_options;
	include_once dirname( __FILE__ ) . '/options.php';
}
add_action('wp_sms_notification_page', 'wps_cf7_options');

// Contact Form 7 Hooks
if( $wps_options['wpsms_add_wpcf7'] ) {
	add_filter('wpcf7_editor_panels', 'wps_cf7_editor_panels');
	add_action('wpcf7_after_save', 'wps_save_wpcf7_form');
	add_action('wpcf7_before_send_mail', 'wps_send_wpcf7_sms');
}

function wps_cf7_editor_panels($panels) {
	$new_page = array(
		'wpsms' => array(
			'title' => __('SMS', 'wp-sms'),
			'callback' => 'wps_setup_wpcf7_form'
		)
	);
	
	$panels = array_merge($panels, $new_page);
	
	return $panels;
}

function wps_setup_wpcf7_form($form) {
	$cf7_options = get_option('wpcf7_sms_' . $form->id);
	$cf7_options_field = get_option('wpcf7_sms_form' . $form->id);
	
	include_once dirname( __FILE__ ) . "/wp-sms-wpcf7-form.php";
}

function wps_save_wpcf7_form($form) {
	update_option('wpcf7_sms_' . $form->id, $_POST['wpcf7-sms']);
	update_option('wpcf7_sms_form' . $form->id, $_POST['wpcf7-sms-form']);
}

function wps_send_wpcf7_sms($form) {
	global $sms;
	
	$cf7_options = get_option('wpcf7_sms_' . $form->id);
	$cf7_options_field = get_option('wpcf7_sms_form' . $form->id);
	
	if( $cf7_options['message'] && $cf7_options['phone'] ) {
		$sms->to = array( $cf7_options['phone'] );
		$sms->msg = preg_replace('/%([a-zA-Z0-9._-]+)%/e', '$_POST["$1"]', $cf7_options['message']);
		$sms->SendSMS();
	}
	
	if( $cf7_options_field['message'] && $cf7_options_field['phone'] ) {
		$sms->to = array( preg_replace('/%([a-zA-Z0-9._-]+)%/e', '$_POST["$1"]', $cf7_options_field['phone']) );
		$sms->msg = preg_replace('/%([a-zA-Z0-9._-]+)%/e', '$_POST["$1"]', $cf7_options_field['message']);
		$sms->SendSMS();
	}
}