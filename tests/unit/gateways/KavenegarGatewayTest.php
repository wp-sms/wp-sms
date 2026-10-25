<?php

namespace unit;

use WP_UnitTestCase;
use WP_SMS\Gateway\kavenegar;

require_once dirname(__DIR__, 3) . '/includes/gateways/class-wpsms-gateway-kavenegar.php';

class KavenegarGatewayTest extends WP_UnitTestCase
{
    /** @var kavenegar */
    protected $gateway;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gateway = $this->getMockBuilder(kavenegar::class)
            ->onlyMethods(['request', 'log'])
            ->getMock();

        $this->gateway->has_key = 'DUMMY_KEY';
        $this->gateway->from    = '5000';
        $this->gateway->msg     = 'Test Message';
    }

    private function makeResponse($status = 200, $message = 'OK', $entries = [])
    {
        return (object)[
            'return'  => (object)['status' => $status, 'message' => $message],
            'entries' => (object)$entries,
        ];
    }

    /** ✅ Test: simple SMS sending should succeed (POST, values in the body) */
    public function test_send_simple_sms_success()
    {
        $this->gateway->to  = ['2025550100', '2025550101'];
        $this->gateway->msg = 'Test Message';

        $this->gateway->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                $this->stringContains('/v1/DUMMY_KEY/sms/send.json'),
                [],
                $this->callback(function ($params) {
                    return isset($params['body']['receptor'], $params['body']['message'])
                        && $params['body']['receptor'] === '2025550100,2025550101';
                })
            )
            ->willReturn($this->makeResponse(200, 'OK'));

        $response = $this->gateway->SendSMS();
        $this->assertFalse(is_wp_error($response), 'SendSMS returned WP_Error: ' .
            (is_wp_error($response) ? $response->get_error_code() . ' - ' . $response->get_error_message() : ''));
        $this->assertEquals(200, $response->return->status);
    }

    /** ✅ Test: a "+" in the number and sender reaches the body as is (WordPress form-encodes the body) */
    public function test_send_simple_sms_encodes_plus_signs()
    {
        remove_all_filters('wp_sms_to');
        remove_all_filters('wp_sms_from');
        remove_all_filters('wp_sms_msg');

        $this->gateway->to   = ['+989121234567'];
        $this->gateway->from = '+9810004346';
        $this->gateway->msg  = 'Test Message';

        $this->gateway->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                $this->stringContains('/v1/DUMMY_KEY/sms/send.json'),
                [],
                $this->callback(function ($params) {
                    return $params['body']['receptor'] === '+989121234567' && $params['body']['sender'] === '+9810004346';
                })
            )
            ->willReturn($this->makeResponse(200, 'OK'));

        $this->assertFalse(is_wp_error($this->gateway->SendSMS()));
    }

    /** ✅ Test: template SMS sending should succeed */
    public function test_send_template_sms_success()
    {
        // Arrange
        $this->gateway->to               = ['2025550101', '2025550102'];
        $this->gateway->templateId       = 1234;
        $this->gateway->messageVariables = ['name' => 'fake', 'order' => '9988'];

        remove_all_filters('wp_sms_to');
        remove_all_filters('wp_sms_from');
        remove_all_filters('wp_sms_msg');

        $this->gateway->expects($this->exactly(2))
            ->method('request')
            ->with(
                'GET',
                $this->stringContains('/v1/DUMMY_KEY/verify/lookup.json'),
                $this->callback(function ($params) {
                    if (!isset($params['template'], $params['receptor'], $params['token'], $params['token2'])) {
                        return false;
                    }
                    if ((int)$params['template'] !== 1234) {
                        return false;
                    }
                    $allowed = ['2025550101', '2025550102'];
                    return in_array($params['receptor'], $allowed, true);
                })
            )
            ->willReturn($this->makeResponse(200, 'OK'));

        // Act
        $response = $this->gateway->SendSMS();

        // Assert
        $this->assertFalse(is_wp_error($response));

        $this->assertIsObject($response);
        $this->assertObjectHasProperty('status', $response);
        $this->assertSame(1, $response->status);

        $this->assertObjectHasProperty('summary', $response);
        $this->assertSame(['success' => 2, 'failure' => 0], (array)$response->summary);
    }

    /** ✅ Test: more than 200 numbers are sent in calls of at most 200 (Kavenegar error 414 above that) */
    public function test_send_simple_sms_splits_into_batches_of_200()
    {
        remove_all_filters('wp_sms_to');

        $this->gateway->to = array_map(function ($i) {
            return '20255' . str_pad((string)$i, 5, '0', STR_PAD_LEFT);
        }, range(1, 201));

        $sizes = [];
        $this->gateway->expects($this->exactly(2))
            ->method('request')
            ->willReturnCallback(function ($method, $url, $args, $params) use (&$sizes) {
                $sizes[] = count(explode(',', $params['body']['receptor']));
                return $this->makeResponse(200, 'OK');
            });

        $this->assertFalse(is_wp_error($this->gateway->SendSMS()));
        $this->assertSame([200, 1], $sizes);
    }

    /** ✅ Test: error 412 gets a plain hint about the sender line */
    public function test_invalid_sender_error_gets_a_hint()
    {
        $this->gateway->to = ['2025550100'];

        $this->gateway->method('request')
            ->willThrowException(new \Exception('Failed to get success response, 412 invalid sender'));

        $response = $this->gateway->SendSMS();

        $this->assertTrue(is_wp_error($response));
        $this->assertStringContainsString('412', $response->get_error_message());
        $this->assertStringContainsString('sender line', $response->get_error_message());
    }

    /** ✅ Test: template values follow Kavenegar's token rules (error 431 otherwise) */
    public function test_template_values_follow_kavenegar_token_rules()
    {
        $clean = new \ReflectionMethod($this->gateway, 'cleanToken');
        $clean->setAccessible(true);

        $this->assertSame('AliReza', $clean->invoke($this->gateway, 'Ali Reza', 'token'));
        $this->assertSame('SAVE10', $clean->invoke($this->gateway, 'SAVE_10', 'token2'));
        $this->assertSame('line1line2', $clean->invoke($this->gateway, "line1\nline2", 'token3'));
        $this->assertSame('a b c d e fgh', $clean->invoke($this->gateway, 'a b c d e f g h', 'token10'));
        $this->assertSame('a b c d e f g h i', $clean->invoke($this->gateway, 'a b c d e f g h i', 'token20'));
        $this->assertSame(100, mb_strlen($clean->invoke($this->gateway, str_repeat('x', 150), 'token')));
    }

    /** ✅ Test: a failed template send says why it failed */
    public function test_failed_template_send_explains_the_reason()
    {
        remove_all_filters('wp_sms_to');
        remove_all_filters('wp_sms_msg');

        $this->gateway->to               = ['2025550101'];
        $this->gateway->msg              = 'Hello %name%|order';
        $this->gateway->messageVariables = ['%name%' => 'fake'];

        $this->gateway->method('request')
            ->willReturn($this->makeResponse(424, 'template not found'));

        $response = $this->gateway->SendSMS();

        $this->assertTrue(is_wp_error($response));
        $this->assertStringContainsString('424', $response->get_error_message());
        $this->assertStringContainsString('Template not found', $response->get_error_message());
    }
}
