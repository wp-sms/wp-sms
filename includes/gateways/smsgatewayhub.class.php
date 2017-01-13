<?php
class smsgatewayhub extends WP_SMS {
	private $wsdl_link = "http://login.smsgatewayhub.com/api/mt/";
	public $tariff = "https://www.smsgatewayhub.com/";
	public $unitrial = true;
	public $unit;
	public $flash = "disable";
	public $isflash = false;

	public function __construct() {
		parent::__construct();
		$this->validateNumber = "91989xxxxxxx,91999xxxxxxx";

		// Enable api key
		$this->has_key = true;
	}

	public function SendSMS() {
		// Check gateway credit
		if( is_wp_error($this->GetCredit()) ) {
			return new WP_Error( 'account-credit', __('Your account does not credit for sending sms.', 'wp-sms') );
		}
		
		/**
		 * Modify sender number
		 *
		 * @since 3.4
		 * @param string $this->from sender number.
		 */
		$this->from = apply_filters('wp_sms_from', $this->from);
		
		/**
		 * Modify Receiver number
		 *
		 * @since 3.4
		 * @param array $this->to receiver number
		 */
		$this->to = apply_filters('wp_sms_to', $this->to);
		
		/**
		 * Modify text message
		 *
		 * @since 3.4
		 * @param string $this->msg text message.
		 */
		$this->msg = apply_filters('wp_sms_msg', $this->msg);

		// Impload numbers
		$to = implode(',', $this->to);

		// Unicide message
		$msg = urlencode($this->msg);
		
		// Get data
		$result = file_get_contents($this->wsdl_link.'SendSMS?APIKey='.$this->has_key.'&senderid='.$this->from.'&channel=2&DCS=0&flashsms=0&number='.$to.'&text='.$msg.'&route=clickhere');

		// Check value
		if(!$result)
			return false;

		// Decode json
		$result = json_decode($result);

		// Check response
		if($result->ErrorMessage != 'Success')
			return new WP_Error( 'send-sms', $result );
		
		$this->InsertToDB($this->from, $this->msg, $this->to);
		
		/**
		 * Run hook after send sms.
		 *
		 * @since 2.4
		 * @param string $result result output.
		 */
		do_action('wp_sms_send', $result);

		return $result;
	}

	public function GetCredit() {
		// Check username and password
		if(!$this->username && !$this->password) {
			return new WP_Error( 'account-credit', __('Username/Password does not set for this gateway', 'wp-sms') );
		}
		
		// Get content
		$result = file_get_contents($this->wsdl_link.'GetBalance?APIKey='.$this->has_key);

		// Check value
		if(!$result)
			return new WP_Error( 'account-credit', $result );

		// Decode json
		$result = json_decode($result);

		// Check response
		if($result->ErrorMessage != 'Success')
			return new WP_Error( 'account-credit', $result );

		// Get first number from result
		$match = reset(array_filter(preg_split("/\D+/", $result->Balance)));

		return $match;
	}
}