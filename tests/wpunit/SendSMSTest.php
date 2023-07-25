<?php

class SendSMSTest extends \Codeception\TestCase\WPTestCase
{
    /**
     * @var \WpunitTester
     */
    protected $tester;

    public function setUp(): void
    {
        // Before...
        parent::setUp();

        // Your set up methods here.
    }

    public function tearDown(): void
    {
        // Your tear down methods here.

        // Then...
        parent::tearDown();
    }

    public function test_send_sms_to_invalid_numbers()
    {
        $response = wp_sms_send('', 'Hello world');

        $this->assertWPError($response);
        $this->assertStringContainsString($response->get_error_code(), 'invalid_mobile_number');
    }

    public function test_send_sms_to_invalid_array_numbers()
    {
        $response = wp_sms_send(['0', ''], 'Hello world');

        $this->assertWPError($response);
        $this->assertStringContainsString($response->get_error_code(), 'invalid_mobile_number');
    }
}
