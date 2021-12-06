<?php

namespace WP_SMS\Gateway;

class cheapglobalsms extends \WP_SMS\Gateway
{
    private $wsdl_link = "http://cheapglobalsms.com/api_v1";
    public $tariff = "https://cheapglobalsms.com";
    public $unitrial = false;
    public $unit;
    public $flash = "enable";
    public $isflash = false;

    public function __construct()
    {
        parent::__construct();
        $this->validateNumber = "The recipient's phone numbers. Multiple numbers can be separated by comma (,). Any mobile numbers starting with zero will have the zero stripped and replaced with the sub-account's default dial code. If the mobile number does not start with a zero, the default dial code will not be applied." . PHP_EOL . "E.G if the sub-account's default dial code is '+234', 08086689567,+2348094309926,4478128372838 will be converted to, 2348086689567,2348094309926,4478128372838";
    }

    public function SendSMS()
    {

        /**
         * Modify sender number
         *
         * @param string $this ->from sender number.
         *
         * @since 3.4
         *
         */
        $this->from = apply_filters('wp_sms_from', $this->from);

        /**
         * Modify Receiver number
         *
         * @param array $this ->to receiver number
         *
         * @since 3.4
         *
         */
        $this->to = apply_filters('wp_sms_to', $this->to);

        /**
         * Modify text message
         *
         * @param string $this ->msg text message.
         *
         * @since 3.4
         *
         */
        $this->msg = apply_filters('wp_sms_msg', $this->msg);

        // Get the credit.
        $credit = $this->GetCredit();

        // Check gateway credit
        if (is_wp_error($credit)) {
            // Log the result
            $this->log($this->from, $this->msg, $this->to, $credit->get_error_message(), 'error');

            return $credit;
        }

        try {

            $unicode = 0;
            if (isset($this->options['send_unicode']) and $this->options['send_unicode']) {
                $unicode = 1;
            }

            $type = 0;
            if ($this->isflash == true) {
                $type = 1;
            }

            $numbers = array();

            foreach ($this->to as $number) {
                $numbers[] = $this->clean_number($number);
            }

            $to  = implode(',', $numbers);
            $msg = $this->msg;

            $args     = array(
                'body' => array(
                    'sub_account'      => $this->username,
                    'sub_account_pass' => $this->password,
                    'action'           => 'send_sms',
                    'sender_id'        => $this->from,
                    'message'          => $msg,
                    'recipients'       => $to,
                    'type'             => $type,
                    'unicode'          => $unicode
                ),
            );
            $response = wp_remote_post($this->wsdl_link, $args);

            // Check response error
            if (is_wp_error($response)) {
                // Log the result
                $this->log($this->from, $this->msg, $this->to, $response->get_error_message(), 'error');

                return new \WP_Error('send-sms', $response->get_error_message());
            }

            $result = json_decode($response['body']);

            if (isset($result->batch_id)) {
                // Log the result
                $this->log($this->from, $this->msg, $this->to, $result);

                /**
                 * Run hook after send sms.
                 *
                 * @since 2.4
                 */
                do_action('wp_sms_send', $result);

                return $result;
            } else {
                // Log the result
                $this->log($this->from, $this->msg, $this->to, $result->error, 'error');

                return new \WP_Error('send-sms', $result->error);
            }
        } catch (\Exception $e) {
            // Log the result
            $this->log($this->from, $this->msg, $this->to, $e->getMessage(), 'error');

            return new \WP_Error('send-sms', $e->getMessage());
        }
    }

    public function GetCredit()
    {
        // Check username and password
        if (!$this->username && !$this->password) {
            return new \WP_Error('account-credit', __('The Username/Password for this gateway is not set', 'wp-sms'));
        }
        $response = wp_remote_get($this->wsdl_link . "?sub_account=" . $this->username . "&sub_account_pass=" . $this->password . "&action=account_info");

        if (is_wp_error($response)) {
            return new \WP_Error('account-credit', $response->get_error_message());
        }

        $result = json_decode($response['body']);

        if (isset($result->balance)) {
            return $result->balance;
        } else {
            return new \WP_Error('account-credit', $result->error);
        }

    }

    private function clean_number($number)
    {
        $number = trim($number);

        return $number;
    }
}
