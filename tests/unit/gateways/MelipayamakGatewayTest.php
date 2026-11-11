<?php

namespace unit;

use WP_UnitTestCase;

require_once dirname(__DIR__, 3) . '/includes/gateways/class-wpsms-gateway-melipayamak.php';

class MelipayamakGatewayTest extends WP_UnitTestCase
{
    public function testCredentialHelpUsesVisiblePanelPath(): void
    {
        $gateway = new \WP_SMS\Gateway\melipayamak();
        $help    = $gateway->gatewayFields['password']['desc'];

        $this->assertStringContainsString('Developers → Web service settings', $help);
        $this->assertStringNotContainsString('&gt;', $help);
    }
}
