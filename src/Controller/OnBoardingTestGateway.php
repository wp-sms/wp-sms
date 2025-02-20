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

        $response = [
            'status'   => !is_wp_error($sms->GetCredit) ? 'active' : 'deactive',
            'balance'  => $sms->GetCredit ? $sms->GetCredit : 0,
            'incoming' => $sms->supportIncoming ? 'true' : 'false',
            'bulk'     => $sms->bulk_send ? 'true' : 'false',
            'mms'      => $sms->supportMedia ? 'true' : 'false',
        ];

        wp_send_json_success($response);
    }

}
