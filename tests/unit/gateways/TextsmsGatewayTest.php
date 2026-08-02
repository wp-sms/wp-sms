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
