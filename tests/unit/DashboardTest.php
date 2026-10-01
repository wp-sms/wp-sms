<?php

namespace unit;

use ReflectionMethod;
use WP_SMS\Admin\Dashboard;
use WP_UnitTestCase;

class DashboardTest extends WP_UnitTestCase
{
    public function testCustomGatewayHeadersAreMaskedBeforeLocalization(): void
    {
        $dashboard = Dashboard::instance();
        $method    = new ReflectionMethod($dashboard, 'maskSensitiveSettings');
        $method->setAccessible(true);

        $settings = $method->invoke($dashboard, [
            'gateway_http_headers' => 'Authorization: Bearer secret-token',
        ]);

        $this->assertSame('••••••••', $settings['gateway_http_headers']);
    }
}
