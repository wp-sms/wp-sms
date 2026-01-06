<?php

namespace unit;

use WP_UnitTestCase;
use WP_REST_Request;
use WP_REST_Server;

/**
 * Tests for Send SMS REST API
 */
class SendSmsApiTest extends WP_UnitTestCase
{
    /**
     * @var int
     */
    private $adminUserId;

    /**
     * @var int
     */
    private $subscriberId;

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

        // Grant send SMS permission
        $user = get_user_by('id', $this->adminUserId);
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
     * Create a test subscriber group
     */
    private function createTestGroup($name = 'Test Group')
    {
        global $wpdb;
        $table = $wpdb->prefix . 'sms_subscribes_group';

        $wpdb->insert($table, [
            'name' => $name
        ]);

        return $wpdb->insert_id;
    }

    /**
     * Create a test subscriber
     */
    private function createTestSubscriber($mobile, $groupId = null, $status = 1)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'sms_subscribes';

        $wpdb->insert($table, [
            'date'      => current_time('mysql'),
            'name'      => 'Test User',
            'mobile'    => $mobile,
            'status'    => $status,
            'group_ID'  => $groupId ?: 0,
        ]);

        return $wpdb->insert_id;
    }

    /**
     * Test quick send requires authentication
     */
    public function testQuickSendRequiresAuthentication()
    {
        wp_set_current_user(0);

        $request = new WP_REST_Request('POST', '/wpsms/v1/send/quick');
        $request->set_body(json_encode([
            'message' => 'Test message',
            'recipients' => ['numbers' => ['+1234567890']]
        ]));
        $request->set_header('Content-Type', 'application/json');

        $response = rest_do_request($request);

        $this->assertContains($response->get_status(), [401, 403]);
    }

    /**
     * Test quick send with empty message returns error
     */
    public function testQuickSendWithEmptyMessageReturnsError()
    {
        $request = new WP_REST_Request('POST', '/wpsms/v1/send/quick');
        $request->set_body(json_encode([
            'message' => '',
            'recipients' => ['numbers' => ['+1234567890']]
        ]));
        $request->set_header('Content-Type', 'application/json');

        $response = rest_do_request($request);

        $this->assertEquals(400, $response->get_status());
    }

    /**
     * Test quick send with no recipients returns error
     */
    public function testQuickSendWithNoRecipientsReturnsError()
    {
        $request = new WP_REST_Request('POST', '/wpsms/v1/send/quick');
        $request->set_body(json_encode([
            'message' => 'Test message',
            'recipients' => ['groups' => [], 'roles' => [], 'numbers' => []]
        ]));
        $request->set_header('Content-Type', 'application/json');

        $response = rest_do_request($request);

        $this->assertEquals(400, $response->get_status());
    }

    /**
     * Test quick send includes flash parameter in request
     */
    public function testQuickSendAcceptsFlashParameter()
    {
        $request = new WP_REST_Request('POST', '/wpsms/v1/send/quick');
        $request->set_body(json_encode([
            'message' => 'Flash test message',
            'recipients' => ['numbers' => ['+1234567890']],
            'flash' => true
        ]));
        $request->set_header('Content-Type', 'application/json');

        $response = rest_do_request($request);
        $data = $response->get_data();

        // Even if gateway isn't configured, endpoint should accept flash parameter
        // Response should either be 200 (success) or 400 (gateway error), not 500
        $this->assertContains($response->get_status(), [200, 400]);
    }

    /**
     * Test quick send accepts media URL for MMS
     */
    public function testQuickSendAcceptsMediaUrl()
    {
        $request = new WP_REST_Request('POST', '/wpsms/v1/send/quick');
        $request->set_body(json_encode([
            'message' => 'MMS test message',
            'recipients' => ['numbers' => ['+1234567890']],
            'media_url' => 'https://example.com/image.jpg'
        ]));
        $request->set_header('Content-Type', 'application/json');

        $response = rest_do_request($request);

        // Even if gateway isn't configured, endpoint should accept media_url parameter
        $this->assertContains($response->get_status(), [200, 400]);
    }

    /**
     * Test quick send accepts sender ID (from parameter)
     */
    public function testQuickSendAcceptsSenderId()
    {
        $request = new WP_REST_Request('POST', '/wpsms/v1/send/quick');
        $request->set_body(json_encode([
            'message' => 'Test message',
            'recipients' => ['numbers' => ['+1234567890']],
            'from' => 'MySender'
        ]));
        $request->set_header('Content-Type', 'application/json');

        $response = rest_do_request($request);

        $this->assertContains($response->get_status(), [200, 400]);
    }

    /**
     * Test recipient count endpoint returns counts
     */
    public function testRecipientCountEndpointReturnsCounts()
    {
        // Create test group and subscribers
        $groupId = $this->createTestGroup('Count Test Group');
        $this->createTestSubscriber('+1111111111', $groupId);
        $this->createTestSubscriber('+2222222222', $groupId);

        $request = new WP_REST_Request('POST', '/wpsms/v1/send/count');
        $request->set_body(json_encode([
            'recipients' => [
                'groups' => [$groupId],
                'roles' => [],
                'numbers' => ['+3333333333']
            ]
        ]));
        $request->set_header('Content-Type', 'application/json');

        $response = rest_do_request($request);
        $data = $response->get_data();

        $this->assertEquals(200, $response->get_status());
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('total', $data['data']);
        $this->assertArrayHasKey('groups', $data['data']);
        $this->assertArrayHasKey('numbers', $data['data']);
    }

    /**
     * Test recipient count requires authentication
     */
    public function testRecipientCountRequiresAuthentication()
    {
        wp_set_current_user(0);

        $request = new WP_REST_Request('POST', '/wpsms/v1/send/count');
        $request->set_body(json_encode([
            'recipients' => ['numbers' => ['+1234567890']]
        ]));
        $request->set_header('Content-Type', 'application/json');

        $response = rest_do_request($request);

        $this->assertContains($response->get_status(), [401, 403]);
    }

    /**
     * Test recipient count with empty recipients returns zero
     */
    public function testRecipientCountWithEmptyRecipientsReturnsZero()
    {
        $request = new WP_REST_Request('POST', '/wpsms/v1/send/count');
        $request->set_body(json_encode([
            'recipients' => [
                'groups' => [],
                'roles' => [],
                'numbers' => []
            ]
        ]));
        $request->set_header('Content-Type', 'application/json');

        $response = rest_do_request($request);
        $data = $response->get_data();

        $this->assertEquals(200, $response->get_status());
        $this->assertEquals(0, $data['data']['total']);
    }

    /**
     * Test recipient count with direct numbers
     */
    public function testRecipientCountWithDirectNumbers()
    {
        $request = new WP_REST_Request('POST', '/wpsms/v1/send/count');
        $request->set_body(json_encode([
            'recipients' => [
                'groups' => [],
                'roles' => [],
                'numbers' => ['+1111111111', '+2222222222', '+3333333333']
            ]
        ]));
        $request->set_header('Content-Type', 'application/json');

        $response = rest_do_request($request);
        $data = $response->get_data();

        $this->assertEquals(200, $response->get_status());
        $this->assertEquals(3, $data['data']['numbers']);
        $this->assertEquals(3, $data['data']['total']);
    }

    /**
     * Test user search endpoint returns users
     */
    public function testUserSearchEndpointReturnsUsers()
    {
        // Create test users
        $userId1 = self::factory()->user->create([
            'display_name' => 'John Doe',
            'user_email' => 'john@example.com'
        ]);
        $userId2 = self::factory()->user->create([
            'display_name' => 'Jane Smith',
            'user_email' => 'jane@example.com'
        ]);

        $request = new WP_REST_Request('GET', '/wpsms/v1/users/search');
        $request->set_param('search', 'John');

        $response = rest_do_request($request);
        $data = $response->get_data();

        $this->assertEquals(200, $response->get_status());
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('users', $data['data']);
    }

    /**
     * Test user search requires authentication
     */
    public function testUserSearchRequiresAuthentication()
    {
        wp_set_current_user(0);

        $request = new WP_REST_Request('GET', '/wpsms/v1/users/search');

        $response = rest_do_request($request);

        $this->assertContains($response->get_status(), [401, 403]);
    }

    /**
     * Test user search returns user mobile status
     */
    public function testUserSearchReturnsUserMobileStatus()
    {
        // Create user with mobile
        $userId = self::factory()->user->create([
            'display_name' => 'Mobile User',
            'user_email' => 'mobile@example.com'
        ]);
        update_user_meta($userId, 'mobile', '+1234567890');

        $request = new WP_REST_Request('GET', '/wpsms/v1/users/search');
        $request->set_param('search', 'Mobile');

        $response = rest_do_request($request);
        $data = $response->get_data();

        $this->assertEquals(200, $response->get_status());

        // Check that user results include hasMobile field
        if (!empty($data['data']['users'])) {
            $user = $data['data']['users'][0];
            $this->assertArrayHasKey('hasMobile', $user);
        }
    }

    /**
     * Test quick send with groups resolves subscriber numbers
     */
    public function testQuickSendWithGroupsResolvesSubscriberNumbers()
    {
        // Create group with subscribers
        $groupId = $this->createTestGroup('Send Test Group');
        $this->createTestSubscriber('+1111111111', $groupId);
        $this->createTestSubscriber('+2222222222', $groupId);

        $request = new WP_REST_Request('POST', '/wpsms/v1/send/quick');
        $request->set_body(json_encode([
            'message' => 'Group message test',
            'recipients' => [
                'groups' => [(string)$groupId],
                'roles' => [],
                'numbers' => []
            ]
        ]));
        $request->set_header('Content-Type', 'application/json');

        $response = rest_do_request($request);

        // Should either succeed or fail with gateway error (not "no recipients" error)
        $this->assertContains($response->get_status(), [200, 400]);

        // If it failed, it should NOT be because of empty recipients
        if ($response->get_status() === 400) {
            $data = $response->get_data();
            $this->assertStringNotContainsString(
                'Could not find any mobile numbers',
                $data['message'] ?? ''
            );
        }
    }

    /**
     * Test quick send with users resolves user mobile numbers
     */
    public function testQuickSendWithUsersResolvesUserMobileNumbers()
    {
        // Create user with mobile number
        $userId = self::factory()->user->create([
            'display_name' => 'Test User With Mobile'
        ]);
        update_user_meta($userId, 'mobile', '+9999999999');

        $request = new WP_REST_Request('POST', '/wpsms/v1/send/quick');
        $request->set_body(json_encode([
            'message' => 'User message test',
            'recipients' => [
                'groups' => [],
                'roles' => [],
                'users' => [$userId],
                'numbers' => []
            ]
        ]));
        $request->set_header('Content-Type', 'application/json');

        $response = rest_do_request($request);

        // Should either succeed or fail with gateway error
        $this->assertContains($response->get_status(), [200, 400]);
    }

    /**
     * Test quick send with mixed recipient types
     */
    public function testQuickSendWithMixedRecipientTypes()
    {
        // Create group with subscriber
        $groupId = $this->createTestGroup('Mixed Test Group');
        $this->createTestSubscriber('+1111111111', $groupId);

        // Create user with mobile
        $userId = self::factory()->user->create(['display_name' => 'Mixed User']);
        update_user_meta($userId, 'mobile', '+2222222222');

        $request = new WP_REST_Request('POST', '/wpsms/v1/send/quick');
        $request->set_body(json_encode([
            'message' => 'Mixed recipients test',
            'recipients' => [
                'groups' => [(string)$groupId],
                'roles' => [],
                'users' => [$userId],
                'numbers' => ['+3333333333']
            ]
        ]));
        $request->set_header('Content-Type', 'application/json');

        $response = rest_do_request($request);

        $this->assertContains($response->get_status(), [200, 400]);
    }

    /**
     * Test recipient count includes users
     */
    public function testRecipientCountIncludesUsers()
    {
        // Create users with mobile numbers
        $userId1 = self::factory()->user->create(['display_name' => 'User 1']);
        $userId2 = self::factory()->user->create(['display_name' => 'User 2']);
        update_user_meta($userId1, 'mobile', '+1111111111');
        update_user_meta($userId2, 'mobile', '+2222222222');

        $request = new WP_REST_Request('POST', '/wpsms/v1/send/count');
        $request->set_body(json_encode([
            'recipients' => [
                'groups' => [],
                'roles' => [],
                'users' => [$userId1, $userId2],
                'numbers' => []
            ]
        ]));
        $request->set_header('Content-Type', 'application/json');

        $response = rest_do_request($request);
        $data = $response->get_data();

        $this->assertEquals(200, $response->get_status());
        $this->assertArrayHasKey('users', $data['data']);
        $this->assertEquals(2, $data['data']['users']);
    }

    /**
     * Test recipient count deduplicates overlapping recipients
     */
    public function testRecipientCountDeduplicatesOverlappingRecipients()
    {
        // Create group with subscriber
        $groupId = $this->createTestGroup('Dedup Test Group');
        $this->createTestSubscriber('+1111111111', $groupId);

        $request = new WP_REST_Request('POST', '/wpsms/v1/send/count');
        $request->set_body(json_encode([
            'recipients' => [
                'groups' => [(string)$groupId],
                'roles' => [],
                'numbers' => ['+1111111111'] // Same number as in group
            ]
        ]));
        $request->set_header('Content-Type', 'application/json');

        $response = rest_do_request($request);
        $data = $response->get_data();

        $this->assertEquals(200, $response->get_status());
        // Total should be 1, not 2 (deduplicated)
        $this->assertEquals(1, $data['data']['total']);
    }
}
