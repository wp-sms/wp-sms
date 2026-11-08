<?php

namespace unit;

use WP_UnitTestCase;

require_once dirname(__DIR__, 3) . '/includes/gateways/class-wpsms-gateway-mediana.php';

/**
 * Mediana sends through its REST API with an API key. Every answer, and no
 * answer at all, ends in a result or a WP_Error, never a fatal error.
 */
class MedianaGatewayTest extends WP_UnitTestCase
{
    /** @var array[] requests the gateway made */
    private $requests = [];

    /** @var array|\WP_Error the next answer */
    private $answer;

    public function setUp(): void
    {
        parent::setUp();
        $this->requests = [];
        add_filter('pre_http_request', [$this, 'fakeHttp'], 10, 3);
    }

    public function tearDown(): void
    {
        remove_filter('pre_http_request', [$this, 'fakeHttp'], 10);
        parent::tearDown();
    }

    public function fakeHttp($pre, $args, $url)
    {
        $this->requests[] = ['url' => $url, 'args' => $args];

        return $this->answer;
    }

    private function answer($code, $body)
    {
        $this->answer = [
            'headers'  => [],
            'body'     => is_string($body) ? $body : wp_json_encode($body),
            'response' => ['code' => $code, 'message' => ''],
            'cookies'  => [],
        ];
    }

    private function gateway()
    {
        $gateway          = new MedianaGatewayWithoutLog();
        $gateway->has_key = 'test-key';
        $gateway->from    = '3000505';
        $gateway->to      = ['+989120000000', '09120000001'];
        $gateway->msg     = 'Test message';

        // The recipient filters read the active gateway from this global.
        $GLOBALS['sms'] = $gateway;

        return $gateway;
    }

    public function test_balance_is_read_with_the_api_key()
    {
        $this->answer(200, ['meta' => ['code' => 'OK'], 'data' => ['balance' => 9358]]);

        $this->assertSame(9358, $this->gateway()->GetCredit());
        $this->assertSame('https://api.mediana.ir/sms/v1/account/balance', $this->requests[0]['url']);
        $this->assertSame('test-key', $this->requests[0]['args']['headers']['X-API-KEY']);
    }

    public function test_send_posts_local_numbers_and_returns_the_answer()
    {
        $this->answer(200, ['meta' => ['code' => 'OK'], 'data' => ['succeed' => true, 'requestId' => 187484315, 'balance' => 1]]);

        $result = $this->gateway()->SendSMS();

        $this->assertSame(187484315, $result['requestId']);
        $send = end($this->requests);
        $this->assertSame('https://api.mediana.ir/sms/v1/send/sms', $send['url']);
        $this->assertSame([
            'sendingNumber' => '3000505',
            'recipients'    => ['09120000000', '09120000001'],
            'messageText'   => 'Test message',
        ], json_decode($send['args']['body'], true));
    }

    public function test_send_splits_more_than_100_recipients()
    {
        $this->answer(200, ['meta' => ['code' => 'OK'], 'data' => ['succeed' => true, 'requestId' => 1]]);

        $gateway     = $this->gateway();
        $gateway->to = array_map(function ($i) {
            return sprintf('0912%07d', $i);
        }, range(1, 150));

        $gateway->SendSMS();

        $this->assertCount(2, $this->requests);
    }

    public function test_documented_error_code_is_explained()
    {
        $this->answer(400, ['meta' => ['code' => 'BadRequest', 'errorMessage' => 'x', 'errors' => [['errorCode' => 1042]]]]);

        $result = $this->gateway()->SendSMS();

        $this->assertInstanceOf('WP_Error', $result);
        $this->assertSame('mediana-1042', $result->get_error_code());
    }

    public function test_refused_key_says_so()
    {
        $this->answer(401, '');

        $credit = $this->gateway()->GetCredit();

        $this->assertInstanceOf('WP_Error', $credit);
        $this->assertStringContainsString('API key', $credit->get_error_message());
    }

    public function test_unreachable_host_is_a_wp_error()
    {
        $this->answer = new \WP_Error('http_request_failed', 'cURL error 28');

        $credit = $this->gateway()->GetCredit();
        $this->assertInstanceOf('WP_Error', $credit);
        $this->assertStringContainsString('could not be reached', $credit->get_error_message());

        $result = $this->gateway()->SendSMS();
        $this->assertSame('gateway-unreachable', $result->get_error_code());
    }

    public function test_missing_key_never_calls_mediana()
    {
        $gateway          = $this->gateway();
        $gateway->has_key = true;

        $this->assertInstanceOf('WP_Error', $gateway->GetCredit());
        $this->assertInstanceOf('WP_Error', $gateway->SendSMS());
        $this->assertCount(0, $this->requests);
    }
}

class MedianaGatewayWithoutLog extends \WP_SMS\Gateway\mediana
{
    public function log($sender, $message, $to, $response, $status = 'success', $media = array())
    {
        return true;
    }
}
