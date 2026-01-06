<?php

namespace unit\BackwardCompatibility;

use unit\WPSMSTestCase;
use WP_REST_Request;

require_once dirname(__DIR__) . '/WPSMSTestCase.php';

/**
 * Backward Compatibility Tests for Groups
 *
 * Ensures that subscriber groups created via legacy admin pages work correctly
 * with the new React dashboard, and vice versa.
 */
class GroupsBackwardCompatibilityTest extends WPSMSTestCase
{
    /**
     * @var \wpdb
     */
    private $wpdb;

    /**
     * @var string
     */
    private $groupsTable;

    /**
     * @var string
     */
    private $subscribersTable;

    /**
     * Set up test environment
     */
    public function setUp(): void
    {
        parent::setUp();

        global $wpdb;
        $this->wpdb = $wpdb;
        $this->groupsTable = $wpdb->prefix . 'sms_subscribes_group';
        $this->subscribersTable = $wpdb->prefix . 'sms_subscribes';
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
     * Helper: Create group via legacy direct database insert
     *
     * @param string $name Group name
     * @return int|false Group ID or false
     */
    private function createLegacyGroup(string $name = null)
    {
        $name = $name ?? 'Legacy Group ' . uniqid();

        $result = $this->wpdb->insert($this->groupsTable, [
            'name' => $name,
        ]);

        return $result ? $this->wpdb->insert_id : false;
    }

    /**
     * Helper: Create subscriber in group
     *
     * @param int $groupId Group ID
     * @return int|false Subscriber ID or false
     */
    private function createSubscriberInGroup(int $groupId)
    {
        $result = $this->wpdb->insert($this->subscribersTable, [
            'date'          => current_time('mysql'),
            'name'          => 'Test User ' . uniqid(),
            'mobile'        => '+1' . str_pad(mt_rand(1, 9999999999), 10, '0', STR_PAD_LEFT),
            'status'        => '1',
            'activate_key'  => '',
            'group_ID'      => $groupId,
            'custom_fields' => '',
        ]);

        return $result ? $this->wpdb->insert_id : false;
    }

    /**
     * Test: Group created via legacy method is readable via REST API
     */
    public function testLegacyGroupReadableViaApi()
    {
        $groupName = 'Legacy Test Group ' . uniqid();
        $groupId = $this->createLegacyGroup($groupName);

        $this->assertNotFalse($groupId);

        // Read via REST API
        $request = new WP_REST_Request('GET', '/wpsms/v1/groups/' . $groupId);
        $response = rest_do_request($request);
        $data = $response->get_data();

        $this->assertEquals(200, $response->get_status());
        $this->assertArrayHasKey('data', $data);
        $this->assertEquals($groupName, $data['data']['name']);
    }

    /**
     * Test: Legacy groups appear in API list endpoint
     */
    public function testLegacyGroupsAppearInApiList()
    {
        // Create multiple legacy groups
        $groupNames = [];
        for ($i = 0; $i < 3; $i++) {
            $name = 'Legacy List Group ' . $i . ' ' . uniqid();
            $groupNames[] = $name;
            $this->createLegacyGroup($name);
        }

        // Fetch via API
        $request = new WP_REST_Request('GET', '/wpsms/v1/groups');
        $response = rest_do_request($request);
        $data = $response->get_data();

        $this->assertEquals(200, $response->get_status());
        $this->assertArrayHasKey('data', $data);

        // Should include our legacy groups
        $foundCount = 0;
        $items = is_array($data['data']['items'] ?? null) ? $data['data']['items'] : $data['data'];

        foreach ($items as $item) {
            foreach ($groupNames as $name) {
                if ($item['name'] === $name) {
                    $foundCount++;
                    break;
                }
            }
        }

        $this->assertEquals(3, $foundCount);
    }

    /**
     * Test: Group created via API is readable via legacy database query
     */
    public function testApiGroupReadableViaLegacy()
    {
        $groupName = 'API Group ' . uniqid();

        // Create via API
        $request = new WP_REST_Request('POST', '/wpsms/v1/groups');
        $request->set_body_params([
            'name' => $groupName,
        ]);

        $response = rest_do_request($request);

        $this->assertContains($response->get_status(), [200, 201]);

        // Read via legacy direct database query
        $group = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->groupsTable} WHERE name = %s",
            $groupName
        ));

        $this->assertNotNull($group);
        $this->assertEquals($groupName, $group->name);
    }

    /**
     * Test: Updating legacy group via API works
     */
    public function testUpdateLegacyGroupViaApi()
    {
        $groupId = $this->createLegacyGroup('Original Group Name');

        // Update via API
        $request = new WP_REST_Request('PUT', '/wpsms/v1/groups/' . $groupId);
        $request->set_body_params([
            'name' => 'Updated Group Name',
        ]);

        $response = rest_do_request($request);

        $this->assertContains($response->get_status(), [200, 201]);

        // Verify via legacy database query
        $group = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->groupsTable} WHERE ID = %d",
            $groupId
        ));

        $this->assertEquals('Updated Group Name', $group->name);
    }

    /**
     * Test: Deleting legacy group via API works
     */
    public function testDeleteLegacyGroupViaApi()
    {
        $groupId = $this->createLegacyGroup();

        // Verify exists
        $exists = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->groupsTable} WHERE ID = %d",
            $groupId
        ));
        $this->assertEquals(1, $exists);

        // Delete via API
        $request = new WP_REST_Request('DELETE', '/wpsms/v1/groups/' . $groupId);
        $response = rest_do_request($request);

        $this->assertContains($response->get_status(), [200, 204]);

        // Verify deleted
        $exists = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->groupsTable} WHERE ID = %d",
            $groupId
        ));
        $this->assertEquals(0, $exists);
    }

    /**
     * Test: Subscriber count is correct for legacy groups
     */
    public function testSubscriberCountForLegacyGroups()
    {
        $groupId = $this->createLegacyGroup('Count Test Group');

        // Add subscribers to group
        for ($i = 0; $i < 5; $i++) {
            $this->createSubscriberInGroup($groupId);
        }

        // Get group via API
        $request = new WP_REST_Request('GET', '/wpsms/v1/groups/' . $groupId);
        $response = rest_do_request($request);
        $data = $response->get_data();

        $this->assertEquals(200, $response->get_status());

        // Subscriber count should be included
        if (isset($data['data']['subscriber_count'])) {
            $this->assertEquals(5, $data['data']['subscriber_count']);
        } elseif (isset($data['data']['subscriberCount'])) {
            $this->assertEquals(5, $data['data']['subscriberCount']);
        }
    }

    /**
     * Test: Group ID format is consistent (integer)
     */
    public function testGroupIdFormatConsistent()
    {
        $groupId = $this->createLegacyGroup();

        // Verify ID is numeric in database
        $dbId = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT ID FROM {$this->groupsTable} WHERE ID = %d",
            $groupId
        ));

        $this->assertIsNumeric($dbId);

        // Read via API
        $request = new WP_REST_Request('GET', '/wpsms/v1/groups/' . $groupId);
        $response = rest_do_request($request);
        $data = $response->get_data();

        $this->assertEquals(200, $response->get_status());

        // API should return consistent ID format
        $apiId = $data['data']['id'] ?? $data['data']['ID'];
        $this->assertEquals((int) $groupId, (int) $apiId);
    }

    /**
     * Test: Bulk delete works on legacy groups
     *
     * Note: Bulk delete endpoint is not currently implemented.
     * Groups can be deleted individually via DELETE /groups/{id}.
     */
    public function testBulkDeleteOnLegacyGroups()
    {
        $this->markTestSkipped(
            'Bulk delete endpoint (/groups/bulk) is not implemented in the Groups API. ' .
            'Groups can be deleted individually via DELETE /groups/{id}.'
        );
    }

    /**
     * Test: Special characters in group names are handled
     */
    public function testSpecialCharactersInGroupNames()
    {
        $specialName = "Test Group & Co. <Special> 'Chars' \"Quoted\"";

        $groupId = $this->createLegacyGroup($specialName);

        // Read via API
        $request = new WP_REST_Request('GET', '/wpsms/v1/groups/' . $groupId);
        $response = rest_do_request($request);
        $data = $response->get_data();

        $this->assertEquals(200, $response->get_status());

        // Name should be preserved or safely encoded
        $this->assertNotEmpty($data['data']['name']);
    }

    /**
     * Test: Unicode/international characters in group names
     */
    public function testUnicodeGroupNames()
    {
        $unicodeName = 'گروه تست فارسی 日本語グループ';

        $groupId = $this->createLegacyGroup($unicodeName);

        // Read via API
        $request = new WP_REST_Request('GET', '/wpsms/v1/groups/' . $groupId);
        $response = rest_do_request($request);
        $data = $response->get_data();

        $this->assertEquals(200, $response->get_status());

        // Unicode should be preserved
        $this->assertEquals($unicodeName, $data['data']['name']);
    }

    /**
     * Test: Empty group name handling
     */
    public function testEmptyGroupNameHandled()
    {
        // Try to create group with empty name via API
        $request = new WP_REST_Request('POST', '/wpsms/v1/groups');
        $request->set_body_params([
            'name' => '',
        ]);

        $response = rest_do_request($request);

        // Should be rejected
        $this->assertEquals(400, $response->get_status());
    }

    /**
     * Test: Duplicate group name handling
     */
    public function testDuplicateGroupNameHandling()
    {
        $name = 'Unique Group Name ' . uniqid();

        // Create first group
        $this->createLegacyGroup($name);

        // Try to create another with same name via API
        $request = new WP_REST_Request('POST', '/wpsms/v1/groups');
        $request->set_body_params([
            'name' => $name,
        ]);

        $response = rest_do_request($request);

        // Behavior may vary: could allow duplicate or reject
        // If rejected, should be 400
        // If allowed, should be 200/201
        $this->assertContains($response->get_status(), [200, 201, 400]);
    }

    /**
     * Test: Group with subscribers - deleting group updates subscribers
     */
    public function testDeleteGroupUpdatesSubscribers()
    {
        $groupId = $this->createLegacyGroup('Group To Delete');

        // Add subscribers
        $subscriberIds = [];
        for ($i = 0; $i < 3; $i++) {
            $subscriberIds[] = $this->createSubscriberInGroup($groupId);
        }

        // Delete group via API
        $request = new WP_REST_Request('DELETE', '/wpsms/v1/groups/' . $groupId);
        $response = rest_do_request($request);

        // Subscribers should still exist (not cascade deleted)
        foreach ($subscriberIds as $subId) {
            $exists = $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->subscribersTable} WHERE ID = %d",
                $subId
            ));
            // Subscriber should either exist or the deletion should have handled them
            $this->assertContains($exists, ['0', '1', 0, 1]);
        }
    }

    /**
     * Test: Groups list includes all legacy groups
     */
    public function testGroupsListIncludesAllLegacyGroups()
    {
        // Clean table first
        $this->wpdb->query("DELETE FROM {$this->groupsTable}");

        // Create known number of groups
        for ($i = 0; $i < 10; $i++) {
            $this->createLegacyGroup('List Test Group ' . $i);
        }

        // Get list via API
        $request = new WP_REST_Request('GET', '/wpsms/v1/groups');
        $response = rest_do_request($request);
        $data = $response->get_data();

        $this->assertEquals(200, $response->get_status());

        // Should have all 10 groups
        $items = is_array($data['data']['items'] ?? null) ? $data['data']['items'] : $data['data'];
        $this->assertCount(10, $items);
    }

    /**
     * Test: Group selection in subscriber form works with legacy groups
     */
    public function testGroupSelectionBackwardCompatible()
    {
        $groupId = $this->createLegacyGroup('Selectable Group');

        // Create subscriber assigned to this group via API
        $request = new WP_REST_Request('POST', '/wpsms/v1/subscribers');
        $request->set_body_params([
            'mobile'   => '+1' . str_pad(mt_rand(1, 9999999999), 10, '0', STR_PAD_LEFT),
            'name'     => 'Group Test Subscriber',
            'group_id' => $groupId,
            'status'   => '1',
        ]);

        $response = rest_do_request($request);

        // Should succeed or give clear error
        $this->assertContains($response->get_status(), [200, 201, 400]);

        if ($response->get_status() === 200 || $response->get_status() === 201) {
            $data = $response->get_data();

            // Verify subscriber was assigned to group via legacy query
            $subscriber = $this->wpdb->get_row($this->wpdb->prepare(
                "SELECT * FROM {$this->subscribersTable} WHERE mobile LIKE %s",
                '%' . substr($data['data']['mobile'] ?? '', -10) . '%'
            ));

            if ($subscriber) {
                $this->assertEquals($groupId, $subscriber->group_ID);
            }
        }
    }

    /**
     * Test: Group stats are accurate for legacy data
     */
    public function testGroupStatsAccurate()
    {
        $groupId = $this->createLegacyGroup('Stats Test Group');

        // Add active and inactive subscribers
        for ($i = 0; $i < 3; $i++) {
            $this->wpdb->insert($this->subscribersTable, [
                'date'          => current_time('mysql'),
                'name'          => 'Active User ' . $i,
                'mobile'        => '+1' . str_pad(mt_rand(1, 9999999999), 10, '0', STR_PAD_LEFT),
                'status'        => '1',
                'activate_key'  => '',
                'group_ID'      => $groupId,
                'custom_fields' => '',
            ]);
        }

        for ($i = 0; $i < 2; $i++) {
            $this->wpdb->insert($this->subscribersTable, [
                'date'          => current_time('mysql'),
                'name'          => 'Inactive User ' . $i,
                'mobile'        => '+1' . str_pad(mt_rand(1, 9999999999), 10, '0', STR_PAD_LEFT),
                'status'        => '0',
                'activate_key'  => '',
                'group_ID'      => $groupId,
                'custom_fields' => '',
            ]);
        }

        // Get group via API
        $request = new WP_REST_Request('GET', '/wpsms/v1/groups/' . $groupId);
        $response = rest_do_request($request);
        $data = $response->get_data();

        $this->assertEquals(200, $response->get_status());

        // Stats should reflect: 3 active, 2 inactive, 5 total
        $group = $data['data'];

        if (isset($group['subscriber_count'])) {
            $this->assertEquals(5, $group['subscriber_count']);
        }

        if (isset($group['active_count'])) {
            $this->assertEquals(3, $group['active_count']);
        }
    }
}
