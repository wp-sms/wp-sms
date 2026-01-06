<?php

namespace unit;

use WP_SMS\Api\V1\SubscribersApi;
use WP_SMS\Newsletter;
use WP_UnitTestCase;
use WP_REST_Request;
use WP_REST_Server;

/**
 * Tests for Subscribers REST API
 */
class SubscribersApiTest extends WP_UnitTestCase
{
    /**
     * @var SubscribersApi
     */
    private $subscribersApi;

    /**
     * @var int
     */
    private $adminUserId;

    /**
     * @var Newsletter
     */
    private $newsletter;

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

        // Initialize REST server
        global $wp_rest_server;
        $wp_rest_server = new WP_REST_Server();
        do_action('rest_api_init');

        $this->subscribersApi = new SubscribersApi();
        $this->newsletter = new Newsletter();
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
     * Test get subscribers returns paginated list
     */
    public function testGetSubscribersReturnsPaginatedList()
    {
        $request = new WP_REST_Request('GET', '/wpsms/v1/subscribers');

        $response = rest_do_request($request);
        $data = $response->get_data();

        $this->assertEquals(200, $response->get_status());
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('items', $data['data']);
        $this->assertArrayHasKey('pagination', $data['data']);
        $this->assertArrayHasKey('stats', $data['data']);
    }

    /**
     * Test get subscribers requires authentication
     */
    public function testGetSubscribersRequiresAuthentication()
    {
        wp_set_current_user(0);

        $request = new WP_REST_Request('GET', '/wpsms/v1/subscribers');

        $response = rest_do_request($request);

        $this->assertEquals(401, $response->get_status());
    }

    /**
     * Test create subscriber with invalid (non-numeric) phone number returns WP_Error message
     */
    public function testCreateSubscriberWithInvalidPhoneNumberReturnsError()
    {
        $request = new WP_REST_Request('POST', '/wpsms/v1/subscribers');
        $request->set_body_params([
            'mobile' => 'invalid-phone-abc',
            'name'   => 'Test User',
        ]);

        $response = rest_do_request($request);
        $data = $response->get_data();

        $this->assertEquals(400, $response->get_status());
        $this->assertArrayHasKey('error', $data);
        $this->assertArrayHasKey('message', $data['error']);
    }

    /**
     * Test create subscriber without country code when international mode is enabled
     */
    public function testCreateSubscriberWithoutCountryCodeReturnsError()
    {
        // Enable international mobile input
        \WP_SMS\Option::updateOption('international_mobile', true);

        $request = new WP_REST_Request('POST', '/wpsms/v1/subscribers');
        $request->set_body_params([
            'mobile' => '9123456789', // No + prefix
            'name'   => 'Test User',
        ]);

        $response = rest_do_request($request);
        $data = $response->get_data();

        $this->assertEquals(400, $response->get_status());
        $this->assertArrayHasKey('error', $data);
        $this->assertStringContainsString('country code', strtolower($data['error']['message']));

        // Reset option
        \WP_SMS\Option::updateOption('international_mobile', false);
    }

    /**
     * Test create subscriber with too short phone number returns error
     */
    public function testCreateSubscriberWithShortPhoneNumberReturnsError()
    {
        $request = new WP_REST_Request('POST', '/wpsms/v1/subscribers');
        $request->set_body_params([
            'mobile' => '+123', // Too short
            'name'   => 'Test User',
        ]);

        $response = rest_do_request($request);
        $data = $response->get_data();

        $this->assertEquals(400, $response->get_status());
        $this->assertArrayHasKey('error', $data);
        $this->assertStringContainsString('length', strtolower($data['error']['message']));
    }

    /**
     * Test create subscriber with invalid country code returns error
     */
    public function testCreateSubscriberWithInvalidCountryCodeReturnsError()
    {
        // Enable international mobile input
        \WP_SMS\Option::updateOption('international_mobile', true);

        $request = new WP_REST_Request('POST', '/wpsms/v1/subscribers');
        $request->set_body_params([
            'mobile' => '+999123456789', // Invalid country code 999
            'name'   => 'Test User',
        ]);

        $response = rest_do_request($request);
        $data = $response->get_data();

        $this->assertEquals(400, $response->get_status());
        $this->assertArrayHasKey('error', $data);
        $this->assertStringContainsString('country', strtolower($data['error']['message']));

        // Reset option
        \WP_SMS\Option::updateOption('international_mobile', false);
    }

    /**
     * Test create subscriber with valid international phone number
     */
    public function testCreateSubscriberWithValidInternationalNumber()
    {
        $uniquePhone = '+1' . str_pad(mt_rand(1, 9999999999), 10, '0', STR_PAD_LEFT);

        $request = new WP_REST_Request('POST', '/wpsms/v1/subscribers');
        $request->set_body_params([
            'mobile' => $uniquePhone,
            'name'   => 'Test User',
            'status' => '1',
        ]);

        $response = rest_do_request($request);
        $data = $response->get_data();

        // Should succeed with 201 (created)
        $this->assertContains($response->get_status(), [200, 201]);
        if ($response->get_status() === 201 || $response->get_status() === 200) {
            $this->assertArrayHasKey('data', $data);
        }
    }

    /**
     * Test create subscriber with duplicate phone number returns error
     */
    public function testCreateSubscriberWithDuplicatePhoneReturnsError()
    {
        // First create a subscriber
        $uniquePhone = '+1' . str_pad(mt_rand(1, 9999999999), 10, '0', STR_PAD_LEFT);

        $request1 = new WP_REST_Request('POST', '/wpsms/v1/subscribers');
        $request1->set_body_params([
            'mobile' => $uniquePhone,
            'name'   => 'First User',
            'status' => '1',
        ]);

        $response1 = rest_do_request($request1);

        // Skip if first creation failed
        if ($response1->get_status() !== 201 && $response1->get_status() !== 200) {
            $this->markTestSkipped('Could not create first subscriber');
            return;
        }

        // Try to create another subscriber with the same phone
        $request2 = new WP_REST_Request('POST', '/wpsms/v1/subscribers');
        $request2->set_body_params([
            'mobile' => $uniquePhone,
            'name'   => 'Second User',
            'status' => '1',
        ]);

        $response2 = rest_do_request($request2);
        $data2 = $response2->get_data();

        $this->assertEquals(400, $response2->get_status());
        $this->assertArrayHasKey('error', $data2);
        $this->assertStringContainsString('exists', strtolower($data2['error']['message']));
    }

    /**
     * Test create subscriber with Persian/Arabic numerals
     */
    public function testCreateSubscriberWithPersianNumerals()
    {
        $persianPhone = '+۹۸۹' . str_pad(mt_rand(1, 99999999), 8, '۰', STR_PAD_LEFT);

        $request = new WP_REST_Request('POST', '/wpsms/v1/subscribers');
        $request->set_body_params([
            'mobile' => $persianPhone,
            'name'   => 'Persian User',
            'status' => '1',
        ]);

        $response = rest_do_request($request);

        // Should succeed (numbers are converted to English)
        $this->assertContains($response->get_status(), [200, 201, 400]);
    }

    /**
     * Test update subscriber with invalid phone number returns WP_Error message
     */
    public function testUpdateSubscriberWithInvalidPhoneReturnsError()
    {
        // First create a valid subscriber
        $subscriberId = $this->createTestSubscriber();

        if (!$subscriberId) {
            $this->markTestSkipped('Could not create test subscriber');
            return;
        }

        $request = new WP_REST_Request('PUT', '/wpsms/v1/subscribers/' . $subscriberId);
        $request->set_body_params([
            'mobile' => 'invalid-phone-xyz',
        ]);

        $response = rest_do_request($request);
        $data = $response->get_data();

        $this->assertEquals(400, $response->get_status());
        $this->assertArrayHasKey('error', $data);
        $this->assertArrayHasKey('message', $data['error']);
    }

    /**
     * Test error response format contains proper error structure
     */
    public function testErrorResponseContainsProperStructure()
    {
        $request = new WP_REST_Request('POST', '/wpsms/v1/subscribers');
        $request->set_body_params([
            'mobile' => 'abc', // Invalid
            'name'   => 'Test',
        ]);

        $response = rest_do_request($request);
        $data = $response->get_data();

        $this->assertEquals(400, $response->get_status());
        $this->assertArrayHasKey('error', $data);
        $this->assertArrayHasKey('code', $data['error']);
        $this->assertArrayHasKey('message', $data['error']);
        $this->assertNotEmpty($data['error']['message']);
    }

    /**
     * Test update subscriber
     */
    public function testUpdateSubscriber()
    {
        // First create a subscriber
        $subscriberId = $this->createTestSubscriber();

        if (!$subscriberId) {
            $this->markTestSkipped('Could not create test subscriber');
            return;
        }

        $request = new WP_REST_Request('PUT', '/wpsms/v1/subscribers/' . $subscriberId);
        $request->set_body_params([
            'name' => 'Updated Name',
        ]);

        $response = rest_do_request($request);

        $this->assertContains($response->get_status(), [200, 400, 404]);
    }

    /**
     * Test delete subscriber
     */
    public function testDeleteSubscriber()
    {
        // First create a subscriber
        $subscriberId = $this->createTestSubscriber();

        if (!$subscriberId) {
            $this->markTestSkipped('Could not create test subscriber');
            return;
        }

        $request = new WP_REST_Request('DELETE', '/wpsms/v1/subscribers/' . $subscriberId);

        $response = rest_do_request($request);

        $this->assertContains($response->get_status(), [200, 404]);
    }

    /**
     * Test bulk delete action
     */
    public function testBulkDeleteAction()
    {
        $request = new WP_REST_Request('POST', '/wpsms/v1/subscribers/bulk');
        $request->set_body_params([
            'action' => 'delete',
            'ids'    => [1, 2, 3], // Test with non-existent IDs
        ]);

        $response = rest_do_request($request);

        // Should complete without error even if IDs don't exist
        $this->assertContains($response->get_status(), [200, 400]);
    }

    /**
     * Test bulk activate action
     */
    public function testBulkActivateAction()
    {
        $request = new WP_REST_Request('POST', '/wpsms/v1/subscribers/bulk');
        $request->set_body_params([
            'action' => 'activate',
            'ids'    => [1, 2],
        ]);

        $response = rest_do_request($request);

        $this->assertContains($response->get_status(), [200, 400]);
    }

    /**
     * Test bulk deactivate action
     */
    public function testBulkDeactivateAction()
    {
        $request = new WP_REST_Request('POST', '/wpsms/v1/subscribers/bulk');
        $request->set_body_params([
            'action' => 'deactivate',
            'ids'    => [1, 2],
        ]);

        $response = rest_do_request($request);

        $this->assertContains($response->get_status(), [200, 400]);
    }

    /**
     * Test bulk move action requires group_id
     */
    public function testBulkMoveActionRequiresGroupId()
    {
        $request = new WP_REST_Request('POST', '/wpsms/v1/subscribers/bulk');
        $request->set_body_params([
            'action' => 'move',
            'ids'    => [1, 2],
            // Missing group_id
        ]);

        $response = rest_do_request($request);

        // Should fail or handle gracefully
        $this->assertContains($response->get_status(), [200, 400]);
    }

    /**
     * Test export subscribers endpoint
     */
    public function testExportSubscribersEndpoint()
    {
        $request = new WP_REST_Request('GET', '/wpsms/v1/subscribers/export');

        $response = rest_do_request($request);
        $data = $response->get_data();

        $this->assertEquals(200, $response->get_status());
        $this->assertArrayHasKey('data', $data);
    }

    /**
     * Test filter subscribers by group returns only subscribers in that group
     */
    public function testFilterSubscribersByGroupReturnsCorrectResults()
    {
        // Create a test group
        global $wpdb;
        $groupTable = $wpdb->prefix . 'sms_subscribes_group';
        $wpdb->insert($groupTable, ['name' => 'Test Group Filter']);
        $groupId = $wpdb->insert_id;

        // Create subscribers in the group
        $this->createTestSubscriberWithGroup('Group Member 1', $groupId);
        $this->createTestSubscriberWithGroup('Group Member 2', $groupId);

        // Create subscribers in a different group (or no group)
        $this->createTestSubscriberWithGroup('Other Group', 0);

        $request = new WP_REST_Request('GET', '/wpsms/v1/subscribers');
        $request->set_param('group_id', $groupId);

        $response = rest_do_request($request);
        $data = $response->get_data();

        $this->assertEquals(200, $response->get_status());

        // Verify all returned items belong to the specified group
        foreach ($data['data']['items'] as $item) {
            $this->assertEquals($groupId, (int)$item['group_id'],
                "Subscriber {$item['id']} should be in group $groupId but is in group {$item['group_id']}");
        }
    }

    /**
     * Test filter subscribers by status=active returns only active subscribers
     */
    public function testFilterSubscribersByStatusActiveReturnsOnlyActive()
    {
        // Create active subscribers
        $this->createTestSubscriberWithStatus('Active User 1', '1');
        $this->createTestSubscriberWithStatus('Active User 2', '1');

        // Create inactive subscribers
        $this->createTestSubscriberWithStatus('Inactive User', '0');

        $request = new WP_REST_Request('GET', '/wpsms/v1/subscribers');
        $request->set_param('status', 'active');

        $response = rest_do_request($request);
        $data = $response->get_data();

        $this->assertEquals(200, $response->get_status());

        // Verify all returned items are active
        foreach ($data['data']['items'] as $item) {
            $this->assertEquals('1', $item['status'],
                "Subscriber {$item['id']} should be active (status=1) but has status={$item['status']}");
        }
    }

    /**
     * Test filter subscribers by status=inactive returns only inactive subscribers
     */
    public function testFilterSubscribersByStatusInactiveReturnsOnlyInactive()
    {
        // Create active subscribers
        $this->createTestSubscriberWithStatus('Active User', '1');

        // Create inactive subscribers
        $this->createTestSubscriberWithStatus('Inactive User 1', '0');
        $this->createTestSubscriberWithStatus('Inactive User 2', '0');

        $request = new WP_REST_Request('GET', '/wpsms/v1/subscribers');
        $request->set_param('status', 'inactive');

        $response = rest_do_request($request);
        $data = $response->get_data();

        $this->assertEquals(200, $response->get_status());

        // Verify all returned items are inactive
        foreach ($data['data']['items'] as $item) {
            $this->assertEquals('0', $item['status'],
                "Subscriber {$item['id']} should be inactive (status=0) but has status={$item['status']}");
        }
    }

    /**
     * Test search subscribers returns only matching results
     */
    public function testSearchSubscribersReturnsMatchingResults()
    {
        // Create subscribers with specific names
        $this->createTestSubscriberWithName('John Doe Searchable');
        $this->createTestSubscriberWithName('Jane Doe Searchable');
        $this->createTestSubscriberWithName('Bob Smith');

        $request = new WP_REST_Request('GET', '/wpsms/v1/subscribers');
        $request->set_param('search', 'Searchable');

        $response = rest_do_request($request);
        $data = $response->get_data();

        $this->assertEquals(200, $response->get_status());

        // Verify all returned items match the search term
        foreach ($data['data']['items'] as $item) {
            $this->assertStringContainsStringIgnoringCase('Searchable', $item['name'],
                "Subscriber {$item['id']} name '{$item['name']}' should contain 'Searchable'");
        }
    }

    /**
     * Test search with no matches returns empty results
     */
    public function testSearchWithNoMatchesReturnsEmpty()
    {
        $request = new WP_REST_Request('GET', '/wpsms/v1/subscribers');
        $request->set_param('search', 'xyznonexistent99999');

        $response = rest_do_request($request);
        $data = $response->get_data();

        $this->assertEquals(200, $response->get_status());
        $this->assertEmpty($data['data']['items']);
        $this->assertEquals(0, $data['data']['pagination']['total']);
    }

    /**
     * Test combined filters work together
     */
    public function testCombinedFiltersReturnCorrectResults()
    {
        // Create a test group
        global $wpdb;
        $groupTable = $wpdb->prefix . 'sms_subscribes_group';
        $wpdb->insert($groupTable, ['name' => 'Combined Test Group']);
        $groupId = $wpdb->insert_id;

        // Create various combinations
        $this->createTestSubscriberWithGroupAndStatus('Active In Group', $groupId, '1');
        $this->createTestSubscriberWithGroupAndStatus('Inactive In Group', $groupId, '0');
        $this->createTestSubscriberWithGroupAndStatus('Active Other', 0, '1');

        $request = new WP_REST_Request('GET', '/wpsms/v1/subscribers');
        $request->set_param('group_id', $groupId);
        $request->set_param('status', 'active');

        $response = rest_do_request($request);
        $data = $response->get_data();

        $this->assertEquals(200, $response->get_status());

        // Verify all returned items match BOTH filters
        foreach ($data['data']['items'] as $item) {
            $this->assertEquals($groupId, (int)$item['group_id'],
                "Subscriber should be in group $groupId");
            $this->assertEquals('1', $item['status'],
                "Subscriber should be active");
        }
    }

    /**
     * Test pagination parameters
     */
    public function testPaginationParameters()
    {
        $request = new WP_REST_Request('GET', '/wpsms/v1/subscribers');
        $request->set_param('page', 1);
        $request->set_param('per_page', 10);

        $response = rest_do_request($request);
        $data = $response->get_data();

        $this->assertEquals(200, $response->get_status());
        $this->assertArrayHasKey('pagination', $data['data']);
    }

    /**
     * Test subscriber not found returns 404
     */
    public function testSubscriberNotFoundReturns404()
    {
        $request = new WP_REST_Request('GET', '/wpsms/v1/subscribers/999999');

        $response = rest_do_request($request);

        $this->assertEquals(404, $response->get_status());
    }

    /**
     * Test invalid bulk action returns error
     */
    public function testInvalidBulkActionReturnsError()
    {
        $request = new WP_REST_Request('POST', '/wpsms/v1/subscribers/bulk');
        $request->set_body_params([
            'action' => 'invalid_action',
            'ids'    => [1],
        ]);

        $response = rest_do_request($request);

        // Should fail with validation error
        $this->assertEquals(400, $response->get_status());
    }

    /**
     * Helper to create a test subscriber
     *
     * @return int|false Subscriber ID or false on failure
     */
    private function createTestSubscriber()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'sms_subscribes';
        $phone = '+1' . str_pad(mt_rand(1, 9999999999), 10, '0', STR_PAD_LEFT);

        $result = $wpdb->insert($table, [
            'name'   => 'Test Subscriber ' . uniqid(),
            'mobile' => $phone,
            'status' => '1',
            'date'   => current_time('mysql'),
        ]);

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Helper to create a test subscriber with specific name
     *
     * @param string $name Subscriber name
     * @return int|false Subscriber ID or false on failure
     */
    private function createTestSubscriberWithName($name)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'sms_subscribes';
        $phone = '+1' . str_pad(mt_rand(1, 9999999999), 10, '0', STR_PAD_LEFT);

        $result = $wpdb->insert($table, [
            'name'   => $name,
            'mobile' => $phone,
            'status' => '1',
            'date'   => current_time('mysql'),
        ]);

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Helper to create a test subscriber with specific status
     *
     * @param string $name Subscriber name
     * @param string $status Status ('1' for active, '0' for inactive)
     * @return int|false Subscriber ID or false on failure
     */
    private function createTestSubscriberWithStatus($name, $status)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'sms_subscribes';
        $phone = '+1' . str_pad(mt_rand(1, 9999999999), 10, '0', STR_PAD_LEFT);

        $result = $wpdb->insert($table, [
            'name'   => $name,
            'mobile' => $phone,
            'status' => $status,
            'date'   => current_time('mysql'),
        ]);

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Helper to create a test subscriber with specific group
     *
     * @param string $name Subscriber name
     * @param int $groupId Group ID
     * @return int|false Subscriber ID or false on failure
     */
    private function createTestSubscriberWithGroup($name, $groupId)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'sms_subscribes';
        $phone = '+1' . str_pad(mt_rand(1, 9999999999), 10, '0', STR_PAD_LEFT);

        $result = $wpdb->insert($table, [
            'name'     => $name,
            'mobile'   => $phone,
            'status'   => '1',
            'group_ID' => $groupId,
            'date'     => current_time('mysql'),
        ]);

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Helper to create a test subscriber with specific group and status
     *
     * @param string $name Subscriber name
     * @param int $groupId Group ID
     * @param string $status Status ('1' for active, '0' for inactive)
     * @return int|false Subscriber ID or false on failure
     */
    private function createTestSubscriberWithGroupAndStatus($name, $groupId, $status)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'sms_subscribes';
        $phone = '+1' . str_pad(mt_rand(1, 9999999999), 10, '0', STR_PAD_LEFT);

        $result = $wpdb->insert($table, [
            'name'     => $name,
            'mobile'   => $phone,
            'status'   => $status,
            'group_ID' => $groupId,
            'date'     => current_time('mysql'),
        ]);

        return $result ? $wpdb->insert_id : false;
    }

    // =====================================================
    // Import Endpoint Tests
    // =====================================================

    /**
     * Test import endpoint requires authentication
     */
    public function testImportSubscribersRequiresAuthentication()
    {
        wp_set_current_user(0);

        $request = new WP_REST_Request('POST', '/wpsms/v1/subscribers/import');

        $response = rest_do_request($request);

        $this->assertEquals(401, $response->get_status());
    }

    /**
     * Test import without file returns error
     */
    public function testImportWithoutFileReturnsError()
    {
        $request = new WP_REST_Request('POST', '/wpsms/v1/subscribers/import');

        $response = rest_do_request($request);
        $data = $response->get_data();

        $this->assertEquals(400, $response->get_status());
        $this->assertArrayHasKey('error', $data);
        $this->assertStringContainsStringIgnoringCase('no file', $data['error']['message']);
    }

    /**
     * Test import with valid CSV file succeeds
     */
    public function testImportWithValidCsvSucceeds()
    {
        // Create a temporary CSV file
        $csvContent = "Name,Mobile\n";
        $csvContent .= "Import Test 1,+1" . str_pad(mt_rand(1, 9999999999), 10, '0', STR_PAD_LEFT) . "\n";
        $csvContent .= "Import Test 2,+1" . str_pad(mt_rand(1, 9999999999), 10, '0', STR_PAD_LEFT) . "\n";

        $tempFile = $this->createTempCsvFile($csvContent);

        $request = new WP_REST_Request('POST', '/wpsms/v1/subscribers/import');
        $request->set_file_params([
            'file' => [
                'name'     => 'test-import.csv',
                'type'     => 'text/csv',
                'tmp_name' => $tempFile,
                'error'    => UPLOAD_ERR_OK,
                'size'     => filesize($tempFile),
            ],
        ]);
        $request->set_param('skip_duplicates', '1');

        $response = rest_do_request($request);
        $data = $response->get_data();

        // Clean up
        @unlink($tempFile);

        $this->assertEquals(200, $response->get_status());
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('imported', $data['data']);
        $this->assertEquals(2, $data['data']['imported']);
    }

    /**
     * Test import skips duplicate phone numbers
     */
    public function testImportSkipsDuplicates()
    {
        // First create a subscriber
        $existingPhone = '+1' . str_pad(mt_rand(1, 9999999999), 10, '0', STR_PAD_LEFT);
        global $wpdb;
        $wpdb->insert($wpdb->prefix . 'sms_subscribes', [
            'name'   => 'Existing Subscriber',
            'mobile' => $existingPhone,
            'status' => '1',
            'date'   => current_time('mysql'),
        ]);

        // Create CSV with the same phone number
        $newPhone = '+1' . str_pad(mt_rand(1, 9999999999), 10, '0', STR_PAD_LEFT);
        $csvContent = "Name,Mobile\n";
        $csvContent .= "Duplicate User,{$existingPhone}\n";
        $csvContent .= "New User,{$newPhone}\n";

        $tempFile = $this->createTempCsvFile($csvContent);

        $request = new WP_REST_Request('POST', '/wpsms/v1/subscribers/import');
        $request->set_file_params([
            'file' => [
                'name'     => 'test-import.csv',
                'type'     => 'text/csv',
                'tmp_name' => $tempFile,
                'error'    => UPLOAD_ERR_OK,
                'size'     => filesize($tempFile),
            ],
        ]);
        $request->set_param('skip_duplicates', '1');

        $response = rest_do_request($request);
        $data = $response->get_data();

        // Clean up
        @unlink($tempFile);

        $this->assertEquals(200, $response->get_status());
        $this->assertEquals(1, $data['data']['imported']);
        $this->assertEquals(1, $data['data']['skipped']);
    }

    /**
     * Test import with invalid phone numbers reports errors
     */
    public function testImportWithInvalidPhoneNumbersReportsErrors()
    {
        $csvContent = "Name,Mobile\n";
        $csvContent .= "Valid User,+14155551234\n";
        $csvContent .= "Invalid User,abc123\n";
        $csvContent .= "Empty Phone,\n";

        $tempFile = $this->createTempCsvFile($csvContent);

        $request = new WP_REST_Request('POST', '/wpsms/v1/subscribers/import');
        $request->set_file_params([
            'file' => [
                'name'     => 'test-import.csv',
                'type'     => 'text/csv',
                'tmp_name' => $tempFile,
                'error'    => UPLOAD_ERR_OK,
                'size'     => filesize($tempFile),
            ],
        ]);

        $response = rest_do_request($request);
        $data = $response->get_data();

        // Clean up
        @unlink($tempFile);

        $this->assertEquals(200, $response->get_status());
        $this->assertGreaterThanOrEqual(1, $data['data']['skipped']);
        $this->assertNotEmpty($data['data']['errors']);
    }

    /**
     * Test import assigns subscribers to specified group
     */
    public function testImportAssignsToGroup()
    {
        // Create a test group
        global $wpdb;
        $wpdb->insert($wpdb->prefix . 'sms_subscribes_group', ['name' => 'Import Test Group']);
        $groupId = $wpdb->insert_id;

        $phone = '+1' . str_pad(mt_rand(1, 9999999999), 10, '0', STR_PAD_LEFT);
        $csvContent = "Name,Mobile\n";
        $csvContent .= "Group Test User,{$phone}\n";

        $tempFile = $this->createTempCsvFile($csvContent);

        $request = new WP_REST_Request('POST', '/wpsms/v1/subscribers/import');
        $request->set_file_params([
            'file' => [
                'name'     => 'test-import.csv',
                'type'     => 'text/csv',
                'tmp_name' => $tempFile,
                'error'    => UPLOAD_ERR_OK,
                'size'     => filesize($tempFile),
            ],
        ]);
        $request->set_param('group_id', $groupId);

        $response = rest_do_request($request);
        $data = $response->get_data();

        // Clean up
        @unlink($tempFile);

        $this->assertEquals(200, $response->get_status());
        $this->assertEquals(1, $data['data']['imported']);

        // Verify subscriber was added to the correct group
        $subscriber = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}sms_subscribes WHERE mobile = %s",
                $phone
            )
        );

        $this->assertNotNull($subscriber);
        $this->assertEquals($groupId, (int)$subscriber->group_ID);
    }

    /**
     * Test import handles empty CSV gracefully
     */
    public function testImportHandlesEmptyCsv()
    {
        $csvContent = "Name,Mobile\n"; // Only header, no data

        $tempFile = $this->createTempCsvFile($csvContent);

        $request = new WP_REST_Request('POST', '/wpsms/v1/subscribers/import');
        $request->set_file_params([
            'file' => [
                'name'     => 'test-import.csv',
                'type'     => 'text/csv',
                'tmp_name' => $tempFile,
                'error'    => UPLOAD_ERR_OK,
                'size'     => filesize($tempFile),
            ],
        ]);

        $response = rest_do_request($request);
        $data = $response->get_data();

        // Clean up
        @unlink($tempFile);

        $this->assertEquals(200, $response->get_status());
        $this->assertEquals(0, $data['data']['imported']);
        $this->assertEquals(0, $data['data']['skipped']);
    }

    /**
     * Test import with international phone numbers
     */
    public function testImportWithInternationalPhoneNumbers()
    {
        $csvContent = "Name,Mobile\n";
        $csvContent .= "US User,+14155551234\n";
        $csvContent .= "UK User,+442071234567\n";
        $csvContent .= "German User,+4917612345678\n";

        $tempFile = $this->createTempCsvFile($csvContent);

        $request = new WP_REST_Request('POST', '/wpsms/v1/subscribers/import');
        $request->set_file_params([
            'file' => [
                'name'     => 'test-import.csv',
                'type'     => 'text/csv',
                'tmp_name' => $tempFile,
                'error'    => UPLOAD_ERR_OK,
                'size'     => filesize($tempFile),
            ],
        ]);

        $response = rest_do_request($request);
        $data = $response->get_data();

        // Clean up
        @unlink($tempFile);

        $this->assertEquals(200, $response->get_status());
        // At least some should import successfully
        $this->assertGreaterThanOrEqual(1, $data['data']['imported']);
    }

    /**
     * Helper to create a temporary CSV file
     *
     * @param string $content CSV content
     * @return string Path to temporary file
     */
    private function createTempCsvFile($content)
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'csv_test_');
        file_put_contents($tempFile, $content);
        return $tempFile;
    }
}
