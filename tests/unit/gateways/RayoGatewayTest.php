<?php

namespace unit;

use WP_SMS\Gateway\rayo;
use WP_UnitTestCase;

require_once dirname(__DIR__, 3) . '/includes/gateways/class-wpsms-gateway-rayo.php';

class RayoGatewayTest extends WP_UnitTestCase
{
    private $requests = [];
    private $httpInterceptor;
    private $reply;

    protected function setUp(): void
    {
        parent::setUp();

        remove_all_filters('wp_sms_to');
        remove_all_filters('wp_sms_from');
        remove_all_filters('wp_sms_msg');

        $this->reply = function ($url) {
            if (substr($url, -strlen('/balance_in_credits')) === '/balance_in_credits') {
                return [200, ['request_id' => 'req-1', 'balance' => ['credits' => 4240]]];
            }

            return [200, ['result' => [
                'username'           => 'user1',
                'resultCode'         => '1',
                'resultDescription'  => 'SUCCESS',
                'clientRequestId'    => null,
                'smsServerRequestId' => 'cc479845-0f15-4ae7-b645-6ef6489868c1',
            ]]];
        };

        $this->httpInterceptor = function ($pre, $args, $url) {
            $this->requests[] = ['url' => $url, 'args' => $args];
            [$code, $body] = call_user_func($this->reply, $url);

            return ['body' => wp_json_encode($body), 'response' => ['code' => $code], 'headers' => [], 'cookies' => []];
        };

        add_filter('pre_http_request', $this->httpInterceptor, 10, 3);
    }

    protected function tearDown(): void
    {
        remove_filter('pre_http_request', $this->httpInterceptor, 10);
        $this->requests = [];

        parent::tearDown();
    }

    private function gateway($platform = 'rayo.gr')
    {
        $gateway           = new rayo();
        $gateway->has_key  = 'key-123';
        $gateway->from     = 'MYSHOP';
        $gateway->platform = $platform;
        $gateway->to       = ['+306912345678', '00306912345679', '306912345670'];
        $gateway->msg      = 'Hello from WSMS';

        return $gateway;
    }

    public function test_send_posts_json_to_rayo_gr()
    {
        $response = $this->gateway()->SendSMS();

        $this->assertNotWPError($response);

        $request = $this->requests[0];
        $this->assertSame('https://sms-api.rayo.gr/v1/send_sms', $request['url']);
        $this->assertSame('POST', $request['args']['method']);
        $this->assertSame('Bearer key-123', $request['args']['headers']['Authorization']);
        $this->assertSame('application/json', $request['args']['headers']['Content-Type']);
        $this->assertSame([
            'toMobileNumber' => '306912345678,306912345679,306912345670',
            'fromLabel'      => 'MYSHOP',
            'smsBody'        => 'Hello from WSMS',
        ], json_decode($request['args']['body'], true));
    }

    public function test_rayosms_com_uses_its_own_host()
    {
        $this->gateway('rayosms.com')->SendSMS();

        $this->assertSame('https://api.rayosms.com/v1/send_sms', $this->requests[0]['url']);
    }

    public function test_unknown_platform_falls_back_to_rayo_gr()
    {
        $this->gateway('')->SendSMS();

        $this->assertSame('https://sms-api.rayo.gr/v1/send_sms', $this->requests[0]['url']);
    }

    public function test_test_mode_sets_the_simulate_flag()
    {
        $gateway           = $this->gateway();
        $gateway->simulate = 'on';
        $gateway->SendSMS();

        $body = json_decode($this->requests[0]['args']['body'], true);
        $this->assertTrue($body['simulateSendFlag']);
    }

    public function test_balance_is_read_in_credits()
    {
        $this->assertSame(4240, $this->gateway()->GetCredit());
        $this->assertSame('https://sms-api.rayo.gr/v1/balance_in_credits', $this->requests[0]['url']);
        $this->assertSame('GET', $this->requests[0]['args']['method']);
    }

    public function test_unregistered_sender_error_is_explained()
    {
        $this->reply = function () {
            return [422, [
                'result' => ['resultCode' => '0', 'resultDescription' => 'ERROR: fromLabel does not belong to you'],
                'error'  => 'fromLabel does not belong to you',
            ]];
        };

        $response = $this->gateway()->SendSMS();

        $this->assertWPError($response);
        $this->assertStringContainsString('422', $response->get_error_message());
        $this->assertStringContainsString('fromLabel does not belong to you', $response->get_error_message());
        $this->assertStringContainsString('Sender ID', $response->get_error_message());
    }

    public function test_result_code_other_than_one_is_a_failure()
    {
        $this->reply = function () {
            return [200, ['result' => ['resultCode' => '0', 'resultDescription' => 'ERROR: no credits']]];
        };

        $response = $this->gateway()->SendSMS();

        $this->assertWPError($response);
        $this->assertStringContainsString('no credits', $response->get_error_message());
    }

    public function test_missing_key_or_sender_is_reported_without_a_request()
    {
        $gateway          = $this->gateway();
        $gateway->has_key = '';
        $this->assertWPError($gateway->SendSMS());
        $this->assertWPError($gateway->GetCredit());

        $gateway       = $this->gateway();
        $gateway->from = '';
        $this->assertWPError($gateway->SendSMS());

        $this->assertSame([], $this->requests);
    }
}
