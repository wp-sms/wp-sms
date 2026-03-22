<?php

namespace WSms\Tests\Unit\Integration\WooCommerce\Triggers;

use PHPUnit\Framework\TestCase;
use WSms\Integration\WooCommerce\Triggers\OrderStatusChangedTrigger;

class OrderStatusChangedTriggerTest extends TestCase
{
    private OrderStatusChangedTrigger $trigger;

    protected function setUp(): void
    {
        $this->trigger = new OrderStatusChangedTrigger();
        $GLOBALS['_test_actions'] = [];
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['_test_actions'], $GLOBALS['_test_wc_order']);
    }

    public function testMetadata(): void
    {
        $this->assertSame('woocommerce.order_status_changed', $this->trigger->getId());
        $this->assertSame('Order Status Changed', $this->trigger->getName());
        $this->assertSame('WooCommerce', $this->trigger->getGroup());
    }

    public function testPayloadSchemaHasExpectedFields(): void
    {
        $schema = $this->trigger->getPayloadSchema();
        $this->assertArrayHasKey('order_id', $schema);
        $this->assertArrayHasKey('old_status', $schema);
        $this->assertArrayHasKey('new_status', $schema);
        $this->assertArrayHasKey('order', $schema);
        $this->assertArrayHasKey('customer', $schema);
    }

    public function testSubscribeRegistersCorrectHook(): void
    {
        $this->trigger->subscribe(function () {});
        $this->assertArrayHasKey('woocommerce_order_status_changed', $GLOBALS['_test_actions']);
    }

    public function testProducesCorrectPayload(): void
    {
        $order = new \WC_Order_Stub(1001);
        $order->set_total('75.00');
        $order->set_billing_email('test@example.com');
        $order->set_billing_phone('+1');
        $order->set_billing_first_name('John');
        $order->set_billing_last_name('Doe');
        $order->set_currency('USD');
        $order->set_payment_method_title('Stripe');
        $GLOBALS['_test_wc_order'] = $order;

        $captured = null;
        $this->trigger->subscribe(function (array $payload) use (&$captured) {
            $captured = $payload;
        });

        $this->fireAction('woocommerce_order_status_changed', 1001, 'pending', 'processing');

        $this->assertSame(1001, $captured['order_id']);
        $this->assertSame('pending', $captured['old_status']);
        $this->assertSame('processing', $captured['new_status']);
        $this->assertSame('75.00', $captured['order']['total']);
        $this->assertSame('USD', $captured['order']['currency']);
        $this->assertSame('Stripe', $captured['order']['payment_method']);
        $this->assertSame('John', $captured['customer']['first_name']);
        $this->assertSame('Doe', $captured['customer']['last_name']);
    }

    public function testDoesNotFireIfOrderNotFound(): void
    {
        $GLOBALS['_test_wc_order'] = null;

        $fired = false;
        $this->trigger->subscribe(function () use (&$fired) {
            $fired = true;
        });

        $this->fireAction('woocommerce_order_status_changed', 999, 'pending', 'processing');
        $this->assertFalse($fired);
    }

    private function fireAction(string $hook, ...$args): void
    {
        foreach ($GLOBALS['_test_actions'][$hook] ?? [] as $callback) {
            $callback(...$args);
        }
    }
}
