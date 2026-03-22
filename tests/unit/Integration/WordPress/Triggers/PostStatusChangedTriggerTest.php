<?php

namespace WSms\Tests\Unit\Integration\WordPress\Triggers;

use PHPUnit\Framework\TestCase;
use WSms\Integration\WordPress\Triggers\PostStatusChangedTrigger;

class PostStatusChangedTriggerTest extends TestCase
{
    private PostStatusChangedTrigger $trigger;

    protected function setUp(): void
    {
        $this->trigger = new PostStatusChangedTrigger();
        $GLOBALS['_test_actions'] = [];
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['_test_actions']);
    }

    public function testMetadata(): void
    {
        $this->assertSame('wordpress.post_status_changed', $this->trigger->getId());
        $this->assertSame('Post Status Changed', $this->trigger->getName());
        $this->assertSame('WordPress', $this->trigger->getGroup());
    }

    public function testPayloadSchemaHasExpectedFields(): void
    {
        $schema = $this->trigger->getPayloadSchema();
        $this->assertArrayHasKey('post_id', $schema);
        $this->assertArrayHasKey('post_title', $schema);
        $this->assertArrayHasKey('post_type', $schema);
        $this->assertArrayHasKey('old_status', $schema);
        $this->assertArrayHasKey('new_status', $schema);
        $this->assertArrayHasKey('author_id', $schema);
    }

    public function testSubscribeRegistersTransitionPostStatusHook(): void
    {
        $this->trigger->subscribe(function () {});
        $this->assertArrayHasKey('transition_post_status', $GLOBALS['_test_actions']);
    }

    public function testProducesCorrectPayload(): void
    {
        $post = (object) [
            'ID' => 123,
            'post_title' => 'Test Post',
            'post_type' => 'page',
            'post_author' => 5,
        ];

        $captured = null;
        $this->trigger->subscribe(function (array $payload) use (&$captured) {
            $captured = $payload;
        });

        $this->fireAction('transition_post_status', 'publish', 'draft', $post);

        $this->assertNotNull($captured);
        $this->assertSame(123, $captured['post_id']);
        $this->assertSame('Test Post', $captured['post_title']);
        $this->assertSame('page', $captured['post_type']);
        $this->assertSame('draft', $captured['old_status']);
        $this->assertSame('publish', $captured['new_status']);
        $this->assertSame(5, $captured['author_id']);
    }

    public function testDoesNotFireWhenStatusUnchanged(): void
    {
        $post = (object) ['ID' => 1, 'post_title' => 'T', 'post_type' => 'post'];

        $fired = false;
        $this->trigger->subscribe(function () use (&$fired) {
            $fired = true;
        });

        $this->fireAction('transition_post_status', 'publish', 'publish', $post);
        $this->assertFalse($fired);
    }

    private function fireAction(string $hook, ...$args): void
    {
        foreach ($GLOBALS['_test_actions'][$hook] ?? [] as $callback) {
            $callback(...$args);
        }
    }
}
