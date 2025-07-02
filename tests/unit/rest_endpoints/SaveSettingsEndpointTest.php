<?php
use PHPUnit\Framework\TestCase;
use WP_SMS\RestEndpoints\Endpoints\V1\Settings\SaveSettingsEndpoint;
use WP_SMS\Settings\SchemaRegistry;
use WP_REST_Request;

class SaveSettingsEndpointTest extends TestCase
{
    public function testHandleSavesValidInput()
    {
        $group = SchemaRegistry::instance()->getGroup('general');
        $fields = $group->getFields();
        $key = $fields[0]->getKey();
        $validValue = $fields[0]->default ?? 'test_value';

        $mockRequest = $this->createMock(WP_REST_Request::class);
        $mockRequest->method('get_json_params')->willReturn([$key => $validValue]);

        $result = SaveSettingsEndpoint::handle($mockRequest);
        $data = $result->get_data();
        $this->assertTrue($data['success']);
        $this->assertContains($key, $data['data']['saved_keys']);
    }

    public function testHandleReturnsErrorForInvalidInput()
    {
        $group = SchemaRegistry::instance()->getGroup('general');
        $fields = $group->getFields();
        $key = $fields[1]->getKey();
        $invalidValue = 'invalid_value_for_field_type';

        $mockRequest = $this->createMock(WP_REST_Request::class);
        $mockRequest->method('get_json_params')->willReturn([$key => $invalidValue]);

        $result = SaveSettingsEndpoint::handle($mockRequest);
        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertEquals('invalid_settings', $result->get_error_code());
    }

    public function testHandleSavesMultipleValidKeys()
    {
        $group = SchemaRegistry::instance()->getGroup('general');
        $fields = $group->getFields();
        $input = [];
        foreach (array_slice($fields, 0, 2) as $field) {
            $input[$field->getKey()] = $field->default ?? 'add_mobile_field_in_profile';
        }

        $mockRequest = $this->createMock(WP_REST_Request::class);
        $mockRequest->method('get_json_params')->willReturn($input);

        $result = SaveSettingsEndpoint::handle($mockRequest);
        $data = $result->get_data();
        $this->assertTrue($data['success']);
        $this->assertCount(2, $data['data']['saved_keys']);
    }
} 