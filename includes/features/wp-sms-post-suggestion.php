<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function wps_post_suggestion_assets() {
	wp_enqueue_script('post-suggestion', WP_SMS_DIR_PLUGIN . 'assets/js/post-suggestion.js', true, '1.0');
	wp_enqueue_style('post-suggestion', WP_SMS_DIR_PLUGIN . 'assets/css/post-suggestion.css', true, '1.1');
}

function wps_post_suggestion($content) {
	if(is_single()) {
		global $sms;
		require_once dirname( __FILE__ ) . "/../templates/wp-sms-post-suggestion.php";
		
		if($_POST['send_post']) {
			$mobile = $_POST['get_fmobile'];
			if($_POST['get_name'] && $_POST['get_fname'] && $_POST['get_fmobile']) {
				if( preg_match("([a-zA-Z])", $mobile) == 0 ) {
					$sms->to = array($_POST['get_fmobile']);
					
					$template_vars = array(
						'post_title'		=> get_the_title(),
						'sms_sender'		=> $_POST['get_name'],
						'sms_receiver'		=> $_POST['get_fname'],
						'post_shortlink'	=> wp_get_shortlink(),
					);
					
					$string = get_option('wpsms_suggestion_tt');
					$final_message = preg_replace('/%(.*?)%/ime', "\$template_vars['$1']", $string);
					$sms->msg = $final_message;
					
					if( $sms->SendSMS() )
						_e('SMS was sent with success', 'wp-sms');
						
				} else {
					_e('Please enter a valid mobile number', 'wp-sms');
				}
			} else {
				_e('Please complete all fields', 'wp-sms');
			}
		}
	}
	return $content;
}

if(get_option('wp_suggestion_status')) {
	add_action('wp_head', 'wps_post_suggestion_assets');
	add_action('the_content', 'wps_post_suggestion');
}