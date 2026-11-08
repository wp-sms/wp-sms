<?php

namespace unit;

use WP_UnitTestCase;

require_once dirname(__DIR__, 3) . '/includes/gateways/class-wpsms-gateway-ippanel.php';

/**
 * IPPanel sends through its new REST API (edge.ippanel.com) with an API key.
 * Every answer, and no answer at all, ends in a result or a WP_Error, never a
 * fatal error.
 */
class IppanelGatewayTest extends WP_UnitTestCase
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

    private function sent($ids = [1123544244])
    {
        $this->answer(200, [
            'data' => ['message_outbox_ids' => $ids],
            'meta' => ['status' => true, 'message' => 'انجام شد', 'message_parameters' => [], 'message_code' => '200-1'],
        ]);
    }

    private function gateway()
    {
        $gateway          = new IppanelGatewayWithoutLog();
        $gateway->has_key = 'test-key';
        $gateway->from    = '3000505';
        $gateway->to      = ['+989120000000', '09120000001'];
        $gateway->msg     = 'Test message';

        // The recipient filters read the active gateway from this global.
        $GLOBALS['sms'] = $gateway;

        return $gateway;
    }

    public function test_balance_is_read_with_the_api_key_in_authorization()
    {
        $this->answer(200, [
            'data' => ['credit' => 6084722932.705052, 'yesterday_credit' => 1, 'gift' => 0, 'gift_expires_at' => null],
            'meta' => ['status' => true, 'message' => 'انجام شد', 'message_code' => '200-1'],
        ]);

        $this->assertSame(6084722932.705052, $this->gateway()->GetCredit());
        $this->assertSame('https://edge.ippanel.com/v1/api/payment/credit/mine', $this->requests[0]['url']);
        $this->assertSame('test-key', $this->requests[0]['args']['headers']['Authorization']);
    }

    public function test_send_posts_e164_numbers_as_a_webservice_send()
    {
        $this->sent();

        $result = $this->gateway()->SendSMS();

        $this->assertSame([1123544244], $result['message_outbox_ids']);
        $send = end($this->requests);
        $this->assertSame('https://edge.ippanel.com/v1/api/send', $send['url']);
        $this->assertSame('POST', $send['args']['method']);
        $this->assertSame([
            'sending_type' => 'webservice',
            'from_number'  => '+983000505',
            'message'      => 'Test message',
            'params'       => ['recipients' => ['+989120000000', '+989120000001']],
        ], json_decode($send['args']['body'], true));
    }

    public function test_send_splits_more_than_100_recipients()
    {
        $this->sent();

        $gateway     = $this->gateway();
        $gateway->to = array_map(function ($i) {
            return sprintf('0912%07d', $i);
        }, range(1, 150));

        $gateway->SendSMS();

        $this->assertCount(2, $this->requests);
    }

    public function test_pattern_is_sent_once_per_recipient_with_named_params()
    {
        $this->sent([1123594208]);

        $gateway                   = $this->gateway();
        $gateway->msg              = 'Your code is 1234|jhaskdab45s6f4sfw';
        $gateway->messageVariables = ['%code%' => 1234, 'site_name' => 'Shop'];

        $this->assertIsArray($gateway->SendSMS());
        $this->assertCount(2, $this->requests);
        $this->assertSame('https://edge.ippanel.com/v1/api/send', $this->requests[0]['url']);
        $this->assertSame([
            'sending_type' => 'pattern',
            'from_number'  => '+983000505',
            'code'         => 'jhaskdab45s6f4sfw',
            'recipients'   => ['+989120000000'],
            'params'       => ['code' => '1234', 'site_name' => 'Shop'],
        ], json_decode($this->requests[0]['args']['body'], true));
        $this->assertSame(['+989120000001'], json_decode($this->requests[1]['args']['body'], true)['recipients']);
    }

    public function test_template_code_without_variables_sends_plain_text()
    {
        $this->sent();

        $gateway      = $this->gateway();
        $gateway->msg = 'Hello|jhaskdab45s6f4sfw';

        $gateway->SendSMS();

        $body = json_decode(end($this->requests)['args']['body'], true);
        $this->assertSame('webservice', $body['sending_type']);
        $this->assertSame('Hello', $body['message']);
    }

    public function test_sender_already_in_e164_is_kept()
    {
        $this->sent();

        $gateway       = $this->gateway();
        $gateway->from = '+983000505';

        $gateway->SendSMS();

        $this->assertSame('+983000505', json_decode(end($this->requests)['args']['body'], true)['from_number']);
    }

    public function test_validation_error_says_what_ippanel_said()
    {
        $this->answer(422, [
            'data' => null,
            'meta' => [
                'status'             => false,
                'message'            => 'تکمیل گزینه پیام الزامی است',
                'message_parameters' => [],
                'message_code'       => '400-2',
                'errors'             => ['message' => ['تکمیل گزینه پیام الزامی است']],
            ],
        ]);

        $result = $this->gateway()->SendSMS();

        $this->assertInstanceOf('WP_Error', $result);
        $this->assertSame('ippanel-refused', $result->get_error_code());
        $this->assertStringContainsString('تکمیل گزینه پیام الزامی است', $result->get_error_message());
    }

    public function test_refused_key_says_so()
    {
        $this->answer(401, [
            'data' => null,
            'meta' => ['status' => false, 'message' => 'اطلاعات وارد شده صحیح نمی باشد', 'message_parameters' => [], 'message_code' => '400-1', 'errors' => []],
        ]);

        $credit = $this->gateway()->GetCredit();

        $this->assertInstanceOf('WP_Error', $credit);
        $this->assertStringContainsString('API key', $credit->get_error_message());
    }

    public function test_ok_status_without_outbox_ids_is_not_a_send()
    {
        $this->answer(200, ['data' => ['message_outbox_ids' => []], 'meta' => ['status' => true, 'message_code' => '200-1']]);

        $this->assertInstanceOf('WP_Error', $this->gateway()->SendSMS());
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

    public function test_missing_key_or_sender_never_calls_ippanel()
    {
        $gateway          = $this->gateway();
        $gateway->has_key = true;

        $this->assertInstanceOf('WP_Error', $gateway->GetCredit());
        $this->assertInstanceOf('WP_Error', $gateway->SendSMS());

        $gateway          = $this->gateway();
        $gateway->from    = '';
        $this->assertInstanceOf('WP_Error', $gateway->SendSMS());

        $this->assertCount(0, $this->requests);
    }
}

class IppanelGatewayWithoutLog extends \WP_SMS\Gateway\ippanel
{
    public function log($sender, $message, $to, $response, $status = 'success', $media = array())
    {
        return true;
    }
}
