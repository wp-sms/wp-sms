<?php

namespace WP_SMS\Gateway;

class zain extends \WP_SMS\Gateway
{
    private $wsdl_link = "https://www.zain.im/index.php/api/";
    public $tariff = "https://www.zain.im/";
    public $unitrial = false;
    public $unit;
    public $flash = "disable";
    public $isflash = false;

    public function __construct()
    {
        parent::__construct();
        $this->has_key        = false;
        $this->validateNumber = "Example: Phone = 966500000000";
        $this->help           = "Use Sender Number as Sender Name";
    }

    public function SendSMS()
    {

        /**
         * Modify sender number
         *
         * @param string $this ->from sender number.
         * @since 3.4
         *
         */
        $this->from = apply_filters('wp_sms_from', $this->from);

        /**
         * Modify Receiver number
         *
         * @param array $this ->to receiver number
         * @since 3.4
         *
         */
        $this->to = apply_filters('wp_sms_to', $this->to);

        /**
         * Modify text message
         *
         * @param string $this ->msg text message.
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

        $to   = implode(",", $this->to);
        $to   = urlencode($to);
        $text = urlencode($this->msg);
        $from = urlencode($this->from);

        $response = wp_remote_get($this->wsdl_link . "sendsms/?user=" . $this->username . "&pass=" . $this->password . "&to=" . $to . "&message=" . $text . "&sender=" . $from);

        // Check gateway credit
        if (is_wp_error($response)) {
            // Log th result
            $this->log($this->from, $this->msg, $this->to, $response->get_error_message(), 'error');

            return new \WP_Error('send-sms', $response->get_error_message());
        }

        $response_code = wp_remote_retrieve_response_code($response);

        if ($response_code == '200') {
            $result = json_decode($response['body']);

            if (isset($result) and is_int($result) and $result < 0) {
                // Log th result
                $this->log($this->from, $this->msg, $this->to, $this->get_error_message_send($result), 'error');

                return new \WP_Error('send-sms', $this->get_error_message_send($result));
            }

            // Log the result
            $this->log($this->from, $this->msg, $this->to, $result);

            /**
             * Run hook after send sms.
             *
             * @param string $result result output.
             * @since 2.4
             *
             */
            do_action('wp_sms_send', $result);

            return $result;

        } else {
            return new \WP_Error('send-sms', $response['body']);
        }
    }

    public function GetCredit()
    {
        // Check api key
        if (!$this->username or !$this->password) {
            return new \WP_Error('account-credit', __('Username and Password are required.', 'wp-sms'));
        }

        $response = wp_remote_get($this->wsdl_link . "chk_balance/?user={$this->username}&pass={$this->password}");

        // Check gateway credit
        if (is_wp_error($response)) {
            return new \WP_Error('account-credit', $response->get_error_message());
        }
        $response_code = wp_remote_retrieve_response_code($response);

        if ($response_code == '200') {
            $result = json_decode($response['body'], true);
            if (isset($result) and $result <= 0) {
                return new \WP_Error('account-credit', $this->get_error_message_balance($result));
            } else {
                return $result;
            }
        } else {
            return new \WP_Error('account-credit', $response['body']);
        }
    }

    /**
     * @param $error_code
     *
     * @return string
     */
    private function get_error_message_balance($error_code)
    {
        switch ($error_code) {
            case '-100':
                return 'Missing parameters (not exist or empty)<br>Username + password';
                break;

            case '-110':
                return 'Account not exist (wrong username or password)';
                break;

            default:
                return $error_code;
                break;
        }
    }

    /**
     * @param $error_code
     *
     * @return string
     */
    private function get_error_message_send($error_code)
    {
        switch ($error_code) {
            case '-100':
                return 'Missing parameters (not exist or empty)<br>user + pass + to + message + sender';
                break;

            case '-110':
                return 'Wrong username or password';
                break;

            case '-111':
                return 'The account not activated';
                break;

            case '-112':
                return 'Blocked account';
                break;

            case '-113':
                return 'not enough balance';
                break;

            case '-114':
                return 'The service not available for now';
                break;

            case '-115':
                return 'The sender not available (if user have no opened sender)<br>Note : if the sender opened will allow any sender';
                break;

            case '-116':
                return 'Invalid sender name';
                break;

            default:
                return $error_code;
                break;
        }
    }
}