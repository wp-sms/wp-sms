<?php

namespace WP_SMS\Gateway;

class octopush extends \WP_SMS\Gateway
{
    private $wsdl_link = "https://www.octopush-dm.com/api";
    public $tariff = "https://www.octopush-dm.com/";
    public $documentUrl = 'https://wp-sms-pro.com/resources/octopush-gateway-configuration/';
    public $unitrial = true;
    public $unit;
    public $flash = "disable";
    public $isflash = false;

    public function __construct()
    {
        parent::__construct();
        $this->validateNumber = "List of numbers in international format + XXZZZZZ separated by commas.";
        $this->help           = 'Enter your <b>API Login / Username (email address)</b> to the <b>API username</b> field, and your <b>API Key</b> to the <b>API password</b> field and the <b>SMS type code (XXX = Lowcost, FR = Premium and WWW = world.)</b> to the <b>API key</b> field.';
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
        $response   = wp_remote_get("{$this->wsdl_link}/sms/json", [
            'body' => [
                'user_login'     => $this->username,
                'api_key'        => $this->password,
                'sms_recipients' => $recipients,
                'sms_text'       => $this->msg,
                'sms_type'       => $this->has_key ? $this->has_key : 'XXX',
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
        if (!$this->username or !$this->password) {
            return new \WP_Error('account-credit', __('API username or API Key is not entered.', 'wp-sms'));
        }

        $response = wp_remote_get("{$this->wsdl_link}/credit/json", [
            'body' => [
                'user_login' => $this->username,
                'api_key'    => $this->password,
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
        $responseCode = [
            100 => 'POST request missing.',
            101 => 'Incorrect login details.',
            102 => 'Your SMS exceeds 160 characters',
            103 => 'Your message has no recipients',
            104 => 'You have run out of credit.',
            105 => 'You don’t have enough credit on your balance, but your last order is waiting for being validated',
            106 => 'You have entered the Sender incorrectly. 3 to 11 characters, chosen from 0 to 9, a to z, A to Z. No accent, space or punctuation.',
            107 => 'The text of your message is missing.',
            108 => 'You have not entered your login details.',
            109 => 'You have not entered your password.',
            110 => 'You have not entered the list of recipient.',
            111 => 'You have not chosen a way to enter your recipients.',
            112 => 'You have not defined the quality of your message.',
            113 => 'Your account is not validated. Log in Octopush and go to the “User interface” section.',
            114 => 'You are under investigation for the fraudulent use of our services.',
            115 => 'The recipient number is different from the number of one of the parameters that you have related it to.',
            116 => 'The mailing option only works by using a contact list.',
            117 => 'Your recipient list contains no correct numbers. Have you formatted your numbers by including the international dialling code? Contact us if you have any problems.',
            118 => 'You must tick one of the two boxes to indicate if you do not wish to send test SMS or if you have correctly received and validated it.',
            119 => 'You cannot send SMS with more than 160 characters for this type of SMS',
            120 => 'A SMS with the same request_id has already been sent.',
            121 => 'In Premium SMS, the mention “STOP au XXXXX” is mandatory and must belong to your text',
            124 => 'The field request_sha1 does not match. The data is wrong, or the query string contains an error or the frame contains an error : the request is rejected.',
            125 => 'An undefined error has occurred. Please contact support.',
            126 => 'An SMS campaign is already waiting for approval to send. You must validate or cancel it in order to start another.',
            127 => 'An SMS campaign is already being processed. You must wait for processing to be completed in order to start another.',
            128 => 'Too many attempts have been made. You need to start a new campaign.',
            129 => 'Campaign is being built.',
            130 => 'Campaign has not been set as finished.',
            131 => 'Campaign not found.',
            132 => 'Campaign sent.',
            133 => 'The user_batch_id has already been used',
            150 => 'No country was found for this prefix.',
            151 => 'The recipient country is not part of the countries serviced by Octopush.',
            152 => 'You cannot send low cost SMS to this country. Choose Premium SMS',
            153 => 'The route is congested. This type of SMS cannot be dispatched immediately. If your order is urgent, please use another type of SMS.',
            201 => 'This option is only available on request. Do not hesitate to request access if you need it.',
            202 => 'The email account you wish to credit is incorrect.',
            203 => 'You already have tokens in use. You can only have one session open at a time.',
            204 => 'You specified a wrong token.',
            205 => 'The number of text messages you want to transfer is too low.',
            206 => 'You may not run campaigns during a credit transfer.',
            207 => 'You do not have access to this feature.',
            208 => 'Wrong type of SMS.',
            209 => 'You are not allowed to send SMS messages to this user.',
            210 => 'This email is not specified in any of your sub accounts or affiliate users.',
            300 => 'You are not authorized to manage your lists by API.',
            301 => 'You have reached the maximum number of lists.',
            302 => 'A list with the same name already exists.',
            303 => 'The specified list does not exist.',
            304 => 'The list is already full.',
            305 => 'There are too many contacts in the query.',
            306 => 'The requested action is unknown.',
            308 => 'Error of file.',
            500 => 'Impossible to process the requested action',
            501 => 'Connection error. Please contact our customer support'
        ];

        return isset($responseCode[$code]) ? $responseCode[$code] : 'Unknown error.';
    }
}