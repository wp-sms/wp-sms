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
            ->onlyMethods(['request', 'log', 'getTemplateIdAndMessageBody'])
            ->getMock();

        $this->gateway->apiKey = 'DUMMY_KEY';
        $this->gateway->from   = '5000';
        $this->gateway->to     = ['09120000000'];
        $this->gateway->msg    = 'Test Message';
    }

    /**
     * Helper: build a fake API response object.
     */
    private function makeResponse($status = 200, $message = 'OK', $entries = [])
    {
        return (object)[
            'return'  => (object)['status' => $status, 'message' => $message],
            'entries' => (object)$entries,
        ];
    }

    /** Test: normal (non-template) SMS sending should succeed. */
    public function test_send_simple_sms_success()
    {
        $this->gateway->expects($this->once())
            ->method('request')
            ->with(
                'GET',
                $this->stringContains('/sms/send.json'),
                $this->callback(function ($params) {
                    return $params['receptor'] === '09120000000'
                        && str_contains($params['message'], 'Test%20Message');
                })
            )
            ->willReturn($this->makeResponse());

        $response = $this->gateway->SendSMS();
        $this->assertEquals(200, $response->return->status);
        $this->assertEquals('OK', $response->return->message);
    }

    /** Test: template-based SMS sending with valid tokens. */
    public function test_send_template_sms_success()
    {
        $this->gateway->to               = ['09120000001', '09120000002'];
        $this->gateway->templateId       = 1234;
        $this->gateway->messageVariables = [
            'name'  => 'علی',
            'order' => '9988'
        ];

        $this->gateway->expects($this->exactly(2))
            ->method('request')
            ->with(
                'GET',
                $this->stringContains('/verify/lookup.json'),
                $this->callback(function ($params) {
                    return isset($params['template'], $params['token'], $params['token2'])
                        && $params['template'] == 1234
                        && $params['token'] === 'علی'
                        && $params['token2'] === '9988';
                })
            )
            ->willReturn($this->makeResponse());

        $response = $this->gateway->SendSMS();
        $this->assertEquals(200, $response->return->status);
    }

    /** Test: error when template ID exists but no message variables are provided. */
    public function test_send_template_sms_missing_variables_returns_error()
    {
        $this->gateway->templateId       = 555;
        $this->gateway->messageVariables = [];

        $this->gateway->expects($this->never())->method('request');

        $result = $this->gateway->SendSMS();
        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertEquals('invalid-template', $result->get_error_code());
    }

    /** Test: error when API key is missing. */
    public function test_missing_api_key_returns_error()
    {
        $this->gateway->apiKey = '';

        $result = $this->gateway->SendSMS();
        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertEquals('missing-api-key', $result->get_error_code());
    }

    /** Test: successful credit retrieval (GetCredit). */
    public function test_get_credit_success()
    {
        $this->gateway->expects($this->once())
            ->method('request')
            ->with('GET', $this->stringContains('/account/info.json'))
            ->willReturn($this->makeResponse(200, 'OK', ['remaincredit' => 42.5]));

        $credit = $this->gateway->GetCredit();
        $this->assertEquals(42.5, $credit);
    }

    /** Test: failed credit retrieval should return WP_Error. */
    public function test_get_credit_error_response()
    {
        $this->gateway->expects($this->once())
            ->method('request')
            ->willReturn($this->makeResponse(400, 'Unauthorized'));

        $result = $this->gateway->GetCredit();
        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertEquals('account-credit-error', $result->get_error_code());
    }
}