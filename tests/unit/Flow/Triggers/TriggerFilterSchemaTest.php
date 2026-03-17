<?php

namespace WSms\Tests\Unit\Flow\Triggers;

use PHPUnit\Framework\TestCase;
use WSms\Integration\WordPress\Triggers\PostPublishedTrigger;
use WSms\Integration\WordPress\Triggers\PostStatusChangedTrigger;
use WSms\Integration\WordPress\Triggers\UserRegisterTrigger;
use WSms\Integration\WordPress\Triggers\UserRoleChangedTrigger;
use WSms\Integration\WordPress\Triggers\CommentPostedTrigger;
use WSms\Integration\WordPress\Triggers\UserUpdatedTrigger;
use WSms\Integration\WordPress\Triggers\UserDeletedTrigger;
use WSms\Integration\WooCommerce\Triggers\OrderStatusChangedTrigger;
use WSms\Integration\WooCommerce\Triggers\ProductPurchasedTrigger;
use WSms\Integration\WooCommerce\Triggers\OrderCreatedTrigger;
use WSms\Integration\Auth\AuthEventTrigger;
use WSms\Enums\EventType;

class TriggerFilterSchemaTest extends TestCase
{
    public function testPostPublishedHasPostTypeFilter(): void
    {
        $trigger = new PostPublishedTrigger();
        $schema = $trigger->getFilterSchema();

        $this->assertArrayHasKey('post_type', $schema);
        $this->assertTrue($schema['post_type']['dynamic']);
        $this->assertSame('string', $schema['post_type']['type']);
    }

    public function testPostStatusChangedHasFilters(): void
    {
        $trigger = new PostStatusChangedTrigger();
        $schema = $trigger->getFilterSchema();

        $this->assertArrayHasKey('post_type', $schema);
        $this->assertArrayHasKey('new_status', $schema);
        $this->assertArrayHasKey('old_status', $schema);
        $this->assertTrue($schema['post_type']['dynamic']);
        $this->assertContains('publish', $schema['new_status']['enum']);
        $this->assertContains('draft', $schema['old_status']['enum']);
    }

    public function testUserRegisterHasRoleFilter(): void
    {
        $trigger = new UserRegisterTrigger();
        $schema = $trigger->getFilterSchema();

        $this->assertArrayHasKey('role', $schema);
        $this->assertTrue($schema['role']['dynamic']);
    }

    public function testUserRoleChangedHasRoleFilters(): void
    {
        $trigger = new UserRoleChangedTrigger();
        $schema = $trigger->getFilterSchema();

        $this->assertArrayHasKey('new_role', $schema);
        $this->assertArrayHasKey('old_role', $schema);
        $this->assertTrue($schema['new_role']['dynamic']);
        $this->assertTrue($schema['old_role']['dynamic']);
    }

    public function testCommentPostedHasApprovedFilter(): void
    {
        $trigger = new CommentPostedTrigger();
        $schema = $trigger->getFilterSchema();

        $this->assertArrayHasKey('approved', $schema);
        $this->assertSame(['0', '1'], $schema['approved']['enum']);
    }

    public function testOrderStatusChangedHasStatusFilters(): void
    {
        $trigger = new OrderStatusChangedTrigger();
        $schema = $trigger->getFilterSchema();

        $this->assertArrayHasKey('new_status', $schema);
        $this->assertArrayHasKey('old_status', $schema);
        $this->assertContains('completed', $schema['new_status']['enum']);
        $this->assertContains('pending', $schema['old_status']['enum']);
    }

    public function testProductPurchasedHasProductFilter(): void
    {
        $trigger = new ProductPurchasedTrigger();
        $schema = $trigger->getFilterSchema();

        $this->assertArrayHasKey('product_id', $schema);
        $this->assertTrue($schema['product_id']['dynamic']);
    }

    public function testTriggersWithNoFiltersReturnEmptySchema(): void
    {
        $trigger = new UserUpdatedTrigger();
        $this->assertSame([], $trigger->getFilterSchema());

        $trigger = new UserDeletedTrigger();
        $this->assertSame([], $trigger->getFilterSchema());

        $trigger = new OrderCreatedTrigger();
        $this->assertSame([], $trigger->getFilterSchema());
    }

    public function testAuthLoginSuccessHasMethodFilter(): void
    {
        $trigger = new AuthEventTrigger(
            'auth.login_success',
            'Login Success',
            [],
            [EventType::LoginSuccess],
            [
                'method' => [
                    'type'  => 'string',
                    'label' => 'Login Method',
                    'enum'  => ['password', 'otp', 'magic_link'],
                ],
            ],
        );

        $schema = $trigger->getFilterSchema();
        $this->assertArrayHasKey('method', $schema);
        $this->assertContains('password', $schema['method']['enum']);
        $this->assertContains('otp', $schema['method']['enum']);
    }

    public function testAuthTriggerWithoutFilterSchemaReturnsEmpty(): void
    {
        $trigger = new AuthEventTrigger(
            'auth.login_failure',
            'Login Failure',
            [],
            [EventType::LoginFailure],
        );

        $this->assertSame([], $trigger->getFilterSchema());
    }

    /**
     * Ensure all filter schema keys correspond to payload schema keys.
     * This guards against filter fields that don't exist in the payload.
     *
     * @dataProvider triggersWithFiltersProvider
     */
    public function testFilterKeysExistInPayloadSchema(string $triggerClass): void
    {
        $trigger = new $triggerClass();
        $filterKeys = array_keys($trigger->getFilterSchema());
        $payloadKeys = array_keys($trigger->getPayloadSchema());

        foreach ($filterKeys as $key) {
            $this->assertContains(
                $key,
                $payloadKeys,
                "Filter key '{$key}' not found in payload schema of " . $triggerClass,
            );
        }
    }

    public static function triggersWithFiltersProvider(): array
    {
        return [
            [PostPublishedTrigger::class],
            [PostStatusChangedTrigger::class],
            [UserRegisterTrigger::class],
            [UserRoleChangedTrigger::class],
            [CommentPostedTrigger::class],
            [OrderStatusChangedTrigger::class],
            [ProductPurchasedTrigger::class],
        ];
    }
}
