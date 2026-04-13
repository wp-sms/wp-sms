<?php

namespace WSms\Tests\Unit\Messaging\Gateway;

use PHPUnit\Framework\TestCase;
use WSms\Messaging\Gateway\GatewayApiClient;

class GatewayApiClientTest extends TestCase
{
    /** @var callable|null Filter callback to remove in tearDown */
    private $httpFilter = null;

    private static function setApiCache(?array $data): void
    {
        $ref = new \ReflectionProperty(GatewayApiClient::class, 'cache');
        $ref->setValue(null, $data);
    }

    protected function setUp(): void
    {
        self::setApiCache(null);
        delete_transient('wsms_gateway_api_data');
        delete_option('wsms_gateway_api_data_fallback');
    }

    protected function tearDown(): void
    {
        self::setApiCache(null);
        delete_transient('wsms_gateway_api_data');
        delete_option('wsms_gateway_api_data_fallback');

        if ($this->httpFilter) {
            remove_filter('pre_http_request', $this->httpFilter, 10);
            $this->httpFilter = null;
        }
    }

    /**
     * Mock wp_remote_get responses via pre_http_request filter.
     */
    private function mockHttpResponse(array|\WP_Error $response): void
    {
        $this->httpFilter = function () use ($response) {
            return $response;
        };
        add_filter('pre_http_request', $this->httpFilter, 10, 3);
    }

    private function mockHttpWithCallback(callable $callback): void
    {
        $this->httpFilter = function ($preempt, $args, $url) use ($callback) {
            return $callback($url, $args);
        };
        add_filter('pre_http_request', $this->httpFilter, 10, 3);
    }

    private function makeApiResponse(array $gateways): array
    {
        return [
            'response' => ['code' => 200, 'message' => 'OK'],
            'body' => json_encode(['gateways' => $gateways]),
            'headers' => [],
            'cookies' => [],
        ];
    }

    // ------------------------------------------------------------------
    // setCache
    // ------------------------------------------------------------------

    public function testSetCacheSeedsData(): void
    {
        self::setApiCache([
            'twilio' => ['name' => 'Twilio', 'features' => ['mms' => true]],
        ]);

        $this->assertSame('Twilio', GatewayApiClient::get('twilio')['name']);
    }

    public function testSetCacheNullClearsCache(): void
    {
        self::setApiCache(['twilio' => ['name' => 'Twilio']]);
        self::setApiCache(null);

        // Mock API to fail so we truly get null
        $this->mockHttpResponse(new \WP_Error('fail', 'fail'));

        $this->assertNull(GatewayApiClient::get('twilio'));
    }

    // ------------------------------------------------------------------
    // get / getAll — in-memory cache
    // ------------------------------------------------------------------

    public function testGetReturnsNullForUnknownSlug(): void
    {
        self::setApiCache(['twilio' => ['name' => 'Twilio']]);

        $this->assertNull(GatewayApiClient::get('nonexistent'));
    }

    public function testGetAllReturnsSeededData(): void
    {
        $data = [
            'twilio' => ['name' => 'Twilio'],
            'vonage' => ['name' => 'Vonage'],
        ];
        self::setApiCache($data);

        $this->assertSame($data, GatewayApiClient::getAll());
    }

    // ------------------------------------------------------------------
    // Transient caching
    // ------------------------------------------------------------------

    public function testGetAllReadsFromTransient(): void
    {
        $data = ['twilio' => ['name' => 'Twilio']];
        set_transient('wsms_gateway_api_data', $data, 3600);

        // Mock API to fail — should not be reached
        $this->mockHttpResponse(new \WP_Error('should_not_reach', 'fail'));

        $this->assertSame($data, GatewayApiClient::getAll());
    }

    public function testTransientCacheIsStoredInMemoryOnSecondCall(): void
    {
        $data = ['twilio' => ['name' => 'Twilio']];
        set_transient('wsms_gateway_api_data', $data, 3600);

        // First call reads transient
        GatewayApiClient::getAll();

        // Clear transient — second call should still work from memory
        delete_transient('wsms_gateway_api_data');
        $this->assertSame($data, GatewayApiClient::getAll());
    }

    // ------------------------------------------------------------------
    // API fetch
    // ------------------------------------------------------------------

    public function testFetchesFromApiWhenNoCacheOrTransient(): void
    {
        $this->mockHttpResponse($this->makeApiResponse([
            ['slug' => 'twilio', 'name' => 'Twilio'],
            ['slug' => 'vonage', 'name' => 'Vonage'],
        ]));

        $result = GatewayApiClient::getAll();

        $this->assertSame('Twilio', $result['twilio']['name']);
        $this->assertSame('Vonage', $result['vonage']['name']);
    }

    public function testFetchStoresTransientAndFallbackOption(): void
    {
        $this->mockHttpResponse($this->makeApiResponse([
            ['slug' => 'twilio', 'name' => 'Twilio'],
        ]));

        GatewayApiClient::getAll();

        // Transient was set
        $transient = get_transient('wsms_gateway_api_data');
        $this->assertIsArray($transient);
        $this->assertSame('Twilio', $transient['twilio']['name']);

        // Fallback option was set
        $fallback = get_option('wsms_gateway_api_data_fallback');
        $this->assertSame('Twilio', $fallback['twilio']['name']);
    }

    public function testFetchReKeysGatewaysBySlug(): void
    {
        $this->mockHttpResponse($this->makeApiResponse([
            ['slug' => 'kavenegar', 'name' => 'Kavenegar'],
        ]));

        $result = GatewayApiClient::getAll();

        $this->assertArrayHasKey('kavenegar', $result);
        $this->assertSame('Kavenegar', $result['kavenegar']['name']);
    }

    // ------------------------------------------------------------------
    // API failure + fallback
    // ------------------------------------------------------------------

    public function testFallsBackToOptionWhenApiAndTransientUnavailable(): void
    {
        update_option('wsms_gateway_api_data_fallback', [
            'twilio' => ['name' => 'Twilio (stale)'],
        ]);

        $this->mockHttpResponse(new \WP_Error('timeout', 'Connection timed out'));

        $result = GatewayApiClient::getAll();

        $this->assertSame('Twilio (stale)', $result['twilio']['name']);
    }

    public function testReturnsNullWhenEverythingUnavailable(): void
    {
        $this->mockHttpResponse(new \WP_Error('timeout', 'fail'));

        $this->assertNull(GatewayApiClient::getAll());
    }

    public function testApiHttpErrorFallsBackGracefully(): void
    {
        $this->mockHttpResponse([
            'response' => ['code' => 500, 'message' => 'Internal Server Error'],
            'body' => 'Internal Server Error',
            'headers' => [],
            'cookies' => [],
        ]);

        update_option('wsms_gateway_api_data_fallback', [
            'twilio' => ['name' => 'Twilio'],
        ]);

        $this->assertSame('Twilio', GatewayApiClient::getAll()['twilio']['name']);
    }

    public function testApiWpErrorFallsBackGracefully(): void
    {
        $this->mockHttpResponse(new \WP_Error('timeout', 'Connection timed out'));

        update_option('wsms_gateway_api_data_fallback', [
            'vonage' => ['name' => 'Vonage'],
        ]);

        $this->assertSame('Vonage', GatewayApiClient::getAll()['vonage']['name']);
    }

    public function testApiInvalidJsonFallsBackGracefully(): void
    {
        $this->mockHttpResponse([
            'response' => ['code' => 200, 'message' => 'OK'],
            'body' => 'not json',
            'headers' => [],
            'cookies' => [],
        ]);

        update_option('wsms_gateway_api_data_fallback', [
            'twilio' => ['name' => 'Twilio'],
        ]);

        $this->assertSame('Twilio', GatewayApiClient::getAll()['twilio']['name']);
    }

    public function testApiEmptyGatewaysArrayFallsBack(): void
    {
        $this->mockHttpResponse($this->makeApiResponse([]));

        update_option('wsms_gateway_api_data_fallback', [
            'twilio' => ['name' => 'Twilio'],
        ]);

        $this->assertSame('Twilio', GatewayApiClient::getAll()['twilio']['name']);
    }

    // ------------------------------------------------------------------
    // refresh
    // ------------------------------------------------------------------

    public function testRefreshClearsCacheAndRefetches(): void
    {
        self::setApiCache(['old' => ['name' => 'Old']]);

        $this->mockHttpResponse($this->makeApiResponse([
            ['slug' => 'new', 'name' => 'New'],
        ]));

        $this->assertTrue(GatewayApiClient::refresh());
        $this->assertSame('New', GatewayApiClient::get('new')['name']);
        $this->assertNull(GatewayApiClient::get('old'));
    }

    public function testRefreshReturnsFalseOnFailure(): void
    {
        $this->mockHttpResponse(new \WP_Error('timeout', 'fail'));

        $this->assertFalse(GatewayApiClient::refresh());
    }

    // ------------------------------------------------------------------
    // URL override
    // ------------------------------------------------------------------

    public function testApiUrlCanBeOverriddenViaFilter(): void
    {
        $requestedUrl = null;

        $this->mockHttpWithCallback(function ($url) use (&$requestedUrl) {
            $requestedUrl = $url;
            return $this->makeApiResponse([['slug' => 'test', 'name' => 'Test']]);
        });

        add_filter('wsms_gateway_api_url', fn($url) => 'https://custom.example.com/gateways.json');

        GatewayApiClient::getAll();

        $this->assertSame('https://custom.example.com/gateways.json', $requestedUrl);

        // Cleanup the URL filter
        remove_all_filters('wsms_gateway_api_url');
    }
}
