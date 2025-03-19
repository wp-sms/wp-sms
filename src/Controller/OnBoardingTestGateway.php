<?php

namespace WP_SMS\Controller;

use WP_SMS;
use WP_SMS\Components\Sms;
use WP_SMS\Option;
use WP_SMS\Utils\Request;

class OnBoardingTestGateway extends AjaxControllerAbstract
{
    protected $action = 'wp_sms_test_gateway';

    protected $fields;
    protected $sms;

    protected function run()
    {
        WP_SMS::get_instance()->init();

        global $sms;
        $this->sms = $sms;

        $action = $this->get('sub_action');

        switch ($action) {
            case 'test_status':
                $this->test_status();
                break;
            case 'send_sms':
                $this->send_sms();
                break;
            default:
                wp_send_json_error(__('Invalid action.', 'wp-sms'), 400);
        }
    }

    public function test_status()
    {
        $fields = $this->sms->gatewayFields;

        foreach ($fields as $key => $field) {
            Option::updateOption($field['id'], $this->get($field['id']));
        }

        try {
            $credit    = $this->sms->GetCredit();
            $is_active = !is_wp_error($credit) && $credit !== false;
        } catch (\Exception $e) {
            $is_active = false;
            $credit    = false;
            error_log('Error getting SMS gateway credit: ' . $e->getMessage());
        }

        $response = array(
            'container' => array(
                'class' => $is_active ? 'c-form__result c-form__result--success' : 'c-form__result c-form__result--danger'
            ),
            'status'    => array(
                'label'       => $is_active ? __('Active', 'wp-sms') : __('Deactivated!', 'wp-sms'),
                'description' => $is_active
                    ? __('Your SMS gateway is successfully connected and ready to use.', 'wp-sms')
                    : __('There is an issue with the SMS gateway connection. Please check your settings.', 'wp-sms'),
                'class'       => $is_active ? 'c-form__result-status--success' : 'c-form__result-status--danger'
            ),
            'balance'   => array(
                'label'       => $is_active && $credit !== false ? '$' . number_format((float)$credit, 2) : '-',
                'description' => __('This is the current credit in your SMS account.', 'wp-sms'),
                'class'       => 'c-form__result-status--primary'
            ),
            'incoming'  => array(
                'label'       => isset($this->sms->supportIncoming) && $this->sms->supportIncoming ? __('Supported', 'wp-sms') : __('Not Supported!', 'wp-sms'),
                'description' => isset($this->sms->supportIncoming) && $this->sms->supportIncoming
                    ? __('You can receive SMS messages on your configured number.', 'wp-sms')
                    : __('Receiving SMS messages is not supported with the current gateway. Choose another gateway for this feature.', 'wp-sms'),
                'class'       => isset($this->sms->supportIncoming) && $this->sms->supportIncoming ? 'c-form__result-status--success' : 'c-form__result-status--danger'
            ),
            'bulk'      => array(
                'label'       => isset($this->sms->bulk_send) && $this->sms->bulk_send ? __('Supported', 'wp-sms') : __('Not Supported!', 'wp-sms'),
                'description' => isset($this->sms->bulk_send) && $this->sms->bulk_send
                    ? __('You can send bulk SMS messages.', 'wp-sms')
                    : __('You cannot send bulk SMS messages with the current gateway setup. To enable this feature, please select a gateway that offers bulk messaging.', 'wp-sms'),
                'class'       => isset($this->sms->bulk_send) && $this->sms->bulk_send ? 'c-form__result-status--success' : 'c-form__result-status--danger'
            ),
            'mms'       => array(
                'label'       => isset($this->sms->supportMedia) && $this->sms->supportMedia ? __('Supported', 'wp-sms') : __('Not Supported!', 'wp-sms'),
                'description' => isset($this->sms->supportMedia) && $this->sms->supportMedia
                    ? __('Multimedia Messaging Service (MMS) is enabled.', 'wp-sms')
                    : __('Your gateway does not support sending MMS. For this service, please select a gateway that offers MMS capabilities.', 'wp-sms'),
                'class'       => isset($this->sms->supportMedia) && $this->sms->supportMedia ? 'c-form__result-status--success' : 'c-form__result-status--danger'
            )
        );

        wp_send_json_success($response);
    }

    public function send_sms()
    {
        $credit = $this->sms->GetCredit();

        $params = [
            'to'  => Option::getOption('admin_mobile_number'),
            'msg' => __('This is a test from WP SMS onboarding process.', 'wp-sms')
        ];

        try {
            $result = Sms::send($params);

            if ($result) {
                wp_send_json_success(array(
                    'messages' => array(
                        'confirmation_title' => __('Did you receive the test SMS?', 'wp-sms'),
                        'confirmation_text'  => __('Please check your device to confirm whether you received the message.', 'wp-sms'),
                    ),
                    'classes'  => array(
                        'success_class' => 'wpsms-admin-alert--success',
                        'info_class'    => 'wpsms-admin-alert--info',
                    ),
                ));
            } else {
                wp_send_json_error(array(
                    'message' => __('Failed to send test SMS.', 'wp-sms'),
                ));
            }
        } catch (\Exception $e) {
            wp_send_json_error(array(
                'message' => __('Error sending test SMS: ', 'wp-sms') . $e->getMessage(),
            ));
        }
    }
}
