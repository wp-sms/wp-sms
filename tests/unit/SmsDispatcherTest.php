<?php

namespace unit;

use WP_SMS\BackgroundProcess\SmsDispatcher;
use WP_SMS\Option;

require_once __DIR__ . '/WPSMSTestCase.php';

/**
 * Tests for SMS Dispatcher and Delivery Methods
 *
 * Tests the three delivery methods:
 * - api_direct_send: Immediate dispatch via Sms::send()
 * - api_async_send: Background async request
 * - api_queued_send: Queued batch processing
 */
class SmsDispatcherTest extends WPSMSTestCase
{
    /**
     * Set up test environment
     */
    public function setUp(): void
    {
        parent::setUp();

        // Configure test gateway
        Option::updateOption('gateway_name', 'test');
        Option::updateOption('gateway_sender_id', 'TestSender');
        Option::updateOption('store_outbox_messages', true);

        // Reinitialize gateway
        $GLOBALS['sms'] = \WP_SMS\Gateway::initial();
    }

    /**
     * Test: Direct send method dispatches immediately
     */
    public function testDirectSendDispatchesImmediately()
    {
        Option::updateOption('sms_delivery_method', 'api_direct_send');

        $dispatcher = new SmsDispatcher(
            ['+15551234567'],
            'Test direct send message'
        );

        $result = $dispatcher->dispatch();

        // Direct send should return a response (not true for queue)
        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
    }

    /**
     * Test: Direct send applies wp_sms filters
     */
    public function testDirectSendAppliesFilters()
    {
        Option::updateOption('sms_delivery_method', 'api_direct_send');

        $filterCalled = false;
        add_filter('wp_sms_msg', function ($msg) use (&$filterCalled) {
            $filterCalled = true;
            return $msg;
        });

        $dispatcher = new SmsDispatcher(
            ['+15551234567'],
            'Test filter message'
        );

        $dispatcher->dispatch();

        $this->assertTrue($filterCalled, 'wp_sms_msg filter should be called for direct send');

        remove_all_filters('wp_sms_msg');
    }

    /**
     * Test: Direct send fires wp_sms_send action
     */
    public function testDirectSendFiresAction()
    {
        Option::updateOption('sms_delivery_method', 'api_direct_send');

        $actionFired = false;
        add_action('wp_sms_send', function () use (&$actionFired) {
            $actionFired = true;
        });

        $dispatcher = new SmsDispatcher(
            ['+15551234567'],
            'Test action message'
        );

        $dispatcher->dispatch();

        $this->assertTrue($actionFired, 'wp_sms_send action should be fired for direct send');

        remove_all_actions('wp_sms_send');
    }

    /**
     * Test: Async send returns dispatch result
     */
    public function testAsyncSendReturnsDispatchResult()
    {
        Option::updateOption('sms_delivery_method', 'api_async_send');

        $dispatcher = new SmsDispatcher(
            ['+15551234567'],
            'Test async send message'
        );

        // Async dispatch returns the async request dispatch result
        $result = $dispatcher->dispatch();

        // Result could be array (from async) or WP_Error if async not available
        $this->assertTrue(
            is_array($result) || is_object($result),
            'Async send should return a result'
        );
    }

    /**
     * Test: Queued send returns true
     */
    public function testQueuedSendReturnsTrue()
    {
        Option::updateOption('sms_delivery_method', 'api_queued_send');

        $dispatcher = new SmsDispatcher(
            ['+15551234567'],
            'Test queued send message'
        );

        $result = $dispatcher->dispatch();

        $this->assertTrue($result, 'Queued send should return true');
    }

    /**
     * Test: Auto-switch to queued when bulk limit exceeded
     */
    public function testAutoSwitchToQueuedForBulkSend()
    {
        // Set to direct send
        Option::updateOption('sms_delivery_method', 'api_direct_send');

        // Create 25 recipients (over the 20 limit)
        $recipients = [];
        for ($i = 0; $i < 25; $i++) {
            $recipients[] = '+1555000' . str_pad($i, 4, '0', STR_PAD_LEFT);
        }

        $dispatcher = new SmsDispatcher(
            $recipients,
            'Test bulk send message'
        );

        $result = $dispatcher->dispatch();

        // When recipients >= 20, it auto-switches to queued (returns true)
        $this->assertTrue($result, 'Bulk send should auto-switch to queued and return true');
    }

    /**
     * Test: Bulk dispatch limit filter works
     */
    public function testBulkDispatchLimitFilter()
    {
        Option::updateOption('sms_delivery_method', 'api_direct_send');

        // Lower the limit to 5
        add_filter('wp_sms_bulk_dispatch_limit', function () {
            return 5;
        });

        // Create 6 recipients (over the new 5 limit)
        $recipients = [];
        for ($i = 0; $i < 6; $i++) {
            $recipients[] = '+1555111' . str_pad($i, 4, '0', STR_PAD_LEFT);
        }

        $dispatcher = new SmsDispatcher(
            $recipients,
            'Test custom limit message'
        );

        $result = $dispatcher->dispatch();

        // Should switch to queued
        $this->assertTrue($result, 'Should switch to queued when exceeding custom limit');

        remove_all_filters('wp_sms_bulk_dispatch_limit');
    }

    /**
     * Test: Queued send applies single dispatch filter
     */
    public function testQueuedSendAppliesSingleDispatchFilter()
    {
        Option::updateOption('sms_delivery_method', 'api_queued_send');

        $filterCalled = false;
        $capturedArguments = [];

        add_filter('wp_sms_single_dispatch_arguments', function ($args) use (&$filterCalled, &$capturedArguments) {
            $filterCalled = true;
            $capturedArguments[] = $args;
            return $args;
        });

        $dispatcher = new SmsDispatcher(
            ['+15551111111', '+15552222222'],
            'Test single dispatch filter'
        );

        $dispatcher->dispatch();

        $this->assertTrue($filterCalled, 'wp_sms_single_dispatch_arguments filter should be called');
        $this->assertCount(2, $capturedArguments, 'Filter should be called once per recipient');

        remove_all_filters('wp_sms_single_dispatch_arguments');
    }

    /**
     * Test: Queued send modifies response filter
     */
    public function testQueuedSendModifiesResponseFilter()
    {
        Option::updateOption('sms_delivery_method', 'api_queued_send');

        $dispatcher = new SmsDispatcher(
            ['+15551234567'],
            'Test response filter'
        );

        $dispatcher->dispatch();

        // Check that the response filter was added
        $response = apply_filters('wp_sms_send_sms_response', 'original');

        $this->assertStringContainsString('background', strtolower($response));

        remove_all_filters('wp_sms_send_sms_response');
    }

    /**
     * Test: Dispatcher handles single number as string (backward compatibility)
     */
    public function testDispatcherHandlesSingleNumberAsString()
    {
        Option::updateOption('sms_delivery_method', 'api_direct_send');

        // Pass single number as string instead of array
        $dispatcher = new SmsDispatcher(
            '+15551234567',
            'Test single number'
        );

        $result = $dispatcher->dispatch();

        // Should work without error
        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
    }

    /**
     * Test: Dispatcher passes flash message parameter
     */
    public function testDispatcherPassesFlashParameter()
    {
        Option::updateOption('sms_delivery_method', 'api_direct_send');

        $capturedFlash = null;
        add_filter('wp_sms_to', function ($to) use (&$capturedFlash) {
            global $sms;
            $capturedFlash = $sms->isflash;
            return $to;
        });

        $dispatcher = new SmsDispatcher(
            ['+15551234567'],
            'Flash message test',
            true // is_flash = true
        );

        $dispatcher->dispatch();

        $this->assertTrue($capturedFlash, 'Flash parameter should be passed to gateway');

        remove_all_filters('wp_sms_to');
    }

    /**
     * Test: Dispatcher passes media URLs
     */
    public function testDispatcherPassesMediaUrls()
    {
        Option::updateOption('sms_delivery_method', 'api_direct_send');

        $capturedMedia = null;
        add_filter('wp_sms_to', function ($to) use (&$capturedMedia) {
            global $sms;
            $capturedMedia = $sms->media;
            return $to;
        });

        $mediaUrls = ['https://example.com/image.jpg'];

        $dispatcher = new SmsDispatcher(
            ['+15551234567'],
            'MMS message test',
            false,
            null,
            $mediaUrls
        );

        $dispatcher->dispatch();

        $this->assertEquals($mediaUrls, $capturedMedia, 'Media URLs should be passed to gateway');

        remove_all_filters('wp_sms_to');
    }

    /**
     * Test: Dispatcher passes custom sender
     */
    public function testDispatcherPassesCustomSender()
    {
        Option::updateOption('sms_delivery_method', 'api_direct_send');

        $capturedFrom = null;
        add_filter('wp_sms_from', function ($from) use (&$capturedFrom) {
            $capturedFrom = $from;
            return $from;
        });

        $dispatcher = new SmsDispatcher(
            ['+15551234567'],
            'Custom sender test',
            false,
            'CustomSender'
        );

        $dispatcher->dispatch();

        $this->assertEquals('CustomSender', $capturedFrom, 'Custom sender should be passed to gateway');

        remove_all_filters('wp_sms_from');
    }

    /**
     * Test: Default delivery method is direct send
     */
    public function testDefaultDeliveryMethodIsDirectSend()
    {
        // Clear the delivery method option
        Option::updateOption('sms_delivery_method', '');

        $dispatcher = new SmsDispatcher(
            ['+15551234567'],
            'Default method test'
        );

        $result = $dispatcher->dispatch();

        // With empty/unset option, should use direct send (returns array, not true)
        $this->assertIsArray($result);
    }

    /**
     * Test: Message variables are passed through dispatcher
     */
    public function testDispatcherPassesMessageVariables()
    {
        Option::updateOption('sms_delivery_method', 'api_direct_send');

        $capturedVariables = null;
        add_filter('wp_sms_to', function ($to) use (&$capturedVariables) {
            global $sms;
            $capturedVariables = $sms->messageVariables;
            return $to;
        });

        $messageVariables = [
            'customer_name' => 'John Doe',
            'order_id' => '12345',
        ];

        $dispatcher = new SmsDispatcher(
            ['+15551234567'],
            'Hello {customer_name}, your order {order_id} is ready',
            false,
            null,
            [],
            $messageVariables
        );

        $dispatcher->dispatch();

        $this->assertEquals($messageVariables, $capturedVariables, 'Message variables should be passed to gateway');

        remove_all_filters('wp_sms_to');
    }
}
