<?php

namespace WP_SMS\Gateway;

class unifonic extends \WP_SMS\Gateway
{
    private $wsdl_link = "https://api.unifonic.com/rest/";
    public $tariff = "https://api.unifonic.com/";
    public $unitrial = false;
    public $unit;
    public $flash = "disable";
    public $isflash = false;

    public function __construct()
    {
        parent::__construct();
        $this->has_key        = true;
        $this->bulk_send      = true;
        $this->help           = "Just fill the API/Key field with the AppSid token from your app. Check link:<a href='https://software.unifonic.com/en/devtools/restApp' target='_blank' >Click Here</a>";
        $this->validateNumber = "e.g. 96655xxxxxxx, 96655xxxxxxx";
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

        $numbers = array();

        foreach ($this->to as $number) {
            $numbers[] = $this->clean_number($number);
        }

        $args = array(
            'body' => array(
                'AppSid'    => $this->has_key,
                'Recipient' => $numbers,
                'Body'      => $this->msg,
                'SenderID'  => $this->from,
            ),
        );

        $response = wp_remote_post($this->wsdl_link . "Messages/SendBulk", $args);

        // Check gateway response
        if (is_wp_error($response)) {
            // Log the result
            $this->log($this->from, $this->msg, $this->to, $response->get_error_message(), 'error');

            return new \WP_Error('send-sms', $response->get_error_message());
        }

        $response_code = wp_remote_retrieve_response_code($response);

        if ($response_code == '200') {
            $result = json_decode($response['body']);
            var_dump($result);
            if (isset($result['success']) and $result['success'] == 'true') {

                // Log the result
                $this->log($this->from, $this->msg, $this->to, $result);

                /**
                 * Run hook after send sms.
                 *
                 * @param string $result result output.
                 *
                 * @since 2.4
                 *
                 */
                do_action('wp_sms_send', $result);

                return $result;
            } else {
                // Log the result
                $this->log($this->from, $this->msg, $this->to, $response['body'], 'error');

                return new \WP_Error('send-sms', $response['body']);
            }

        } else {
            // Log the result
            $this->log($this->from, $this->msg, $this->to, $response['body'], 'error');

            return new \WP_Error('send-sms', $response['body']);
        }
    }

    public function GetCredit()
    {
        // Check api key
        if (!$this->has_key) {
            return new \WP_Error('account-credit', __('API/Key does not set for this gateway', 'wp-sms-pro'));
        }

        $args = array(
            'body' => array(
                'AppSid' => $this->has_key,
            ),
        );

        $response = wp_remote_post($this->wsdl_link . "Account/GetBalance", $args);

        // Check gateway credit
        if (is_wp_error($response)) {
            return new \WP_Error('account-credit', $response->get_error_message());
        }

        $response_code = wp_remote_retrieve_response_code($response);

        if ($response_code == '200') {
            $result = json_decode($response['body'], true);

            if (isset($result['success']) and $result['success'] == 'true') {
                return 'B:' . $result['data']['Balance'] . '|P:' . $result['data']['remainingPoints']['points_count'];
            } else {
                return new \WP_Error('account-credit', $response['body']);
            }
        } else {
            return new \WP_Error('account-credit', $response['body']);
        }
    }

    /**
     * Clean number
     *
     * @param $number
     *
     * @return bool|string
     */
    private function clean_number($number)
    {
        $number = str_replace('+', '', $number);
        $number = trim($number);

        return $number;
    }
}