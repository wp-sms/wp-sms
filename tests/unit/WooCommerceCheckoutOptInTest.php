<?php

namespace unit;

use WP_SMS\Services\WooCommerce\WooCommerceCheckout;
use WP_UnitTestCase;

class WooCommerceCheckoutOptInTest extends WP_UnitTestCase
{
    const FIELD = WooCommerceCheckout::FIELD_ORDER_NOTIFICATION;

    protected function setUp(): void
    {
        parent::setUp();

        if (!class_exists('WooCommerce')) {
            $this->markTestSkipped('WooCommerce is not loaded.');
        }
    }

    protected function tearDown(): void
    {
        unset($_POST[self::FIELD]);

        parent::tearDown();
    }

    public function testClassicCheckoutSavesTheChoiceOnTheOrder()
    {
        $checkout = new WooCommerceCheckout();
        $order    = wc_create_order();

        $_POST[self::FIELD] = '1';
        $checkout->registerStoreCheckboxCallback($order->get_id(), array());
        $this->assertSame('yes', wc_get_order($order->get_id())->get_meta(self::FIELD));

        unset($_POST[self::FIELD]);
        $checkout->registerStoreCheckboxCallback($order->get_id(), array());
        $this->assertSame('no', wc_get_order($order->get_id())->get_meta(self::FIELD));
    }

    public function testBlockCheckoutRespectsAnUntickedBox()
    {
        $checkout = new WooCommerceCheckout();
        $order    = new \WC_Order();

        $checkout->registerStoreCheckboxBlockBasedCallback('wpsms/opt-in', true, 'other', $order);
        $this->assertSame('yes', $order->get_meta(self::FIELD));

        $checkout->registerStoreCheckboxBlockBasedCallback('wpsms/opt-in', false, 'other', $order);
        $this->assertSame('no', $order->get_meta(self::FIELD));
    }

    public function testBlockCheckoutOtherFieldsDoNotReplaceTheChoice()
    {
        $checkout = new WooCommerceCheckout();
        $order    = new \WC_Order();

        $checkout->registerStoreCheckboxBlockBasedCallback('wpsms/mobile', '+12025550123', 'billing', $order);
        $this->assertSame('no', $order->get_meta(self::FIELD));

        $checkout->registerStoreCheckboxBlockBasedCallback('wpsms/opt-in', true, 'other', $order);
        $checkout->registerStoreCheckboxBlockBasedCallback('wpsms/mobile', '+12025550123', 'shipping', $order);
        $this->assertSame('yes', $order->get_meta(self::FIELD));
    }

    public function testChoiceSavedAsPostMetaIsStillRead()
    {
        $postId = self::factory()->post->create();
        update_post_meta($postId, self::FIELD, 'no');

        // An order object that holds no such meta, as with orders stored in their own tables.
        $order = $this->getMockBuilder(\WC_Order::class)->disableOriginalConstructor()->onlyMethods(array('get_id', 'get_meta'))->getMock();
        $order->method('get_id')->willReturn($postId);
        $order->method('get_meta')->willReturn('');

        $this->assertSame('no', WooCommerceCheckout::getOrderOptInStatus($order));
    }
}
