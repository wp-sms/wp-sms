<?php

namespace unit;

use WP_SMS\Gateway\globalvoice;
use WP_SMS\Gateway\smsense;
use WP_UnitTestCase;

require_once dirname(__DIR__, 3) . '/includes/gateways/class-wpsms-gateway-smsense.php';

class SmsenseGatewayTest extends WP_UnitTestCase
{
    private $requests = [];
    private $previousSms;

    protected function setUp(): void
    {
        parent::setUp();

        // The bulk-send filter reads the global gateway; in the plugin that is the gateway itself.
        $this->previousSms = $GLOBALS['sms'] ?? null;
    }

    protected function tearDown(): void
    {
        remove_all_filters('pre_http_request');
        $GLOBALS['sms'] = $this->previousSms;
        $this->requests = [];

        parent::tearDown();
    }

    public function test_balance_is_read_from_smsense_account()
    {
        $this->interceptHttp();

        $gateway          = new smsense();
        $gateway->has_key = 'token-123';

        $this->assertSame('42.5 EUR', $gateway->GetCredit());

        $request = $this->requests[0];
        $this->assertSame('https://retail.smsense.com/rest/account', $request['url']);
        $this->assertSame('GET', $request['args']['method']);
        $this->assertSame('Bearer token-123', $request['args']['headers']['authorization']);
    }

    public function test_send_posts_to_smsense()
    {
        $this->interceptHttp();

        $gateway          = new smsense();
        $gateway->has_key = 'token-123';
        $gateway->from    = 'VeronaLabs';
        $gateway->to      = ['+15555550100', '+15555550101'];
        $gateway->msg     = 'Hello from WSMS';
        $GLOBALS['sms']   = $gateway;

        $response = $gateway->SendSMS();

        $this->assertNotWPError($response);

        $send = end($this->requests);
        $this->assertSame('https://retail.smsense.com/rest/send_sms', $send['url']);
        $this->assertSame('POST', $send['args']['method']);
        $this->assertSame('Bearer token-123', $send['args']['headers']['authorization']);
        $this->assertSame([
            'from'    => 'VeronaLabs',
            'to'      => '+15555550100,+15555550101',
            'message' => 'Hello from WSMS',
        ], $send['args']['body']);
    }

    public function test_missing_token_is_reported()
    {
        $gateway          = new smsense();
        $gateway->has_key = '';

        $this->assertWPError($gateway->GetCredit());
    }

    public function test_global_voice_keeps_its_own_host()
    {
        $this->interceptHttp();

        $gateway          = new globalvoice();
        $gateway->has_key = 'token-123';
        $gateway->GetCredit();

        $this->assertSame('https://rest.global-voice.net/rest/account', $this->requests[0]['url']);
    }

    private function interceptHttp()
    {
        add_filter('pre_http_request', function ($pre, $args, $url) {
            $this->requests[] = ['url' => $url, 'args' => $args];

            if (substr($url, -strlen('/account')) === '/account') {
                $body = wp_json_encode([['balance' => '42.5', 'currency_code' => 'EUR']]);
            } else {
                $body = wp_json_encode(['message_id' => 'msg-1']);
            }

            return ['body' => $body, 'response' => ['code' => 200], 'headers' => [], 'cookies' => []];
        }, 10, 3);
    }
}
