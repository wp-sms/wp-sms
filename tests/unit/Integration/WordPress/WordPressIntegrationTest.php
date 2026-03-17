<?php

namespace WSms\Tests\Unit\Integration\WordPress;

use PHPUnit\Framework\TestCase;
use WSms\Flow\Contracts\ActionInterface;
use WSms\Flow\Contracts\TriggerInterface;
use WSms\Integration\WordPress\WordPressIntegration;

class WordPressIntegrationTest extends TestCase
{
    private WordPressIntegration $integration;

    protected function setUp(): void
    {
        $this->integration = new WordPressIntegration();
    }

    public function testMetadata(): void
    {
        $this->assertSame('wordpress', $this->integration->getId());
        $this->assertSame('WordPress', $this->integration->getName());
        $this->assertSame('cms', $this->integration->getCategory());
        $this->assertTrue($this->integration->isAvailable());
    }

    public function testReturnsSevenTriggers(): void
    {
        $triggers = $this->integration->getTriggers();
        $this->assertCount(7, $triggers);

        foreach ($triggers as $trigger) {
            $this->assertInstanceOf(TriggerInterface::class, $trigger);
        }
    }

    public function testTriggerIdsAreCorrect(): void
    {
        $ids = array_map(fn($t) => $t->getId(), $this->integration->getTriggers());

        $expected = [
            'wordpress.user_register',
            'wordpress.post_published',
            'wordpress.comment_posted',
            'wordpress.user_updated',
            'wordpress.user_deleted',
            'wordpress.user_role_changed',
            'wordpress.post_status_changed',
        ];

        foreach ($expected as $id) {
            $this->assertContains($id, $ids);
        }
    }

    public function testReturnsFourActions(): void
    {
        $actions = $this->integration->getActions();
        $this->assertCount(4, $actions);

        foreach ($actions as $action) {
            $this->assertInstanceOf(ActionInterface::class, $action);
        }
    }

    public function testActionIdsAreCorrect(): void
    {
        $ids = array_map(fn($a) => $a->getId(), $this->integration->getActions());

        $this->assertContains('update_user_meta', $ids);
        $this->assertContains('set_user_role', $ids);
        $this->assertContains('create_post', $ids);
        $this->assertContains('delete_user', $ids);
    }
}
