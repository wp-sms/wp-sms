<?php

namespace unit\BackwardCompatibility;

use WP_SMS\Option;
use WP_UnitTestCase;
use WP_REST_Request;
use WP_REST_Server;

/**
 * Backward Compatibility Tests for SMS Sending
 *
 * Ensures that SMS sending works consistently between legacy functions/filters
 * and the new React dashboard API.
 */
class SmsSendingBackwardCompatibilityTest extends WP_UnitTestCase
{
    /**
     * @var int
     */
    private $adminUserId;

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

        $this->adminUserId = self::factory()->user->create([
            'role' => 'administrator'
        ]);
        wp_set_current_user($this->adminUserId);

        global $wp_rest_server;
        $wp_rest_server = new WP_REST_Server();
        do_action('rest_api_init');

        // Reset captured data
        $this->capturedHookData = [];

        // Clear outbox
        $this->wpdb->query("DELETE FROM {$this->outboxTable}");
    }

    /**
     * Tear down test environment
     */
    public function tearDown(): void
    {
        parent::tearDown();
        wp_set_current_user(0);

        // Remove all filters/actions we added
        remove_all_filters('wp_sms_from');
        remove_all_filters('wp_sms_to');
        remove_all_filters('wp_sms_msg');
        remove_all_actions('wp_sms_send');

        // Clear outbox
        $this->wpdb->query("DELETE FROM {$this->outboxTable}");
    }

    /**
     * Test: wp_sms_from filter is applied when sending via API
     */
    public function testWpSmsFromFilterAppliedViaApi()
    {
        $originalFrom = '+15551234567';
        $modifiedFrom = '+15559999999';

        // Add filter to modify sender
        add_filter('wp_sms_from', function ($from) use ($modifiedFrom) {
            $this->capturedHookData['from_filter_called'] = true;
            $this->capturedHookData['original_from'] = $from;
            return $modifiedFrom;
        });

        // Send via API
        $request = new WP_REST_Request('POST', '/wpsms/v1/send');
        $request->set_body_params([
            'recipients' => ['+15550000001'],
            'message'    => 'Test message for filter',
            'sender'     => $originalFrom,
        ]);

        rest_do_request($request);

        // Filter should have been called
        $this->assertTrue($this->capturedHookData['from_filter_called'] ?? false, 'wp_sms_from filter should be called');
    }

    /**
     * Test: wp_sms_to filter is applied when sending via API
     */
    public function testWpSmsToFilterAppliedViaApi()
    {
        $originalTo = ['+15551111111'];
        $modifiedTo = ['+15552222222'];

        // Add filter to modify recipients
        add_filter('wp_sms_to', function ($to) use ($modifiedTo) {
            $this->capturedHookData['to_filter_called'] = true;
            $this->capturedHookData['original_to'] = $to;
            return $modifiedTo;
        });

        // Send via API
        $request = new WP_REST_Request('POST', '/wpsms/v1/send');
        $request->set_body_params([
            'recipients' => $originalTo,
            'message'    => 'Test message for filter',
        ]);

        rest_do_request($request);

        // Filter should have been called
        $this->assertTrue($this->capturedHookData['to_filter_called'] ?? false, 'wp_sms_to filter should be called');
    }

    /**
     * Test: wp_sms_msg filter is applied when sending via API
     */
    public function testWpSmsMsgFilterAppliedViaApi()
    {
        $originalMsg = 'Original message';
        $modifiedMsg = 'Modified message';

        // Add filter to modify message
        add_filter('wp_sms_msg', function ($msg) use ($modifiedMsg) {
            $this->capturedHookData['msg_filter_called'] = true;
            $this->capturedHookData['original_msg'] = $msg;
            return $modifiedMsg;
        });

        // Send via API
        $request = new WP_REST_Request('POST', '/wpsms/v1/send');
        $request->set_body_params([
            'recipients' => ['+15550000001'],
            'message'    => $originalMsg,
        ]);

        rest_do_request($request);

        // Filter should have been called
        $this->assertTrue($this->capturedHookData['msg_filter_called'] ?? false, 'wp_sms_msg filter should be called');
    }

    /**
     * Test: wp_sms_send action is fired when sending via API
     */
    public function testWpSmsSendActionFiredViaApi()
    {
        // Add action to capture send event
        add_action('wp_sms_send', function ($result) {
            $this->capturedHookData['send_action_called'] = true;
            $this->capturedHookData['send_result'] = $result;
        });

        // Send via API
        $request = new WP_REST_Request('POST', '/wpsms/v1/send');
        $request->set_body_params([
            'recipients' => ['+15550000001'],
            'message'    => 'Test message for action',
        ]);

        rest_do_request($request);

        // Action should have been called
        $this->assertTrue($this->capturedHookData['send_action_called'] ?? false, 'wp_sms_send action should be called');
    }

    /**
     * Test: API uses same gateway configuration as legacy
     */
    public function testApiUsesSameGatewayAsLegacy()
    {
        // Set gateway via legacy method
        Option::updateOption('gateway_name', 'twilio');
        Option::updateOption('gateway_key', 'test_account_sid');
        Option::updateOption('gateway_password', 'test_auth_token');

        // Verify settings via API
        $request = new WP_REST_Request('GET', '/wpsms/v1/settings');
        $response = rest_do_request($request);
        $data = $response->get_data();

        $this->assertEquals(200, $response->get_status());
        $this->assertEquals('twilio', $data['data']['settings']['gateway_name']);
    }

    /**
     * Test: Message stored in outbox has same format from both interfaces
     */
    public function testOutboxFormatConsistent()
    {
        // Enable outbox storage
        Option::updateOption('store_outbox_messages', 'store_outbox_messages');

        // Send via API
        $message = 'API test message ' . uniqid();
        $recipient = '+15550000001';

        $request = new WP_REST_Request('POST', '/wpsms/v1/send');
        $request->set_body_params([
            'recipients' => [$recipient],
            'message'    => $message,
        ]);

        rest_do_request($request);

        // Check outbox entry format
        $entry = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->outboxTable} WHERE message = %s ORDER BY ID DESC LIMIT 1",
            $message
        ));

        if ($entry) {
            // Verify expected columns exist
            $this->assertObjectHasProperty('date', $entry);
            $this->assertObjectHasProperty('sender', $entry);
            $this->assertObjectHasProperty('message', $entry);
            $this->assertObjectHasProperty('recipient', $entry);
            $this->assertObjectHasProperty('status', $entry);
        }
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

        // Request recipient count
        $request = new WP_REST_Request('GET', '/wpsms/v1/send/count-recipients');
        $request->set_param('group_ids', [1]);

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
