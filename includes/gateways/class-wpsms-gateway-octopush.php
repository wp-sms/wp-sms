<?php

namespace WP_SMS\Gateway;

class octopush extends \WP_SMS\Gateway
{
    private $wsdl_link = "https://www.octopush-dm.com/api";
    public $tariff = "https://www.octopush-dm.com/";
    public $unitrial = true;
    public $unit;
    public $flash = "disable";
    public $isflash = false;

    public function __construct()
    {
        parent::__construct();
        $this->validateNumber = "List of numbers in international format + XXZZZZZ separated by commas.";
        $this->help           = 'Enter your API key, user login (email address) in the <b>API password</b> field and leave blank the <b>API password</b> field.';
        $this->has_key        = true;
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

        if (is_wp_error($credit)) {
            $this->log($this->from, $this->msg, $this->to, $credit->get_error_message(), 'error');
            return $credit;
        }

        $recipients = implode(',', $this->to);
        $response   = wp_remote_get("{$this->wsdl_link}/credit/json", [
            'body' => [
                'user_login'     => $this->username,
                'api_key'        => $this->has_key,
                'sms_recipients' => $recipients,
                'sms_text'       => $this->msg,
                'sms_type'       => 'XXX', // todo should get from setting
                'sms_sender'     => $this->from,
            ]
        ]);

        if (is_wp_error($response)) {
            $this->log($this->from, $this->msg, $this->to, $response->get_error_message(), 'error');
            return $response;
        }

        if (wp_remote_retrieve_response_code($response) != '200') {
            return new \WP_Error('send-sms', $response['body']);
        }

        $response = json_decode($response['body']);

        if ($response->error_code != '000') {
            $errorMessage = $this->getErrorMessageFromErrorCode($response->error_code);

            $this->log($this->from, $this->msg, $this->to, $errorMessage, 'error');
            return new \WP_Error('send-sms', $errorMessage);
        }

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
    }

    public function GetCredit()
    {
        // Check username and password
        if (!$this->username or !$this->has_key) {
            return new \WP_Error('account-credit', __('API username or API Key is not entered.', 'wp-sms-pro'));
        }

        $response = wp_remote_get("{$this->wsdl_link}/credit/json", [
            'body' => [
                'user_login' => $this->username,
                'api_key'    => $this->has_key,
            ]
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        if (wp_remote_retrieve_response_code($response) != '200') {
            return new \WP_Error('account-credit', $response['body']);
        }

        $response = json_decode($response['body']);

        if ($response->error_code != '000') {
            return new \WP_Error('account-credit', $this->getErrorMessageFromErrorCode($response->error_code));
        }

        return $response->credit;
    }

    private function getErrorMessageFromErrorCode($code)
    {
        switch ($code) {
            case 100;
                return 'POST request missing.';
                break;
            case 101;
                return 'Incorrect login details.';
                break;
            case 102;
                return 'Your SMS exceeds 160 characters';
                break;
            case 103;
                return 'Your message has no recipients';
                break;
            case 104;
                return 'You have run out of credit.';
                break;
            case 105;
                return 'You don’t have enough credit on your balance, but your last order is waiting for being validated';
                break;
            case 106;
                return 'You have entered the Sender incorrectly. 3 to 11 characters, chosen from 0 to 9, a to z, A to Z. No accent, space or punctuation.';
                break;
            case 107;
                return 'The text of your message is missing.';
                break;
            case 108;
                return 'You have not entered your login details.';
                break;
            case 109;
                return 'You have not entered your password.';
                break;
            case 110;
                return 'You have not entered the list of recipient.';
                break;
            case 111;
                return 'You have not chosen a way to enter your recipients.';
                break;
            case 112;
                return 'You have not defined the quality of your message.';
                break;
            case 113;
                return 'Your account is not validated. Log in Octopush and go to the “User interface” section.';
                break;
            case 114;
                return 'You are under investigation for the fraudulent use of our services.';
                break;
            case 115;
                return 'The recipient number is different from the number of one of the parameters that you have related it to.';
                break;
            case 116;
                return 'The mailing option only works by using a contact list.';
                break;
            case 117;
                return 'Your recipient list contains no correct numbers. Have you formatted your numbers by including the international dialling code? Contact us if you have any problems.';
                break;
            case 118;
                return 'You must tick one of the two boxes to indicate if you do not wish to send test SMS or if you have correctly received and validated it.';
                break;
            case 119;
                return 'You cannot send SMS with more than 160 characters for this type of SMS';
                break;
            case 120;
                return 'A SMS with the same request_id has already been sent.';
                break;
            case 121;
                return 'In Premium SMS, the mention “STOP au XXXXX” is mandatory and must belong to your text (respect the case).';
                break;
            case 122;
                return 'In Standard SMS, the mention “no PUB=STOP” is mandatory and must belong to your text (respect the case).';
                break;
            case 123;
                return 'The field request_sha1 is missing.';
                break;
            case 124;
                return 'The field request_sha1 does not match. The data is wrong, or the query string contains an error or the frame contains an error : the request is rejected.';
                break;
            case 125;
                return 'An undefined error has occurred. Please contact support.';
                break;
            case 126;
                return 'An SMS campaign is already waiting for approval to send. You must validate or cancel it in order to start another.';
                break;
            case 127;
                return 'An SMS campaign is already being processed. You must wait for processing to be completed in order to start another.';
                break;
            case 128;
                return 'Too many attempts have been made. You need to start a new campaign.';
                break;
            case 129;
                return 'Campaign is being built.';
                break;
            case 130;
                return 'Campaign has not been set as finished.';
                break;
            case 131;
                return 'Campaign not found.';
                break;
            case 132;
                return 'Campaign sent.';
                break;
            case 133;
                return 'The user_batch_id has already been used';
                break;
            case 150;
                return 'No country was found for this prefix.';
                break;
            case 151;
                return 'The recipient country is not part of the countries serviced by Octopush.';
                break;
            case 152;
                return 'You cannot send low cost SMS to this country. Choose Premium SMS';
                break;
            case 153;
                return 'The route is congested. This type of SMS cannot be dispatched immediately. If your order is urgent, please use another type of SMS.';
                break;
            case 201;
                return 'This option is only available on request. Do not hesitate to request access if you need it.';
                break;
            case 202;
                return 'The email account you wish to credit is incorrect.';
                break;
            case 203;
                return 'You already have tokens in use. You can only have one session open at a time.';
                break;
            case 204;
                return 'You specified a wrong token.';
                break;
            case 205;
                return 'The number of text messages you want to transfer is too low.';
                break;
            case 206;
                return 'You may not run campaigns during a credit transfer.';
                break;
            case 207;
                return 'You do not have access to this feature.';
                break;
            case 208;
                return 'Wrong type of SMS.';
                break;
            case 209;
                return 'You are not allowed to send SMS messages to this user.';
                break;
            case 210;
                return 'This email is not specified in any of your sub accounts or affiliate users.';
                break;
            case 300;
                return 'You are not authorized to manage your lists by API.';
                break;
            case 301;
                return 'You have reached the maximum number of lists.';
                break;
            case 302;
                return 'A list with the same name already exists.';
                break;
            case 303;
                return 'The specified list does not exist.';
                break;
            case 304;
                return 'The list is already full.';
                break;
            case 305;
                return 'There are too many contacts in the query.';
                break;
            case 306;
                return 'The requested action is unknown.';
                break;
            case 308;
                return 'Error of file.';
                break;
            case 500;
                return 'Impossible to process the requested action';
                break;
            case 501;
                return 'Connection error. Please contact our customer support';
                break;
        }
    }
}