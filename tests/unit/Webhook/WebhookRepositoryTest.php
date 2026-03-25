<?php

namespace WSms\Tests\Unit\Webhook;

use PHPUnit\Framework\TestCase;
use WSms\Webhook\WebhookRepository;

class WebhookRepositoryTest extends TestCase
{
    private WebhookRepository $repo;

    protected function setUp(): void
    {
        $GLOBALS['_test_options'] = [];
        $this->repo = new WebhookRepository();
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['_test_options']);
    }

    public function test_save_new_webhook_generates_id(): void
    {
        $id = $this->repo->save([
            'name'   => 'Test',
            'url'    => 'https://example.com/webhook',
            'events' => ['message.sent'],
            'status' => 'active',
            'secret' => 'abc',
        ]);

        $this->assertNotEmpty($id);

        $webhook = $this->repo->find($id);
        $this->assertNotNull($webhook);
        $this->assertSame('Test', $webhook['name']);
        $this->assertSame('active', $webhook['status']);
        $this->assertArrayHasKey('created_at', $webhook);
        $this->assertArrayHasKey('updated_at', $webhook);
    }

    public function test_save_existing_webhook_updates_it(): void
    {
        $id = $this->repo->save([
            'name'   => 'Original',
            'url'    => 'https://example.com/webhook',
            'events' => ['message.sent'],
            'status' => 'active',
            'secret' => 'abc',
        ]);

        $webhook = $this->repo->find($id);
        $webhook['name'] = 'Updated';
        $this->repo->save($webhook);

        $updated = $this->repo->find($id);
        $this->assertSame('Updated', $updated['name']);
    }

    public function test_find_returns_null_for_missing(): void
    {
        $this->assertNull($this->repo->find('nonexistent'));
    }

    public function test_find_all_returns_all_webhooks(): void
    {
        $this->repo->save(['name' => 'A', 'url' => 'https://a.com', 'events' => [], 'status' => 'active', 'secret' => 'a']);
        $this->repo->save(['name' => 'B', 'url' => 'https://b.com', 'events' => [], 'status' => 'paused', 'secret' => 'b']);

        $all = $this->repo->findAll();
        $this->assertCount(2, $all);
    }

    public function test_delete_removes_webhook(): void
    {
        $id = $this->repo->save(['name' => 'Del', 'url' => 'https://del.com', 'events' => [], 'status' => 'active', 'secret' => 'x']);

        $this->assertTrue($this->repo->delete($id));
        $this->assertNull($this->repo->find($id));
    }

    public function test_delete_nonexistent_returns_false(): void
    {
        $this->assertFalse($this->repo->delete('nonexistent'));
    }

    public function test_find_active_for_event_matches_active_with_event(): void
    {
        $this->repo->save(['name' => 'A', 'url' => 'https://a.com', 'events' => ['message.sent', 'contact.created'], 'status' => 'active', 'secret' => 'a']);
        $this->repo->save(['name' => 'B', 'url' => 'https://b.com', 'events' => ['message.sent'], 'status' => 'active', 'secret' => 'b']);
        $this->repo->save(['name' => 'C', 'url' => 'https://c.com', 'events' => ['contact.created'], 'status' => 'active', 'secret' => 'c']);

        $matches = $this->repo->findActiveForEvent('message.sent');
        $this->assertCount(2, $matches);
    }

    public function test_find_active_for_event_skips_paused(): void
    {
        $this->repo->save(['name' => 'Paused', 'url' => 'https://p.com', 'events' => ['message.sent'], 'status' => 'paused', 'secret' => 'p']);

        $matches = $this->repo->findActiveForEvent('message.sent');
        $this->assertCount(0, $matches);
    }

    public function test_find_active_for_event_no_match(): void
    {
        $this->repo->save(['name' => 'A', 'url' => 'https://a.com', 'events' => ['contact.created'], 'status' => 'active', 'secret' => 'a']);

        $matches = $this->repo->findActiveForEvent('message.sent');
        $this->assertCount(0, $matches);
    }

    public function test_empty_option_returns_empty_array(): void
    {
        $this->assertSame([], $this->repo->findAll());
        $this->assertSame([], $this->repo->findActiveForEvent('message.sent'));
    }

    public function test_last_test_persisted_on_save(): void
    {
        $id = $this->repo->save([
            'name'      => 'WithTest',
            'url'       => 'https://t.com',
            'events'    => ['message.sent'],
            'status'    => 'active',
            'secret'    => 'x',
            'last_test' => ['success' => true, 'message' => 'OK', 'at' => '2026-01-01T00:00:00Z'],
        ]);

        $webhook = $this->repo->find($id);
        $this->assertTrue($webhook['last_test']['success']);
        $this->assertSame('OK', $webhook['last_test']['message']);
    }
}
