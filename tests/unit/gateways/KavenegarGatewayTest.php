<?php

namespace unit;

use WP_UnitTestCase;
use WP_Error;
use WP_SMS\Gateway\kavenegar;

require_once dirname(__DIR__, 3) . '/includes/gateways/class-wpsms-gateway-kavenegar.php';

class KavenegarGatewayTest extends WP_UnitTestCase
{
    /** @var kavenegar */
    protected $gateway;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gateway = $this->getMockBuilder(kavenegar::class)
            ->onlyMethods(['request', 'log'])
            ->getMock();

        // مقداردهی بعد از ساخت mock
        $this->gateway->apiKey = 'DUMMY_KEY';
        $this->gateway->from   = '5000';
        $this->gateway->msg    = 'Test Message';
    }

    private function makeResponse($status = 200, $message = 'OK', $entries = [])
    {
        return (object)[
            'return'  => (object)['status' => $status, 'message' => $message],
            'entries' => (object)$entries,
        ];
    }

    /** ✅ Test: simple SMS sending should succeed */
    public function test_send_simple_sms_success()
    {
        $this->gateway->to  = ['09120000000', '09120000001'];
        $this->gateway->msg = 'Test Message';

        $this->gateway->expects($this->once())
            ->method('request')
            ->with(
                'GET',
                $this->stringContains('/v1/DUMMY_KEY/sms/send.json'),
                $this->callback(function ($params) {
                    return isset($params['receptor'], $params['message']);
                })
            )
            ->willReturn($this->makeResponse(200, 'OK'));

        $response = $this->gateway->SendSMS();
        $this->assertFalse(is_wp_error($response), 'SendSMS returned WP_Error: ' .
            (is_wp_error($response) ? $response->get_error_code() . ' - ' . $response->get_error_message() : ''));
        $this->assertEquals(200, $response->return->status);
    }

    /** ✅ Test: template SMS sending should succeed */
    public function test_send_template_sms_success()
    {
        $this->gateway->to               = ['09120000001', '09120000002'];
        $this->gateway->templateId       = 1234;
        $this->gateway->messageVariables = ['name' => 'fake', 'order' => '9988'];

        $this->gateway->expects($this->exactly(2))
            ->method('request')
            ->with(
                'GET',
                $this->stringContains('/v1/DUMMY_KEY/verify/lookup.json'),
                $this->callback(function ($params) {
                    // در هر فراخوانی فقط وجود فیلدهای کلیدی کافی است
                    return isset($params['template'], $params['receptor'], $params['token'], $params['token2'])
                        && (int)$params['template'] === 1234;
                })
            )
            ->willReturn($this->makeResponse(200, 'OK'));

        $response = $this->gateway->SendSMS();
        $this->assertFalse(is_wp_error($response), 'SendSMS returned WP_Error: ' .
            (is_wp_error($response) ? $response->get_error_code() . ' - ' . $response->get_error_message() : ''));
        $this->assertEquals(200, $response->return->status);
    }
}
