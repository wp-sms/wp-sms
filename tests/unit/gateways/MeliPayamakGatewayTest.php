<?php

namespace unit;

use WP_UnitTestCase;
use WP_SMS\Gateway\melipayamak;

// Manually load the MeliPayamak gateway class since it's not autoloaded in tests
require_once dirname(__DIR__, 3) . '/includes/gateways/class-wpsms-gateway-melipayamak.php';

/**
 * Test class for MeliPayamak gateway message parsing logic
 */
class MeliPayamakGatewayTest extends WP_UnitTestCase
{
    protected $gateway;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gateway = new melipayamak();
    }

    /**
     * Test message parsing with template pattern: message|templateId
     */
    public function testMessageParsingWithTemplatePattern()
    {
        $test_cases = [
            [
                'input' => 'a:b:c:d|999',
                'expected' => [
                    'api_type' => 'shared',
                    'body_id' => '999',
                    'message' => 'a:b:c:d',
                    'formatted_text' => 'a;b;c;d'
                ]
            ],
            [
                'input' => 'a:b:c:d|999##shared',
                'expected' => [
                    'api_type' => 'shared',
                    'body_id' => '999',
                    'message' => 'a:b:c:d',
                    'formatted_text' => 'a;b;c;d'
                ]
            ],
            [
                'input' => 'a:b:c:d|999##smart',
                'expected' => [
                    'api_type' => 'smart',
                    'body_id' => null,
                    'message' => 'a:b:c:d|999',
                    'formatted_text' => 'a:b:c:d|999'
                ]
            ]
        ];

        foreach ($test_cases as $test_case) {
            $this->gateway->msg = $test_case['input'];
            $result = $this->gateway->parseMessageAndApiType($test_case['input']);
            
            $this->assertEquals($test_case['expected']['api_type'], $result['api_type'], 
                "API type mismatch for input: {$test_case['input']}");
            $this->assertEquals($test_case['expected']['body_id'], $result['body_id'], 
                "Body ID mismatch for input: {$test_case['input']}");
            $this->assertEquals($test_case['expected']['formatted_text'], $result['formatted_text'], 
                "Formatted text mismatch for input: {$test_case['input']}");
        }
    }

    /**
     * Test message parsing with template pattern: templateId-args
     */
    public function testMessageParsingWithTemplateIdArgsPattern()
    {
        $test_cases = [
            [
                'input' => '46453-otp##shared',
                'expected' => [
                    'api_type' => 'shared',
                    'body_id' => '46453',
                    'message' => 'otp',
                    'formatted_text' => 'otp'
                ]
            ],
            [
                'input' => '8888-otp1:site2:site3##shared',
                'expected' => [
                    'api_type' => 'shared',
                    'body_id' => '8888',
                    'message' => 'otp1:site2:site3',
                    'formatted_text' => 'otp1;site2;site3'
                ]
            ],
            [
                'input' => '8888-otp1:site2:site3##smart',
                'expected' => [
                    'api_type' => 'smart',
                    'body_id' => null,
                    'message' => '8888-otp1:site2:site3',
                    'formatted_text' => '8888-otp1:site2:site3'
                ]
            ],
            [
                'input' => '12345-param1:param2:param3',
                'expected' => [
                    'api_type' => 'shared',
                    'body_id' => '12345',
                    'message' => 'param1:param2:param3',
                    'formatted_text' => 'param1;param2;param3'
                ]
            ],
            [
                'input' => '12345-param1:param2:param3##smart',
                'expected' => [
                    'api_type' => 'smart',
                    'body_id' => null,
                    'message' => '12345-param1:param2:param3',
                    'formatted_text' => '12345-param1:param2:param3'
                ]
            ],
            [
                'input' => '999-single',
                'expected' => [
                    'api_type' => 'smart',
                    'body_id' => null,
                    'message' => '999-single',
                    'formatted_text' => '999-single'
                ]
            ]
        ];

        foreach ($test_cases as $test_case) {
            $this->gateway->msg = $test_case['input'];
            $result = $this->gateway->parseMessageAndApiType($test_case['input']);
            
            $this->assertEquals($test_case['expected']['api_type'], $result['api_type'], 
                "API type mismatch for input: {$test_case['input']}");
            $this->assertEquals($test_case['expected']['body_id'], $result['body_id'], 
                "Body ID mismatch for input: {$test_case['input']}");
            $this->assertEquals($test_case['expected']['formatted_text'], $result['formatted_text'], 
                "Formatted text mismatch for input: {$test_case['input']}");
        }
    }

    /**
     * Test message parsing with simple messages
     */
    public function testMessageParsingWithSimpleMessages()
    {
        $test_cases = [
            [
                'input' => 'a-b',
                'expected' => [
                    'api_type' => 'smart',
                    'body_id' => null,
                    'message' => 'a-b',
                    'formatted_text' => 'a-b'
                ]
            ],
            [
                'input' => 'a-b:c:d',
                'expected' => [
                    'api_type' => 'smart',
                    'body_id' => null,
                    'message' => 'a-b:c:d',
                    'formatted_text' => 'a-b:c:d'
                ]
            ],
            [
                'input' => 'hi there, my name is##smart',
                'expected' => [
                    'api_type' => 'smart',
                    'body_id' => null,
                    'message' => 'hi there, my name is',
                    'formatted_text' => 'hi there, my name is'
                ]
            ],
            [
                'input' => 'hi there',
                'expected' => [
                    'api_type' => 'smart',
                    'body_id' => null,
                    'message' => 'hi there',
                    'formatted_text' => 'hi there'
                ]
            ],
            [
                'input' => 'hi there##shared1',
                'expected' => [
                    'api_type' => 'shared1',
                    'body_id' => null,
                    'message' => 'hi there',
                    'formatted_text' => 'hi there##shared1'
                ]
            ],
            [
                'input' => 'hi there##smart1',
                'expected' => [
                    'api_type' => 'smart1',
                    'body_id' => null,
                    'message' => 'hi there',
                    'formatted_text' => 'hi there##smart1'
                ]
            ],
            [
                'input' => 'hi there##custom',
                'expected' => [
                    'api_type' => 'custom',
                    'body_id' => null,
                    'message' => 'hi there',
                    'formatted_text' => 'hi there##custom'
                ]
            ]
        ];

        foreach ($test_cases as $test_case) {
            $this->gateway->msg = $test_case['input'];
            $result = $this->gateway->parseMessageAndApiType($test_case['input']);
            
            $this->assertEquals($test_case['expected']['api_type'], $result['api_type'], 
                "API type mismatch for input: {$test_case['input']}");
            $this->assertEquals($test_case['expected']['body_id'], $result['body_id'], 
                "Body ID mismatch for input: {$test_case['input']}");
            $this->assertEquals($test_case['expected']['formatted_text'], $result['formatted_text'], 
                "Formatted text mismatch for input: {$test_case['input']}");
        }
    }

    /**
     * Test gateway fields are configured correctly
     */
    public function testGatewayFields()
    {
        $expected_fields = [
            'username',
            'password', 
            'from',
            'from_support_one',
            'from_support_two'
        ];

        foreach ($expected_fields as $field) {
            $this->assertArrayHasKey($field, $this->gateway->gatewayFields);
        }

        // Test specific field configuration
        $this->assertEquals('gateway_username', $this->gateway->gatewayFields['username']['id']);
        $this->assertEquals('gateway_password', $this->gateway->gatewayFields['password']['id']);
        $this->assertEquals('gateway_sender_id', $this->gateway->gatewayFields['from']['id']);
    }

    /**
     * Test API type override parsing
     */
    public function testApiTypeOverrideParsing()
    {
        $test_cases = [
            'message##smart' => 'smart',
            'message##shared' => 'shared',
            'message##smart1' => 'smart1',
            'message##shared2' => 'shared2',
            'message' => null
        ];

        foreach ($test_cases as $input => $expected) {
            $parts = explode("##", $input, 2);
            $api_type_override = isset($parts[1]) ? $parts[1] : null;
            
            $this->assertEquals($expected, $api_type_override, "Failed for input: $input");
        }
    }

    /**
     * Test template pattern detection
     */
    public function testTemplatePatternDetection()
    {
        // Test message|templateId pattern
        $this->gateway->msg = 'Hello World|1234';
        $result = $this->gateway->parseMessageAndApiType('Hello World|1234');
        $this->assertEquals('shared', $result['api_type']);
        $this->assertEquals('1234', $result['body_id']);
        $this->assertEquals('Hello World', $result['message']);

        // Test message|templateId with smart override
        $this->gateway->msg = 'Hello World|1234';
        $result = $this->gateway->parseMessageAndApiType('Hello World|1234##smart');
        $this->assertEquals('smart', $result['api_type']);
        $this->assertNull($result['body_id']);
        $this->assertEquals('Hello World|1234', $result['message']);

        // Test no template pattern
        $this->gateway->msg = 'Hello World';
        $result = $this->gateway->parseMessageAndApiType('Hello World');
        $this->assertEquals('smart', $result['api_type']);
        $this->assertNull($result['body_id']);
        $this->assertEquals('Hello World', $result['message']);

        // Test templateId-args pattern with colons (should be treated as template)
        $result = $this->gateway->parseMessageAndApiType('12345-param1:param2:param3');
        $this->assertEquals('shared', $result['api_type']);
        $this->assertEquals('12345', $result['body_id']);
        $this->assertEquals('param1:param2:param3', $result['message']);

        // Test templateId-args pattern with smart override
        $result = $this->gateway->parseMessageAndApiType('12345-param1:param2:param3##smart');
        $this->assertEquals('smart', $result['api_type']);
        $this->assertNull($result['body_id']);
        $this->assertEquals('12345-param1:param2:param3', $result['message']);

        // Test templateId-args pattern without colons (should not be treated as template)
        $result = $this->gateway->parseMessageAndApiType('12345-single');
        $this->assertEquals('smart', $result['api_type']);
        $this->assertNull($result['body_id']);
        $this->assertEquals('12345-single', $result['message']);

        // Test templateId-args pattern without colons but with shared override (should be treated as template)
        $result = $this->gateway->parseMessageAndApiType('12345-single##shared');
        $this->assertEquals('shared', $result['api_type']);
        $this->assertEquals('12345', $result['body_id']);
        $this->assertEquals('single', $result['message']);
    }

    /**
     * Test argument parsing
     */
    public function testArgumentParsing()
    {
        $args = $this->parseArgsFromMessage('a:b:c:d', ':');
        $this->assertEquals(['a', 'b', 'c', 'd'], $args);

        $args = $this->parseArgsFromMessage('single', ':');
        $this->assertEquals(['single'], $args);

        $args = $this->parseArgsFromMessage('', ':');
        $this->assertEquals([''], $args);
    }

    /**
     * Helper method to parse arguments from message (copied from gateway logic)
     */
    private function parseArgsFromMessage($message, $separator = ":")
    {
        $message_body = explode($separator, $message);

        if (is_array($message_body)) {
            return $message_body;
        }

        return null;
    }
} 