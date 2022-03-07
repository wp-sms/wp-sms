<?php

namespace WP_SMS\Gateway;

class mobiledotnet extends \WP_SMS\Gateway
{
    private $wsdl_link = "https://mobile.net.sa/sms/gw/";
    public $tariff = "https://mobile.net.sa/";
    public $unitrial = true;
    public $unit;
    public $flash = "disable";
    public $isflash = false;

    public function __construct()
    {
        parent::__construct();
        $this->validateNumber = "e.g. 9029963999";
        $this->help           = 'Please enter Route ID in API Key field';
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

        $response = wp_remote_get($this->wsdl_link . "?userName=" . $this->username . "&userPassword=" . $this->password . "&numbers=" . implode(',', $this->to) . "&userSender=" . $this->from . "&msg=" . urlencode($this->msg) . "&By=standard");

        // Check gateway credit
        if (is_wp_error($response)) {
            // Log the result
            $this->log($this->from, $this->msg, $this->to, $response->get_error_message(), 'error');

            return new \WP_Error('send-sms', $response->get_error_message());
        }

        // Ger response code
        if (wp_remote_retrieve_response_code($response) == '200') {
            if ($response['body'] == '1') {
                // Log the result
                $this->log($this->from, $this->msg, $this->to, $response);

                /**
                 * Run hook after send sms.
                 *
                 * @param string $response result output.
                 * @since 2.4
                 *
                 */
                do_action('wp_sms_send', $response);

                return $response;
            } else {
                // Log the result
                $this->log($this->from, $this->msg, $this->to, $this->get_message_by_code($response['body']), 'error');

                return new \WP_Error('send-sms', $this->get_message_by_code($response['body']));
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
        if (!$this->username or !$this->password) {
            return new \WP_Error('account-credit', __('API username or API password is not entered.', 'wp-sms'));
        }

        $response = wp_remote_get($this->wsdl_link . "Credits.php?userName={$this->username}&userPassword={$this->password}&By=standard");

        // Check gateway credit
        if (is_wp_error($response)) {
            return new \WP_Error('account-credit', $response->get_error_message());
        }

        if (wp_remote_retrieve_response_code($response) == '200') {
            if ($response['body'] > 0) {
                return $response['body'];
            } else {
                return $this->get_message_by_code($response['body']);
            }
        } else {
            return new \WP_Error('account-credit', $response['body']);
        }
    }

    private function get_message_by_code($code)
    {
        if ($code === '0') {
            return 'Incomplete information. Username or password.';
        } elseif ($code === '00') {
            return 'A username or password is empty.';
        } elseif ($code === '000') {
            return 'Wrong data entry.';
        } elseif ($code === '0000') {
            return 'Balance is 0.';
        } elseif ($code === '1010') {
            return 'Incomplete information. username, password, phone number or message content.';
        } elseif ($code === '1020') {
            return 'Wrong login data.';
        } elseif ($code === '1030') {
            return 'The same message with the same destination in the queue, wait ten seconds before resending.';
        } elseif ($code === '1040') {
            return 'Letters not recognized.';
        } elseif ($code === '1050') {
            return 'The message is empty, the reason: the selection may be the reason for deleting the message content.';
        } elseif ($code === '1060') {
            return 'Balance not enough to send the message.';
        } elseif ($code === '1070') {
            return 'is 0, not enough to send the message.Balance';
        } elseif ($code === '1080') {
            return 'Message not sent an error while sending the message.';
        } elseif ($code === '1090') {
            return 'Repeating the selection process produced the message.';
        } elseif ($code === '1100') {
            return 'Sorry, the message not sent. Please try later.';
        } elseif ($code === '1110') {
            return 'Sorry, the sender name is incorrect. Please try to correct the sender name.';
        } elseif ($code === '1120') {
            return 'Sorry, the country in the destination phone numberyou are trying to send to is not covered by our network.';
        } elseif ($code === '1130') {
            return 'Sorry, return to supervisor of our networks as specified in your account network.';
        } elseif ($code === '1140') {
            return 'Sorry, you exceeded the maximum message parts. Try to send less number of parts.';
        } elseif ($code === '1150') {
            return 'This message is repeated with the same mobile number, sender name and message body.';
        } elseif ($code === '1160') {
            return 'There is a problem in the input of datetimeof later sending.';
        }
    }
}