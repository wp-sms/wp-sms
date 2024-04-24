<?php

namespace WP_SMS\Gateway;

// Import necessary classes
use Exception;
use WP_Error;
use WP_SMS\Gateway;
use WP_SMS\Helper;

class sample extends Gateway
{
    private $wsdl_link = ""; // The link to the gateway API
    public $tariff = "";
    public $unitrial = false;
    public $unit;
    public $flash = "disable";
    public $isflash = false;

    public function __construct()
    {
        parent::__construct();
        $this->has_key         = false; // The gateway does not require an API key (bool)
        $this->bulk_send       = false; // The gateway does not support bulk sending (bool)
        $this->supportMedia    = false; // The gateway does not support MMS (bool)
        $this->supportIncoming = false; // The gateway does not support incoming messages (bool)
        $this->validateNumber  = ""; // Show valid number instruction (bool)
        $this->help            = true; // Show help instruction (bool)
        $this->documentUrl     = ""; // The URL of the gateway document (string)
        /**
         * The gateway fields are the fields that the user must complete in the
         * settings section of the plugin to connect to the gateway.
         *
         * The fields are as follows:
         * id: The field ID
         * name: The field name
         * desc: The field description
         * type: The field type (text, select, checkbox, radio, etc.)
         * options: The field options (array)
         */
        $this->gatewayFields = [
            'route' => [
                'id'      => 'route',
                'name'    => 'Route',
                'desc'    => 'Please select SMS route.',
                'type'    => 'select',
                'options' => [
                    'route1' => 'Route 1',
                ]
            ],
        ];

    }

    public function SendSMS()
    {
        /**
         * From/Sender ID
         *
         * The sender ID is the name or number that appears on the recipient's mobile phone.
         * The sender ID must be registered and approved by the gateway.
         * If the sender ID is not registered, the gateway will use a default sender ID.
         * The sender ID is optional and may not be supported by all gateways.
         *
         * @var string
         */
        $this->from = apply_filters('wp_sms_from', $this->from);

        /**
         * Recipient mobile number
         *
         * The mobile number of the recipient of the message.
         * The mobile number is required and must be validated.
         *
         * @var array
         */
        $this->to = apply_filters('wp_sms_to', $this->to);

        /**
         * Message content
         *
         * The content of the message to be sent.
         * The message content is required and must be validated.
         *
         * @var string
         */
        $this->msg = apply_filters('wp_sms_msg', $this->msg);

        try {
            /**
             * First, we check whether it is possible to connect to the gateway through the
             * GetCredit function, and if not, a connection error and no message will be sent.
             */
            $credit = $this->GetCredit();

            if (is_wp_error($credit)) {
                $this->log($this->from, $this->msg, $this->to, $credit->get_error_message(), 'error');

                return $credit;
            }

            /**
             * If the SMS gateway has restrictions on the structure of mobile phone numbers,
             * you can use the helper function removeNumbersPrefix in the WP SMS plugin. This
             * auxiliary function removes extra prefixes from mobile numbers and converts them
             * to the format required by the SMS gateway.
             *
             * The following example removes the prefixes 91, 0091, +91 from a mobile number
             * and converts the number to a format accepted by the gateway.
             * If the international structure is accepted by the gateway,
             * the following code is not needed.
             */
            $this->to = Helper::removeNumbersPrefix(['91', '+91', '0091'], $this->to);

            /**
             * Set the arguments and/or parameters required to send the message
             * and send the request to send the message using $this->request.
             *
             * To check the description of the parameters,
             * refer to the class-wpsms-gateway.php file.
             *
             */
            $arguments = [];
            $params    = [];
            $response  = $this->request('GET', $this->wsdl_link, $arguments, $params, true);

            /**
             * After sending the message without error, we check whether
             * the gateway sent the message successfully or not,
             * and if there was an error, we register the error.
             *
             * The code below is an example, you can change it based on the parameter
             * in which the message status is determined by the gateway.
             */
            if ($response->status != true) {
                throw new Exception($response->message);
            }

            /**
             * If there are other errors by the gateway, handle them as above.
             */

            /**
             * If there are no errors, the response of the gateway is stored in the database.
             *
             * No need to change the above code.
             */
            $this->log($this->from, $this->msg, $this->to, $response);

            do_action('wp_sms_send', $response);

            return $response;

        } catch (Exception $e) {
            $this->log($this->from, $this->msg, $this->to, $e->getMessage(), 'error');

            return new WP_Error('send-sms', $e->getMessage());
        }

    }

    /**
     * Get the balance of the user account
     *
     * In this function, by receiving the balance of the user's account,
     * we check whether it is possible to connect to the gateway or not
     *
     * @return mixed
     */
    public function GetCredit()
    {
        try {
            /**
             * First, check that the gateway configuration fields are complete
             * and if not, return an error.
             */
            if (empty($this->key)) {
                return new WP_Error('account-credit', 'Please enter your API key.');
            }

            /**
             * If the api gateway does not receive the user balance,
             * return the following text and skip the continuation of the code.
             */
            return 'Unable to check balance!';

            /**
             * Set the argument and/or params required to receive the user account
             * and send the request to receive the user balance using $this->request.
             */
            $arguments = [];
            $params    = [];
            $response  = $this->request('GET', $this->wsdl_link, $arguments, $params, true);

            /**
             * In case of not connecting to the gateway or any other error,
             * manage it in this section.
             */
            if (!isset($response->balance)) {
                throw new Exception($response->message);
            }

            /**
             * If the connection to the gateway is successful,
             * the balance of the user's account is returned.
             *
             * Change the value below based on the Balance response field.
             */
            return $response->balance;

        } catch (Exception $e) {
            return new WP_Error('account-credit', $e->getMessage());
        }
    }

    /**
     * Other functions if needed!
     */

}