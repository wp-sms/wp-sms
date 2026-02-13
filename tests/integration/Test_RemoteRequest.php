<?php

namespace WP_SMS\Tests\Components;

use Exception;
use WP_SMS\Components\RemoteRequest;
use WP_UnitTestCase;
use WP_Error;

class Test_RemoteRequest extends WP_UnitTestCase
{
    /**
     * Set up mock functions and filters.
     */
    public function setUp(): void
    {
        parent::setUp();

        // Mock the 'wp_sms_request_arguments' filter
        add_filter('wp_sms_request_arguments', function ($args) {
            return $args;
        });

        // Mock the 'wp_sms_request_params' filter
        add_filter('wp_sms_request_params', function ($params) {
            return $params;
        });
    }

    /**
     * Test the constructor and request URL generation.
     */
    public function test_constructor_and_requestUrl_generation()
    {
        $method = 'GET';
        $url = 'https://example.com/api';
        $arguments = ['key' => 'value'];
        $params = ['timeout' => 5];

        $remoteRequest = new RemoteRequest($method, $url, $arguments, $params);

        // Assert the request URL is generated correctly
        $expectedUrl = add_query_arg($arguments, $url);
        $this->assertEquals($expectedUrl, $remoteRequest->getRequestUrl());
    }

    /**
     * Test that custom cache key uses direct get_transient instead of trait method.
     */
    public function test_execute_with_custom_cache_key_reads_from_direct_transient()
    {
        $method = 'GET';
        $url = 'https://example.com/api';
        $arguments = [];
        $params = [];
        $customCacheKey = 'wp_sms_custom_cache_key_123';
        $cachedData = (object) ['data' => 'cached_value'];

        // Set transient directly (simulating cached data)
        set_transient($customCacheKey, $cachedData, HOUR_IN_SECONDS);

        $remoteRequest = new RemoteRequest($method, $url, $arguments, $params);

        // Execute with custom cache key - should return cached data without making request
        $result = $remoteRequest->execute(true, true, HOUR_IN_SECONDS, $customCacheKey);

        $this->assertEquals($cachedData, $result);

        // Clean up
        delete_transient($customCacheKey);
    }

    /**
     * Test that custom cache key uses direct set_transient instead of trait method.
     */
    public function test_execute_with_custom_cache_key_writes_to_direct_transient()
    {
        $method = 'GET';
        $url = 'https://example.com/api';
        $arguments = [];
        $params = [];
        $customCacheKey = 'wp_sms_custom_cache_key_456';

        // Mock wp_remote_request response
        $mockResponse = [
            'response' => ['code' => 200],
            'body' => json_encode(['success' => true, 'data' => 'test'])
        ];

        add_filter('pre_http_request', function () use ($mockResponse) {
            return $mockResponse;
        });

        $remoteRequest = new RemoteRequest($method, $url, $arguments, $params);

        // Execute with custom cache key
        $result = $remoteRequest->execute(true, true, DAY_IN_SECONDS, $customCacheKey);

        // Verify data was cached using direct transient (not trait method which transforms key)
        $cachedValue = get_transient($customCacheKey);
        $this->assertNotFalse($cachedValue);
        $this->assertEquals(json_decode($mockResponse['body']), $cachedValue);

        // Clean up
        delete_transient($customCacheKey);
    }

    /**
     * Test that custom cache key does NOT use trait's getCachedResult (which transforms key).
     */
    public function test_execute_with_custom_cache_key_does_not_use_trait_cache_methods()
    {
        $method = 'GET';
        $url = 'https://example.com/api';
        $arguments = [];
        $params = [];
        $customCacheKey = 'wp_sms_custom_cache_key_789';

        // Mock wp_remote_request response
        $mockResponse = [
            'response' => ['code' => 200],
            'body' => json_encode(['success' => true])
        ];

        add_filter('pre_http_request', function () use ($mockResponse) {
            return $mockResponse;
        });

        $remoteRequest = $this->getMockBuilder(RemoteRequest::class)
            ->setConstructorArgs([$method, $url, $arguments, $params])
            ->onlyMethods(['getCachedResult', 'setCachedResult'])
            ->getMock();

        // Trait methods should NOT be called when custom cache key is provided
        $remoteRequest->expects($this->never())
            ->method('getCachedResult');

        $remoteRequest->expects($this->never())
            ->method('setCachedResult');

        // Execute with custom cache key
        $result = $remoteRequest->execute(true, true, HOUR_IN_SECONDS, $customCacheKey);

        $this->assertEquals(json_decode($mockResponse['body']), $result);

        // Clean up
        delete_transient($customCacheKey);
    }

    /**
     * Test that without custom cache key, trait methods are still used (backward compatibility).
     */
    public function test_execute_without_custom_cache_key_uses_trait_methods()
    {
        $method = 'GET';
        $url = 'https://example.com/api';
        $arguments = [];
        $params = [];

        $remoteRequest = $this->getMockBuilder(RemoteRequest::class)
            ->setConstructorArgs([$method, $url, $arguments, $params])
            ->onlyMethods(['getCachedResult', 'setCachedResult', 'generateCacheKey'])
            ->getMock();

        // Mock cache key generation
        $remoteRequest->expects($this->atLeastOnce())
            ->method('generateCacheKey')
            ->willReturn('auto_generated_key');

        // Trait's getCachedResult should be called when no custom cache key
        $remoteRequest->expects($this->once())
            ->method('getCachedResult')
            ->with('auto_generated_key')
            ->willReturn('cached_from_trait');

        // Execute without custom cache key (null = default)
        $result = $remoteRequest->execute(true, true, HOUR_IN_SECONDS, null);

        $this->assertEquals('cached_from_trait', $result);
    }

    /**
     * Test the execute method throws an exception on WP_Error.
     */
    public function test_execute_throws_exception_on_wp_error()
    {
        $this->expectException(Exception::class);

        $method = 'GET';
        $url = 'https://example.com/api';
        $arguments = [];
        $params = [];

        // Mock wp_remote_request to return a WP_Error
        add_filter('pre_http_request', function () {
            return new WP_Error('http_error', 'An error occurred');
        });

        $remoteRequest = new RemoteRequest($method, $url, $arguments, $params);

        // Execute and expect an exception to be thrown
        $remoteRequest->execute(true, false);
    }

    /**
     * Test the execute method throws an exception on non-200 status code.
     */
    public function test_execute_throws_exception_on_failed_status_code()
    {
        $this->expectException(Exception::class);

        $method = 'GET';
        $url = 'https://example.com/api';
        $arguments = [];
        $params = [];

        // Mock wp_remote_request to return a response with a failed status code
        add_filter('pre_http_request', function () {
            return [
                'response' => ['code' => 500],
                'body' => 'Internal Server Error'
            ];
        });

        $remoteRequest = new RemoteRequest($method, $url, $arguments, $params);

        // Execute and expect an exception due to failed HTTP code
        $remoteRequest->execute(true, false);
    }
}
