<?php
/**
 * Main features class
 * 
 * @category   class
 * @package    WP_SMS
 * @version    1.0
 */
class WP_SMS_Features {

	/**
	 * WP-SMS Options
	 * @var array
	 */
	public $options;

	/**
	 * Constructor for the gateways class
	 */
	public function __construct() {
		// Set global options
		$this->options = $GLOBALS['wp_sms_options'];

		echo 'dsds';
	}

}

// Create object
new WP_SMS_Features();