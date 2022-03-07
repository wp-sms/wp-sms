<?php

namespace WP_SMS\Gateway;

class sunwaysms extends \WP_SMS\Gateway
{
    private $wsdl_link = "http://sms.sunwaysms.com/smsws/";
    public $tariff = "http://sunwaysms.com";
    public $unitrial = true;
    public $unit;
    public $flash = "disabled";
    public $isflash = false;

    public function __construct()
    {
        parent::__construct();
        $this->validateNumber = "e.g. 0910000000";
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

        $params = add_query_arg(array(
            'service'  => 'SendArray',
            'UserName' => $this->username,
            'Password' => $this->password,
            'To'       => implode(',', $this->to),
            'Message'  => urlencode($this->msg),
            'From'     => $this->from,
            'Flash'    => $this->isflash,
        ), $this->wsdl_link . "HttpService.ashx");

        $response = wp_remote_get($params);

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

            // Get the API error message.
            $error = $this->get_error_message($response['body']);

            // Check the error.
            if ($error) {
                // Log the result
                $this->log($this->from, $this->msg, $this->to, $error, 'error');

                return new \WP_Error('send-sms', $error);
            }

            // Log the result
            $this->log($this->from, $this->msg, $this->to, $response);

            /**
             * Run hook after send sms.
             *
             * @param string $response result output.
             *
             * @since 2.4
             *
             */
            do_action('wp_sms_send', $response);

            return $response;


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

        $response = wp_remote_get($this->wsdl_link . "HttpService.ashx?service=GetCredit&username={$this->username}&password=" . $this->password);

        // Check gateway credit
        if (is_wp_error($response)) {
            return new \WP_Error('account-credit', $response->get_error_message());
        }

        $response_code = wp_remote_retrieve_response_code($response);

        if ($response_code == '200') {
            return $response['body'];
        } else {
            return new \WP_Error('account-credit', $response['body']);
        }
    }

    /**
     * @param $code
     *
     * @return string
     */
    private function get_error_message($code)
    {
        switch ($code) {
            case '0':
                return 'MessageIDIsInvalid';
                break;
            case '1':
                return 'PendingStatus';
                break;
            case '2':
                return 'DeliveredToPhone';
                break;
            case '3':
                return 'FailedToPhone';
                break;
            case '4':
                return 'DeliveredToServiceCenter';
                break;
            case '5':
                return 'FailedToServiceCenter';
                break;
            case '6':
                return 'InDisableList';
                break;
            case '7':
                return 'InSendQueue';
                break;
            case '8':
                return 'Sending';
                break;
            case '9':
                return 'LowCredit';
                break;
            case '10':
                return 'NotSent';
                break;
            case '50':
                return 'Successful';
                break;
            case '51':
                return 'UserNameOrPasswordIsWrong';
                break;
            case '52':
                return 'UserNameOrPasswordIsEmpty';
                break;
            case '53':
                return 'RecipientNumberLengthIsMoreThanUsual';
                break;
            case '54':
                return 'RecipientNumberIsEmpty';
                break;
            case '55':
                return 'RecipientNumberIsNull';
                break;
            case '56':
                return 'MessageIDLengthIsMoreThanUsual';
                break;
            case '57':
                return 'MessageIDIsEmpty';
                break;
            case '58':
                return 'MessageIDIsNull';
                break;
            case '59':
                return 'MessageBodyIsEmpty';
                break;
            case '60':
                return 'InThisTimeServerCannotRespond';
                break;
            case '61':
                return 'SpecialNumberIsInvalid';
                break;
            case '62':
                return 'SpecialNumberIsEmpty';
                break;
            case '63':
                return 'ThisIPIsInvalid';
                break;
            case '64':
                return 'WSIDIsWrong';
                break;
            case '65':
                return 'NumberOfMessageIsWrong';
                break;
            case '66':
                return 'CheckingMessageIDLengthIsNotEqualWithRecipientNumberLength';
                break;
            case '67':
                return 'CheckingMessageIDLengthIsMoreThanUsual';
                break;
            case '68':
                return 'CheckingMessageIDIsEmpty';
                break;
            case '69':
                return 'CheckingMessageIDIsNull';
                break;
            case '70':
                return 'YourUserIsInActive';
                break;
            case '71':
                return 'DomainIsInvalid';
                break;
            case '72':
                return 'TimeIsWrong';
                break;
            case '73':
                return 'DateIsWrong';
                break;
            case '74':
                return 'NumberGroupIDLengthIsMoreThanUsual';
                break;
            case '75':
                return 'NumberGroupIDIsEmpty';
                break;
            case '76':
                return 'NumberGroupIDIsNull';
                break;
            case '77':
                return 'YouAreNotWebServiceUser';
                break;
            case '78':
                return 'YouAreNotSMSPanelUser';
                break;
            case '79':
                return 'PersonNameLengthIsNotEqualWithPersonNumberLength';
                break;
            case '80':
                return 'ServiceIsInActive';
                break;
            case '81':
                return 'PersonNumberLengthIsMoreThanUsual';
                break;
            case '82':
                return 'PersonNumberIsEmpty';
                break;
            case '83':
                return 'PersonNumberIsNull';
                break;
            case '84':
                return 'NumberGroupIDIsInvalid';
                break;
            case '201':
                return 'RecipientNumberFormatIsWrong';
                break;
            case '202':
                return 'RecipientNumberOperatorIsInvalid';
                break;
            case '203':
                return 'YouCanNotSendThisBecauseYourCreditIsNotEnough';
                break;
            case '204':
                return 'CheckingMessageIDIsNotValid';
                break;
            case '205':
                return 'PersonNumberFormatIsWrong';
                break;
            case '206':
                return 'PersonNumberOperatorIsInvalid';
                break;
        }
    }

}