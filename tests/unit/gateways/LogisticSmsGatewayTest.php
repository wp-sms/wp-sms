<?php

namespace unit\gateways;

use WP_Error;
use WP_SMS\Gateway\logisticsms;
use WP_UnitTestCase;

require_once dirname(__DIR__, 3) . '/includes/gateways/class-wpsms-gateway-logisticsms.php';

class LogisticSmsGatewayTest extends WP_UnitTestCase
{
    /** @var logisticsms */
    protected $gateway;

    protected function setUp(): void
    {
        parent::setUp();

        remove_all_filters('wp_sms_from');
        remove_all_filters('wp_sms_to');
        remove_all_filters('wp_sms_msg');

        $this->gateway = $this->getMockBuilder(logisticsms::class)
            ->onlyMethods(array('request', 'log'))
            ->getMock();

        // The base constructor registers a global-recipient filter that relies
        // on the runtime $sms global rather than this isolated gateway mock.
        remove_all_filters('wp_sms_to');

        $this->gateway->username = 'api-user';
        $this->gateway->password = 'api-password';
        $this->gateway->from     = '3000';
        $this->gateway->to       = array('09120000001', '09120000002', '09120000003');
        $this->gateway->msg      = 'Hello';
    }

    public function test_bulk_send_keeps_processing_after_a_recipient_fails()
    {
        $responses = array(
            (object) array('msg' => 'success', 'data' => (object) array('token' => 'token-value')),
            (object) array('msg' => 'success', 'data' => (object) array('id' => 1)),
            (object) array('msg' => 'insufficient credit'),
            (object) array('msg' => 'success', 'data' => (object) array('id' => 3)),
        );

        $this->gateway->expects($this->exactly(4))
            ->method('request')
            ->willReturnCallback(function () use (&$responses) {
                return array_shift($responses);
            });

        $results = $this->gateway->SendSMS();

        $this->assertCount(3, $results);
        $this->assertSame(1, $results[0]->data->id);
        $this->assertInstanceOf(WP_Error::class, $results[1]);
        $this->assertSame('insufficient credit', $results[1]->get_error_message());
        $this->assertSame(array('recipient' => '09120000002'), $results[1]->get_error_data());
        $this->assertSame(3, $results[2]->data->id);
    }
}
