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
                    $expectsRecipients =
                        isset($params['receptor']) &&
                        $params['receptor'] === '09120000000,09120000001';

                    $msg = $params['message'] ?? '';
                    $expectsMessage =
                        $msg === rawurlencode('Test Message') || $msg === 'Test Message';

                    return $expectsRecipients && $expectsMessage;
                })
            )
            ->willReturn($this->makeResponse(200, 'OK'));

        $response = $this->gateway->SendSMS();

        $this->assertFalse(is_wp_error($response), 'SendSMS returned WP_Error: ' .
            (is_wp_error($response) ? $response->get_error_code() . ' - ' . $response->get_error_message() : ''));

        $this->assertEquals(200, $response->return->status);
        $this->assertEquals('OK', $response->return->message);
    }

    /** ✅ Test: template-based SMS sending should succeed */
    public function test_send_template_sms_success()
    {
        $this->gateway->to               = ['09120000001', '09120000002'];
        $this->gateway->templateId       = 1234;
        $this->gateway->messageVariables = [
            'name'  => 'fake',
            'order' => '9988',
        ];

        $this->gateway->expects($this->exactly(2))
            ->method('request')
            ->withConsecutive(
                [
                    'GET',
                    $this->stringContains('/v1/DUMMY_KEY/verify/lookup.json'),
                    $this->callback(function ($params) {
                        return isset($params['template'], $params['token'], $params['token2'], $params['receptor'])
                            && (int)$params['template'] === 1234
                            && $params['token'] === 'fake'
                            && $params['token2'] === '9988'
                            && $params['receptor'] === '09120000001';
                    })
                ],
                [
                    'GET',
                    $this->stringContains('/v1/DUMMY_KEY/verify/lookup.json'),
                    $this->callback(function ($params) {
                        return isset($params['template'], $params['token'], $params['token2'], $params['receptor'])
                            && (int)$params['template'] === 1234
                            && $params['token'] === 'fake'
                            && $params['token2'] === '9988'
                            && $params['receptor'] === '09120000002';
                    })
                ]
            )
            ->willReturnOnConsecutiveCalls(
                $this->makeResponse(200, 'OK'),
                $this->makeResponse(200, 'OK')
            );

        $response = $this->gateway->SendSMS();

        $this->assertFalse(is_wp_error($response), 'SendSMS returned WP_Error: ' .
            (is_wp_error($response) ? $response->get_error_code() . ' - ' . $response->get_error_message() : ''));

        $this->assertEquals(200, $response->return->status);
    }

    /** Test: error when template ID exists but no variables */
    public function test_send_template_sms_missing_variables_returns_error()
    {
        $this->gateway->templateId       = 555;
        $this->gateway->messageVariables = [];

        $this->gateway->expects($this->any())->method('request');

        $result = $this->gateway->SendSMS();
        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertEquals('send-sms-error', $result->get_error_code());
    }

    /** Test: error when API key is missing */
    public function test_missing_api_key_returns_error()
    {
        $this->gateway->apiKey = '';

        $result = $this->gateway->SendSMS();
        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertEquals('missing-api-key', $result->get_error_code());
    }

    /** Test: successful credit retrieval */
    public function test_get_credit_success()
    {
        $this->gateway->expects($this->once())
            ->method('request')
            ->with('GET', $this->stringContains('/account/info.json'))
            ->willReturn($this->makeResponse(200, 'OK', ['remaincredit' => 42.5]));

        $credit = $this->gateway->GetCredit();
        $this->assertEquals(42.5, $credit);
    }

    /** Test: failed credit retrieval */
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
