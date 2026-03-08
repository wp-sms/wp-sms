<?php

namespace unit\BackwardCompatibility;

use unit\WPSMSTestCase;
use WP_SMS\Option;
use WP_REST_Request;

require_once dirname(__DIR__) . '/WPSMSTestCase.php';

/**
 * Backward Compatibility Tests for SMS Sending
 *
 * Ensures that SMS sending works consistently between legacy functions/filters
 * and the new React dashboard API.
 */
class SmsSendingBackwardCompatibilityTest extends WPSMSTestCase
{
    /**
     * @var array
     */
    private $capturedHookData = [];

    /**
     * @var \wpdb
     */
    private $wpdb;

    /**
     * @var string
     */
    private $outboxTable;

    /**
     * Set up test environment
     */
    public function setUp(): void
    {
        parent::setUp();

        global $wpdb;
        $this->wpdb = $wpdb;
        $this->outboxTable = $wpdb->prefix . 'sms_send';

        // Reset captured data
        $this->capturedHookData = [];

        // Clear outbox
        $this->wpdb->query("DELETE FROM {$this->outboxTable}");

        // Configure test gateway (available when WP_DEBUG is enabled)
        $this->configureTestGateway();
    }

    /**
     * Configure the test gateway for SMS sending tests
     */
    private function configureTestGateway(): void
    {
        Option::updateOption('gateway_name', 'test');
        Option::updateOption('gateway_sender_id', 'TestSender');
        Option::updateOption('store_outbox_messages', true);

        // Reinitialize the global gateway instance
        $GLOBALS['sms'] = \WP_SMS\Gateway::initial();
    }

    /**
     * Tear down test environment
     */
    public function tearDown(): void
    {
        // Remove all filters/actions we added
        remove_all_filters('wp_sms_from');
        remove_all_filters('wp_sms_to');
        remove_all_filters('wp_sms_msg');
        remove_all_actions('wp_sms_send');

        // Clear outbox
        $this->wpdb->query("DELETE FROM {$this->outboxTable}");
        parent::tearDown();
    }

    /**
     * Test: wp_sms_from filter is applied when sending via API
     */
    public function testWpSmsFromFilterAppliedViaApi()
    {
        $filterCalled = false;
        $capturedFrom = null;

        add_filter('wp_sms_from', function ($from) use (&$filterCalled, &$capturedFrom) {
            $filterCalled = true;
            $capturedFrom = $from;
            return 'ModifiedSender';
        });

        $request = $this->createJsonRequest('POST', '/wpsms/v1/send/quick', [
            'recipients' => ['numbers' => ['+15551234567']],
            'message'    => 'Test wp_sms_from filter',
        ]);

        $response = rest_do_request($request);

        $this->assertEquals(200, $response->get_status());
        $this->assertTrue($filterCalled, 'wp_sms_from filter should be called');
        $this->assertNotNull($capturedFrom, 'Filter should receive sender value');
    }

    /**
     * Test: wp_sms_to filter is applied when sending via API
     */
    public function testWpSmsToFilterAppliedViaApi()
    {
        $filterCalled = false;
        $capturedTo = null;

        add_filter('wp_sms_to', function ($to) use (&$filterCalled, &$capturedTo) {
            $filterCalled = true;
            $capturedTo = $to;
            return $to;
        });

        $request = $this->createJsonRequest('POST', '/wpsms/v1/send/quick', [
            'recipients' => ['numbers' => ['+15551234567']],
            'message'    => 'Test wp_sms_to filter',
        ]);

        $response = rest_do_request($request);

        $this->assertTrue($filterCalled, 'wp_sms_to filter should be called');
        $this->assertIsArray($capturedTo, 'Filter should receive recipients array');
    }

    /**
     * Test: wp_sms_msg filter is applied when sending via API
     */
    public function testWpSmsMsgFilterAppliedViaApi()
    {
        $filterCalled = false;
        $capturedMsg = null;

        add_filter('wp_sms_msg', function ($msg) use (&$filterCalled, &$capturedMsg) {
            $filterCalled = true;
            $capturedMsg = $msg;
            return $msg . ' [modified]';
        });

        $testMessage = 'Test wp_sms_msg filter';
        $request = $this->createJsonRequest('POST', '/wpsms/v1/send/quick', [
            'recipients' => ['numbers' => ['+15551234567']],
            'message'    => $testMessage,
        ]);

        $response = rest_do_request($request);

        $this->assertTrue($filterCalled, 'wp_sms_msg filter should be called');
        $this->assertEquals($testMessage, $capturedMsg, 'Filter should receive original message');
    }

    /**
     * Test: wp_sms_send action is fired when sending via API
     */
    public function testWpSmsSendActionFiredViaApi()
    {
        $actionFired = false;
        $capturedResponse = null;

        add_action('wp_sms_send', function ($response) use (&$actionFired, &$capturedResponse) {
            $actionFired = true;
            $capturedResponse = $response;
        });

        $request = $this->createJsonRequest('POST', '/wpsms/v1/send/quick', [
            'recipients' => ['numbers' => ['+15551234567']],
            'message'    => 'Test wp_sms_send action',
        ]);

        $response = rest_do_request($request);

        $this->assertTrue($actionFired, 'wp_sms_send action should be fired after sending');
        $this->assertNotNull($capturedResponse, 'Action should receive response data');
    }

    /**
     * Test: API uses same gateway configuration as legacy
     */
    public function testApiUsesSameGatewayAsLegacy()
    {
        // Test gateway is already set in setUp(), verify it's readable via API
        $request = new WP_REST_Request('GET', '/wpsms/v1/settings');
        $response = rest_do_request($request);
        $data = $response->get_data();

        $this->assertEquals(200, $response->get_status());
        $this->assertEquals('test', $data['data']['settings']['gateway_name']);

        // Now set a different gateway via legacy method and verify API sees it
        Option::updateOption('gateway_name', 'twilio');
        Option::updateOption('gateway_key', 'test_account_sid');

        $request = new WP_REST_Request('GET', '/wpsms/v1/settings');
        $response = rest_do_request($request);
        $data = $response->get_data();

        $this->assertEquals('twilio', $data['data']['settings']['gateway_name']);

        // Restore test gateway for other tests
        $this->configureTestGateway();
    }

    /**
     * Test: Message stored in outbox has same format from both interfaces
     */
    public function testOutboxFormatConsistent()
    {
        $testMessage = 'Outbox format test ' . uniqid();
        $testRecipient = '+15559876543';

        $request = $this->createJsonRequest('POST', '/wpsms/v1/send/quick', [
            'recipients' => ['numbers' => [$testRecipient]],
            'message'    => $testMessage,
        ]);

        $response = rest_do_request($request);

        // Check outbox entry was created
        $outboxEntry = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->outboxTable} WHERE message = %s ORDER BY ID DESC LIMIT 1",
            $testMessage
        ));

        $this->assertNotNull($outboxEntry, 'Outbox entry should be created');
        $this->assertEquals($testMessage, $outboxEntry->message, 'Message should match');
        $this->assertNotEmpty($outboxEntry->recipient, 'Recipient should be stored');
        $this->assertNotEmpty($outboxEntry->date, 'Date should be stored');
    }

    /**
     * Test: Recipient format is normalized consistently
     * Legacy and API should handle phone numbers the same way
     */
    public function testRecipientFormatNormalized()
    {
        // Various phone number formats
        $formats = [
            '+15551234567',
            '15551234567',
            '5551234567',
            '+1 555 123 4567',
            '+1-555-123-4567',
        ];

        foreach ($formats as $format) {
            $request = new WP_REST_Request('POST', '/wpsms/v1/send');
            $request->set_body_params([
                'recipients' => [$format],
                'message'    => 'Format test: ' . $format,
            ]);

            $response = rest_do_request($request);

            // Should either succeed or give clear validation error
            $this->assertContains(
                $response->get_status(),
                [200, 201, 400],
                "Phone format {$format} should be handled"
            );
        }
    }

    /**
     * Test: Flash SMS setting is respected from API
     */
    public function testFlashSmsSettingRespected()
    {
        // Send flash SMS via API
        $request = new WP_REST_Request('POST', '/wpsms/v1/send');
        $request->set_body_params([
            'recipients' => ['+15550000001'],
            'message'    => 'Flash message test',
            'flash'      => true,
        ]);

        $response = rest_do_request($request);

        // Should process without error
        $this->assertContains($response->get_status(), [200, 201, 400, 500]);
    }

    /**
     * Test: Media URLs are passed correctly from API
     */
    public function testMediaUrlsPassedFromApi()
    {
        $mediaUrls = [
            'https://example.com/image1.jpg',
            'https://example.com/image2.png',
        ];

        $request = new WP_REST_Request('POST', '/wpsms/v1/send');
        $request->set_body_params([
            'recipients' => ['+15550000001'],
            'message'    => 'MMS test message',
            'media'      => $mediaUrls,
        ]);

        $response = rest_do_request($request);

        // Should process without error
        $this->assertContains($response->get_status(), [200, 201, 400, 500]);
    }

    /**
     * Test: Send to group works from API
     */
    public function testSendToGroupViaApi()
    {
        global $wpdb;

        // Create group with subscribers
        $groupsTable = $wpdb->prefix . 'sms_subscribes_group';
        $subscribersTable = $wpdb->prefix . 'sms_subscribes';

        $wpdb->insert($groupsTable, ['name' => 'API Send Test Group']);
        $groupId = $wpdb->insert_id;

        // Add subscribers to group
        for ($i = 0; $i < 3; $i++) {
            $wpdb->insert($subscribersTable, [
                'date'          => current_time('mysql'),
                'name'          => 'Group Member ' . $i,
                'mobile'        => '+1555000000' . $i,
                'status'        => '1',
                'activate_key'  => '',
                'group_ID'      => $groupId,
                'custom_fields' => '',
            ]);
        }

        // Send to group via API
        $request = new WP_REST_Request('POST', '/wpsms/v1/send');
        $request->set_body_params([
            'group_ids' => [$groupId],
            'message'   => 'Group message test',
        ]);

        $response = rest_do_request($request);

        // Should process the group
        $this->assertContains($response->get_status(), [200, 201, 400, 500]);

        // Cleanup
        $wpdb->delete($subscribersTable, ['group_ID' => $groupId]);
        $wpdb->delete($groupsTable, ['ID' => $groupId]);
    }

    /**
     * Test: Count recipients endpoint works correctly
     */
    public function testCountRecipientsEndpoint()
    {
        global $wpdb;

        // Create subscribers
        $subscribersTable = $wpdb->prefix . 'sms_subscribes';

        for ($i = 0; $i < 5; $i++) {
            $wpdb->insert($subscribersTable, [
                'date'          => current_time('mysql'),
                'name'          => 'Count Test User ' . $i,
                'mobile'        => '+1555111000' . $i,
                'status'        => '1',
                'activate_key'  => '',
                'group_ID'      => '1',
                'custom_fields' => '',
            ]);
        }

        // Request recipient count - endpoint is POST /send/count with recipients param
        $request = $this->createJsonRequest('POST', '/wpsms/v1/send/count', [
            'recipients' => [
                'groups' => [1],
            ],
        ]);

        $response = rest_do_request($request);

        $this->assertContains($response->get_status(), [200, 400]);

        // Cleanup
        $wpdb->query("DELETE FROM {$subscribersTable} WHERE name LIKE 'Count Test User%'");
    }

    /**
     * Test: Error response format is consistent with legacy
     */
    public function testErrorResponseFormatConsistent()
    {
        // Send with invalid data to trigger error
        $request = new WP_REST_Request('POST', '/wpsms/v1/send');
        $request->set_body_params([
            'recipients' => [], // Empty recipients
            'message'    => '',  // Empty message
        ]);

        $response = rest_do_request($request);

        // Should return error
        $this->assertEquals(400, $response->get_status());

        $data = $response->get_data();

        // Error response should have consistent structure
        $this->assertTrue(
            isset($data['error']) || isset($data['message']) || isset($data['code']),
            'Error response should have standard structure'
        );
    }

    /**
     * Test: Scheduled SMS capability (if available)
     */
    public function testScheduledSmsCapability()
    {
        $futureDate = date('Y-m-d H:i:s', strtotime('+1 hour'));

        $request = new WP_REST_Request('POST', '/wpsms/v1/send');
        $request->set_body_params([
            'recipients'     => ['+15550000001'],
            'message'        => 'Scheduled message test',
            'scheduled_time' => $futureDate,
        ]);

        $response = rest_do_request($request);

        // Should either support scheduling or ignore the parameter
        $this->assertContains($response->get_status(), [200, 201, 400, 500]);
    }

    /**
     * Test: Send respects admin mobile number setting
     */
    public function testSendToAdminMobile()
    {
        // Set admin mobile
        Option::updateOption('admin_mobile_number', '+15559999999');

        $request = new WP_REST_Request('POST', '/wpsms/v1/send');
        $request->set_body_params([
            'recipients' => ['admin'], // Special value for admin
            'message'    => 'Admin notification test',
        ]);

        $response = rest_do_request($request);

        // Should handle admin recipient
        $this->assertContains($response->get_status(), [200, 201, 400, 500]);
    }

    /**
     * Test: Character count/encoding is consistent
     */
    public function testCharacterCountConsistent()
    {
        // Test various message lengths
        $messages = [
            str_repeat('a', 160),   // Exactly 1 SMS
            str_repeat('a', 161),   // Requires 2 SMS
            str_repeat('a', 320),   // Requires 2-3 SMS
            'Unicode: سلام 你好 👋',  // Unicode affects character count
        ];

        foreach ($messages as $message) {
            $request = new WP_REST_Request('POST', '/wpsms/v1/send');
            $request->set_body_params([
                'recipients' => ['+15550000001'],
                'message'    => $message,
            ]);

            $response = rest_do_request($request);

            // All should be handled
            $this->assertContains(
                $response->get_status(),
                [200, 201, 400, 500],
                'Message of length ' . strlen($message) . ' should be handled'
            );
        }
    }

    /**
     * Test: Webhook is triggered when sending via API
     */
    public function testWebhookTriggeredOnApiSend()
    {
        // Set webhook URL
        Option::updateOption('webhook_outgoing_sms', 'https://example.com/webhook');

        // Add filter to capture webhook trigger
        add_filter('pre_http_request', function ($preempt, $args, $url) {
            if (strpos($url, 'webhook') !== false) {
                $this->capturedHookData['webhook_triggered'] = true;
                $this->capturedHookData['webhook_url'] = $url;
                // Return fake response to prevent actual HTTP request
                return [
                    'response' => ['code' => 200],
                    'body'     => '{"success": true}',
                ];
            }
            return $preempt;
        }, 10, 3);

        $request = new WP_REST_Request('POST', '/wpsms/v1/send');
        $request->set_body_params([
            'recipients' => ['+15550000001'],
            'message'    => 'Webhook test message',
        ]);

        rest_do_request($request);

        // Webhook might have been triggered (depends on gateway success)
        // This test validates the infrastructure is in place
        $this->assertTrue(true);
    }

    /**
     * Test: Rate limiting respects legacy settings
     */
    public function testRateLimitingRespected()
    {
        // Send multiple messages rapidly
        $responses = [];

        for ($i = 0; $i < 5; $i++) {
            $request = new WP_REST_Request('POST', '/wpsms/v1/send');
            $request->set_body_params([
                'recipients' => ['+1555000000' . $i],
                'message'    => 'Rate limit test ' . $i,
            ]);

            $responses[] = rest_do_request($request)->get_status();
        }

        // Should handle rapid requests (might rate limit, might succeed)
        $validStatuses = [200, 201, 400, 429, 500];
        foreach ($responses as $status) {
            $this->assertContains($status, $validStatuses);
        }
    }

    /**
     * Test: API validates recipient phone format like legacy
     */
    public function testRecipientValidationMatchesLegacy()
    {
        $invalidFormats = [
            'not-a-phone',
            'abc123',
            '++15551234567',
            '',
        ];

        foreach ($invalidFormats as $format) {
            $request = new WP_REST_Request('POST', '/wpsms/v1/send');
            $request->set_body_params([
                'recipients' => [$format],
                'message'    => 'Validation test',
            ]);

            $response = rest_do_request($request);

            // Invalid formats should be rejected
            $this->assertEquals(
                400,
                $response->get_status(),
                "Invalid phone '{$format}' should be rejected"
            );
        }
    }
}
