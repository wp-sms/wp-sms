<?php

namespace WSms\Tests\Unit\Integration\WooCommerce\Actions;

use PHPUnit\Framework\TestCase;
use WSms\Integration\WooCommerce\Actions\AddOrderNoteAction;

class AddOrderNoteActionTest extends TestCase
{
    private AddOrderNoteAction $action;

    protected function setUp(): void
    {
        $this->action = new AddOrderNoteAction();
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['_test_wc_order']);
    }

    public function testMetadata(): void
    {
        $this->assertSame('add_order_note', $this->action->getId());
        $this->assertSame('Add Order Note', $this->action->getName());
        $this->assertSame('WooCommerce', $this->action->getGroup());
    }

    public function testConfigSchemaHasExpectedFields(): void
    {
        $schema = $this->action->getConfigSchema();
        $this->assertArrayHasKey('order_id', $schema);
        $this->assertArrayHasKey('note', $schema);
        $this->assertArrayHasKey('is_customer_note', $schema);
        $this->assertTrue($schema['note']['required']);
        $this->assertSame('boolean', $schema['is_customer_note']['type']);
    }

    public function testExecuteSuccess(): void
    {
        $order = new \WC_Order_Stub(1001);
        $GLOBALS['_test_wc_order'] = $order;

        $result = $this->action->execute([], [
            'order_id' => '1001',
            'note' => 'Test note',
        ]);

        $this->assertTrue($result->success);
        $this->assertSame(1001, $result->output['order_id']);
        $this->assertSame(1, $result->output['note_id']);

        $notes = $order->get_notes();
        $this->assertSame('Test note', $notes[0]['note']);
        $this->assertFalse($notes[0]['is_customer_note']);
    }

    public function testExecuteWithCustomerNote(): void
    {
        $order = new \WC_Order_Stub(1001);
        $GLOBALS['_test_wc_order'] = $order;

        $this->action->execute([], [
            'order_id' => '1001',
            'note' => 'Visible note',
            'is_customer_note' => true,
        ]);

        $notes = $order->get_notes();
        $this->assertTrue($notes[0]['is_customer_note']);
    }

    public function testExecuteFailsWithMissingParams(): void
    {
        $result = $this->action->execute([], ['order_id' => '0', 'note' => '']);
        $this->assertFalse($result->success);
    }

    public function testExecuteFailsIfOrderNotFound(): void
    {
        $GLOBALS['_test_wc_order'] = null;

        $result = $this->action->execute([], ['order_id' => '999', 'note' => 'Test']);
        $this->assertFalse($result->success);
        $this->assertStringContainsString('Order not found', $result->error);
    }

    public function testGetPlaceholdersForOrderCreated(): void
    {
        $placeholders = $this->action->getPlaceholders('woocommerce.order_created');
        $this->assertArrayHasKey('note', $placeholders);
        $this->assertStringContainsString('{{order_id}}', $placeholders['note']);
    }

    public function testGetPlaceholdersForOrderStatusChanged(): void
    {
        $placeholders = $this->action->getPlaceholders('woocommerce.order_status_changed');
        $this->assertArrayHasKey('note', $placeholders);
        $this->assertStringContainsString('{{old_status}}', $placeholders['note']);
    }

    public function testGetPlaceholdersForUnknownTrigger(): void
    {
        $this->assertSame([], $this->action->getPlaceholders('unknown.trigger'));
    }
}
