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

    /** @var array[] queued answers for multi-request tests */
    private $answers = [];

    public function setUp(): void
    {
        parent::setUp();
        $this->requests = [];
        $this->answers  = [];
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

        return $this->answers ? array_shift($this->answers) : $this->answer;
    }

    private function answer($code, $body)
    {
        $this->answer = $this->response($code, $body);
    }

    private function response($code, $body)
    {
        return [
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

    public function test_empty_sender_sends_from_the_shared_line()
    {
        $this->answer(200, ['meta' => ['code' => 'OK'], 'data' => ['succeed' => true, 'requestId' => 1]]);

        $gateway       = $this->gateway();
        $gateway->from = '';

        $this->assertIsArray($gateway->SendSMS());
        $this->assertSame([
            'type'        => 'Informational',
            'recipients'  => ['09120000000', '09120000001'],
            'messageText' => 'Test message',
        ], json_decode(end($this->requests)['args']['body'], true));
    }

    public function test_pattern_is_sent_once_per_recipient_with_named_parameters()
    {
        $this->answer(200, ['meta' => ['code' => 'OK'], 'data' => ['succeed' => true, 'requestId' => 2]]);

        $gateway                   = $this->gateway();
        $gateway->msg              = 'Your code is 1234|815422';
        $gateway->messageVariables = ['code' => 1234, 'site_name' => 'Shop'];

        $this->assertIsArray($gateway->SendSMS());
        $this->assertCount(2, $this->requests);
        $this->assertSame('https://api.mediana.ir/sms/v1/send/pattern', $this->requests[0]['url']);
        $this->assertSame([
            'sendingNumber' => '3000505',
            'recipients'    => ['09120000000'],
            'patternCode'   => '815422',
            'parameters'    => ['code' => '1234', 'site_name' => 'Shop'],
        ], json_decode($this->requests[0]['args']['body'], true));
        $this->assertSame(['09120000001'], json_decode($this->requests[1]['args']['body'], true)['recipients']);
    }

    public function test_pattern_without_sender_uses_the_shared_line()
    {
        $this->answer(200, ['meta' => ['code' => 'OK'], 'data' => ['succeed' => true, 'requestId' => 3]]);

        $gateway                   = $this->gateway();
        $gateway->from             = '';
        $gateway->to               = ['09120000000'];
        $gateway->msg              = 'Your code is 1234|815422';
        $gateway->messageVariables = ['code' => '1234'];

        $gateway->SendSMS();

        $body = json_decode($this->requests[0]['args']['body'], true);
        $this->assertSame('Informational', $body['type']);
        $this->assertArrayNotHasKey('sendingNumber', $body);
    }

    public function test_template_code_without_variables_sends_plain_text()
    {
        $this->answer(200, ['meta' => ['code' => 'OK'], 'data' => ['succeed' => true, 'requestId' => 4]]);

        $gateway      = $this->gateway();
        $gateway->msg = 'Hello|815422';

        $gateway->SendSMS();

        $send = end($this->requests);
        $this->assertSame('https://api.mediana.ir/sms/v1/send/sms', $send['url']);
        $this->assertSame('Hello', json_decode($send['args']['body'], true)['messageText']);
    }

    public function test_message_filter_can_select_a_pattern()
    {
        $this->answer(200, ['meta' => ['code' => 'OK'], 'data' => ['succeed' => true, 'requestId' => 4]]);
        $filter = static function ($message) {
            return $message . '|815422';
        };
        add_filter('wp_sms_msg', $filter);

        try {
            $gateway                   = $this->gateway();
            $gateway->messageVariables = ['code' => 1234];
            $gateway->SendSMS();
        } finally {
            remove_filter('wp_sms_msg', $filter);
        }

        $this->assertSame('https://api.mediana.ir/sms/v1/send/pattern', $this->requests[0]['url']);
        $this->assertSame('815422', json_decode($this->requests[0]['args']['body'], true)['patternCode']);
    }

    public function test_unknown_sender_number_says_to_fix_or_clear_it()
    {
        $this->answer(400, ['meta' => ['code' => 'BadRequest', 'errorMessage' => 'x', 'errors' => [['key' => 'error', 'errorCode' => 1101]]], 'data' => null]);

        $result = $this->gateway()->SendSMS();

        $this->assertInstanceOf('WP_Error', $result);
        $this->assertSame('mediana-1101', $result->get_error_code());
        $this->assertStringContainsString('3000505', $result->get_error_message());
        $this->assertStringContainsString('leave it empty', $result->get_error_message());
        $this->assertCount(1, $this->requests, 'No silent retry on the shared line.');
    }

    public function test_rejected_pattern_is_explained()
    {
        $this->answer(400, ['meta' => ['code' => 'BadRequest', 'errorMessage' => 'x', 'errors' => [['key' => 'error', 'errorCode' => 1072]]], 'data' => null]);

        $gateway                   = $this->gateway();
        $gateway->msg              = 'Your code is 1234|815422';
        $gateway->messageVariables = ['code' => '1234'];

        $result = $gateway->SendSMS();

        $this->assertInstanceOf('WP_Error', $result);
        $this->assertSame('mediana-1072', $result->get_error_code());
        $this->assertStringContainsString('rejected', $result->get_error_message());
        $this->assertCount(2, $this->requests);
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

    public function test_invalid_recipients_are_not_reported_as_sent()
    {
        $gateway     = $this->gateway();
        $gateway->to = ['not-a-number'];

        $result = $gateway->SendSMS();

        $this->assertInstanceOf('WP_Error', $result);
        $this->assertCount(0, $this->requests);
        $this->assertSame('error', $gateway->logs[0]['status']);
    }

    public function test_partial_pattern_failure_keeps_sending_and_logs_each_outcome()
    {
        $this->answers = [
            $this->response(200, ['meta' => ['code' => 'OK'], 'data' => ['succeed' => true, 'requestId' => 1]]),
            $this->response(400, ['meta' => ['code' => 'BadRequest', 'errors' => [['errorCode' => 1042]]]]),
            $this->response(200, ['meta' => ['code' => 'OK'], 'data' => ['succeed' => true, 'requestId' => 3]]),
        ];

        $gateway                   = $this->gateway();
        $gateway->to               = ['09120000000', '09120000001', '09120000002'];
        $gateway->msg              = 'Your code is 1234|815422';
        $gateway->messageVariables = ['code' => 1234];

        $result = $gateway->SendSMS();

        $this->assertIsArray($result);
        $this->assertCount(3, $this->requests);
        $this->assertCount(2, $gateway->logs);
        $this->assertSame('error', $gateway->logs[0]['status']);
        $this->assertSame(['09120000001'], $gateway->logs[0]['to']);
        $this->assertSame('success', $gateway->logs[1]['status']);
        $this->assertSame(['09120000000', '09120000002'], $gateway->logs[1]['to']);
    }
}

class MedianaGatewayWithoutLog extends \WP_SMS\Gateway\mediana
{
    public $logs = [];

    public function log($sender, $message, $to, $response, $status = 'success', $media = array())
    {
        $this->logs[] = compact('sender', 'message', 'to', 'response', 'status', 'media');

        return true;
    }
}
