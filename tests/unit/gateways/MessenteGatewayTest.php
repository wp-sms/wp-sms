<?php

namespace unit;

use WP_SMS\Gateway\messente;
use WP_SMS\Services\Gateway\GatewayRegistry;
use WP_UnitTestCase;

require_once dirname(__DIR__, 3) . '/includes/gateways/class-wpsms-gateway-messente.php';

class MessenteGatewayTest extends WP_UnitTestCase
{
    private $requests = [];
    private $httpInterceptor;

    protected function tearDown(): void
    {
        delete_transient(GatewayRegistry::CACHE_KEY_GATEWAYS);
        if ($this->httpInterceptor) {
            remove_filter('pre_http_request', $this->httpInterceptor, 10);
        }
        remove_all_filters('wpsms_gateway_registry');
        $this->requests = [];
        $this->httpInterceptor = null;

        parent::tearDown();
    }

    public function test_cached_premium_messente_is_listed_as_free()
    {
        remove_all_filters('wpsms_gateway_registry');

        set_transient(GatewayRegistry::CACHE_KEY_GATEWAYS, [
            'source'           => 'api',
            'gateways'         => [],
            'premium_gateways' => [
                ['slug' => 'messente', 'name' => 'Messente', 'premium' => true],
                ['slug' => 'twilio', 'name' => 'Twilio', 'premium' => true],
            ],
            'premium_count'    => 2,
            'regions'          => [],
        ], 60);

        $registry = GatewayRegistry::getGateways();

        $this->assertSame(['messente'], array_column($registry['gateways'], 'slug'));
        $this->assertFalse($registry['gateways'][0]['premium']);
        $this->assertSame(['twilio'], array_column($registry['premium_gateways'], 'slug'));
        $this->assertSame(1, $registry['premium_count']);
    }

    public function test_cached_registry_without_messente_adds_bundled_gateway()
    {
        set_transient(GatewayRegistry::CACHE_KEY_GATEWAYS, [
            'source'           => 'api',
            'gateways'         => [
                ['slug' => 'test-gateway', 'name' => 'Test Gateway', 'premium' => false],
            ],
            'premium_gateways' => [],
            'premium_count'    => 0,
            'regions'          => [],
        ], 60);

        $registry = GatewayRegistry::getGateways();

        $this->assertContains('messente', array_column($registry['gateways'], 'slug'));
    }

    public function test_balance_is_read_from_ok_response()
    {
        $this->interceptHttp();

        $gateway           = new messente();
        $gateway->username = 'user';
        $gateway->password = 'secret';

        $this->assertSame(12.34, $gateway->GetCredit());
    }

    public function test_send_encodes_text_and_credentials()
    {
        $this->interceptHttp();

        $gateway           = new messente();
        $gateway->username = 'user';
        $gateway->password = 'p&ss';
        $gateway->from     = 'VeronaLabs';
        $gateway->to       = ['+3725551234', '+3725559999'];
        $gateway->msg      = 'Hi Sara & team: 20% off, ünïcode';

        $this->assertSame('OK msg-id', $gateway->SendSMS());

        $sendUrl = end($this->requests);
        parse_str(wp_parse_url($sendUrl, PHP_URL_QUERY), $query);

        $this->assertStringStartsWith('https://api2.messente.com/send_sms/?', $sendUrl);
        $this->assertSame('Hi Sara & team: 20% off, ünïcode', $query['text']);
        $this->assertSame('p&ss', $query['password']);
        $this->assertSame('+3725551234,+3725559999', $query['to']);
    }

    public function test_balance_failure_does_not_prevent_sending()
    {
        $this->interceptHttp(true);

        $gateway           = new messente();
        $gateway->username = 'user';
        $gateway->password = 'secret';
        $gateway->from     = 'VeronaLabs';
        $gateway->to       = ['+3725551234'];
        $gateway->msg      = 'Hello';

        $this->assertSame('OK msg-id', $gateway->SendSMS());
        $this->assertCount(1, $this->requests);
        $this->assertStringContainsString('/send_sms/', $this->requests[0]);
    }

    private function interceptHttp($failBalance = false)
    {
        $this->httpInterceptor = function ($pre, $args, $url) use ($failBalance) {
            $this->requests[] = $url;

            if (strpos($url, 'get_balance') !== false) {
                if ($failBalance) {
                    return new \WP_Error('balance-unavailable', 'Balance unavailable');
                }

                return ['body' => 'OK 12.34', 'response' => ['code' => 200], 'headers' => [], 'cookies' => []];
            }

            return ['body' => 'OK msg-id', 'response' => ['code' => 200], 'headers' => [], 'cookies' => []];
        };

        add_filter('pre_http_request', $this->httpInterceptor, 10, 3);
    }
}
