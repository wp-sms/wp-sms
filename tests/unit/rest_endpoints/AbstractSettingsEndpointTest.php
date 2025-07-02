<?php
use PHPUnit\Framework\TestCase;
use WP_SMS\RestEndpoints\Abstracts\AbstractSettingsEndpoint;
use WP_REST_Request;
use WP_Error;

class AbstractSettingsEndpointTest extends TestCase
{

    public function testSuccessReturnsCorrectResponse()
    {
        $ref = new \ReflectionClass(AbstractSettingsEndpoint::class);
        $method = $ref->getMethod('success');
        $method->setAccessible(true);
        $resp = $method->invoke(null, ['foo' => 'bar'], 201);
        $this->assertEquals(['success' => true, 'data' => ['foo' => 'bar']], $resp->get_data());
        $this->assertEquals(201, $resp->get_status());
    }

    public function testErrorReturnsCorrectResponse()
    {
        $ref = new \ReflectionClass(AbstractSettingsEndpoint::class);
        $method = $ref->getMethod('error');
        $method->setAccessible(true);
        $resp = $method->invoke(null, 'fail', 400, ['extra' => 1]);
        $this->assertEquals(['success' => false, 'message' => 'fail', 'extra' => 1], $resp->get_data());
        $this->assertEquals(400, $resp->get_status());
    }

    public function testValidationErrorReturnsWPError()
    {
        $ref = new \ReflectionClass(AbstractSettingsEndpoint::class);
        $method = $ref->getMethod('validation_error');
        $method->setAccessible(true);
        $err = $method->invoke(null, ['field' => 'bad'], 422);
        $this->assertInstanceOf(WP_Error::class, $err);
        $this->assertEquals('invalid_settings', $err->get_error_code());
        $this->assertEquals(422, $err->get_error_data()['status']);
        $this->assertEquals(['field' => 'bad'], $err->get_error_data()['fields']);
    }

    public function testGetJsonReturnsArray()
    {
        $ref = new \ReflectionClass(AbstractSettingsEndpoint::class);
        $method = $ref->getMethod('get_json');
        $method->setAccessible(true);
        $mockRequest = $this->createMock(WP_REST_Request::class);
        $mockRequest->method('get_json_params')->willReturn(['a' => 1]);
        $result = $method->invoke(null, $mockRequest);
        $this->assertEquals(['a' => 1], $result);
    }
} 