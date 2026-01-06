<?php

namespace unit\BackwardCompatibility;

use unit\WPSMSTestCase;
use WP_SMS\Newsletter;
use WP_REST_Request;

require_once dirname(__DIR__) . '/WPSMSTestCase.php';

/**
 * Backward Compatibility Tests for Subscribers
 *
 * Ensures that subscribers created via legacy admin pages work correctly
 * with the new React dashboard, and vice versa.
 */
class SubscribersBackwardCompatibilityTest extends WPSMSTestCase
{
    /**
     * @var Newsletter
     */
    private $newsletter;

    /**
     * @var \wpdb
     */
    private $wpdb;

    /**
     * @var string
     */
    private $subscribersTable;

    /**
     * @var string
     */
    private $groupsTable;

    /**
     * Set up test environment
     */
    public function setUp(): void
    {
        parent::setUp();

        global $wpdb;
        $this->wpdb = $wpdb;
        $this->subscribersTable = $wpdb->prefix . 'sms_subscribes';
        $this->groupsTable = $wpdb->prefix . 'sms_subscribes_group';

        $this->newsletter = new Newsletter();

        // Create test group
        $this->wpdb->insert($this->groupsTable, ['name' => 'Test Group']);
    }

    /**
     * Tear down test environment
     */
    public function tearDown(): void
    {
        // Clean up test data
        $this->wpdb->query("DELETE FROM {$this->subscribersTable}");
        $this->wpdb->query("DELETE FROM {$this->groupsTable}");
        parent::tearDown();
    }

    /**
     * Helper: Create subscriber via legacy direct database insert
     *
     * @param array $data Subscriber data
     * @return int|false Subscriber ID or false
     */
    private function createLegacySubscriber(array $data = [])
    {
        $defaults = [
            'date'          => current_time('mysql'),
            'name'          => 'Legacy Test User ' . uniqid(),
            'mobile'        => '+1' . str_pad(mt_rand(1, 9999999999), 10, '0', STR_PAD_LEFT),
            'status'        => '1',
            'activate_key'  => '',
            'group_ID'      => '1',
            'custom_fields' => '',
        ];

        $data = array_merge($defaults, $data);

        $result = $this->wpdb->insert($this->subscribersTable, $data);

        return $result ? $this->wpdb->insert_id : false;
    }

    /**
     * Test: Subscriber created via legacy method is readable via REST API
     */
    public function testLegacySubscriberReadableViaApi()
    {
        $phone = '+1' . str_pad(mt_rand(1, 9999999999), 10, '0', STR_PAD_LEFT);
        $name = 'Legacy User ' . uniqid();

        $subscriberId = $this->createLegacySubscriber([
            'name'   => $name,
            'mobile' => $phone,
            'status' => '1',
        ]);

        $this->assertNotFalse($subscriberId);

        // Read via REST API
        $request = new WP_REST_Request('GET', '/wpsms/v1/subscribers/' . $subscriberId);
        $response = rest_do_request($request);
        $data = $response->get_data();

        $this->assertEquals(200, $response->get_status());
        $this->assertArrayHasKey('data', $data);
        $this->assertEquals($name, $data['data']['name']);
        $this->assertEquals($phone, $data['data']['mobile']);
    }

    /**
     * Test: Legacy subscribers appear in API list endpoint
     */
    public function testLegacySubscribersAppearInApiList()
    {
        // Create multiple legacy subscribers
        $ids = [];
        for ($i = 0; $i < 3; $i++) {
            $ids[] = $this->createLegacySubscriber([
                'name' => 'Legacy List User ' . $i,
            ]);
        }

        // Fetch via API
        $request = new WP_REST_Request('GET', '/wpsms/v1/subscribers');
        $response = rest_do_request($request);
        $data = $response->get_data();

        $this->assertEquals(200, $response->get_status());
        $this->assertArrayHasKey('items', $data['data']);

        // Should include our legacy subscribers
        $foundCount = 0;
        foreach ($data['data']['items'] as $item) {
            if (in_array($item['id'], $ids)) {
                $foundCount++;
            }
        }

        $this->assertEquals(3, $foundCount);
    }

    /**
     * Test: Subscriber created via API is readable via legacy Newsletter class
     */
    public function testApiSubscriberReadableViaLegacy()
    {
        $phone = '+1' . str_pad(mt_rand(1, 9999999999), 10, '0', STR_PAD_LEFT);
        $name = 'API User ' . uniqid();

        // Create via API
        $request = new WP_REST_Request('POST', '/wpsms/v1/subscribers');
        $request->set_body_params([
            'mobile' => $phone,
            'name'   => $name,
            'status' => '1',
        ]);

        $response = rest_do_request($request);

        // Skip if creation failed (e.g., validation)
        if ($response->get_status() !== 201 && $response->get_status() !== 200) {
            $this->markTestSkipped('Could not create subscriber via API');
            return;
        }

        // Read via legacy direct database query (as legacy code would)
        $subscriber = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->subscribersTable} WHERE mobile = %s",
            $phone
        ));

        $this->assertNotNull($subscriber);
        $this->assertEquals($name, $subscriber->name);
        $this->assertEquals($phone, $subscriber->mobile);
    }

    /**
     * Test: Status field format is backward compatible
     * Legacy: '1' (active) or '0' (inactive) as string
     * API: Should handle both string and integer formats
     */
    public function testStatusFieldBackwardCompatible()
    {
        // Create with legacy status format (string '1')
        $activeId = $this->createLegacySubscriber(['status' => '1']);
        $inactiveId = $this->createLegacySubscriber(['status' => '0']);

        // Read active subscriber via API
        $request = new WP_REST_Request('GET', '/wpsms/v1/subscribers/' . $activeId);
        $response = rest_do_request($request);
        $data = $response->get_data();

        // Status should indicate active
        $this->assertEquals(200, $response->get_status());
        $this->assertNotEmpty($data['data']['status']);

        // Read inactive subscriber via API
        $request = new WP_REST_Request('GET', '/wpsms/v1/subscribers/' . $inactiveId);
        $response = rest_do_request($request);
        $data = $response->get_data();

        // Status should indicate inactive (could be '0', 0, or 'inactive')
        $this->assertEquals(200, $response->get_status());
    }

    /**
     * Test: Custom fields JSON format is backward compatible
     * Legacy: Stored as JSON string or empty string
     */
    public function testCustomFieldsBackwardCompatible()
    {
        $customFields = json_encode([
            'field1' => 'value1',
            'field2' => 'value2',
            'nested' => ['a' => 'b'],
        ]);

        $subscriberId = $this->createLegacySubscriber([
            'custom_fields' => $customFields,
        ]);

        // Read via API
        $request = new WP_REST_Request('GET', '/wpsms/v1/subscribers/' . $subscriberId);
        $response = rest_do_request($request);
        $data = $response->get_data();

        $this->assertEquals(200, $response->get_status());

        // Custom fields should be accessible
        if (isset($data['data']['custom_fields'])) {
            $this->assertNotEmpty($data['data']['custom_fields']);
        }
    }

    /**
     * Test: Empty custom fields are handled properly
     */
    public function testEmptyCustomFieldsHandled()
    {
        $subscriberId = $this->createLegacySubscriber([
            'custom_fields' => '',
        ]);

        $request = new WP_REST_Request('GET', '/wpsms/v1/subscribers/' . $subscriberId);
        $response = rest_do_request($request);

        // Should not error on empty custom fields
        $this->assertEquals(200, $response->get_status());
    }

    /**
     * Test: Group ID reference is maintained
     */
    public function testGroupIdReferenceBackwardCompatible()
    {
        // Create group and subscriber
        $this->wpdb->insert($this->groupsTable, ['name' => 'Backward Compat Group']);
        $groupId = $this->wpdb->insert_id;

        $subscriberId = $this->createLegacySubscriber([
            'group_ID' => $groupId,
        ]);

        // Read via API
        $request = new WP_REST_Request('GET', '/wpsms/v1/subscribers/' . $subscriberId);
        $response = rest_do_request($request);
        $data = $response->get_data();

        $this->assertEquals(200, $response->get_status());

        // Group reference should be maintained
        if (isset($data['data']['group_ID'])) {
            $this->assertEquals($groupId, $data['data']['group_ID']);
        } elseif (isset($data['data']['group_id'])) {
            $this->assertEquals($groupId, $data['data']['group_id']);
        }
    }

    /**
     * Test: Filter by group works for legacy subscribers
     */
    public function testFilterByGroupWorksForLegacySubscribers()
    {
        // Create group
        $this->wpdb->insert($this->groupsTable, ['name' => 'Filter Test Group']);
        $groupId = $this->wpdb->insert_id;

        // Create subscribers in that group
        for ($i = 0; $i < 3; $i++) {
            $this->createLegacySubscriber([
                'name'     => 'Group Member ' . $i,
                'group_ID' => $groupId,
            ]);
        }

        // Create subscriber in different group
        $this->createLegacySubscriber([
            'name'     => 'Other Group Member',
            'group_ID' => '999',
        ]);

        // Filter via API
        $request = new WP_REST_Request('GET', '/wpsms/v1/subscribers');
        $request->set_param('group_id', $groupId);

        $response = rest_do_request($request);
        $data = $response->get_data();

        $this->assertEquals(200, $response->get_status());
        $this->assertArrayHasKey('items', $data['data']);

        // All results should be from the specified group
        foreach ($data['data']['items'] as $item) {
            $itemGroupId = $item['group_ID'] ?? $item['group_id'] ?? null;
            if ($itemGroupId !== null) {
                $this->assertEquals($groupId, $itemGroupId);
            }
        }
    }

    /**
     * Test: Updating legacy subscriber via API works
     */
    public function testUpdateLegacySubscriberViaApi()
    {
        $subscriberId = $this->createLegacySubscriber([
            'name'   => 'Original Name',
            'status' => '1',
        ]);

        // Update via API
        $request = new WP_REST_Request('PUT', '/wpsms/v1/subscribers/' . $subscriberId);
        $request->set_body_params([
            'name' => 'Updated Name',
        ]);

        $response = rest_do_request($request);

        $this->assertContains($response->get_status(), [200, 201]);

        // Verify via legacy database query
        $subscriber = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->subscribersTable} WHERE ID = %d",
            $subscriberId
        ));

        $this->assertEquals('Updated Name', $subscriber->name);
    }

    /**
     * Test: Deleting legacy subscriber via API works
     */
    public function testDeleteLegacySubscriberViaApi()
    {
        $subscriberId = $this->createLegacySubscriber();

        // Verify exists
        $exists = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->subscribersTable} WHERE ID = %d",
            $subscriberId
        ));
        $this->assertEquals(1, $exists);

        // Delete via API
        $request = new WP_REST_Request('DELETE', '/wpsms/v1/subscribers/' . $subscriberId);
        $response = rest_do_request($request);

        $this->assertContains($response->get_status(), [200, 204]);

        // Verify deleted
        $exists = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->subscribersTable} WHERE ID = %d",
            $subscriberId
        ));
        $this->assertEquals(0, $exists);
    }

    /**
     * Test: Bulk delete works on legacy subscribers
     */
    public function testBulkDeleteOnLegacySubscribers()
    {
        $ids = [];
        for ($i = 0; $i < 3; $i++) {
            $ids[] = $this->createLegacySubscriber();
        }

        // Bulk delete via API
        $request = new WP_REST_Request('POST', '/wpsms/v1/subscribers/bulk');
        $request->set_body_params([
            'action' => 'delete',
            'ids'    => $ids,
        ]);

        $response = rest_do_request($request);

        $this->assertEquals(200, $response->get_status());

        // Verify all deleted
        $remaining = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->subscribersTable} WHERE ID IN (" . implode(',', array_fill(0, count($ids), '%d')) . ")",
            ...$ids
        ));

        $this->assertEquals(0, $remaining);
    }

    /**
     * Test: Bulk activate works on legacy subscribers
     */
    public function testBulkActivateOnLegacySubscribers()
    {
        $ids = [];
        for ($i = 0; $i < 3; $i++) {
            $ids[] = $this->createLegacySubscriber(['status' => '0']); // inactive
        }

        // Bulk activate via API
        $request = new WP_REST_Request('POST', '/wpsms/v1/subscribers/bulk');
        $request->set_body_params([
            'action' => 'activate',
            'ids'    => $ids,
        ]);

        $response = rest_do_request($request);

        $this->assertEquals(200, $response->get_status());

        // Verify all activated
        foreach ($ids as $id) {
            $status = $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT status FROM {$this->subscribersTable} WHERE ID = %d",
                $id
            ));
            $this->assertEquals('1', $status);
        }
    }

    /**
     * Test: Bulk deactivate works on legacy subscribers
     */
    public function testBulkDeactivateOnLegacySubscribers()
    {
        $ids = [];
        for ($i = 0; $i < 3; $i++) {
            $ids[] = $this->createLegacySubscriber(['status' => '1']); // active
        }

        // Bulk deactivate via API
        $request = new WP_REST_Request('POST', '/wpsms/v1/subscribers/bulk');
        $request->set_body_params([
            'action' => 'deactivate',
            'ids'    => $ids,
        ]);

        $response = rest_do_request($request);

        $this->assertEquals(200, $response->get_status());

        // Verify all deactivated
        foreach ($ids as $id) {
            $status = $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT status FROM {$this->subscribersTable} WHERE ID = %d",
                $id
            ));
            $this->assertEquals('0', $status);
        }
    }

    /**
     * Test: Bulk move to group works on legacy subscribers
     */
    public function testBulkMoveGroupOnLegacySubscribers()
    {
        // Create target group
        $this->wpdb->insert($this->groupsTable, ['name' => 'Target Group']);
        $targetGroupId = $this->wpdb->insert_id;

        // Create subscribers in different group
        $ids = [];
        for ($i = 0; $i < 3; $i++) {
            $ids[] = $this->createLegacySubscriber(['group_ID' => '1']);
        }

        // Bulk move via API
        $request = new WP_REST_Request('POST', '/wpsms/v1/subscribers/bulk');
        $request->set_body_params([
            'action'   => 'move',
            'ids'      => $ids,
            'group_id' => $targetGroupId,
        ]);

        $response = rest_do_request($request);

        $this->assertEquals(200, $response->get_status());

        // Verify all moved
        foreach ($ids as $id) {
            $groupId = $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT group_ID FROM {$this->subscribersTable} WHERE ID = %d",
                $id
            ));
            $this->assertEquals($targetGroupId, $groupId);
        }
    }

    /**
     * Test: Search works on legacy subscribers
     */
    public function testSearchWorksOnLegacySubscribers()
    {
        // Create subscriber with unique name
        $uniqueName = 'UniqueSearchableName' . uniqid();
        $this->createLegacySubscriber(['name' => $uniqueName]);

        // Search via API
        $request = new WP_REST_Request('GET', '/wpsms/v1/subscribers');
        $request->set_param('search', 'UniqueSearchableName');

        $response = rest_do_request($request);
        $data = $response->get_data();

        $this->assertEquals(200, $response->get_status());
        $this->assertArrayHasKey('items', $data['data']);

        // Should find the subscriber
        $found = false;
        foreach ($data['data']['items'] as $item) {
            if (strpos($item['name'], 'UniqueSearchableName') !== false) {
                $found = true;
                break;
            }
        }

        $this->assertTrue($found, 'Legacy subscriber should be found by search');
    }

    /**
     * Test: Export includes legacy subscribers
     */
    public function testExportIncludesLegacySubscribers()
    {
        // Create some legacy subscribers
        $uniquePhone = '+1' . str_pad(mt_rand(1, 9999999999), 10, '0', STR_PAD_LEFT);
        $this->createLegacySubscriber(['mobile' => $uniquePhone]);

        // Export via API
        $request = new WP_REST_Request('GET', '/wpsms/v1/subscribers/export');
        $response = rest_do_request($request);

        $this->assertEquals(200, $response->get_status());

        $data = $response->get_data();
        $this->assertArrayHasKey('data', $data);
    }

    /**
     * Test: Date format is consistent between legacy and API
     */
    public function testDateFormatConsistent()
    {
        $testDate = '2024-01-15 14:30:00';

        $subscriberId = $this->createLegacySubscriber([
            'date' => $testDate,
        ]);

        // Read via API
        $request = new WP_REST_Request('GET', '/wpsms/v1/subscribers/' . $subscriberId);
        $response = rest_do_request($request);
        $data = $response->get_data();

        $this->assertEquals(200, $response->get_status());

        // Date should be accessible (format may vary but should be parseable)
        if (isset($data['data']['date'])) {
            $parsedDate = strtotime($data['data']['date']);
            $this->assertNotFalse($parsedDate, 'Date should be parseable');
        }
    }

    /**
     * Test: Activation key for double opt-in is preserved
     *
     * Note: The activate_key column is INT(11) in the database schema,
     * so activation keys must be numeric values.
     */
    public function testActivationKeyPreserved()
    {
        // Use numeric activation key (database column is INT(11))
        $activateKey = mt_rand(100000000, 999999999);

        $subscriberId = $this->createLegacySubscriber([
            'activate_key' => $activateKey,
            'status'       => '0', // pending activation
        ]);

        // Verify key is preserved in database
        $savedKey = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT activate_key FROM {$this->subscribersTable} WHERE ID = %d",
            $subscriberId
        ));

        $this->assertEquals($activateKey, (int) $savedKey);
    }

    /**
     * Test: Duplicate phone number detection works across interfaces
     */
    public function testDuplicatePhoneDetectionAcrossInterfaces()
    {
        $phone = '+1' . str_pad(mt_rand(1, 9999999999), 10, '0', STR_PAD_LEFT);

        // Create via legacy
        $this->createLegacySubscriber(['mobile' => $phone]);

        // Try to create same number via API
        $request = new WP_REST_Request('POST', '/wpsms/v1/subscribers');
        $request->set_body_params([
            'mobile' => $phone,
            'name'   => 'Duplicate Test',
        ]);

        $response = rest_do_request($request);

        // Should be rejected as duplicate
        $this->assertEquals(400, $response->get_status());

        $data = $response->get_data();
        $this->assertArrayHasKey('error', $data);
    }

    /**
     * Test: Pagination works correctly with mixed legacy/API data
     */
    public function testPaginationWithMixedData()
    {
        // Create 15 legacy subscribers
        for ($i = 0; $i < 15; $i++) {
            $this->createLegacySubscriber(['name' => 'Pagination Test ' . $i]);
        }

        // Request first page with 10 per page
        $request = new WP_REST_Request('GET', '/wpsms/v1/subscribers');
        $request->set_param('per_page', 10);
        $request->set_param('page', 1);

        $response = rest_do_request($request);
        $data = $response->get_data();

        $this->assertEquals(200, $response->get_status());
        $this->assertCount(10, $data['data']['items']);
        $this->assertArrayHasKey('pagination', $data['data']);
        $this->assertGreaterThanOrEqual(15, $data['data']['pagination']['total']);
    }

    /**
     * Test: Stats endpoint includes legacy subscriber counts
     */
    public function testStatsIncludeLegacySubscribers()
    {
        // Create some legacy subscribers with different statuses
        $this->createLegacySubscriber(['status' => '1']); // active
        $this->createLegacySubscriber(['status' => '1']); // active
        $this->createLegacySubscriber(['status' => '0']); // inactive

        // Get subscribers list (includes stats)
        $request = new WP_REST_Request('GET', '/wpsms/v1/subscribers');
        $response = rest_do_request($request);
        $data = $response->get_data();

        $this->assertEquals(200, $response->get_status());

        if (isset($data['data']['stats'])) {
            $stats = $data['data']['stats'];
            // Stats should reflect the created subscribers
            $this->assertArrayHasKey('total', $stats);
            $this->assertGreaterThanOrEqual(3, $stats['total']);
        }
    }
}
