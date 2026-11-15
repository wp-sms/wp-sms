<?php

namespace unit;

use WP_UnitTestCase;

require_once dirname(__DIR__, 3) . '/includes/gateways/class-wpsms-gateway-uwaziimobile.php';

/**
 * UwaziiMobile sends with one /send call per message. It does not read the
 * balance first: an extra /me call per SMS hit UwaziiMobile's rate limit when
 * many messages went out at once, and the SMS failed with 429 (#614).
 */
class UwaziimobileGatewayTest extends WP_UnitTestCase
{
    /** @var string[] URLs the gateway called */
    private $urls = [];

    /** @var array<string,array> answer per path */
    private $answers = [];

    public function setUp(): void
    {
        parent::setUp();
        $this->urls    = [];
        $this->answers = [];
        delete_transient('wpsms_uwazii_token');
        add_filter('pre_http_request', [$this, 'fakeHttp'], 10, 3);
    }

    public function tearDown(): void
    {
        remove_filter('pre_http_request', [$this, 'fakeHttp'], 10);
        delete_transient('wpsms_uwazii_token');
        parent::tearDown();
    }

    public function fakeHttp($pre, $args, $url)
    {
        $this->urls[] = $url;
        $path         = (string) wp_parse_url($url, PHP_URL_PATH);
        [$code, $body] = $this->answers[$path] ?? [404, ['status' => false, 'errors' => 'Not found']];

        return [
            'headers'  => [],
            'body'     => wp_json_encode($body),
            'response' => ['code' => $code, 'message' => ''],
            'cookies'  => [],
        ];
    }

    private function gateway()
    {
        $gateway           = new UwaziimobileGatewayWithoutLog();
        $gateway->username = 'sample-user';
        $gateway->password = 'sample-pass';
        $gateway->from     = 'SAMPLE';
        $gateway->to       = ['254700000000'];
        $gateway->msg      = 'Test message';
        $GLOBALS['sms']    = $gateway;

        return $gateway;
    }

    public function test_send_makes_no_balance_call()
    {
        set_transient('wpsms_uwazii_token', 'sample-token', HOUR_IN_SECONDS);
        $this->answers['/v1/send'] = [200, ['status' => 1, 'data' => []]];
        $this->answers['/v1/me']   = [429, ['status' => false, 'error_code' => 429, 'errors' => 'Too Many Requests']];

        $result = $this->gateway()->SendSMS();

        $this->assertNotWPError($result);
        $this->assertCount(1, $this->urls);
        $this->assertStringEndsWith('/v1/send', $this->urls[0]);
    }

    public function test_send_fails_clearly_when_the_login_is_refused()
    {
        $this->answers['/v1/authorize'] = [401, ['status' => false, 'errors' => 'Invalid credentials']];

        $result = $this->gateway()->SendSMS();

        $this->assertWPError($result);
        foreach ($this->urls as $url) {
            $this->assertStringEndsNotWith('/v1/send', $url);
        }
    }

    public function test_send_without_credentials_fails_without_calling_uwaziimobile()
    {
        $gateway           = $this->gateway();
        $gateway->username = '';

        $this->assertWPError($gateway->SendSMS());
        $this->assertSame([], $this->urls);
    }
}

class UwaziimobileGatewayWithoutLog extends \WP_SMS\Gateway\uwaziimobile
{
    public function log($sender, $message, $to, $response, $status = 'success', $media = [])
    {
    }
}
