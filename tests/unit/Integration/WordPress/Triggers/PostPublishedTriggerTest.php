<?php

namespace WSms\Tests\Unit\Integration\WordPress\Triggers;

use PHPUnit\Framework\TestCase;
use WSms\Integration\WordPress\Triggers\PostPublishedTrigger;

class PostPublishedTriggerTest extends TestCase
{
    private PostPublishedTrigger $trigger;

    protected function setUp(): void
    {
        $this->trigger = new PostPublishedTrigger();
        $GLOBALS['_test_actions'] = [];
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['_test_actions'], $GLOBALS['_test_permalink']);
    }

    public function testMetadata(): void
    {
        $this->assertSame('wordpress.post_published', $this->trigger->getId());
        $this->assertSame('Post Published', $this->trigger->getName());
        $this->assertSame('WordPress', $this->trigger->getGroup());
    }

    public function testPayloadSchemaHasExpectedFields(): void
    {
        $schema = $this->trigger->getPayloadSchema();
        $this->assertArrayHasKey('post_id', $schema);
        $this->assertArrayHasKey('post_title', $schema);
        $this->assertArrayHasKey('post_url', $schema);
        $this->assertArrayHasKey('post_type', $schema);
        $this->assertArrayHasKey('author_id', $schema);
        $this->assertSame('url', $schema['post_url']['format']);
        $this->assertArrayHasKey('example', $schema['post_id']);
    }

    public function testSubscribeRegistersTransitionPostStatusHook(): void
    {
        $this->trigger->subscribe(function () {});
        $this->assertArrayHasKey('transition_post_status', $GLOBALS['_test_actions']);
    }

    public function testFiresWhenPostPublished(): void
    {
        $GLOBALS['_test_permalink'] = 'http://localhost/hello-world';

        $post = (object) [
            'ID' => 123,
            'post_title' => 'Hello World',
            'post_type' => 'post',
            'post_author' => '1',
        ];

        $captured = null;
        $this->trigger->subscribe(function (array $payload) use (&$captured) {
            $captured = $payload;
        });

        $this->fireAction('transition_post_status', 'publish', 'draft', $post);

        $this->assertNotNull($captured);
        $this->assertSame(123, $captured['post_id']);
        $this->assertSame('Hello World', $captured['post_title']);
        $this->assertSame('http://localhost/hello-world', $captured['post_url']);
        $this->assertSame('post', $captured['post_type']);
        $this->assertSame(1, $captured['author_id']);
    }

    public function testDoesNotFireWhenAlreadyPublished(): void
    {
        $post = (object) ['ID' => 1, 'post_title' => 'T', 'post_type' => 'post', 'post_author' => '1'];

        $fired = false;
        $this->trigger->subscribe(function () use (&$fired) {
            $fired = true;
        });

        $this->fireAction('transition_post_status', 'publish', 'publish', $post);
        $this->assertFalse($fired);
    }

    public function testDoesNotFireForNonPublishTransition(): void
    {
        $post = (object) ['ID' => 1, 'post_title' => 'T', 'post_type' => 'post', 'post_author' => '1'];

        $fired = false;
        $this->trigger->subscribe(function () use (&$fired) {
            $fired = true;
        });

        $this->fireAction('transition_post_status', 'draft', 'pending', $post);
        $this->assertFalse($fired);
    }

    private function fireAction(string $hook, ...$args): void
    {
        foreach ($GLOBALS['_test_actions'][$hook] ?? [] as $callback) {
            $callback(...$args);
        }
    }
}
