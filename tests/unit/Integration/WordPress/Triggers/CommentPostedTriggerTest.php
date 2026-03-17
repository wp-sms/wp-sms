<?php

namespace WSms\Tests\Unit\Integration\WordPress\Triggers;

use PHPUnit\Framework\TestCase;
use WSms\Integration\WordPress\Triggers\CommentPostedTrigger;

class CommentPostedTriggerTest extends TestCase
{
    private CommentPostedTrigger $trigger;

    protected function setUp(): void
    {
        $this->trigger = new CommentPostedTrigger();
        $GLOBALS['_test_actions'] = [];
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['_test_actions'], $GLOBALS['_test_comment']);
    }

    public function testMetadata(): void
    {
        $this->assertSame('wordpress.comment_posted', $this->trigger->getId());
        $this->assertSame('Comment Posted', $this->trigger->getName());
        $this->assertSame('WordPress', $this->trigger->getGroup());
    }

    public function testPayloadSchemaHasExpectedFields(): void
    {
        $schema = $this->trigger->getPayloadSchema();
        $this->assertArrayHasKey('comment_id', $schema);
        $this->assertArrayHasKey('post_id', $schema);
        $this->assertArrayHasKey('author', $schema);
        $this->assertArrayHasKey('email', $schema);
        $this->assertArrayHasKey('content', $schema);
        $this->assertArrayHasKey('approved', $schema);
        $this->assertSame('email', $schema['email']['format']);
    }

    public function testSubscribeRegistersCommentPostHook(): void
    {
        $this->trigger->subscribe(function () {});
        $this->assertArrayHasKey('comment_post', $GLOBALS['_test_actions']);
    }

    public function testProducesCorrectPayload(): void
    {
        $comment = (object) [
            'comment_post_ID' => '123',
            'comment_author' => 'Jane',
            'comment_author_email' => 'jane@example.com',
            'comment_content' => 'Great!',
        ];
        $GLOBALS['_test_comment'] = $comment;

        $captured = null;
        $this->trigger->subscribe(function (array $payload) use (&$captured) {
            $captured = $payload;
        });

        $this->fireAction('comment_post', 15, 1);

        $this->assertNotNull($captured);
        $this->assertSame(15, $captured['comment_id']);
        $this->assertSame(123, $captured['post_id']);
        $this->assertSame('Jane', $captured['author']);
        $this->assertSame('jane@example.com', $captured['email']);
        $this->assertSame('Great!', $captured['content']);
        $this->assertSame('1', $captured['approved']);
    }

    public function testDoesNotFireIfCommentNotFound(): void
    {
        $GLOBALS['_test_comment'] = null;

        $fired = false;
        $this->trigger->subscribe(function () use (&$fired) {
            $fired = true;
        });

        $this->fireAction('comment_post', 999, 0);
        $this->assertFalse($fired);
    }

    private function fireAction(string $hook, ...$args): void
    {
        foreach ($GLOBALS['_test_actions'][$hook] ?? [] as $callback) {
            $callback(...$args);
        }
    }
}
