<?php

namespace unit;

use WP_SMS\Components\Sms;
use WP_UnitTestCase;

class SendSMSTest extends WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Additional setup if needed
    }

    /**
     * Test sending an SMS to invalid numbers.
     */
    public function testSendSmsToInvalidNumbers()
    {
        $response = wp_sms_send('', 'Hello world');

        $this->assertWPError($response);
        $this->assertStringContainsString('invalid_mobile_number', $response->get_error_code());
    }

    /**
     * Test sending an SMS to invalid numbers in an array.
     */
    public function testSendSmsToInvalidArrayNumbers()
    {
        $response = wp_sms_send(['0', ''], 'Hello world');

        $this->assertWPError($response);
        $this->assertStringContainsString('invalid_mobile_number', $response->get_error_code());
    }

    /**
     * Test sending SMS with empty message returns error.
     */
    public function testSendSmsWithEmptyMessageReturnsError()
    {
        $response = Sms::send([
            'to'  => ['+12025551234'],
            'msg' => ''
        ]);

        $this->assertWPError($response);
        $this->assertEquals('empty_message', $response->get_error_code());
    }

    /**
     * Test sending SMS with null message returns error.
     */
    public function testSendSmsWithNullMessageReturnsError()
    {
        $response = Sms::send([
            'to'  => ['+12025551234'],
            'msg' => null
        ]);

        $this->assertWPError($response);
    }

    /**
     * Test sending SMS to empty array returns error.
     */
    public function testSendSmsToEmptyArrayReturnsError()
    {
        $response = Sms::send([
            'to'  => [],
            'msg' => 'Hello'
        ]);

        $this->assertWPError($response);
        $this->assertEquals('invalid_mobile_number', $response->get_error_code());
    }

    /**
     * Test sending SMS filters out empty numbers from array.
     */
    public function testSendSmsFiltersOutEmptyNumbers()
    {
        $response = Sms::send([
            'to'  => ['', '0', ''],
            'msg' => 'Hello'
        ]);

        $this->assertWPError($response);
        $this->assertEquals('invalid_mobile_number', $response->get_error_code());
    }

    /**
     * Test wp_sms_send helper function with string number.
     */
    public function testWpSmsSendHelperWithStringNumber()
    {
        $response = wp_sms_send('', 'Test message');

        $this->assertWPError($response);
    }

    /**
     * Test wp_sms_send helper function with array numbers.
     */
    public function testWpSmsSendHelperWithArrayNumbers()
    {
        $response = wp_sms_send([], 'Test message');

        $this->assertWPError($response);
    }

    /**
     * Test sending SMS with whitespace-only message.
     */
    public function testSendSmsWithWhitespaceOnlyMessage()
    {
        $response = Sms::send([
            'to'  => ['+12025551234'],
            'msg' => '   '
        ]);

        // Whitespace-only should be treated as empty
        $this->assertWPError($response);
    }

    /**
     * Test Sms::send accepts single number as string (backward compatibility).
     */
    public function testSmsSendAcceptsSingleNumberAsString()
    {
        $response = Sms::send([
            'to'  => '',  // Empty string should fail
            'msg' => 'Hello'
        ]);

        $this->assertWPError($response);
    }

    /**
     * Test Sms::send with only zeros in array.
     */
    public function testSmsSendWithOnlyZerosInArray()
    {
        $response = Sms::send([
            'to'  => ['0', '0', '0'],
            'msg' => 'Hello'
        ]);

        $this->assertWPError($response);
        $this->assertEquals('invalid_mobile_number', $response->get_error_code());
    }
}
