<?php

namespace unit\Gateway;

use WP_Error;
use WP_SMS\Gateway\tskin;
use WP_SMS\Services\Gateway\GatewayRegistry;
use WP_UnitTestCase;

/**
 * Tests for the TSKIN WhatsApp gateway.
 *
 * Request and response fixtures follow the provider's public API documentation:
 * https://app.tskin.sa/assets/api-docs.php
 */
class TskinGatewayTest extends WP_UnitTestCase
{
    const SEND_URL = 'https://app.tskin.sa/api/v1/send-message.php';

    /** @var array<int, array{url: string, args: array}> */
    private $requests = [];

    /** @var array<int, mixed> */
    private $logs = [];

    /** @var array<string, mixed> */
    private $response;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        require_once dirname(__DIR__, 3) . '/includes/gateways/class-wpsms-gateway-tskin.php';
    }

    public function setUp(): void
    {
        parent::setUp();

        $this->requests = [];
        $this->logs     = [];
        $this->respondWith([
            'status'     => 'success',
            'message_id' => 'wamid.HBgLOTY2...',
            'recipient'  => '966500000000',
            'type'       => 'text',
        ]);

        add_filter('pre_http_request', [$this, 'interceptRequest'], 10, 3);
        add_action('wp_sms_log_after_save', [$this, 'captureLog'], 10, 7);
    }

    public function tearDown(): void
    {
        remove_filter('pre_http_request', [$this, 'interceptRequest'], 10);
        remove_action('wp_sms_log_after_save', [$this, 'captureLog'], 10);
        parent::tearDown();
    }

    public function interceptRequest($preempt, $args, $url)
    {
        $this->requests[] = ['url' => $url, 'args' => $args];

        return $this->response;
    }

    public function captureLog($result, $sender, $message, $to, $response, $status)
    {
        $this->logs[] = compact('response', 'status');
    }

    public function testApiKeySettingIsMasked()
    {
        $gateway = new tskin();
        $field   = $gateway->gatewayFields['has_key'];

        $this->assertSame('gateway_key', $field['id']);
        $this->assertSame('password', $field['type']);
    }

    public function testSendUsesTheDocumentedTextMessageContract()
    {
        $result = $this->makeGateway()->SendSMS();

        $this->assertCount(1, $result);
        $this->assertCount(1, $this->requests);
        $this->assertSame(self::SEND_URL, $this->requests[0]['url']);
        $this->assertSame('POST', $this->requests[0]['args']['method']);
        $this->assertSame('Bearer test-api-key', $this->requests[0]['args']['headers']['Authorization']);
        $this->assertSame('application/json', $this->requests[0]['args']['headers']['Content-Type']);
        $this->assertSame([
            'phone'        => '966500000000',
            'message_type' => 'text',
            'message'      => 'Order shipped',
        ], json_decode($this->requests[0]['args']['body'], true));
    }

    public function testSendSurfacesProviderErrorsAndLogsFailure()
    {
        $this->respondWith(['message' => 'Invalid API Key'], 401);

        $result = $this->makeGateway()->SendSMS();

        $this->assertWPError($result);
        $this->assertStringContainsString('Invalid API Key', $result->get_error_message());
        $this->assertNotEmpty($this->logs);
        $this->assertSame('error', $this->logs[0]['status']);
        $this->assertStringContainsString('Invalid API Key', $this->logs[0]['response']);
    }

    public function testSendSurfacesProviderLevelErrorsFromSuccessfulHttpResponses()
    {
        $this->respondWith([
            'status'  => 'error',
            'message' => 'WhatsApp customer service window is closed',
        ]);

        $result = $this->makeGateway()->SendSMS();

        $this->assertWPError($result);
        $this->assertSame('WhatsApp customer service window is closed', $result->get_error_message());
        $this->assertNotEmpty($this->logs);
        $this->assertSame('error', $this->logs[0]['status']);
        $this->assertSame('WhatsApp customer service window is closed', $this->logs[0]['response']);
    }

    public function testGetCreditValidatesApiKeyWithoutCallingTheApi()
    {
        $gateway          = $this->makeGateway();
        $gateway->has_key = '';

        $this->assertWPError($gateway->GetCredit());
        $this->assertSame([], $this->requests);

        $gateway->has_key = 'test-api-key';
        $this->assertSame(1, $gateway->GetCredit());
        $this->assertSame([], $this->requests);
    }

    private function makeGateway()
    {
        $gateway          = new tskin();
        $gateway->has_key = 'test-api-key';
        $gateway->to      = ['+966500000000'];
        $gateway->msg     = 'Order shipped';
        $gateway->options['notify_errors_to_admin_email'] = false;

        return $gateway;
    }

    private function respondWith(array $body, $statusCode = 200)
    {
        $this->response = [
            'headers'  => [],
            'body'     => wp_json_encode($body),
            'response' => ['code' => $statusCode, 'message' => ''],
            'cookies'  => [],
            'filename' => null,
        ];
    }

    public function test_cached_premium_tskin_is_listed_as_free()
    {
        remove_all_filters('wpsms_gateway_registry');

        set_transient(GatewayRegistry::CACHE_KEY_GATEWAYS, [
            'source'           => 'api',
            'gateways'         => [],
            'premium_gateways' => [
                ['slug' => 'tskin', 'name' => 'TSKIN', 'premium' => true],
                ['slug' => 'twilio', 'name' => 'Twilio', 'premium' => true],
            ],
            'premium_count'    => 2,
            'regions'          => [],
        ], 60);

        $registry = GatewayRegistry::getGateways();

        $free = array_column($registry['gateways'], 'premium', 'slug');

        $this->assertArrayHasKey('tskin', $free);
        $this->assertFalse($free['tskin']);
        $this->assertSame(['twilio'], array_column($registry['premium_gateways'], 'slug'));

        delete_transient(GatewayRegistry::CACHE_KEY_GATEWAYS);
    }
}
