<?php

namespace WSms\Tests\Unit\Integration\WooCommerce\Triggers;

use PHPUnit\Framework\TestCase;
use WSms\Integration\WooCommerce\Triggers\CustomerCreatedTrigger;

class CustomerCreatedTriggerTest extends TestCase
{
    private CustomerCreatedTrigger $trigger;

    protected function setUp(): void
    {
        $this->trigger = new CustomerCreatedTrigger();
        $GLOBALS['_test_actions'] = [];
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['_test_actions'], $GLOBALS['_test_user_meta']);
        $GLOBALS['_test_user_meta'] = [];
    }

    public function testMetadata(): void
    {
        $this->assertSame('woocommerce.customer_created', $this->trigger->getId());
        $this->assertSame('Customer Created', $this->trigger->getName());
        $this->assertSame('WooCommerce', $this->trigger->getGroup());
    }

    public function testPayloadSchemaHasExpectedFields(): void
    {
        $schema = $this->trigger->getPayloadSchema();
        $this->assertArrayHasKey('customer_id', $schema);
        $this->assertArrayHasKey('email', $schema);
        $this->assertArrayHasKey('first_name', $schema);
        $this->assertArrayHasKey('last_name', $schema);
        $this->assertArrayHasKey('phone', $schema);
        $this->assertSame('email', $schema['email']['format']);
    }

    public function testSubscribeRegistersCorrectHook(): void
    {
        $this->trigger->subscribe(function () {});
        $this->assertArrayHasKey('woocommerce_created_customer', $GLOBALS['_test_actions']);
    }

    public function testProducesCorrectPayload(): void
    {
        $GLOBALS['_test_user_meta'][42]['billing_phone'] = '+1234567890';

        $captured = null;
        $this->trigger->subscribe(function (array $payload) use (&$captured) {
            $captured = $payload;
        });

        $this->fireAction('woocommerce_created_customer', 42, [
            'user_email' => 'customer@example.com',
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);

        $this->assertNotNull($captured);
        $this->assertSame(42, $captured['customer_id']);
        $this->assertSame('customer@example.com', $captured['email']);
        $this->assertSame('John', $captured['first_name']);
        $this->assertSame('Doe', $captured['last_name']);
        $this->assertSame('+1234567890', $captured['phone']);
    }

    private function fireAction(string $hook, ...$args): void
    {
        foreach ($GLOBALS['_test_actions'][$hook] ?? [] as $callback) {
            $callback(...$args);
        }
    }
}
