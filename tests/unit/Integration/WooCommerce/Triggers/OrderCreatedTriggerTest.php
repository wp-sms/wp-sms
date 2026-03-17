<?php

namespace WSms\Tests\Unit\Integration\WooCommerce\Triggers;

use PHPUnit\Framework\TestCase;
use WSms\Integration\WooCommerce\Triggers\OrderCreatedTrigger;

class OrderCreatedTriggerTest extends TestCase
{
    private OrderCreatedTrigger $trigger;

    protected function setUp(): void
    {
        $this->trigger = new OrderCreatedTrigger();
        $GLOBALS['_test_actions'] = [];
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['_test_actions'], $GLOBALS['_test_wc_order']);
    }

    public function testMetadata(): void
    {
        $this->assertSame('woocommerce.order_created', $this->trigger->getId());
        $this->assertSame('Order Created', $this->trigger->getName());
        $this->assertSame('WooCommerce', $this->trigger->getGroup());
    }

    public function testPayloadSchemaHasExpectedFields(): void
    {
        $schema = $this->trigger->getPayloadSchema();
        $this->assertArrayHasKey('order_id', $schema);
        $this->assertArrayHasKey('order', $schema);
        $this->assertArrayHasKey('customer', $schema);
        $this->assertArrayHasKey('example', $schema['order_id']);
    }

    public function testSubscribeRegistersWooCommerceNewOrderHook(): void
    {
        $this->trigger->subscribe(function () {});
        $this->assertArrayHasKey('woocommerce_new_order', $GLOBALS['_test_actions']);
    }

    public function testProducesCorrectPayload(): void
    {
        $order = new \WC_Order_Stub(1001);
        $order->set_total('59.99');
        $order->set_billing_email('customer@example.com');
        $order->set_billing_phone('+1234567890');
        $order->set_billing_first_name('John');
        $order->set_billing_last_name('Doe');
        $GLOBALS['_test_wc_order'] = $order;

        $captured = null;
        $this->trigger->subscribe(function (array $payload) use (&$captured) {
            $captured = $payload;
        });

        $this->fireAction('woocommerce_new_order', 1001);

        $this->assertNotNull($captured);
        $this->assertSame(1001, $captured['order_id']);
        $this->assertSame('59.99', $captured['order']['total']);
        $this->assertSame('customer@example.com', $captured['customer']['email']);
        $this->assertSame('John Doe', $captured['customer']['name']);
    }

    public function testDoesNotFireIfOrderNotFound(): void
    {
        $GLOBALS['_test_wc_order'] = null;

        $fired = false;
        $this->trigger->subscribe(function () use (&$fired) {
            $fired = true;
        });

        $this->fireAction('woocommerce_new_order', 999);
        $this->assertFalse($fired);
    }

    private function fireAction(string $hook, ...$args): void
    {
        foreach ($GLOBALS['_test_actions'][$hook] ?? [] as $callback) {
            $callback(...$args);
        }
    }
}
