<?php

namespace unit\gateways;

use WP_Error;
use WP_SMS\Gateway\tskin;
use WP_UnitTestCase;

$gatewayFile = dirname(__DIR__, 3) . '/includes/gateways/class-wpsms-gateway-tskin.php';
if (file_exists($gatewayFile)) {
    require_once $gatewayFile;
}

class TskinGatewayTest extends WP_UnitTestCase
{
    public function test_exposes_api_key_as_a_secret_setting()
    {
        $this->assertTrue(class_exists(tskin::class), 'The TSKIN gateway class should exist.');

        $gateway = new tskin();

        $this->assertSame('https://tskin.sa/', $gateway->tariff);
        $this->assertSame('gateway_key', $gateway->gatewayFields['has_key']['id']);
        $this->assertSame('password', $gateway->gatewayFields['has_key']['type']);
    }

    public function test_sends_text_message_with_bearer_authentication()
    {
        $this->assertTrue(method_exists(tskin::class, 'SendSMS'), 'The TSKIN gateway should send messages.');

        $gateway = $this->getMockBuilder(tskin::class)
            ->onlyMethods(array('request', 'log'))
            ->getMock();

        remove_all_filters('wp_sms_from');
        remove_all_filters('wp_sms_to');
        remove_all_filters('wp_sms_msg');

        $gateway->has_key = 'test-api-key';
        $gateway->to      = array('966500000000');
        $gateway->msg     = 'Order shipped';

        $response = (object) array(
            'status'     => 'success',
            'message_id' => 'wamid.test',
            'recipient'  => '966500000000',
            'type'       => 'text',
        );

        $gateway->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                'https://app.tskin.sa/api/v1/send-message.php',
                array(),
                $this->callback(function ($params) {
                    $this->assertSame('Bearer test-api-key', $params['headers']['Authorization']);
                    $this->assertSame('application/json', $params['headers']['Content-Type']);
                    $this->assertSame(
                        array(
                            'phone'        => '966500000000',
                            'message_type' => 'text',
                            'message'      => 'Order shipped',
                        ),
                        json_decode($params['body'], true)
                    );

                    return true;
                })
            )
            ->willReturn($response);

        $gateway->expects($this->once())
            ->method('log')
            ->with('', 'Order shipped', array('966500000000'), $response);

        $this->assertSame($response, $gateway->SendSMS());
    }

    public function test_returns_useful_error_when_api_request_fails()
    {
        $gateway = $this->getMockBuilder(tskin::class)
            ->onlyMethods(array('request', 'log'))
            ->getMock();

        remove_all_filters('wp_sms_from');
        remove_all_filters('wp_sms_to');
        remove_all_filters('wp_sms_msg');

        $gateway->has_key = 'invalid-api-key';
        $gateway->to      = array('966500000000');
        $gateway->msg     = 'Order shipped';

        $gateway->expects($this->once())
            ->method('request')
            ->willThrowException(new \Exception('Failed to get success response, Invalid API key'));

        $gateway->expects($this->once())
            ->method('log')
            ->with(
                '',
                'Order shipped',
                array('966500000000'),
                'Failed to get success response, Invalid API key',
                'error'
            );

        try {
            $result = $gateway->SendSMS();
        } catch (\Exception $exception) {
            $this->fail('The gateway should convert API failures into WP_Error responses.');
        }

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('send-sms', $result->get_error_code());
        $this->assertSame('Failed to get success response, Invalid API key', $result->get_error_message());
    }

    public function test_gateway_status_requires_an_api_key()
    {
        $this->assertTrue(method_exists(tskin::class, 'GetCredit'), 'The TSKIN gateway should expose its configuration status.');

        $gateway          = new tskin();
        $gateway->has_key = '';
        $result           = $gateway->GetCredit();

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('account-credit', $result->get_error_code());
        $this->assertSame('TSKIN API Key is required.', $result->get_error_message());

        $gateway->has_key = 'test-api-key';
        $this->assertTrue($gateway->GetCredit());
    }
}
