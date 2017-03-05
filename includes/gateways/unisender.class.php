<?php

class unisender extends WP_SMS
{
	private $wsdl_link = "https://api.unisender.com/en/api/";
	public $tariff = "http://www.unisender.com/en/prices/";
	public $unitrial = false;
	public $unit;
	public $flash = "enable";
	public $isflash = false;

	public function __construct()
	{
		parent::__construct();
		$this->validateNumber = "";
		$this->has_key = true;
	}

	public function SendSMS()
	{
		// Check gateway credit
		if (is_wp_error($this->GetCredit())) {
			return new WP_Error('account-credit', __('Your account does not credit for sending sms.', 'wp-sms'));
		}

		/**
		 * Modify sender number
		 *
		 * @since 3.4
		 * @param string $this ->from sender number.
		 */
		$this->from = apply_filters('wp_sms_from', $this->from);

		/**
		 * Modify Receiver number
		 *
		 * @since 3.4
		 * @param array $this ->to receiver number
		 */
		$this->to = apply_filters('wp_sms_to', $this->to);

		/**
		 * Modify text message
		 *
		 * @since 3.4
		 * @param string $this ->msg text message.
		 */
		$this->msg = apply_filters('wp_sms_msg', $this->msg);

		$to = implode($this->to, ",");
		$text = iconv('cp1251', 'utf-8', $this->msg);

		$response = wp_remote_get($this->wsdl_link . "sendSms?format=json&api_key=" . $this->has_key . "&sender=" . $this->from . "&text=" . $text . "&phone=" . $to);

		// Check gateway credit
		if (is_wp_error($response)) {
			return new WP_Error('send-sms', $response->get_error_message());
		}

		$response_code = wp_remote_retrieve_response_code($response);
		if ($response_code == '200') {
			$result = json_decode($response['body']);

			if (isset($result->error)) {
				return new WP_Error('send-sms', $result->code);
			}

			$this->InsertToDB($this->from, $this->msg, $this->to);

			/**
			 * Run hook after send sms.
			 *
			 * @since 2.4
			 * @param string $result result output.
			 */
			do_action('wp_sms_send', $result);

			return $result;

		} else {
			return new WP_Error('send-sms', $response['body']);
		}
	}

	public function GetCredit()
	{
		// Check username and password
		if (!$this->username && !$this->password) {
			return new WP_Error('account-credit', __('Username/Password does not set for this gateway', 'wp-sms'));
		}

		$json = file_get_contents("{$this->wsdl_link}getUserInfo?format=json&api_key=" . $this->has_key);
		$result = json_decode($json, true);

		if (isset($result['error'])) {
			return new WP_Error('account-credit', $result['code']);
		}

		return $result['result']['balance'];
	}
}