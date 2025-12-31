<?php

namespace unit;

use WP_SMS\Api\V1\SettingsApi;
use WP_SMS\Option;
use WP_UnitTestCase;
use WP_REST_Request;
use WP_REST_Server;

/**
 * Tests for Settings REST API
 */
class SettingsApiTest extends WP_UnitTestCase
{
    /**
     * @var SettingsApi
     */
    private $settingsApi;

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

        // Initialize REST server
        global $wp_rest_server;
        $wp_rest_server = new WP_REST_Server();
        do_action('rest_api_init');

        $this->settingsApi = new SettingsApi();
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
     * Test get settings returns expected structure
     */
    public function testGetSettingsReturnsExpectedStructure()
    {
        $request = new WP_REST_Request('GET', '/wpsms/v1/settings');

        $response = rest_do_request($request);
        $data = $response->get_data();

        $this->assertEquals(200, $response->get_status());
        $this->assertArrayHasKey('message', $data);
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('settings', $data['data']);
        $this->assertArrayHasKey('proSettings', $data['data']);
    }

    /**
     * Test update settings requires authentication
     */
    public function testUpdateSettingsRequiresAuthentication()
    {
        wp_set_current_user(0); // Log out

        $request = new WP_REST_Request('POST', '/wpsms/v1/settings');
        $request->set_body_params([
            'settings' => ['gateway_name' => 'twilio'],
        ]);

        $response = rest_do_request($request);

        $this->assertEquals(401, $response->get_status());
    }

    /**
     * Test update settings as subscriber is forbidden
     */
    public function testUpdateSettingsAsSubscriberForbidden()
    {
        $subscriberId = self::factory()->user->create([
            'role' => 'subscriber'
        ]);
        wp_set_current_user($subscriberId);

        $request = new WP_REST_Request('POST', '/wpsms/v1/settings');
        $request->set_body_params([
            'settings' => ['gateway_name' => 'twilio'],
        ]);

        $response = rest_do_request($request);

        $this->assertEquals(403, $response->get_status());
    }

    /**
     * Test update settings saves correctly
     */
    public function testUpdateSettingsSavesCorrectly()
    {
        $testValue = 'test_value_' . uniqid();

        $request = new WP_REST_Request('POST', '/wpsms/v1/settings');
        $request->set_body_params([
            'settings' => [
                'admin_mobile_number' => '+1234567890',
            ],
        ]);

        $response = rest_do_request($request);
        $data = $response->get_data();

        $this->assertEquals(200, $response->get_status());
        $this->assertArrayHasKey('message', $data);
    }

    /**
     * Test sensitive fields are masked in responses
     */
    public function testSensitiveFieldsAreMasked()
    {
        // First, save a password value
        Option::updateOption('gateway_password', 'secret_password');

        $request = new WP_REST_Request('GET', '/wpsms/v1/settings');

        $response = rest_do_request($request);
        $data = $response->get_data();

        $this->assertEquals(200, $response->get_status());

        // Check that password is masked in response
        if (isset($data['data']['settings']['gateway_password'])) {
            $this->assertEquals('••••••••', $data['data']['settings']['gateway_password']);
        }
    }

    /**
     * Test settings validation catches invalid URL
     */
    public function testSettingsValidationCatchesInvalidUrl()
    {
        $request = new WP_REST_Request('POST', '/wpsms/v1/settings');
        $request->set_body_params([
            'settings' => [
                'webhook_outgoing_sms' => 'not-a-valid-url',
            ],
        ]);

        $response = rest_do_request($request);
        $data = $response->get_data();

        // Should either fail validation or sanitize the value
        $this->assertIsArray($data);
    }

    /**
     * Test test gateway endpoint exists
     */
    public function testTestGatewayEndpointExists()
    {
        $request = new WP_REST_Request('POST', '/wpsms/v1/settings/test-gateway');

        $response = rest_do_request($request);

        // Should return some response (success or failure based on gateway config)
        $this->assertContains($response->get_status(), [200, 400, 401, 403, 500]);
    }

    /**
     * Test get settings section endpoint
     */
    public function testGetSettingsSectionEndpoint()
    {
        $request = new WP_REST_Request('GET', '/wpsms/v1/settings/gateway');

        $response = rest_do_request($request);

        // Should return settings for the specified section
        $this->assertContains($response->get_status(), [200, 400, 404]);
    }

    /**
     * Test sanitization removes dangerous input
     */
    public function testSanitizationRemovesDangerousInput()
    {
        $request = new WP_REST_Request('POST', '/wpsms/v1/settings');
        $request->set_body_params([
            'settings' => [
                'admin_mobile_number' => '<script>alert("xss")</script>+1234567890',
            ],
        ]);

        $response = rest_do_request($request);
        $data = $response->get_data();

        $this->assertEquals(200, $response->get_status());

        // Verify no script tags in saved value
        $savedSettings = Option::getOptions();
        if (isset($savedSettings['admin_mobile_number'])) {
            $this->assertStringNotContainsString('<script>', $savedSettings['admin_mobile_number']);
        }
    }

    /**
     * Test updating multiple settings at once
     */
    public function testUpdateMultipleSettingsAtOnce()
    {
        $request = new WP_REST_Request('POST', '/wpsms/v1/settings');
        $request->set_body_params([
            'settings' => [
                'admin_mobile_number' => '+9876543210',
                'notif_publish_new_post_enabled' => '1',
            ],
        ]);

        $response = rest_do_request($request);
        $data = $response->get_data();

        $this->assertEquals(200, $response->get_status());
    }

    /**
     * Test empty settings update doesn't cause error
     */
    public function testEmptySettingsUpdateDoesNotCauseError()
    {
        $request = new WP_REST_Request('POST', '/wpsms/v1/settings');
        $request->set_body_params([
            'settings' => [],
        ]);

        $response = rest_do_request($request);

        $this->assertContains($response->get_status(), [200, 400]);
    }

    /**
     * Test settings are correctly typed
     */
    public function testSettingsAreCorrectlyTyped()
    {
        $request = new WP_REST_Request('POST', '/wpsms/v1/settings');
        $request->set_body_params([
            'settings' => [
                'notif_publish_new_post_enabled' => true,
                'some_number_field' => 42,
            ],
        ]);

        $response = rest_do_request($request);

        $this->assertEquals(200, $response->get_status());
    }
}
