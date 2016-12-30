<?php
/**
 * WP SMS integrations class
 * 
 * @category   class
 * @package    WP_SMS
 * @version    1.0
 */

class WP_SMS_Integrations {

	public $sms;
	public $date;
	public $options;

	public function __construct() {
		global $wpsms_option, $sms, $wp_version;

		$this->sms = $sms;
		$this->date = WP_SMS_CURRENT_DATE;
		$this->options = $wpsms_option;

		// Contact Form 7 Hooks
		if( isset($this->options['cf7_metabox']) ) {
			add_filter('wpcf7_editor_panels', array(&$this, 'cf7_editor_panels'));
			add_action('wpcf7_after_save', array(&$this, 'wpcf7_save_form'));
			add_action('wpcf7_before_send_mail', array(&$this, 'wpcf7_sms_handler'));
		}
	}

	public function cf7_editor_panels($panels) {
		$new_page = array(
			'wpsms' => array(
				'title' => __('SMS', 'wp-sms'),
				'callback' => array(&$this, 'cf7_setup_form')
			)
		);
		
		$panels = array_merge($panels, $new_page);
		
		return $panels;
	}

	public function cf7_setup_form($form) {
		$cf7_options = get_option('wpcf7_sms_' . $form->id());
		$cf7_options_field = get_option('wpcf7_sms_form' . $form->id());
		
		include_once dirname( __FILE__ ) . "/templates/wp-sms-wpcf7-form.php";
	}

	public function wpcf7_save_form($form) {
		update_option('wpcf7_sms_' . $form->id(), $_POST['wpcf7-sms']);
		update_option('wpcf7_sms_form' . $form->id(), $_POST['wpcf7-sms-form']);
	}

	public function wpcf7_sms_handler($form) {
		$cf7_options = get_option('wpcf7_sms_' . $form->id());
		$cf7_options_field = get_option('wpcf7_sms_form' . $form->id());
		
		if( $cf7_options['message'] && $cf7_options['phone'] ) {
			$this->sms->to = array( $cf7_options['phone'] );
			$this->sms->msg = @preg_replace('/%([a-zA-Z0-9._-]+)%/e', '$_POST["$1"]', $cf7_options['message']);
			$this->sms->SendSMS();
		}
		
		if( $cf7_options_field['message'] && $cf7_options_field['phone'] ) {
			$this->sms->to = array( @preg_replace('/%([a-zA-Z0-9._-]+)%/e', '$_POST["$1"]', $cf7_options_field['phone']) );
			$this->sms->msg = @preg_replace('/%([a-zA-Z0-9._-]+)%/e', '$_POST["$1"]', $cf7_options_field['message']);
			$this->sms->SendSMS();
		}
	}

}

new WP_SMS_Integrations();