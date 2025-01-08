<?php

namespace unit;

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
}
