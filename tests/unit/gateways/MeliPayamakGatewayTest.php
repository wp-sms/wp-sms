<?php

namespace unit;

use WP_UnitTestCase;
use WP_SMS\Gateway\melipayamak;
use WP_Error;

// Manually load the MeliPayamak gateway class
require_once dirname(__DIR__, 3) . '/includes/gateways/class-wpsms-gateway-melipayamak.php';

/**
 * Unit tests for the MeliPayamak gateway class.
 */
class MeliPayamakGatewayTest extends WP_UnitTestCase
{
    /**
     * @var melipayamak
     */
    protected $gateway;

    public function setUp()
    {
        parent::setUp();
        $this->gateway = new melipayamak();
    }

    /**
     * Test that required gateway fields are defined.
     */
    public function testGatewayFieldsExist()
    {
        $expected_fields = array('username', 'password', 'from', 'from_support_one', 'from_support_two');

        foreach ($expected_fields as $field) {
            $this->assertArrayHasKey(
                $field,
                $this->gateway->gatewayFields,
                "Gateway field {$field} is missing."
            );
        }

        // Check some specific field configurations
        $this->assertEquals('gateway_username', $this->gateway->gatewayFields['username']['id']);
        $this->assertEquals('gateway_password', $this->gateway->gatewayFields['password']['id']);
        $this->assertEquals('gateway_sender_id', $this->gateway->gatewayFields['from']['id']);
    }

    /**
     * Test phone number formatting logic.
     */
    public function testFormatReceiverNumbers()
    {
        $method = new \ReflectionMethod('WP_SMS\Gateway\melipayamak', 'formatReceiverNumbers');
        $method->setAccessible(true);

        // Mobile numbers
        $this->assertEquals(array('09123456789'), $method->invoke($this->gateway, '09123456789'));
        $this->assertEquals(array('09123456789'), $method->invoke($this->gateway, '989123456789'));
        $this->assertEquals(array('09123456789'), $method->invoke($this->gateway, '9123456789'));

        // Landline numbers should remain unchanged
        $this->assertEquals(array('02112345678'), $method->invoke($this->gateway, '02112345678'));
    }

    /**
     * Test SendSMS() returns WP_Error when credentials are missing.
     */
    public function testSendSmsWithoutCredentials()
    {
        $this->gateway->username = '';
        $this->gateway->password = '';
        $this->gateway->msg      = 'Hello world';
        $this->gateway->to       = '09123456789';

        $result = $this->gateway->SendSMS();

        $this->assertInstanceOf('WP_Error', $result);
        $this->assertEquals('missing-credentials', $result->get_error_code());
    }

    /**
     * Test GetCredit() returns WP_Error when credentials are missing.
     */
    public function testGetCreditWithoutCredentials()
    {
        $this->gateway->username = '';
        $this->gateway->password = '';

        $result = $this->gateway->GetCredit();

        $this->assertInstanceOf('WP_Error', $result);
        $this->assertEquals('account-credit-error', $result->get_error_code());
    }
}
