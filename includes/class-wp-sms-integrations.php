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
	}

}

new WP_SMS_Integrations();