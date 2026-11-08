<?php

namespace unit;

use WP_UnitTestCase;
use WP_SMS\Components\Sms;
use WP_SMS\Gateway;
use WP_SMS\Option;

require_once dirname(__DIR__, 3) . '/includes/gateways/class-wpsms-gateway-mediana.php';

/**
 * A provider being down must never become a fatal error: not on page load,
 * not while sending, not while checking credit.
 */
class GatewayOutageTest extends WP_UnitTestCase
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
        $gateway = new \WP_SMS\Gateway\mediana();

        $wsdl = new \ReflectionProperty($gateway, 'wsdl_link');
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

    public function test_dispatch_turns_a_thrown_soap_fault_into_wp_error()
    {
        $gateway      = new GatewayThatThrowsOnSend();
        $gateway->to  = array('+15555550100');
        $gateway->msg = 'Test message';

        $result = Sms::dispatch($gateway);

        $this->assertInstanceOf('WP_Error', $result);
        $this->assertSame('send-sms', $result->get_error_code());
        $this->assertStringContainsString('502 Bad Gateway', $result->get_error_message());
    }

    public function test_dispatch_restores_socket_timeout()
    {
        $before = ini_get('default_socket_timeout');

        Sms::dispatch(new GatewayThatThrowsOnSend());

        $this->assertSame($before, ini_get('default_socket_timeout'));
    }

    public function test_initial_falls_back_to_default_gateway_when_constructor_throws()
    {
        $path = WP_SMS_DIR . 'includes/gateways/class-wpsms-gateway-zzoutagetest.php';
        file_put_contents($path, "<?php\nnamespace WP_SMS\\Gateway;\nclass zzoutagetest extends \\WP_SMS\\Gateway { public function __construct() { throw new \\SoapFault('HTTP', 'Bad Gateway'); } }\n");

        Option::updateOption('gateway_name', 'zzoutagetest');

        try {
            $sms = Gateway::initial();
        } finally {
            unlink($path);
            Option::updateOption('gateway_name', '');
        }

        $this->assertInstanceOf(\WP_SMS\Gateway\Default_Gateway::class, $sms);
    }
}

class GatewayThatThrowsOnSend extends Gateway
{
    public function SendSMS()
    {
        throw new \SoapFault('HTTP', 'HTTP/1.1 502 Bad Gateway');
    }

    public function log($sender, $message, $to, $response, $status = 'success', $media = array())
    {
        return true;
    }
}
