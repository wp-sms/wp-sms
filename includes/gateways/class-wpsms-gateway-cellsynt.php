<?php

namespace WP_SMS\Gateway;

if (!defined('ABSPATH')) exit; // Exit if accessed directly

class cellsynt extends \WP_SMS\Gateway
{
    private $wsdl_link = "https://se-1.cellsynt.net/sms.php";
    public $tariff = "https://www.cellsynt.com/";
    public $unitrial = false;
    public $unit;
    public $flash = "enable";
    public $isflash = false;
    public $originatortype = 'numeric';

    public function __construct()
    {
        parent::__construct();
        $this->validateNumber  = "00xxxxxxxxxxxx";
        $this->supportIncoming = true;
        $this->documentUrl     = 'https://wp-sms-pro.com/resources/cellsynt-gateway-configuration/';
        $this->gatewayFields = [
            'username'         => [
                'id'   => 'username',
                'name' => 'API Username',
                'desc' => 'Enter API username of gateway.',
            ],
            'password'         => [
                'id'   => 'password',
                'name' => 'API Password',
                'desc' => 'Enter API password of gateway.',
            ],
            'from'         => [
                'id'   => 'originator',
                'name' => 'Sender Number',
                'desc' => 'Sender number or sender ID',
            ],
            'originatortype'         => [
                'id'      => 'originatortype',
                'name'    => __('Originator Type', 'wp-sms'),
                'desc'    => __('Please select originator type.', 'wp-sms'),
                'type'    => 'select',
                'options' => [
                    'alpha'     => __('Alpha', 'wp-sms'),
                    'numeric'   => __('Numeric', 'wp-sms'),
                    'shortcode' => __('Shortcode', 'wp-sms'),
                ]
            ],
        ];
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

        $params = [
            'username'        => $this->username,
            'password'        => $this->password,
            'destination'     => implode(',', $this->to),
            'type'            => 'text',
            'charset'         => 'UTF-8',
            'text'            => urlencode($this->msg),
            'originatortype'  => $this->originatortype,
            'allowconcat'     => 6,
            'originator'      => $this->from,
        ];

        $result = $this->request('GET', "{$this->wsdl_link}/SendSMS", $params);

        if (strstr($result, 'OK:')) {
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
            $this->log($this->from, $this->msg, $this->to, $result, 'error');

            return new \WP_Error('send-sms', $result);
        }
    }

    public function GetCredit()
    {
        // Check username and password
        if (!$this->username or !$this->password) {
            return new \WP_Error('account-credit', esc_html__('The username/password for this gateway is not set', 'wp-sms'));
        }

        return true;
    }
}

?>