<?php

namespace WSms\Tests\Unit\Integration\WooCommerce\Triggers;

use PHPUnit\Framework\TestCase;
use WSms\Integration\WooCommerce\Triggers\PaymentFailedTrigger;

class PaymentFailedTriggerTest extends TestCase
{
    private PaymentFailedTrigger $trigger;

    protected function setUp(): void
    {
        $this->trigger = new PaymentFailedTrigger();
        $GLOBALS['_test_actions'] = [];
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['_test_actions'], $GLOBALS['_test_wc_order']);
    }

    public function testMetadata(): void
    {
        $this->assertSame('woocommerce.payment_failed', $this->trigger->getId());
        $this->assertSame('Payment Failed', $this->trigger->getName());
        $this->assertSame('WooCommerce', $this->trigger->getGroup());
    }

    public function testPayloadSchemaHasExpectedFields(): void
    {
        $schema = $this->trigger->getPayloadSchema();
        $this->assertArrayHasKey('order_id', $schema);
        $this->assertArrayHasKey('order', $schema);
        $this->assertArrayHasKey('customer', $schema);
    }

    public function testSubscribeRegistersCorrectHook(): void
    {
        $this->trigger->subscribe(function () {});
        $this->assertArrayHasKey('woocommerce_order_status_failed', $GLOBALS['_test_actions']);
    }

    public function testProducesPayloadWithOrder(): void
    {
        $order = new \WC_Order_Stub(1001);
        $order->set_total('50.00');
        $order->set_billing_email('test@example.com');
        $order->set_billing_phone('+1');
        $order->set_billing_first_name('A');
        $order->set_billing_last_name('B');
        $order->set_currency('USD');
        $order->set_payment_method_title('Credit Card');
        $GLOBALS['_test_wc_order'] = $order;

        $captured = null;
        $this->trigger->subscribe(function (array $payload) use (&$captured) {
            $captured = $payload;
        });

        $this->fireAction('woocommerce_order_status_failed', 1001);

        $this->assertSame(1001, $captured['order_id']);
        $this->assertSame('pending', $captured['order']['status']);
        $this->assertSame('USD', $captured['order']['currency']);
        $this->assertSame('Credit Card', $captured['order']['payment_method']);
        $this->assertSame('A', $captured['customer']['first_name']);
        $this->assertSame('B', $captured['customer']['last_name']);
    }

    public function testDoesNotFireIfOrderNotFound(): void
    {
        $GLOBALS['_test_wc_order'] = null;

        $fired = false;
        $this->trigger->subscribe(function () use (&$fired) {
            $fired = true;
        });

        $this->fireAction('woocommerce_order_status_failed', 999);
        $this->assertFalse($fired);
    }

    private function fireAction(string $hook, ...$args): void
    {
        foreach ($GLOBALS['_test_actions'][$hook] ?? [] as $callback) {
            $callback(...$args);
        }
    }
}
