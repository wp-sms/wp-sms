<?php

namespace WSms\Tests\Unit\Integration\WooCommerce\Triggers;

use PHPUnit\Framework\TestCase;
use WSms\Integration\WooCommerce\Triggers\ProductPurchasedTrigger;

class ProductPurchasedTriggerTest extends TestCase
{
    private ProductPurchasedTrigger $trigger;

    protected function setUp(): void
    {
        $this->trigger = new ProductPurchasedTrigger();
        $GLOBALS['_test_actions'] = [];
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['_test_actions'], $GLOBALS['_test_wc_order']);
    }

    public function testMetadata(): void
    {
        $this->assertSame('woocommerce.product_purchased', $this->trigger->getId());
        $this->assertSame('Product Purchased', $this->trigger->getName());
        $this->assertSame('WooCommerce', $this->trigger->getGroup());
    }

    public function testPayloadSchemaHasExpectedFields(): void
    {
        $schema = $this->trigger->getPayloadSchema();
        $this->assertArrayHasKey('order_id', $schema);
        $this->assertArrayHasKey('product_id', $schema);
        $this->assertArrayHasKey('product_name', $schema);
        $this->assertArrayHasKey('quantity', $schema);
        $this->assertArrayHasKey('customer', $schema);
    }

    public function testSubscribeRegistersCompletedHook(): void
    {
        $this->trigger->subscribe(function () {});
        $this->assertArrayHasKey('woocommerce_order_status_completed', $GLOBALS['_test_actions']);
    }

    public function testFiresOncePerItem(): void
    {
        $order = new \WC_Order_Stub(1001);
        $order->set_billing_email('test@example.com');
        $order->set_billing_phone('+1');
        $order->set_billing_first_name('John');
        $order->set_billing_last_name('Doe');
        $order->set_items([
            new \WC_Order_Item_Stub(55, 'Widget', 2),
            new \WC_Order_Item_Stub(66, 'Gadget', 1),
        ]);
        $GLOBALS['_test_wc_order'] = $order;

        $payloads = [];
        $this->trigger->subscribe(function (array $payload) use (&$payloads) {
            $payloads[] = $payload;
        });

        $this->fireAction('woocommerce_order_status_completed', 1001);

        $this->assertCount(2, $payloads);
        $this->assertSame(55, $payloads[0]['product_id']);
        $this->assertSame('Widget', $payloads[0]['product_name']);
        $this->assertSame(2, $payloads[0]['quantity']);
        $this->assertSame(66, $payloads[1]['product_id']);
    }

    public function testDoesNotFireIfOrderNotFound(): void
    {
        $GLOBALS['_test_wc_order'] = null;

        $fired = false;
        $this->trigger->subscribe(function () use (&$fired) {
            $fired = true;
        });

        $this->fireAction('woocommerce_order_status_completed', 999);
        $this->assertFalse($fired);
    }

    private function fireAction(string $hook, ...$args): void
    {
        foreach ($GLOBALS['_test_actions'][$hook] ?? [] as $callback) {
            $callback(...$args);
        }
    }
}
