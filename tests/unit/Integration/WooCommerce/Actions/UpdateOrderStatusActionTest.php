<?php

namespace WSms\Tests\Unit\Integration\WooCommerce\Actions;

use PHPUnit\Framework\TestCase;
use WSms\Integration\WooCommerce\Actions\UpdateOrderStatusAction;

class UpdateOrderStatusActionTest extends TestCase
{
    private UpdateOrderStatusAction $action;

    protected function setUp(): void
    {
        $this->action = new UpdateOrderStatusAction();
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['_test_wc_order']);
    }

    public function testMetadata(): void
    {
        $this->assertSame('update_order_status', $this->action->getId());
        $this->assertSame('Update Order Status', $this->action->getName());
        $this->assertSame('WooCommerce', $this->action->getGroup());
    }

    public function testConfigSchemaHasExpectedFields(): void
    {
        $schema = $this->action->getConfigSchema();
        $this->assertArrayHasKey('order_id', $schema);
        $this->assertArrayHasKey('status', $schema);
        $this->assertArrayHasKey('note', $schema);
        $this->assertTrue($schema['status']['required']);
        $this->assertContains('processing', $schema['status']['enum']);
    }

    public function testExecuteSuccess(): void
    {
        $order = new \WC_Order_Stub(1001);
        $GLOBALS['_test_wc_order'] = $order;

        $result = $this->action->execute([], ['order_id' => '1001', 'status' => 'processing']);

        $this->assertTrue($result->success);
        $this->assertSame(1001, $result->output['order_id']);
        $this->assertSame('processing', $result->output['status']);
        $this->assertSame('processing', $order->get_status());
    }

    public function testExecuteWithNote(): void
    {
        $order = new \WC_Order_Stub(1001);
        $GLOBALS['_test_wc_order'] = $order;

        $this->action->execute([], [
            'order_id' => '1001',
            'status' => 'completed',
            'note' => 'Auto-completed by flow',
        ]);

        $notes = $order->get_notes();
        $this->assertNotEmpty($notes);
    }

    public function testExecuteFailsWithMissingParams(): void
    {
        $result = $this->action->execute([], ['order_id' => '0', 'status' => '']);
        $this->assertFalse($result->success);
    }

    public function testExecuteFailsIfOrderNotFound(): void
    {
        $GLOBALS['_test_wc_order'] = null;

        $result = $this->action->execute([], ['order_id' => '999', 'status' => 'processing']);
        $this->assertFalse($result->success);
        $this->assertStringContainsString('Order not found', $result->error);
    }

    public function testGetPlaceholdersForOrderCreated(): void
    {
        $placeholders = $this->action->getPlaceholders('woocommerce.order_created');
        $this->assertArrayHasKey('order_id', $placeholders);
        $this->assertArrayHasKey('note', $placeholders);
    }

    public function testGetPlaceholdersForUnknownTrigger(): void
    {
        $this->assertSame([], $this->action->getPlaceholders('unknown.trigger'));
    }
}
