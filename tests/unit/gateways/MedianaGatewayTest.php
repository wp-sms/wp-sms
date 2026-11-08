<?php

namespace unit;

use WP_UnitTestCase;

require_once dirname(__DIR__, 3) . '/includes/gateways/class-wpsms-gateway-mediana.php';

/**
 * Mediana being down must never become a fatal error: not on page load,
 * not while sending, not while checking credit.
 */
class MedianaGatewayTest extends WP_UnitTestCase
{
    public function test_mediana_constructor_does_not_connect()
    {
        $gateway = new \WP_SMS\Gateway\mediana();

        $client = new \ReflectionProperty($gateway, 'client');
        $client->setAccessible(true);

        $this->assertNull($client->getValue($gateway));
    }

    public function test_mediana_returns_wp_error_when_wsdl_cannot_be_loaded()
    {
        $gateway = new MedianaGatewayWithoutLog();

        $wsdl = new \ReflectionProperty(\WP_SMS\Gateway\mediana::class, 'wsdl_link');
        $wsdl->setAccessible(true);
        $wsdl->setValue($gateway, 'http://127.0.0.1:1/server.php?wsdl');

        $gateway->username = 'test-user';
        $gateway->password = 'test-password';
        $gateway->from     = '3000';
        $gateway->to       = array('09120000000');
        $gateway->msg      = 'Test message';

        $credit = $gateway->GetCredit();
        $this->assertInstanceOf('WP_Error', $credit);
        $this->assertSame('gateway-unreachable', $credit->get_error_code());

        $result = $gateway->SendSMS();
        $this->assertInstanceOf('WP_Error', $result);
    }

}

class MedianaGatewayWithoutLog extends \WP_SMS\Gateway\mediana
{
    public function log($sender, $message, $to, $response, $status = 'success', $media = array())
    {
        return true;
    }
}
