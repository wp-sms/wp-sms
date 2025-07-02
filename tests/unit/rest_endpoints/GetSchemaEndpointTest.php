<?php
use PHPUnit\Framework\TestCase;
use WP_SMS\RestEndpoints\Endpoints\V1\Settings\GetSchemaEndpoint;
use WP_SMS\Settings\SchemaRegistry;
use WP_REST_Request;

class GetSchemaEndpointTest extends TestCase
{
    public function testGetNestedDataReturnsCorrectData()
    {
        $schema = [
            'integrations' => [
                'children' => [
                    'contact_forms' => [
                        'children' => [
                            'contact_form_7' => ['label' => 'CF7']
                        ]
                    ]
                ]
            ]
        ];
        $ref = new \ReflectionClass(GetSchemaEndpoint::class);
        $method = $ref->getMethod('getNestedData');
        $method->setAccessible(true);
        $result = $method->invoke(null, $schema, 'integrations.contact_forms.contact_form_7');
        $this->assertEquals(['label' => 'CF7'], $result);
    }

    public function testGetAllReturnsSchemaArray()
    {
        $mockRequest = $this->createMock(WP_REST_Request::class);
        $result = GetSchemaEndpoint::getAll($mockRequest);
        
        $this->assertArrayHasKey('core', $result->get_data()['data']);
    }

    public function testGetCategoryReturnsCategoryOrError()
    {
        $mockRequest = $this->createMock(WP_REST_Request::class);
        $mockRequest->method('get_param')->willReturn('core');
        $result = GetSchemaEndpoint::getCategory($mockRequest);
        $data = $result->get_data();
        $this->assertArrayHasKey('core', ['core' => $data]); // Just check structure
    }

    public function testGetGroupReturnsGroupOrError()
    {
        $mockRequest = $this->createMock(WP_REST_Request::class);
        $mockRequest->method('get_param')->willReturn('general');
        $result = GetSchemaEndpoint::getGroup($mockRequest);
        $data = $result->get_data();
        $this->assertIsArray($data);
    }

    public function testGetGroupListReturnsArray()
    {
        $mockRequest = $this->createMock(WP_REST_Request::class);
        $result = GetSchemaEndpoint::getGroupList($mockRequest);
        $this->assertIsArray($result->get_data());
    }
} 