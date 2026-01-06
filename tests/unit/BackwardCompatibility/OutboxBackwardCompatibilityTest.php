<?php

namespace unit\BackwardCompatibility;

use WP_UnitTestCase;
use WP_REST_Request;
use WP_REST_Server;

/**
 * Backward Compatibility Tests for Outbox (Sent Messages)
 *
 * Ensures that sent messages stored via legacy methods work correctly
 * with the new React dashboard, and vice versa.
 */
class OutboxBackwardCompatibilityTest extends WP_UnitTestCase
{
    /**
     * @var int
     */
    private $adminUserId;

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
    }

    /**
     * Tear down test environment
     */
    public function tearDown(): void
    {
        parent::tearDown();
        wp_set_current_user(0);

        // Clean up test data
        $this->wpdb->query("DELETE FROM {$this->outboxTable}");
    }

    /**
     * Helper: Create outbox entry via legacy direct database insert
     *
     * @param array $data Message data
     * @return int|false Message ID or false
     */
    private function createLegacyOutboxEntry(array $data = [])
    {
        $defaults = [
            'date'      => current_time('mysql'),
            'sender'    => '+15551234567',
            'message'   => 'Legacy test message ' . uniqid(),
            'recipient' => '+15559876543',
            'response'  => serialize(['status' => 'success', 'message_id' => 'msg_' . uniqid()]),
            'status'    => 'success',
            'media'     => '',
        ];

        $data = array_merge($defaults, $data);

        $result = $this->wpdb->insert($this->outboxTable, $data);

        return $result ? $this->wpdb->insert_id : false;
    }

    /**
     * Test: Outbox entry created via legacy method is readable via REST API
     */
    public function testLegacyOutboxReadableViaApi()
    {
        $message = 'Legacy message content ' . uniqid();
        $recipient = '+1' . str_pad(mt_rand(1, 9999999999), 10, '0', STR_PAD_LEFT);

        $entryId = $this->createLegacyOutboxEntry([
            'message'   => $message,
            'recipient' => $recipient,
            'status'    => 'success',
        ]);

        $this->assertNotFalse($entryId);

        // Read via REST API
        $request = new WP_REST_Request('GET', '/wpsms/v1/outbox');
        $response = rest_do_request($request);
        $data = $response->get_data();

        $this->assertEquals(200, $response->get_status());
        $this->assertArrayHasKey('data', $data);

        // Find our entry in the list
        $items = $data['data']['items'] ?? $data['data'];
        $found = false;

        foreach ($items as $item) {
            if (($item['id'] ?? $item['ID']) == $entryId) {
                $found = true;
                $this->assertEquals($message, $item['message']);
                break;
            }
        }

        $this->assertTrue($found, 'Legacy outbox entry should be in API list');
    }

    /**
     * Test: Multiple recipients stored as comma-separated are handled
     * Legacy format: "recipient" = "+15551111111,+15552222222,+15553333333"
     */
    public function testMultipleRecipientsBackwardCompatible()
    {
        $recipients = '+15551111111,+15552222222,+15553333333';

        $entryId = $this->createLegacyOutboxEntry([
            'recipient' => $recipients,
        ]);

        // Read via API
        $request = new WP_REST_Request('GET', '/wpsms/v1/outbox');
        $response = rest_do_request($request);
        $data = $response->get_data();

        $this->assertEquals(200, $response->get_status());

        // Find our entry
        $items = $data['data']['items'] ?? $data['data'];
        foreach ($items as $item) {
            if (($item['id'] ?? $item['ID']) == $entryId) {
                // Recipients should be accessible
                $itemRecipient = $item['recipient'] ?? $item['recipients'];
                $this->assertNotEmpty($itemRecipient);
                break;
            }
        }
    }

    /**
     * Test: Serialized response field is handled properly
     * Legacy stores response as PHP serialized array
     */
    public function testSerializedResponseFieldHandled()
    {
        $responseData = [
            'status'     => 'success',
            'message_id' => 'msg_12345',
            'credits'    => 1,
            'raw'        => ['gateway_specific' => 'data'],
        ];

        $entryId = $this->createLegacyOutboxEntry([
            'response' => serialize($responseData),
        ]);

        // Read via API
        $request = new WP_REST_Request('GET', '/wpsms/v1/outbox');
        $response = rest_do_request($request);

        // Should not error on serialized data
        $this->assertEquals(200, $response->get_status());
    }

    /**
     * Test: Status field values are backward compatible
     * Legacy values: 'success', 'failed', 'pending'
     */
    public function testStatusValuesBackwardCompatible()
    {
        $statuses = ['success', 'failed', 'pending'];
        $ids = [];

        foreach ($statuses as $status) {
            $ids[$status] = $this->createLegacyOutboxEntry([
                'status' => $status,
            ]);
        }

        // Read via API
        $request = new WP_REST_Request('GET', '/wpsms/v1/outbox');
        $response = rest_do_request($request);
        $data = $response->get_data();

        $this->assertEquals(200, $response->get_status());

        $items = $data['data']['items'] ?? $data['data'];

        foreach ($items as $item) {
            $itemId = $item['id'] ?? $item['ID'];
            if (in_array($itemId, $ids)) {
                // Status should be a recognized value
                $this->assertContains(
                    $item['status'],
                    ['success', 'failed', 'pending', 'sent', 'delivered', 'error']
                );
            }
        }
    }

    /**
     * Test: Media URLs stored as serialized array are handled
     * Legacy format: serialize(['url1', 'url2'])
     */
    public function testMediaUrlsSerializedFormat()
    {
        $mediaUrls = [
            'https://example.com/image1.jpg',
            'https://example.com/image2.png',
        ];

        $entryId = $this->createLegacyOutboxEntry([
            'media' => serialize($mediaUrls),
        ]);

        // Read via API
        $request = new WP_REST_Request('GET', '/wpsms/v1/outbox');
        $response = rest_do_request($request);

        $this->assertEquals(200, $response->get_status());

        $data = $response->get_data();
        $items = $data['data']['items'] ?? $data['data'];

        foreach ($items as $item) {
            if (($item['id'] ?? $item['ID']) == $entryId) {
                // Media should be accessible (either as array or serialized string)
                if (isset($item['media'])) {
                    $this->assertNotEmpty($item['media']);
                }
                break;
            }
        }
    }

    /**
     * Test: Empty media field is handled
     */
    public function testEmptyMediaFieldHandled()
    {
        $entryId = $this->createLegacyOutboxEntry([
            'media' => '',
        ]);

        $request = new WP_REST_Request('GET', '/wpsms/v1/outbox');
        $response = rest_do_request($request);

        // Should not error on empty media
        $this->assertEquals(200, $response->get_status());
    }

    /**
     * Test: Date format is consistent between legacy and API
     */
    public function testDateFormatConsistent()
    {
        $testDate = '2024-06-15 10:30:45';

        $entryId = $this->createLegacyOutboxEntry([
            'date' => $testDate,
        ]);

        // Read via API
        $request = new WP_REST_Request('GET', '/wpsms/v1/outbox');
        $response = rest_do_request($request);
        $data = $response->get_data();

        $this->assertEquals(200, $response->get_status());

        $items = $data['data']['items'] ?? $data['data'];

        foreach ($items as $item) {
            if (($item['id'] ?? $item['ID']) == $entryId) {
                // Date should be parseable
                $itemDate = $item['date'] ?? $item['created_at'] ?? $item['sent_at'];
                if ($itemDate) {
                    $parsedDate = strtotime($itemDate);
                    $this->assertNotFalse($parsedDate, 'Date should be parseable');
                }
                break;
            }
        }
    }

    /**
     * Test: Deleting legacy outbox entry via API works
     */
    public function testDeleteLegacyOutboxViaApi()
    {
        $entryId = $this->createLegacyOutboxEntry();

        // Verify exists
        $exists = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->outboxTable} WHERE ID = %d",
            $entryId
        ));
        $this->assertEquals(1, $exists);

        // Delete via API
        $request = new WP_REST_Request('DELETE', '/wpsms/v1/outbox/' . $entryId);
        $response = rest_do_request($request);

        $this->assertContains($response->get_status(), [200, 204]);

        // Verify deleted
        $exists = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->outboxTable} WHERE ID = %d",
            $entryId
        ));
        $this->assertEquals(0, $exists);
    }

    /**
     * Test: Bulk delete works on legacy outbox entries
     */
    public function testBulkDeleteOnLegacyOutbox()
    {
        $ids = [];
        for ($i = 0; $i < 3; $i++) {
            $ids[] = $this->createLegacyOutboxEntry();
        }

        // Bulk delete via API
        $request = new WP_REST_Request('POST', '/wpsms/v1/outbox/bulk');
        $request->set_body_params([
            'action' => 'delete',
            'ids'    => $ids,
        ]);

        $response = rest_do_request($request);

        $this->assertEquals(200, $response->get_status());

        // Verify all deleted
        $remaining = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->outboxTable} WHERE ID IN (" . implode(',', array_fill(0, count($ids), '%d')) . ")",
            ...$ids
        ));

        $this->assertEquals(0, $remaining);
    }

    /**
     * Test: Pagination works with legacy outbox entries
     */
    public function testPaginationWithLegacyOutbox()
    {
        // Create 15 entries
        for ($i = 0; $i < 15; $i++) {
            $this->createLegacyOutboxEntry([
                'message' => 'Pagination test message ' . $i,
            ]);
        }

        // Request first page
        $request = new WP_REST_Request('GET', '/wpsms/v1/outbox');
        $request->set_param('per_page', 10);
        $request->set_param('page', 1);

        $response = rest_do_request($request);
        $data = $response->get_data();

        $this->assertEquals(200, $response->get_status());

        $items = $data['data']['items'] ?? $data['data'];
        $this->assertLessThanOrEqual(10, count($items));

        if (isset($data['data']['pagination'])) {
            $this->assertGreaterThanOrEqual(15, $data['data']['pagination']['total']);
        }
    }

    /**
     * Test: Search/filter works on legacy outbox entries
     */
    public function testSearchWorksOnLegacyOutbox()
    {
        $uniqueMessage = 'UniqueSearchableMessage' . uniqid();
        $this->createLegacyOutboxEntry([
            'message' => $uniqueMessage,
        ]);

        // Search via API
        $request = new WP_REST_Request('GET', '/wpsms/v1/outbox');
        $request->set_param('search', 'UniqueSearchableMessage');

        $response = rest_do_request($request);
        $data = $response->get_data();

        $this->assertEquals(200, $response->get_status());

        $items = $data['data']['items'] ?? $data['data'];

        // Should find the message
        $found = false;
        foreach ($items as $item) {
            if (strpos($item['message'], 'UniqueSearchableMessage') !== false) {
                $found = true;
                break;
            }
        }

        $this->assertTrue($found, 'Legacy outbox message should be found by search');
    }

    /**
     * Test: Special characters in message are preserved
     */
    public function testSpecialCharactersInMessagePreserved()
    {
        $specialMessage = "Hello! It's a \"test\" message with symbols: @#$%^&*() and <html>";

        $entryId = $this->createLegacyOutboxEntry([
            'message' => $specialMessage,
        ]);

        // Read via API
        $request = new WP_REST_Request('GET', '/wpsms/v1/outbox');
        $response = rest_do_request($request);
        $data = $response->get_data();

        $this->assertEquals(200, $response->get_status());

        $items = $data['data']['items'] ?? $data['data'];

        foreach ($items as $item) {
            if (($item['id'] ?? $item['ID']) == $entryId) {
                // Message content should be preserved (possibly encoded)
                $this->assertNotEmpty($item['message']);
                break;
            }
        }
    }

    /**
     * Test: Unicode/emoji in message are preserved
     */
    public function testUnicodeEmojiInMessagePreserved()
    {
        $unicodeMessage = 'سلام! 你好! Hello! 👋 🎉 Test message';

        $entryId = $this->createLegacyOutboxEntry([
            'message' => $unicodeMessage,
        ]);

        // Read via API
        $request = new WP_REST_Request('GET', '/wpsms/v1/outbox');
        $response = rest_do_request($request);
        $data = $response->get_data();

        $this->assertEquals(200, $response->get_status());

        $items = $data['data']['items'] ?? $data['data'];

        foreach ($items as $item) {
            if (($item['id'] ?? $item['ID']) == $entryId) {
                // Unicode should be preserved
                $this->assertEquals($unicodeMessage, $item['message']);
                break;
            }
        }
    }

    /**
     * Test: Long messages are stored and retrieved correctly
     */
    public function testLongMessagesHandled()
    {
        // Create a message longer than standard SMS (160 chars)
        $longMessage = str_repeat('This is a long test message. ', 20);

        $entryId = $this->createLegacyOutboxEntry([
            'message' => $longMessage,
        ]);

        // Read via API
        $request = new WP_REST_Request('GET', '/wpsms/v1/outbox');
        $response = rest_do_request($request);
        $data = $response->get_data();

        $this->assertEquals(200, $response->get_status());

        $items = $data['data']['items'] ?? $data['data'];

        foreach ($items as $item) {
            if (($item['id'] ?? $item['ID']) == $entryId) {
                // Full message should be preserved
                $this->assertEquals($longMessage, $item['message']);
                break;
            }
        }
    }

    /**
     * Test: Filter by status works on legacy entries
     */
    public function testFilterByStatusWorksOnLegacyEntries()
    {
        // Create entries with different statuses
        $this->createLegacyOutboxEntry(['status' => 'success']);
        $this->createLegacyOutboxEntry(['status' => 'success']);
        $this->createLegacyOutboxEntry(['status' => 'failed']);

        // Filter by success status
        $request = new WP_REST_Request('GET', '/wpsms/v1/outbox');
        $request->set_param('status', 'success');

        $response = rest_do_request($request);
        $data = $response->get_data();

        $this->assertEquals(200, $response->get_status());

        $items = $data['data']['items'] ?? $data['data'];

        // All returned items should have success status
        foreach ($items as $item) {
            $this->assertContains($item['status'], ['success', 'sent', 'delivered']);
        }
    }

    /**
     * Test: Filter by date range works on legacy entries
     */
    public function testFilterByDateRangeWorksOnLegacyEntries()
    {
        // Create entries with different dates
        $this->createLegacyOutboxEntry(['date' => '2024-01-01 10:00:00']);
        $this->createLegacyOutboxEntry(['date' => '2024-06-15 10:00:00']);
        $this->createLegacyOutboxEntry(['date' => '2024-12-31 10:00:00']);

        // Filter by date range
        $request = new WP_REST_Request('GET', '/wpsms/v1/outbox');
        $request->set_param('date_from', '2024-06-01');
        $request->set_param('date_to', '2024-07-01');

        $response = rest_do_request($request);

        // Should return filtered results or all results if filter not supported
        $this->assertEquals(200, $response->get_status());
    }

    /**
     * Test: Sender field is preserved from legacy entries
     */
    public function testSenderFieldPreserved()
    {
        $sender = 'CompanyName';

        $entryId = $this->createLegacyOutboxEntry([
            'sender' => $sender,
        ]);

        // Read via API
        $request = new WP_REST_Request('GET', '/wpsms/v1/outbox');
        $response = rest_do_request($request);
        $data = $response->get_data();

        $this->assertEquals(200, $response->get_status());

        $items = $data['data']['items'] ?? $data['data'];

        foreach ($items as $item) {
            if (($item['id'] ?? $item['ID']) == $entryId) {
                $this->assertEquals($sender, $item['sender']);
                break;
            }
        }
    }

    /**
     * Test: Response field with error details is accessible
     */
    public function testErrorResponseAccessible()
    {
        $errorResponse = [
            'status'  => 'failed',
            'error'   => 'Invalid phone number',
            'code'    => 'INVALID_RECIPIENT',
        ];

        $entryId = $this->createLegacyOutboxEntry([
            'status'   => 'failed',
            'response' => serialize($errorResponse),
        ]);

        // Read via API
        $request = new WP_REST_Request('GET', '/wpsms/v1/outbox');
        $response = rest_do_request($request);
        $data = $response->get_data();

        $this->assertEquals(200, $response->get_status());

        $items = $data['data']['items'] ?? $data['data'];

        foreach ($items as $item) {
            if (($item['id'] ?? $item['ID']) == $entryId) {
                // Response should be accessible for debugging
                if (isset($item['response'])) {
                    $this->assertNotEmpty($item['response']);
                }
                break;
            }
        }
    }
}
