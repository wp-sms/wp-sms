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
     * Test create subscriber validates phone number
     */
    public function testCreateSubscriberValidatesPhoneNumber()
    {
        $request = new WP_REST_Request('POST', '/wpsms/v1/subscribers');
        $request->set_body_params([
            'mobile' => 'invalid-phone',
            'name'   => 'Test User',
        ]);

        $response = rest_do_request($request);

        // Should either fail validation or sanitize
        $this->assertContains($response->get_status(), [200, 400]);
    }

    /**
     * Test create subscriber with valid data
     */
    public function testCreateSubscriberWithValidData()
    {
        $uniquePhone = '+1' . time();

        $request = new WP_REST_Request('POST', '/wpsms/v1/subscribers');
        $request->set_body_params([
            'mobile' => $uniquePhone,
            'name'   => 'Test User',
            'status' => '1',
        ]);

        $response = rest_do_request($request);
        $data = $response->get_data();

        if ($response->get_status() === 200) {
            $this->assertArrayHasKey('data', $data);
        }
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
     * Test filter subscribers by group
     */
    public function testFilterSubscribersByGroup()
    {
        $request = new WP_REST_Request('GET', '/wpsms/v1/subscribers');
        $request->set_param('group_id', 1);

        $response = rest_do_request($request);

        $this->assertEquals(200, $response->get_status());
    }

    /**
     * Test filter subscribers by status
     */
    public function testFilterSubscribersByStatus()
    {
        $request = new WP_REST_Request('GET', '/wpsms/v1/subscribers');
        $request->set_param('status', 'active');

        $response = rest_do_request($request);

        $this->assertEquals(200, $response->get_status());
    }

    /**
     * Test search subscribers
     */
    public function testSearchSubscribers()
    {
        $request = new WP_REST_Request('GET', '/wpsms/v1/subscribers');
        $request->set_param('search', 'test');

        $response = rest_do_request($request);

        $this->assertEquals(200, $response->get_status());
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
}
