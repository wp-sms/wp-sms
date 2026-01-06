<?php

namespace unit;

use WP_SMS\Api\V1\OutboxApi;
use WP_UnitTestCase;
use WP_REST_Request;
use WP_REST_Server;

/**
 * Tests for Outbox REST API
 */
class OutboxApiTest extends WP_UnitTestCase
{
    /**
     * @var int
     */
    private $adminUserId;

    /**
     * Set up test environment
     */
    public function setUp(): void
    {
        parent::setUp();

        // Create admin user
        $this->adminUserId = self::factory()->user->create([
            'role' => 'administrator'
        ]);
        wp_set_current_user($this->adminUserId);

        // Grant outbox permission
        $user = get_user_by('id', $this->adminUserId);
        $user->add_cap('wpsms_outbox');
        $user->add_cap('wpsms_sendsms');

        // Initialize REST server
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
    }

    /**
     * Test get outbox returns paginated list
     */
    public function testGetOutboxReturnsPaginatedList()
    {
        $request = new WP_REST_Request('GET', '/wpsms/v1/outbox');

        $response = rest_do_request($request);
        $data = $response->get_data();

        $this->assertEquals(200, $response->get_status());
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('items', $data['data']);
        $this->assertArrayHasKey('pagination', $data['data']);
        $this->assertArrayHasKey('stats', $data['data']);
    }

    /**
     * Test get outbox requires authentication
     */
    public function testGetOutboxRequiresAuthentication()
    {
        wp_set_current_user(0);

        $request = new WP_REST_Request('GET', '/wpsms/v1/outbox');

        $response = rest_do_request($request);

        $this->assertContains($response->get_status(), [401, 403]);
    }

    /**
     * Test filter outbox by status=success returns only successful messages
     */
    public function testFilterOutboxByStatusSuccessReturnsOnlySuccess()
    {
        // Create success messages
        $this->createTestOutboxMessage('Test message 1', '+1234567890', 'success');
        $this->createTestOutboxMessage('Test message 2', '+1234567891', 'success');

        // Create failed messages (stored as 'error' in DB)
        $this->createTestOutboxMessage('Failed message', '+1234567892', 'error');

        $request = new WP_REST_Request('GET', '/wpsms/v1/outbox');
        $request->set_param('status', 'success');

        $response = rest_do_request($request);
        $data = $response->get_data();

        $this->assertEquals(200, $response->get_status());

        // Verify all returned items have success status
        foreach ($data['data']['items'] as $item) {
            $this->assertEquals('success', $item['status'],
                "Message {$item['id']} should have status 'success' but has '{$item['status']}'");
        }
    }

    /**
     * Test filter outbox by status=failed returns only failed messages
     */
    public function testFilterOutboxByStatusFailedReturnsOnlyFailed()
    {
        // Create success messages
        $this->createTestOutboxMessage('Success message', '+1234567890', 'success');

        // Create failed messages (stored as 'error' in DB, returned as 'failed')
        $this->createTestOutboxMessage('Failed message 1', '+1234567891', 'error');
        $this->createTestOutboxMessage('Failed message 2', '+1234567892', 'error');

        $request = new WP_REST_Request('GET', '/wpsms/v1/outbox');
        $request->set_param('status', 'failed');

        $response = rest_do_request($request);
        $data = $response->get_data();

        $this->assertEquals(200, $response->get_status());

        // Verify all returned items have failed status
        foreach ($data['data']['items'] as $item) {
            $this->assertEquals('failed', $item['status'],
                "Message {$item['id']} should have status 'failed' but has '{$item['status']}'");
        }
    }

    /**
     * Test search outbox returns matching results
     */
    public function testSearchOutboxReturnsMatchingResults()
    {
        // Create messages with specific content
        $this->createTestOutboxMessage('Welcome to our service', '+1234567890', 'success');
        $this->createTestOutboxMessage('Thank you for signing up', '+1234567891', 'success');
        $this->createTestOutboxMessage('Your order has shipped', '+1234567892', 'success');

        $request = new WP_REST_Request('GET', '/wpsms/v1/outbox');
        $request->set_param('search', 'Welcome');

        $response = rest_do_request($request);
        $data = $response->get_data();

        $this->assertEquals(200, $response->get_status());

        // Verify all returned items contain the search term
        foreach ($data['data']['items'] as $item) {
            $messageContains = stripos($item['message'], 'Welcome') !== false;
            $recipientContains = stripos($item['recipient'], 'Welcome') !== false;
            $senderContains = stripos($item['sender'] ?? '', 'Welcome') !== false;

            $this->assertTrue(
                $messageContains || $recipientContains || $senderContains,
                "Message {$item['id']} should contain 'Welcome' in message, recipient or sender"
            );
        }
    }

    /**
     * Test filter outbox by date_from returns only messages from that date
     */
    public function testFilterOutboxByDateFromReturnsCorrectResults()
    {
        // Create old message
        $this->createTestOutboxMessageWithDate('Old message', '+1234567890', 'success', '2024-01-01 10:00:00');

        // Create recent message
        $this->createTestOutboxMessageWithDate('Recent message', '+1234567891', 'success', '2025-06-15 10:00:00');

        $request = new WP_REST_Request('GET', '/wpsms/v1/outbox');
        $request->set_param('date_from', '2025-01-01');

        $response = rest_do_request($request);
        $data = $response->get_data();

        $this->assertEquals(200, $response->get_status());

        // Verify all returned items are from 2025-01-01 or later
        foreach ($data['data']['items'] as $item) {
            $itemDate = date('Y-m-d', strtotime($item['date']));
            $this->assertGreaterThanOrEqual('2025-01-01', $itemDate,
                "Message {$item['id']} date {$itemDate} should be >= 2025-01-01");
        }
    }

    /**
     * Test filter outbox by date_to returns only messages up to that date
     */
    public function testFilterOutboxByDateToReturnsCorrectResults()
    {
        // Create old message
        $this->createTestOutboxMessageWithDate('Old message', '+1234567890', 'success', '2024-01-15 10:00:00');

        // Create recent message
        $this->createTestOutboxMessageWithDate('Recent message', '+1234567891', 'success', '2025-06-15 10:00:00');

        $request = new WP_REST_Request('GET', '/wpsms/v1/outbox');
        $request->set_param('date_to', '2024-12-31');

        $response = rest_do_request($request);
        $data = $response->get_data();

        $this->assertEquals(200, $response->get_status());

        // Verify all returned items are from 2024-12-31 or earlier
        foreach ($data['data']['items'] as $item) {
            $itemDate = date('Y-m-d', strtotime($item['date']));
            $this->assertLessThanOrEqual('2024-12-31', $itemDate,
                "Message {$item['id']} date {$itemDate} should be <= 2024-12-31");
        }
    }

    /**
     * Test filter outbox by date range returns only messages within range
     */
    public function testFilterOutboxByDateRangeReturnsCorrectResults()
    {
        // Create messages at different dates
        $this->createTestOutboxMessageWithDate('Before range', '+1234567890', 'success', '2024-06-01 10:00:00');
        $this->createTestOutboxMessageWithDate('In range', '+1234567891', 'success', '2025-01-15 10:00:00');
        $this->createTestOutboxMessageWithDate('After range', '+1234567892', 'success', '2025-06-01 10:00:00');

        $request = new WP_REST_Request('GET', '/wpsms/v1/outbox');
        $request->set_param('date_from', '2025-01-01');
        $request->set_param('date_to', '2025-02-28');

        $response = rest_do_request($request);
        $data = $response->get_data();

        $this->assertEquals(200, $response->get_status());

        // Verify all returned items are within the date range
        foreach ($data['data']['items'] as $item) {
            $itemDate = date('Y-m-d', strtotime($item['date']));
            $this->assertGreaterThanOrEqual('2025-01-01', $itemDate,
                "Message {$item['id']} date {$itemDate} should be >= 2025-01-01");
            $this->assertLessThanOrEqual('2025-02-28', $itemDate,
                "Message {$item['id']} date {$itemDate} should be <= 2025-02-28");
        }
    }

    /**
     * Test combined filters work together
     */
    public function testCombinedFiltersReturnCorrectResults()
    {
        // Create various combinations
        $this->createTestOutboxMessageWithDate('Success recent', '+1234567890', 'success', '2025-01-15 10:00:00');
        $this->createTestOutboxMessageWithDate('Failed recent', '+1234567891', 'error', '2025-01-15 10:00:00');
        $this->createTestOutboxMessageWithDate('Success old', '+1234567892', 'success', '2024-01-15 10:00:00');

        $request = new WP_REST_Request('GET', '/wpsms/v1/outbox');
        $request->set_param('status', 'success');
        $request->set_param('date_from', '2025-01-01');

        $response = rest_do_request($request);
        $data = $response->get_data();

        $this->assertEquals(200, $response->get_status());

        // Verify all returned items match BOTH filters
        foreach ($data['data']['items'] as $item) {
            $this->assertEquals('success', $item['status'],
                "Message should have status 'success'");
            $itemDate = date('Y-m-d', strtotime($item['date']));
            $this->assertGreaterThanOrEqual('2025-01-01', $itemDate,
                "Message date should be >= 2025-01-01");
        }
    }

    /**
     * Test search with no matches returns empty results
     */
    public function testSearchWithNoMatchesReturnsEmpty()
    {
        $request = new WP_REST_Request('GET', '/wpsms/v1/outbox');
        $request->set_param('search', 'xyznonexistent99999');

        $response = rest_do_request($request);
        $data = $response->get_data();

        $this->assertEquals(200, $response->get_status());
        $this->assertEmpty($data['data']['items']);
        $this->assertEquals(0, $data['data']['pagination']['total']);
    }

    /**
     * Test pagination parameters
     */
    public function testPaginationParameters()
    {
        $request = new WP_REST_Request('GET', '/wpsms/v1/outbox');
        $request->set_param('page', 1);
        $request->set_param('per_page', 10);

        $response = rest_do_request($request);
        $data = $response->get_data();

        $this->assertEquals(200, $response->get_status());
        $this->assertArrayHasKey('pagination', $data['data']);
        $this->assertEquals(1, $data['data']['pagination']['current_page']);
        $this->assertEquals(10, $data['data']['pagination']['per_page']);
    }

    /**
     * Test orderby and order parameters
     */
    public function testOrderByParameters()
    {
        // Create messages with different dates
        $this->createTestOutboxMessageWithDate('First', '+1234567890', 'success', '2025-01-01 10:00:00');
        $this->createTestOutboxMessageWithDate('Second', '+1234567891', 'success', '2025-01-02 10:00:00');

        $request = new WP_REST_Request('GET', '/wpsms/v1/outbox');
        $request->set_param('orderby', 'date');
        $request->set_param('order', 'asc');

        $response = rest_do_request($request);
        $data = $response->get_data();

        $this->assertEquals(200, $response->get_status());

        // Verify items are in ascending date order
        $previousDate = null;
        foreach ($data['data']['items'] as $item) {
            if ($previousDate !== null) {
                $this->assertGreaterThanOrEqual($previousDate, $item['date'],
                    "Items should be in ascending date order");
            }
            $previousDate = $item['date'];
        }
    }

    /**
     * Test message not found returns 404
     */
    public function testMessageNotFoundReturns404()
    {
        $request = new WP_REST_Request('GET', '/wpsms/v1/outbox/999999');

        $response = rest_do_request($request);

        $this->assertEquals(404, $response->get_status());
    }

    /**
     * Test delete message
     */
    public function testDeleteMessage()
    {
        $messageId = $this->createTestOutboxMessage('To delete', '+1234567890', 'success');

        if (!$messageId) {
            $this->markTestSkipped('Could not create test message');
            return;
        }

        $request = new WP_REST_Request('DELETE', '/wpsms/v1/outbox/' . $messageId);

        $response = rest_do_request($request);

        $this->assertEquals(200, $response->get_status());
    }

    /**
     * Test bulk delete action
     */
    public function testBulkDeleteAction()
    {
        $request = new WP_REST_Request('POST', '/wpsms/v1/outbox/bulk');
        $request->set_body_params([
            'action' => 'delete',
            'ids'    => [1, 2, 3], // Test with non-existent IDs
        ]);

        $response = rest_do_request($request);

        // Should complete without error even if IDs don't exist
        $this->assertContains($response->get_status(), [200, 400]);
    }

    /**
     * Test export endpoint
     */
    public function testExportEndpoint()
    {
        $request = new WP_REST_Request('GET', '/wpsms/v1/outbox/export');

        $response = rest_do_request($request);
        $data = $response->get_data();

        $this->assertEquals(200, $response->get_status());
        $this->assertArrayHasKey('data', $data);
    }

    /**
     * Test export with status filter
     */
    public function testExportWithStatusFilter()
    {
        // Create messages
        $this->createTestOutboxMessage('Success message', '+1234567890', 'success');
        $this->createTestOutboxMessage('Failed message', '+1234567891', 'error');

        $request = new WP_REST_Request('GET', '/wpsms/v1/outbox/export');
        $request->set_param('status', 'success');

        $response = rest_do_request($request);
        $data = $response->get_data();

        $this->assertEquals(200, $response->get_status());
        $this->assertArrayHasKey('data', $data);
    }

    /**
     * Helper to create a test outbox message
     *
     * @param string $message Message content
     * @param string $recipient Recipient phone number
     * @param string $status Status ('success' or 'error')
     * @return int|false Message ID or false on failure
     */
    private function createTestOutboxMessage($message, $recipient, $status)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'sms_send';

        $result = $wpdb->insert($table, [
            'date'      => current_time('mysql'),
            'sender'    => 'TestSender',
            'recipient' => $recipient,
            'message'   => $message,
            'status'    => $status,
            'response'  => $status === 'success' ? 'OK' : 'Error occurred',
        ]);

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Helper to create a test outbox message with specific date
     *
     * @param string $message Message content
     * @param string $recipient Recipient phone number
     * @param string $status Status ('success' or 'error')
     * @param string $date Date in MySQL format
     * @return int|false Message ID or false on failure
     */
    private function createTestOutboxMessageWithDate($message, $recipient, $status, $date)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'sms_send';

        $result = $wpdb->insert($table, [
            'date'      => $date,
            'sender'    => 'TestSender',
            'recipient' => $recipient,
            'message'   => $message,
            'status'    => $status,
            'response'  => $status === 'success' ? 'OK' : 'Error occurred',
        ]);

        return $result ? $wpdb->insert_id : false;
    }
}
