<?php

namespace WP_SMS\Controller;

use WP_SMS;

class OnBoardingTestGateway extends AjaxControllerAbstract
{
    protected $action = 'wp_sms_test_gateway_status';

    protected function run()
    {
        WP_SMS::get_instance()->init();

        global $sms;

        do_action('onboarding_before_test_gateway_response');

        $credit    = $sms->GetCredit();
        $is_active = !is_wp_error($credit) && $credit !== false;

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
                'label'       => isset($sms->supportIncoming) && $sms->supportIncoming ? __('Supported', 'wp-sms') : __('Does not support!', 'wp-sms'),
                'description' => isset($sms->supportIncoming) && $sms->supportIncoming
                    ? __('You can receive SMS messages on your configured number.', 'wp-sms')
                    : __('Receiving SMS messages is not supported with the current gateway. Choose another gateway for this feature.', 'wp-sms'),
                'class'       => isset($sms->supportIncoming) && $sms->supportIncoming ? 'c-form__result-status--success' : 'c-form__result-status--danger'
            ),
            'bulk'      => array(
                'label'       => isset($sms->bulk_send) && $sms->bulk_send ? __('Supported', 'wp-sms') : __('Does not support!', 'wp-sms'),
                'description' => isset($sms->bulk_send) && $sms->bulk_send
                    ? __('You can send bulk SMS messages.', 'wp-sms')
                    : __('You cannot send bulk SMS messages with the current gateway setup. To enable this feature, please select a gateway that offers bulk messaging.', 'wp-sms'),
                'class'       => isset($sms->bulk_send) && $sms->bulk_send ? 'c-form__result-status--success' : 'c-form__result-status--danger'
            ),
            'mms'       => array(
                'label'       => isset($sms->supportMedia) && $sms->supportMedia ? __('Supported', 'wp-sms') : __('Does not support!', 'wp-sms'),
                'description' => isset($sms->supportMedia) && $sms->supportMedia
                    ? __('Multimedia Messaging Service (MMS) is enabled.', 'wp-sms')
                    : __('Your gateway does not support sending MMS. For this service, please select a gateway that offers MMS capabilities.', 'wp-sms'),
                'class'       => isset($sms->supportMedia) && $sms->supportMedia ? 'c-form__result-status--success' : 'c-form__result-status--danger'
            )
        );

        wp_send_json_success($response);
    }


}
