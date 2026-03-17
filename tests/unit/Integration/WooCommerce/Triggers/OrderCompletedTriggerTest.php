<?php

namespace WSms\Tests\Unit\Integration\WooCommerce\Triggers;

use PHPUnit\Framework\TestCase;
use WSms\Integration\WooCommerce\Triggers\OrderCompletedTrigger;

class OrderCompletedTriggerTest extends TestCase
{
    private OrderCompletedTrigger $trigger;

    protected function setUp(): void
    {
        $this->trigger = new OrderCompletedTrigger();
        $GLOBALS['_test_actions'] = [];
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['_test_actions'], $GLOBALS['_test_wc_order']);
    }

    public function testMetadata(): void
    {
        $this->assertSame('woocommerce.order_completed', $this->trigger->getId());
        $this->assertSame('Order Completed', $this->trigger->getName());
        $this->assertSame('WooCommerce', $this->trigger->getGroup());
    }

    public function testSubscribeRegistersCorrectHook(): void
    {
        $this->trigger->subscribe(function () {});
        $this->assertArrayHasKey('woocommerce_order_status_completed', $GLOBALS['_test_actions']);
    }

    public function testProducesCorrectPayload(): void
    {
        $order = new \WC_Order_Stub(1001);
        $order->set_total('100.00');
        $order->set_billing_email('test@example.com');
        $order->set_billing_phone('+1');
        $order->set_billing_first_name('A');
        $order->set_billing_last_name('B');
        $GLOBALS['_test_wc_order'] = $order;

        $captured = null;
        $this->trigger->subscribe(function (array $payload) use (&$captured) {
            $captured = $payload;
        });

        $this->fireAction('woocommerce_order_status_completed', 1001);

        $this->assertSame('completed', $captured['order']['status']);
        $this->assertSame('100.00', $captured['order']['total']);
    }

    private function fireAction(string $hook, ...$args): void
    {
        foreach ($GLOBALS['_test_actions'][$hook] ?? [] as $callback) {
            $callback(...$args);
        }
    }
}
