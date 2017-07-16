<?php

class _ebulksms extends WP_SMS {

    public $tariff = "http://api.ebulksms.com:8080/";
    public $unitrial = true;
    public $unit;
    public $flash = "enable";
    public $isflash = false;

    public function __construct() {
        parent::__construct();
        $this->validateNumber = "2347030000000,2348020000000,23489010000000";

        // Enable api key
        $this->has_key = true;

        // includes library
        include( 'includes/ebulksms/ebulksms.class.php' );
    }

    public function SendSMS() {
        // Check gateway credit
        if (is_wp_error($this->GetCredit())) {
            return new WP_Error('account-credit', __('Your account has no credit for sending sms.', 'wp-sms'));
        }

        /**
         * Modify sender number
         *
         * @since 3.4
         *
         * @param string $this ->from sender number.
         */
        $this->from = apply_filters('wp_sms_from', $this->from);

        /**
         * Modify Receiver number
         *
         * @since 3.4
         *
         * @param array $this ->to receiver number
         */
        $this->to = apply_filters('wp_sms_to', $this->to);

        /**
         * Modify text message
         *
         * @since 3.4
         *
         * @param string $this ->msg text message.
         */
        $this->msg = apply_filters('wp_sms_msg', $this->msg);

        // Init class
        $ebulksms = new Ebulksms($this->username, $this->has_key);

        // Add recipients
        $ebulksms->add_recipients($this->to);

        // Set From Name
        $ebulksms->set_from($this->from);

        // Set Message
        $ebulksms->set_message($this->msg);

        // Send sms
        $result = $ebulksms->send();

        // Check result
        if (!$result) {
            return new WP_Error('send-sms', $result);
        }

        $this->InsertToDB($this->from, $this->msg, $this->to);

        /**
         * Run hook after send sms.
         *
         * @since 2.4
         *
         * @param string $result result output.
         */
        do_action('wp_sms_send', $result);

        return $result;
    }

    public function GetCredit() {
        // Check username and password
        if (!$this->username && !$this->has_key) {
            return new WP_Error('account-credit', __('Username/Password was not set for this gateway', 'wp-sms'));
        }

        // Init class
        $ebulksms = new Ebulksms($this->has_key);

        // Get credit
        $credits = $ebulksms->get_credits($this->username, $this->has_key);

        return $credits;
    }

}
