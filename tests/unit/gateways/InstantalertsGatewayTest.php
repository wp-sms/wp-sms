<?php

namespace unit;

use WP_SMS\Gateway\instantalerts;
use WP_SMS\Services\Gateway\GatewayRegistry;
use WP_UnitTestCase;

require_once dirname(__DIR__, 3) . '/includes/gateways/class-wpsms-gateway-instantalerts.php';

class InstantalertsGatewayTest extends WP_UnitTestCase
{
    protected function tearDown(): void
    {
        delete_transient(GatewayRegistry::CACHE_KEY_GATEWAYS);

        parent::tearDown();
    }

    public function test_uses_spring_edge_website()
    {
        $gateway = new instantalerts();

        $this->assertSame('https://www.springedge.com/', $gateway->tariff);
    }

    public function test_registry_displays_spring_edge_without_changing_slug()
    {
        set_transient(GatewayRegistry::CACHE_KEY_GATEWAYS, [
            'source'   => 'api',
            'gateways' => [[
                'slug'        => 'instantalerts',
                'name'        => 'Instantalerts',
                'description' => 'Instantalerts gateway',
                'website'     => 'http://springedge.com/',
            ]],
            'regions'  => [],
        ], 60);

        $registry = GatewayRegistry::getGateways();
        $gateway  = $registry['gateways'][0];

        $this->assertSame('instantalerts', $gateway['slug']);
        $this->assertSame('Spring Edge', $gateway['name']);
        $this->assertSame(
            'Spring Edge is an SMS gateway providing SMS messaging services via API integration for businesses worldwide.',
            $gateway['description']
        );
        $this->assertSame('https://www.springedge.com/', $gateway['website']);
    }

    public function test_registry_displays_spring_edge_for_premium_gateway()
    {
        set_transient(GatewayRegistry::CACHE_KEY_GATEWAYS, [
            'source'           => 'api',
            'gateways'         => [],
            'premium_gateways' => [[
                'slug'        => 'instantalerts',
                'name'        => 'Instantalerts',
                'description' => 'Instantalerts gateway',
                'website'     => 'http://springedge.com/',
                'premium'     => true,
            ]],
            'regions'          => [],
        ], 60);

        $registry = GatewayRegistry::getGateways();
        $gateway  = $registry['premium_gateways'][0];

        $this->assertSame('instantalerts', $gateway['slug']);
        $this->assertSame('Spring Edge', $gateway['name']);
        $this->assertSame(
            'Spring Edge is an SMS gateway providing SMS messaging services via API integration for businesses worldwide.',
            $gateway['description']
        );
        $this->assertSame('https://www.springedge.com/', $gateway['website']);
        $this->assertTrue($gateway['premium']);
    }
}
