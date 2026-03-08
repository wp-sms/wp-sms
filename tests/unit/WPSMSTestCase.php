<?php

namespace unit;

use WP_UnitTestCase;
use WP_REST_Server;

/**
 * Base Test Case for WP SMS Plugin Tests
 *
 * This class provides common setup for API tests including
 * proper authentication and capability assignment.
 */
abstract class WPSMSTestCase extends WP_UnitTestCase
{
    /**
     * @var int
     */
    protected $adminUserId;

    /**
     * WP SMS custom capabilities
     * @var array
     */
    protected static $wpsmsCaps = [
        'wpsms_setting',
        'wpsms_subscribers',
        'wpsms_outbox',
        'wpsms_sendsms',
    ];

    /**
     * Set up test environment with proper authentication
     */
    public function setUp(): void
    {
        parent::setUp();

        // Add WP SMS capabilities to administrator role
        $adminRole = get_role('administrator');
        if ($adminRole) {
            foreach (self::$wpsmsCaps as $cap) {
                $adminRole->add_cap($cap);
            }
        }

        // Create admin user
        $this->adminUserId = self::factory()->user->create([
            'role' => 'administrator'
        ]);
        wp_set_current_user($this->adminUserId);

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
        wp_set_current_user(0);

        // Remove WP SMS capabilities from administrator role
        $adminRole = get_role('administrator');
        if ($adminRole) {
            foreach (self::$wpsmsCaps as $cap) {
                $adminRole->remove_cap($cap);
            }
        }

        parent::tearDown();
    }

    /**
     * Helper to create a subscriber user without WP SMS capabilities
     *
     * @return int User ID
     */
    protected function createSubscriberUser()
    {
        return self::factory()->user->create([
            'role' => 'subscriber'
        ]);
    }

    /**
     * Helper to simulate unauthenticated request
     */
    protected function actAsGuest()
    {
        wp_set_current_user(0);
    }

    /**
     * Helper to restore admin user context
     */
    protected function actAsAdmin()
    {
        wp_set_current_user($this->adminUserId);
    }

    /**
     * Helper to create a JSON REST request
     *
     * @param string $method HTTP method (GET, POST, PUT, DELETE)
     * @param string $route API route
     * @param array $data Request body data
     * @return \WP_REST_Request
     */
    protected function createJsonRequest($method, $route, $data = [])
    {
        $request = new \WP_REST_Request($method, $route);
        if (!empty($data)) {
            $request->set_header('Content-Type', 'application/json');
            $request->set_body(wp_json_encode($data));
        }
        return $request;
    }
}
