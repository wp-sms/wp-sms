<?php

namespace WP_SMS\Gateway;

class infodomain extends \WP_SMS\Gateway
{
    private $wsdl_link = "http://sms.infodomain.asia/websmsapi";
    public $tariff = "http://sms.infodomain.asia";
    public $unitrial = false;
    public $unit;
    public $flash = "disable";
    public $isflash = false;

    public function __construct()
    {
        parent::__construct();
        $this->validateNumber = "";
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

        $to       = implode(",",$this->to);
        $msg      = urlencode($this->msg);
        $response = wp_remote_get($this->wsdl_link . "/ISendSMSNoDR.aspx?username=" . $this->username . "&password=" . $this->password . "&message=" . $msg . "&mobile=" . $to . "&Sender=" . $this->from . "&type=1");

        // Check gateway credit
        if (is_wp_error($response)) {
            // Log the result
            $this->log($this->from, $this->msg, $this->to, $response->get_error_message(), 'error');

            return new \WP_Error('send-sms', $response->get_error_message());
        }

        // Ger response code
        $response_code = wp_remote_retrieve_response_code($response);

        // Check response code
        if ($response_code == '200') {
            if (strpos($response['body'], '1701:') !== false) {
                // Log the result
                $this->log($this->from, $this->msg, $this->to, $response['body']);

                /**
                 * Run hook after send sms.
                 *
                 * @param string $response result output.
                 * @since 2.4
                 *
                 */
                do_action('wp_sms_send', $response['body']);

                return $response['body'];
            } else {
                $error_message = '';

                switch ($response['body']) {
                    case '1702':
                        $error_message = 'Invalid Username/Password';
                        break;

                    case '1703':
                        $error_message = 'Internal Server Error';
                        break;

                    case '1704':
                        $error_message = 'Insufficient Credits';
                        break;

                    case '1705':
                        $error_message = 'Invalid Mobile Number';
                        break;

                    case '1706':
                        $error_message = 'Invalid Message / Invalid SenderID';
                        break;

                    case '1707':
                        $error_message = 'Transfer Credits Successful';
                        break;

                    case '1708':
                        $error_message = 'Account not existing for Credits Transfer';
                        break;

                    case '1709':
                        $error_message = 'Invalid Credits Value for Credits Transfer';
                        break;

                    case '1718':
                        $error_message = 'Duplicate record received';
                        break;
                }
                // Log the result
                $this->log($this->from, $this->msg, $this->to, $error_message, 'error');

                return new \WP_Error('send-sms', $error_message);
            }

        } else {
            // Log the result
            $this->log($this->from, $this->msg, $this->to, $response['body'], 'error');

            return new \WP_Error('send-sms', $response['body']);
        }
    }

    public function GetCredit()
    {
        // Check username and password
        if (!$this->username && !$this->password) {
            return new \WP_Error('account-credit', __('API username or API password is not entered.', 'wp-sms'));
        }

        $response      = wp_remote_get($this->wsdl_link . "/creditsLeft.aspx?username=" . $this->username . "&password=" . $this->password);
        $response_code = wp_remote_retrieve_response_code($response);

        if ($response_code == '200') {
            return $response['body'];
        } else {
            return new \WP_Error('account-credit', __('Username/Password is not valid.', 'wp-sms'));
        }
    }
}