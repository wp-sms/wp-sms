<?php

namespace WP_SMS\Gateway;

class _0098sms extends \WP_SMS\Gateway
{
    private $wsdl_link = "http://webservice.0098sms.com/service.asmx?wsdl";
    public $tariff = "http://www.0098sms.com/";
    public $unitrial = false;
    public $unit;
    public $flash = "enable";
    public $isflash = false;

    public function __construct()
    {
        parent::__construct();
        $this->validateNumber = "09xxxxxxxxx";
        $this->bulk_send      = false;
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

        try {
            $sms_client = new \SoapClient($this->wsdl_link, array('encoding' => 'UTF-8'));

            $parameters['username'] = $this->username;
            $parameters['password'] = $this->password;
            $result                 = $sms_client->RemainSms($parameters)->RemainSmsResult;

            $parameters['username'] = $this->username;
            $parameters['password'] = $this->password;
            $parameters['mobileno'] = $this->to[0];
            $parameters['pnlno']    = $this->from;
            $parameters['text']     = $this->msg;
            $parameters['isflash']  = false;

            $result = $sms_client->SendSMS($parameters)->SendSMSResult;

            if (!$this->get_error_message($result)) {
                // Log the result
                $this->log($this->from, $this->msg, $this->to, $result);

                return $result;
            } else {
                // Log the result
                $this->log($this->from, $this->msg, $this->to, $this->get_error_message($result), 'error');

                return new \WP_Error('send-sms', $this->get_error_message($result));
            }
        } catch (\Exception $e) {
            // Log th result
            $this->log($this->from, $this->msg, $this->to, $e->getMessage(), 'error');

            return new \WP_Error('send-sms', $e->getMessage());
        }

        // Log the result
        $this->log($this->from, $this->msg, $this->to, $result, 'error');

        return new \WP_Error('send-sms', $result);
    }

    public function GetCredit()
    {
        // Check username and password
        if (!$this->username && !$this->password) {
            return new \WP_Error('account-credit', __('API username or API password is not entered.', 'wp-sms'));
        }

        try {
            $sms_client = new \SoapClient($this->wsdl_link, array('encoding' => 'UTF-8'));

            $parameters['username'] = $this->username;
            $parameters['password'] = $this->password;
            $result                 = $sms_client->RemainSms($parameters)->RemainSmsResult;

            if (!$this->get_error_message($result)) {
                return $result;
            } else {
                return new \WP_Error('account-credit', $this->get_error_message($result));
            }
        } catch (\Exception $e) {
            return new \WP_Error('account-credit', $e->getMessage());
        }
    }

    /**
     * @param $error_code
     *
     * @return string
     */
    private function get_error_message($error_code)
    {
        switch ($error_code) {
            case '-3':
                return 'عدم تطابق نام کاربری و کلمه ی عبور. لطفا با پشتیبانی تماس بگیرید.';
                break;

            case '10':
                return 'نام کاربری یا کلمه ی عبور اشتباه است. لطفا با پشتیبانی تماس بگیرید.';
                break;

            case '11':
                return 'کاراکتر غیر مجاز در متن وجود دارد.';
                break;

            case '-17':
                return 'متن پیامک خالی است.';
                break;

            case '-18':
                return 'خطای شارژ . لطفا با پشتیبانی تماس بگیرید.';
                break;

            case '-19':
                return 'شارژ پنل شما برای ارسال کافی نیست. لطفا اقدام به شارژ پنل نمایید.';
                break;

            case '-22':
                return 'شماره موبایل صحیح نیست.';
                break;

            case '66':
                return 'عدم تطابق نام کاربری و کلمه ی عبور. لطفا با پشتیبانی تماس بگیرید.';
                break;

            case '1111':
                return 'کاراکتر غیرمجاز در متن وجود دارد.';
                break;

            case 'Hang':
                return 'حساب کاربری شما مسدود است. لطفا با پشتیبانی تماس بگیرید.';
                break;

            case 'Doc N':
            case 'No Doc':
                return 'مرحله دوم ثبت نام شما انجام نگرفته است. لطفا ثبت نام را کامل نمایید.';
                break;

            default:
                return false;
                break;
        }
    }
}