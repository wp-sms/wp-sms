<?php
use PHPUnit\Framework\TestCase;
use WP_SMS\RestEndpoints\Endpoints\V1\Settings\SaveSettingsEndpoint;
use WP_SMS\Settings\SchemaRegistry;
use WP_SMS\Settings\Option;
use WP_REST_Request;

class SaveSettingsEndpointTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Clean up any existing options before each test
        delete_option('wp_sms_settings');
        delete_option('wp_sms_pro_settings');
        delete_option('wp_sms_two_way_settings');
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        // Clean up after each test
        delete_option('wp_sms_settings');
        delete_option('wp_sms_pro_settings');
        delete_option('wp_sms_two_way_settings');
    }

    public function testHandleSavesValidInputWithAddon()
    {
        $group = SchemaRegistry::instance()->getGroup('pro_wordpress');
        $fields = $group->getFields();
        $key = $fields[0]->getKey();
        $validValue = $fields[0]->default ?? 'test_value';

        /** @var WP_REST_Request $mockRequest */
        $mockRequest = $this->createMock(WP_REST_Request::class);
        $mockRequest->method('get_json_params')->willReturn([
            'settings' => [$key => $validValue],
            'addon' => 'pro'
        ]);

        $result = SaveSettingsEndpoint::handle($mockRequest);
        $data = $result->get_data();
        $this->assertTrue($data['success']);
        $this->assertContains($key, $data['data']['saved_keys']);

        // Verify the option was saved to the correct addon option key
        $savedValue = Option::getOption($key, 'pro');
        $this->assertEquals($validValue, $savedValue);
    }

    public function testHandleSavesValidInputWithoutAddon()
    {
        $group = SchemaRegistry::instance()->getGroup('general');
        $fields = $group->getFields();
        $key = $fields[0]->getKey();
        $validValue = $fields[0]->default ?? 'test_value';

        /** @var WP_REST_Request $mockRequest */
        $mockRequest = $this->createMock(WP_REST_Request::class);
        $mockRequest->method('get_json_params')->willReturn([
            'settings' => [$key => $validValue],
            'addon' => null
        ]);

        $result = SaveSettingsEndpoint::handle($mockRequest);
        $data = $result->get_data();
        $this->assertTrue($data['success']);
        $this->assertContains($key, $data['data']['saved_keys']);

        // Verify the option was saved to the core option key
        $savedValue = Option::getOption($key);
        $this->assertEquals($validValue, $savedValue);
    }

    public function testHandleSavesValidInputWithLegacyFormat()
    {
        $group = SchemaRegistry::instance()->getGroup('general');
        $fields = $group->getFields();
        $key = $fields[0]->getKey();
        $validValue = $fields[0]->default ?? 'test_value';

        /** @var WP_REST_Request $mockRequest */
        $mockRequest = $this->createMock(WP_REST_Request::class);
        $mockRequest->method('get_json_params')->willReturn([$key => $validValue]);

        $result = SaveSettingsEndpoint::handle($mockRequest);
        $data = $result->get_data();
        $this->assertTrue($data['success']);
        $this->assertContains($key, $data['data']['saved_keys']);

        // Verify the option was saved to the core option key (backward compatibility)
        $savedValue = Option::getOption($key);
        $this->assertEquals($validValue, $savedValue);
    }

    public function testHandleReturnsErrorForInvalidInput()
    {
        $group = SchemaRegistry::instance()->getGroup('general');
        $fields = $group->getFields();
        $key = $fields[1]->getKey();
        $invalidValue = 'invalid_value_for_field_type';

        /** @var WP_REST_Request $mockRequest */
        $mockRequest = $this->createMock(WP_REST_Request::class);
        $mockRequest->method('get_json_params')->willReturn([
            'settings' => [$key => $invalidValue],
            'addon' => null
        ]);

        $result = SaveSettingsEndpoint::handle($mockRequest);
        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertEquals('invalid_settings', $result->get_error_code());
    }

    public function testHandleSavesMultipleValidKeysWithAddon()
    {
        $group = SchemaRegistry::instance()->getGroup('two_way');
        $fields = $group->getFields();
        $input = [];
        foreach (array_slice($fields, 0, 2) as $field) {
            $input[$field->getKey()] = $field->default ?? 'test_value';
        }

        /** @var WP_REST_Request $mockRequest */
        $mockRequest = $this->createMock(WP_REST_Request::class);
        $mockRequest->method('get_json_params')->willReturn([
            'settings' => $input,
            'addon' => 'two_way'
        ]);

        $result = SaveSettingsEndpoint::handle($mockRequest);
        $data = $result->get_data();
        $this->assertTrue($data['success']);
        $this->assertCount(2, $data['data']['saved_keys']);

        // Verify all options were saved to the correct addon option key
        foreach ($input as $key => $value) {
            $savedValue = Option::getOption($key, 'two_way');
            $this->assertEquals($value, $savedValue);
        }
    }

    public function testHandleSavesToCorrectAddonOptionKey()
    {
        // Use a valid field from the pro_wordpress group
        $group = SchemaRegistry::instance()->getGroup('pro_wordpress');
        $fields = $group->getFields();
        $key = $fields[0]->getKey();
        $value = $fields[0]->default ?? 'test_value';
        $addon = 'pro';

        /** @var WP_REST_Request $mockRequest */
        $mockRequest = $this->createMock(WP_REST_Request::class);
        $mockRequest->method('get_json_params')->willReturn([
            'settings' => [$key => $value],
            'addon' => $addon
        ]);

        $result = SaveSettingsEndpoint::handle($mockRequest);
        $data = $result->get_data();
        $this->assertTrue($data['success']);

        // Verify the option was saved to the correct addon option key
        $savedOptions = get_option('wp_sms_pro_settings');
        if ($savedOptions === false) {
            $savedOptions = [];
        }
        $this->assertIsArray($savedOptions);
        $this->assertEquals($value, $savedOptions[$key]);

        // Verify it's not in the core options
        $coreOptions = get_option('wp_sms_settings');
        if ($coreOptions === false) {
            $coreOptions = [];
        }
        $this->assertArrayNotHasKey($key, $coreOptions);
    }

    public function testHandleSavesToCoreOptionKeyWhenNoAddon()
    {
        // Use a valid field from the general group
        $group = SchemaRegistry::instance()->getGroup('general');
        $fields = $group->getFields();
        $key = $fields[0]->getKey();
        $value = $fields[0]->default ?? 'test_value';

        /** @var WP_REST_Request $mockRequest */
        $mockRequest = $this->createMock(WP_REST_Request::class);
        $mockRequest->method('get_json_params')->willReturn([
            'settings' => [$key => $value],
            'addon' => null
        ]);

        $result = SaveSettingsEndpoint::handle($mockRequest);
        $data = $result->get_data();
        $this->assertTrue($data['success']);

        // Verify the option was saved to the core option key
        $savedOptions = get_option('wp_sms_settings');
        if ($savedOptions === false) {
            $savedOptions = [];
        }
        $this->assertIsArray($savedOptions);
        $this->assertEquals($value, $savedOptions[$key]);
    }

    public function testHandleRemovesAddonFromSettingsBeforeValidation()
    {
        // Use a valid field from the pro_wordpress group
        $group = SchemaRegistry::instance()->getGroup('pro_wordpress');
        $fields = $group->getFields();
        $key = $fields[0]->getKey();
        $value = $fields[0]->default ?? 'test_value';

        /** @var WP_REST_Request $mockRequest */
        $mockRequest = $this->createMock(WP_REST_Request::class);
        $mockRequest->method('get_json_params')->willReturn([
            'settings' => [$key => $value, 'addon' => 'pro'],
            'addon' => 'pro'
        ]);

        $result = SaveSettingsEndpoint::handle($mockRequest);
        $data = $result->get_data();
        $this->assertTrue($data['success']);
        $this->assertContains($key, $data['data']['saved_keys']);

        // Verify 'addon' was removed from settings before saving
        $savedOptions = get_option('wp_sms_pro_settings');
        if ($savedOptions === false) {
            $savedOptions = [];
        }
        $this->assertArrayNotHasKey('addon', $savedOptions);
        $this->assertEquals($value, $savedOptions[$key]);
    }

    public function testHandleWithEmptySettingsArray()
    {
        /** @var WP_REST_Request $mockRequest */
        $mockRequest = $this->createMock(WP_REST_Request::class);
        $mockRequest->method('get_json_params')->willReturn([
            'settings' => [],
            'addon' => 'pro'
        ]);

        $result = SaveSettingsEndpoint::handle($mockRequest);
        $data = $result->get_data();
        $this->assertTrue($data['success']);
        $this->assertEmpty($data['data']['saved_keys']);
    }
} 