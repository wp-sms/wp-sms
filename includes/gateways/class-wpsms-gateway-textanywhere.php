<?php

namespace WP_SMS\Gateway;

class textanywhere extends \WP_SMS\Gateway
{
    private $wsdl_link = "https://www.textapp.net/webservice/httpservice.aspx";
    public $tariff = "http://www.textanywhere.net/";
    public $unitrial = false;
    public $unit;
    public $flash = "disable";
    public $isflash = false;

    public function __construct()
    {
        parent::__construct();
        $this->has_key        = true;
        $this->help           = 'You should use the Client_Id and Client_Pass instead API username and API password. The Client_Id and Client_Pass value can be found by logging in to your online account, and clicking on the ADMIN PANEL button.';
        $this->validateNumber = "For example, mobile number (07836) 123-456 would be formatted as +447836123456.";
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

        $to      = implode(",", $this->to);
        $message = urlencode($this->msg);

        $response = wp_remote_get($this->wsdl_link . "?method=sendsms&externallogin=" . $this->username . "&password=" . $this->password . "&clientbillingreference=myclientbillingreference&clientmessagereference=myclientmessagereference&originator=" . $this->from . "&destinations=" . $to . "&body=" . $message . "&validity=72&charactersetid=2&replymethodid=1");

        // Check gateway credit
        if (is_wp_error($response)) {
            // Log the result
            $this->log($this->from, $this->msg, $this->to, $response->get_error_message(), 'error');

            return new \WP_Error('send-sms', $response->get_error_message());
        }

        $result = $this->XML2Array($response['body']);

        if (isset($result['Transaction']['Code']) and $result['Transaction']['Code'] == '1') {

            if (isset($result['Destinations']['Destination']['Code']) and $result['Destinations']['Destination']['Code'] == '1') {
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
                // Log the result
                $this->log($this->from, $this->msg, $this->to, $this->get_error_message($result['Destinations']['Destination']['Code']), 'error');

                return new \WP_Error('send-sms', $this->get_error_message($result['Destinations']['Destination']['Code']));
            }
        } else {
            // Log the result
            $this->log($this->from, $this->msg, $this->to, $result['Transaction']['Description'], 'error');

            return new \WP_Error('send-sms', $result['Transaction']['Description']);
        }
    }

    public function GetCredit()
    {
        // Check api key and password
        if (!$this->has_key && !$this->password) {
            return new \WP_Error('account-credit', __('API username or API password is not entered.', 'wp-sms'));
        }

        $response = wp_remote_get($this->wsdl_link . "?method=GetCreditsLeft&externallogin=" . $this->username . "&password=" . $this->password);

        // Check gateway credit
        if (is_wp_error($response)) {
            return new \WP_Error('account-credit', $response->get_error_message());
        }

        if (!function_exists('simplexml_load_string')) {
            return new \WP_Error('account-credit', sprintf(__('The <code>%s</code> function is not active in your server.', 'wp-sms'), 'simplexml_load_string'));
        }

        $result = $this->XML2Array($response['body']);

        if (isset($result['Transaction']['Code']) and $result['Transaction']['Code'] == '1') {
            return $result['CreditLeft'];
        } else {
            return new \WP_Error('account-credit', $result['Transaction']['Description']);
        }
    }

    /**
     * @param $xml
     * @param bool $recursive
     *
     * @return array
     */
    private function XML2Array(
        $xml, $recursive = false
    )
    {
        if (!$recursive) {
            $array = simplexml_load_string($xml);
        } else {
            $array = $xml;
        }

        $newArray = array();
        $array    = ( array )$array;
        foreach ($array as $key => $value) {
            $value = ( array )$value;
            if (isset ($value [0])) {
                $newArray [$key] = trim($value [0]);
            } else {
                $newArray [$key] = $this->XML2Array($value, true);
            }
        }

        return $newArray;
    }

    /**
     * @param $error_code
     *
     * @return string
     */
    private function get_error_message($error_code)
    {
        switch ($error_code) {
            case '361':
                return 'Destination in wrong format';
                break;

            case '901':
                return 'Account suspended';
                break;

            default:
                return sprintf('Error code: %s, See message codes: http://developer.textapp.net/HTTPService/TransactionCodes.aspx', $error_code);
                break;
        }
    }
}