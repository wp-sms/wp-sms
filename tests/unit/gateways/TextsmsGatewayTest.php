<?php

namespace unit;

use WP_UnitTestCase;
use WP_SMS\Gateway\textsms;

require_once dirname(__DIR__, 3) . '/includes/gateways/class-wpsms-gateway-textsms.php';

class TextsmsGatewayTest extends WP_UnitTestCase
{
    public function test_get_credit_returns_wp_error_when_wsdl_cannot_be_loaded()
    {
        $gateway = new TextsmsGatewayWithFailingSoapClient();

        $gateway->username = 'test-user';
        $gateway->password = 'test-password';

        $result = $gateway->GetCredit();

        $this->assertInstanceOf('WP_Error', $result);
        $this->assertSame('account-credit', $result->get_error_code());
        $this->assertStringContainsString('SOAP-ERROR: Parsing WSDL', $result->get_error_message());
    }

    public function test_send_sms_returns_wp_error_when_soap_call_fails()
    {
        $gateway = new TextsmsGatewayWithFailingSendSoapClient();

        $gateway->username = 'test-user';
        $gateway->password = 'test-password';
        $gateway->from     = '1000';
        $gateway->to       = array('09120000000');
        $gateway->msg      = 'Test message';

        $result = $gateway->SendSMS();

        $this->assertInstanceOf('WP_Error', $result);
        $this->assertSame('send-sms', $result->get_error_code());
        $this->assertSame('SOAP send failed', $result->get_error_message());
    }

    public function test_get_credit_returns_wp_error_when_soap_client_is_unavailable()
    {
        $gateway = new TextsmsGatewayWithoutSoapClient();

        $gateway->username = 'test-user';
        $gateway->password = 'test-password';

        $result = $gateway->GetCredit();

        $this->assertInstanceOf('WP_Error', $result);
        $this->assertSame('required-class', $result->get_error_code());
    }
}

class TextsmsGatewayWithFailingSoapClient extends textsms
{
    protected function getSoapClient()
    {
        throw new \Exception('SOAP-ERROR: Parsing WSDL: Could not load WSDL');
    }

    protected function hasSoapClient()
    {
        return true;
    }
}

class TextsmsGatewayWithFailingSendSoapClient extends textsms
{
    protected function getSoapClient()
    {
        return new TextsmsSoapClientWithFailingSend();
    }

    protected function hasSoapClient()
    {
        return true;
    }

    public function log($sender, $message, $to, $response, $status = 'success', $media = array())
    {
        return true;
    }
}

class TextsmsSoapClientWithFailingSend
{
    public function sms_credit($username, $password)
    {
        return 10;
    }

    public function send_sms($username, $password, $from, $to, $message)
    {
        throw new \Exception('SOAP send failed');
    }
}

class TextsmsGatewayWithoutSoapClient extends textsms
{
    protected function getSoapClient()
    {
        throw new \LogicException('SOAP client must not be constructed');
    }

    protected function hasSoapClient()
    {
        return false;
    }
}
