<?php

namespace unit;

use WP_UnitTestCase;
use WP_SMS\Gateway\custom;

require_once dirname(__DIR__, 3) . '/includes/gateways/class-wpsms-gateway-custom.php';

class CustomGatewayTest extends WP_UnitTestCase
{
    /** @var custom */
    protected $gateway;

    protected function setUp(): void
    {
        parent::setUp();

        remove_all_filters('wp_sms_from');
        remove_all_filters('wp_sms_to');
        remove_all_filters('wp_sms_msg');

        $this->gateway = $this->getMockBuilder(custom::class)
            ->onlyMethods(['request', 'log'])
            ->getMock();

        $this->gateway->api_url = 'https://example.test/send';
        $this->gateway->from    = 'Sender';
        $this->gateway->to      = ['+31600000001', '+31600000002'];
        $this->gateway->msg     = 'Hello world';
    }

    public function test_http_headers_are_marked_as_secret()
    {
        $this->assertTrue($this->gateway->gatewayFields['http_headers']['isPassword']);
    }

    public function test_http_headers_secret_flag_is_exposed_to_dashboard()
    {
        global $sms;

        $previousGateway = $sms ?? null;
        $sms             = $this->gateway;
        $dashboard       = \WP_SMS\Admin\Dashboard::instance();
        $method          = new \ReflectionMethod($dashboard, 'getGatewayCapabilities');
        $method->setAccessible(true);

        try {
            $capabilities = $method->invoke($dashboard);
        } finally {
            $sms = $previousGateway;
        }

        $this->assertTrue($capabilities['gatewayFields']['http_headers']['isPassword']);
    }

    public function test_key_value_get_sends_params_as_query_string()
    {
        $this->gateway->http_parameters = "to:{to}\nmessage:{message}";

        $this->gateway->expects($this->once())
            ->method('request')
            ->with(
                'GET',
                'https://example.test/send',
                $this->callback(function ($params) {
                    return $params === [
                        'to'      => '+31600000001,+31600000002',
                        'message' => 'Hello world',
                    ];
                }),
                $this->callback(function ($args) {
                    return isset($args['headers']) && empty($args['body']);
                })
            )
            ->willReturn('ok');

        $this->gateway->SendSMS();
    }

    public function test_key_value_post_encodes_params_as_json_body()
    {
        $this->gateway->is_post_body    = 'yes';
        $this->gateway->http_parameters = "to:{to}\nmessage:{message}";

        $this->gateway->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                'https://example.test/send',
                [],
                $this->callback(function ($args) {
                    $decoded = json_decode($args['body'], true);
                    return $decoded === [
                        'to'      => '+31600000001,+31600000002',
                        'message' => 'Hello world',
                    ];
                })
            )
            ->willReturn('ok');

        $this->gateway->SendSMS();
    }

    public function test_key_value_post_supports_form_urlencoded_body()
    {
        $this->gateway->to               = ['+31600000001'];
        $this->gateway->is_post_body     = 'yes';
        $this->gateway->post_body_format = 'form_urlencoded';
        $this->gateway->http_parameters  = "to:{to}\nmessage:{message}";

        $this->gateway->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                'https://example.test/send',
                [],
                $this->callback(function ($args) {
                    parse_str($args['body'], $decoded);

                    return $decoded === [
                        'to'      => '+31600000001',
                        'message' => 'Hello world',
                    ] && $args['headers']['Content-Type'] === 'application/x-www-form-urlencoded';
                })
            )
            ->willReturn('ok');

        $this->gateway->SendSMS();
    }

    public function test_form_urlencoded_body_does_not_double_encode_message()
    {
        $this->gateway->to               = ['+31600000001'];
        $this->gateway->msg              = 'Hello world & friends + more';
        $this->gateway->encode_message   = 'yes';
        $this->gateway->is_post_body     = 'yes';
        $this->gateway->post_body_format = 'form_urlencoded';
        $this->gateway->http_parameters  = "to:{to}\nmessage:{message}";

        $this->gateway->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                'https://example.test/send',
                [],
                $this->callback(function ($args) {
                    parse_str($args['body'], $decoded);

                    return $decoded['message'] === 'Hello world & friends + more';
                })
            )
            ->willReturn('ok');

        $this->gateway->SendSMS();
    }

    public function test_key_value_bracket_syntax_produces_json_array()
    {
        $this->gateway->is_post_body    = 'yes';
        $this->gateway->http_parameters = "recipients:[{to}]\nmessage:{message}";

        $this->gateway->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                'https://example.test/send',
                [],
                $this->callback(function ($args) {
                    $decoded = json_decode($args['body'], true);
                    return is_array($decoded)
                        && $decoded['recipients'] === ['+31600000001', '+31600000002']
                        && $decoded['message'] === 'Hello world';
                })
            )
            ->willReturn('ok');

        $this->gateway->SendSMS();
    }

    public function test_raw_json_substitutes_basic_placeholders()
    {
        $this->gateway->body_format = 'raw_json';
        $this->gateway->raw_payload = '{"from":"{from}","to":"{to}","text":"{message}"}';

        $this->gateway->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                'https://example.test/send',
                [],
                $this->callback(function ($args) {
                    $decoded = json_decode($args['body'], true);
                    return $decoded === [
                        'from' => 'Sender',
                        'to'   => '+31600000001,+31600000002',
                        'text' => 'Hello world',
                    ];
                })
            )
            ->willReturn('ok');

        $this->gateway->SendSMS();
    }

    public function test_raw_json_recipients_expands_to_json_array_fragment()
    {
        $this->gateway->body_format = 'raw_json';
        $this->gateway->raw_payload = '{"body":"{message}","originator":"{from}","recipients":[{recipients}],"route":"business"}';

        $this->gateway->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                'https://example.test/send',
                [],
                $this->callback(function ($args) {
                    $decoded = json_decode($args['body'], true);
                    return $decoded === [
                        'body'       => 'Hello world',
                        'originator' => 'Sender',
                        'recipients' => ['+31600000001', '+31600000002'],
                        'route'      => 'business',
                    ];
                })
            )
            ->willReturn('ok');

        $this->gateway->SendSMS();
    }

    public function test_raw_json_preserves_static_keys_and_nested_objects()
    {
        $this->gateway->body_format = 'raw_json';
        $this->gateway->raw_payload = '{"text":"{message}","sender":{"name":"{from}","type":"alphanumeric"},"meta":{"reference":"order-42"}}';

        $this->gateway->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                'https://example.test/send',
                [],
                $this->callback(function ($args) {
                    $decoded = json_decode($args['body'], true);
                    return $decoded === [
                        'text'   => 'Hello world',
                        'sender' => ['name' => 'Sender', 'type' => 'alphanumeric'],
                        'meta'   => ['reference' => 'order-42'],
                    ];
                })
            )
            ->willReturn('ok');

        $this->gateway->SendSMS();
    }

    public function test_raw_json_escapes_special_characters_in_message()
    {
        $this->gateway->body_format = 'raw_json';
        $this->gateway->msg         = "\"Line one\nLine \"two\"";
        $this->gateway->raw_payload = '{"text":"{message}"}';

        $this->gateway->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                'https://example.test/send',
                [],
                $this->callback(function ($args) {
                    $decoded = json_decode($args['body'], true);
                    return is_array($decoded) && $decoded['text'] === "\"Line one\nLine \"two\"";
                })
            )
            ->willReturn('ok');

        $this->gateway->SendSMS();
    }

    public function test_raw_json_falls_back_to_key_value_when_payload_empty()
    {
        $this->gateway->body_format     = 'raw_json';
        $this->gateway->raw_payload     = '';
        $this->gateway->http_parameters = "to:{to}";

        $this->gateway->expects($this->once())
            ->method('request')
            ->with(
                'GET',
                $this->anything(),
                $this->callback(function ($params) {
                    return $params === ['to' => '+31600000001,+31600000002'];
                }),
                $this->anything()
            )
            ->willReturn('ok');

        $this->gateway->SendSMS();
    }

    public function test_http_headers_are_parsed_per_line()
    {
        $this->gateway->http_headers    = "Authorization: Bearer xyz\nContent-Type: application/json";
        $this->gateway->http_parameters = "to:{to}";

        $this->gateway->expects($this->once())
            ->method('request')
            ->with(
                $this->anything(),
                $this->anything(),
                $this->anything(),
                $this->callback(function ($args) {
                    return $args['headers'] === [
                        'Authorization' => 'Bearer xyz',
                        'Content-Type'  => 'application/json',
                    ];
                })
            )
            ->willReturn('ok');

        $this->gateway->SendSMS();
    }

    public function test_encode_message_yes_urlencodes_the_message_body()
    {
        $this->gateway->msg             = 'Hello world & friends';
        $this->gateway->encode_message  = 'yes';
        $this->gateway->http_parameters = "message:{message}";

        $this->gateway->expects($this->once())
            ->method('request')
            ->with(
                $this->anything(),
                $this->anything(),
                $this->callback(function ($params) {
                    return $params === ['message' => 'Hello+world+%26+friends'];
                }),
                $this->anything()
            )
            ->willReturn('ok');

        $this->gateway->SendSMS();
    }
}
