<?php

namespace WSms\Tests\Unit\Integration\WooCommerce;

use PHPUnit\Framework\TestCase;
use WSms\Flow\Contracts\ActionInterface;
use WSms\Flow\Contracts\TriggerInterface;
use WSms\Integration\WooCommerce\WooCommerceIntegration;

class WooCommerceIntegrationTest extends TestCase
{
    private WooCommerceIntegration $integration;

    protected function setUp(): void
    {
        $this->integration = new WooCommerceIntegration();
    }

    public function testMetadata(): void
    {
        $this->assertSame('woocommerce', $this->integration->getId());
        $this->assertSame('WooCommerce', $this->integration->getName());
        $this->assertSame('ecommerce', $this->integration->getCategory());
    }

    public function testReturnsSixTriggers(): void
    {
        $triggers = $this->integration->getTriggers();
        $this->assertCount(6, $triggers);

        foreach ($triggers as $trigger) {
            $this->assertInstanceOf(TriggerInterface::class, $trigger);
        }
    }

    public function testTriggerIdsAreCorrect(): void
    {
        $ids = array_map(fn($t) => $t->getId(), $this->integration->getTriggers());

        $expected = [
            'woocommerce.order_created',
            'woocommerce.order_completed',
            'woocommerce.payment_failed',
            'woocommerce.order_status_changed',
            'woocommerce.product_purchased',
            'woocommerce.customer_created',
        ];

        foreach ($expected as $id) {
            $this->assertContains($id, $ids);
        }
    }

    public function testReturnsTwoActions(): void
    {
        $actions = $this->integration->getActions();
        $this->assertCount(2, $actions);

        foreach ($actions as $action) {
            $this->assertInstanceOf(ActionInterface::class, $action);
        }
    }

    public function testActionIdsAreCorrect(): void
    {
        $ids = array_map(fn($a) => $a->getId(), $this->integration->getActions());

        $this->assertContains('update_order_status', $ids);
        $this->assertContains('add_order_note', $ids);
    }
}
