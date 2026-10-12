<?php

namespace unit;

use WP_UnitTestCase;
use WP_SMS\Gateway\smsense;

require_once dirname(__DIR__, 3) . '/includes/gateways/class-wpsms-gateway-smsense.php';

class SmsenseGatewayTest extends WP_UnitTestCase
{
    /** @var smsense */
    protected $gateway;

    /** @var array Requests the gateway made, as [url, args]. */
    protected $requests = [];

    protected function setUp(): void
    {
        parent::setUp();

        remove_all_filters('wp_sms_to');
        remove_all_filters('wp_sms_from');
        remove_all_filters('wp_sms_msg');

        $this->gateway = $this->getMockBuilder(smsense::class)
            ->onlyMethods(['log'])
            ->getMock();

        $this->gateway->has_key = 'DUMMY_TOKEN';
        $this->gateway->from    = 'WSMS';
        $this->gateway->to      = ['31612345678'];
        $this->gateway->msg     = 'Test Message';

        $this->requests = [];

        add_filter('pre_http_request', function ($pre, $args, $url) {
            $this->requests[] = [$url, $args];

            if (substr($url, -8) === '/account') {
                return ['response' => ['code' => 200], 'body' => json_encode([['balance' => '4.50', 'currency_code' => 'EUR']])];
            }

            return ['response' => ['code' => 200], 'body' => json_encode(['id' => 'abc123'])];
        }, 10, 3);
    }

    /** Requests go to the SMSense host, not to Global Voice. */
    public function test_requests_use_the_smsense_host()
    {
        $this->gateway->SendSMS();

        $this->assertNotEmpty($this->requests);
        foreach ($this->requests as $request) {
            $this->assertStringStartsWith('https://retail.smsense.com/rest/', $request[0]);
        }
    }

    /** The token goes out as a Bearer header and the message fields are sent. */
    public function test_send_uses_bearer_token_and_fields()
    {
        $response = $this->gateway->SendSMS();

        $this->assertFalse(is_wp_error($response));

        $send = array_values(array_filter($this->requests, function ($r) {
            return substr($r[0], -9) === '/send_sms';
        }));

        $this->assertCount(1, $send);
        $this->assertSame('Bearer DUMMY_TOKEN', $send[0][1]['headers']['authorization']);
        $this->assertSame('WSMS', $send[0][1]['body']['from']);
        $this->assertSame('31612345678', $send[0][1]['body']['to']);
        $this->assertSame('Test Message', $send[0][1]['body']['message']);
    }

    /** The balance reads the first account entry. */
    public function test_get_credit_returns_balance_and_currency()
    {
        $this->assertSame('4.50 EUR', $this->gateway->GetCredit());
    }

    /** An empty token returns an error instead of a request. */
    public function test_get_credit_without_token_is_an_error()
    {
        $this->gateway->has_key = '';

        $this->assertTrue(is_wp_error($this->gateway->GetCredit()));
        $this->assertSame([], $this->requests);
    }
}
