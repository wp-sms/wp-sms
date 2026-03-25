<?php

namespace WSms\Tests\Unit\Rest;

use PHPUnit\Framework\TestCase;
use WSms\Rest\OutboundWebhookController;
use WSms\Webhook\WebhookRepository;

class OutboundWebhookControllerTest extends TestCase
{
    private WebhookRepository $repo;
    private OutboundWebhookController $controller;

    protected function setUp(): void
    {
        $GLOBALS['_test_options'] = [];
        $this->repo = new WebhookRepository();
        $this->controller = new OutboundWebhookController($this->repo);
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['_test_options']);
    }

    // --- Create ---

    public function test_store_creates_webhook_with_auto_generated_id_and_secret(): void
    {
        $request = new \WP_REST_Request('POST');
        $request->set_body(json_encode([
            'name'   => 'Zapier',
            'url'    => 'https://hooks.zapier.com/abc',
            'events' => ['message.sent', 'contact.created'],
        ]));

        $response = $this->controller->store($request);

        $this->assertSame(201, $response->get_status());
        $data = $response->get_data()['data'];
        $this->assertNotEmpty($data['id']);
        $this->assertSame('Zapier', $data['name']);
        $this->assertSame('https://hooks.zapier.com/abc', $data['url']);
        $this->assertNotEmpty($data['secret']);
        $this->assertSame(64, strlen($data['secret'])); // bin2hex(32 bytes)
        $this->assertSame('active', $data['status']);
        $this->assertSame(['message.sent', 'contact.created'], $data['events']);
    }

    public function test_store_validates_required_name(): void
    {
        $request = new \WP_REST_Request('POST');
        $request->set_body(json_encode([
            'url'    => 'https://hooks.zapier.com/abc',
            'events' => ['message.sent'],
        ]));

        $response = $this->controller->store($request);

        $this->assertSame(422, $response->get_status());
        $this->assertArrayHasKey('name', $response->get_data()['errors']);
    }

    public function test_store_validates_required_url(): void
    {
        $request = new \WP_REST_Request('POST');
        $request->set_body(json_encode([
            'name'   => 'Test',
            'url'    => 'not-a-url',
            'events' => ['message.sent'],
        ]));

        $response = $this->controller->store($request);

        $this->assertSame(422, $response->get_status());
        $this->assertArrayHasKey('url', $response->get_data()['errors']);
    }

    public function test_store_validates_required_events(): void
    {
        $request = new \WP_REST_Request('POST');
        $request->set_body(json_encode([
            'name' => 'Test',
            'url'  => 'https://hooks.zapier.com/abc',
        ]));

        $response = $this->controller->store($request);

        $this->assertSame(422, $response->get_status());
        $this->assertArrayHasKey('events', $response->get_data()['errors']);
    }

    public function test_store_validates_empty_events(): void
    {
        $request = new \WP_REST_Request('POST');
        $request->set_body(json_encode([
            'name'   => 'Test',
            'url'    => 'https://hooks.zapier.com/abc',
            'events' => [],
        ]));

        $response = $this->controller->store($request);

        $this->assertSame(422, $response->get_status());
    }

    public function test_store_rejects_unknown_events(): void
    {
        $request = new \WP_REST_Request('POST');
        $request->set_body(json_encode([
            'name'   => 'Test',
            'url'    => 'https://hooks.zapier.com/abc',
            'events' => ['nonexistent.event'],
        ]));

        $response = $this->controller->store($request);

        $this->assertSame(422, $response->get_status());
    }

    // --- Update ---

    public function test_update_partial_update_works(): void
    {
        $id = $this->createTestWebhook();

        $request = new \WP_REST_Request('PUT');
        $request->set_param('id', $id);
        $request->set_body(json_encode(['name' => 'Updated Name']));

        $response = $this->controller->update($request);

        $this->assertSame(200, $response->get_status());
        $this->assertSame('Updated Name', $response->get_data()['data']['name']);
    }

    public function test_update_nonexistent_returns_404(): void
    {
        $request = new \WP_REST_Request('PUT');
        $request->set_param('id', 'nonexistent');
        $request->set_body(json_encode(['name' => 'Foo']));

        $response = $this->controller->update($request);

        $this->assertSame(404, $response->get_status());
    }

    // --- Toggle ---

    public function test_toggle_flips_active_to_paused(): void
    {
        $id = $this->createTestWebhook();

        $request = new \WP_REST_Request('POST');
        $request->set_param('id', $id);

        $response = $this->controller->toggle($request);

        $this->assertSame(200, $response->get_status());
        $this->assertSame('paused', $response->get_data()['data']['status']);

        // Toggle back
        $response2 = $this->controller->toggle($request);
        $this->assertSame('active', $response2->get_data()['data']['status']);
    }

    // --- Delete ---

    public function test_destroy_removes_webhook(): void
    {
        $id = $this->createTestWebhook();

        $request = new \WP_REST_Request('DELETE');
        $request->set_param('id', $id);

        $response = $this->controller->destroy($request);

        $this->assertSame(200, $response->get_status());
        $this->assertNull($this->repo->find($id));
    }

    public function test_destroy_nonexistent_returns_404(): void
    {
        $request = new \WP_REST_Request('DELETE');
        $request->set_param('id', 'nonexistent');

        $response = $this->controller->destroy($request);

        $this->assertSame(404, $response->get_status());
    }

    // --- Index ---

    public function test_index_lists_all_webhooks(): void
    {
        $this->createTestWebhook();
        $this->createTestWebhook('Second');

        $response = $this->controller->index();

        $this->assertSame(200, $response->get_status());
        $this->assertCount(2, $response->get_data()['data']);
    }

    // --- Show ---

    public function test_show_returns_single_webhook(): void
    {
        $id = $this->createTestWebhook();

        $request = new \WP_REST_Request('GET');
        $request->set_param('id', $id);

        $response = $this->controller->show($request);

        $this->assertSame(200, $response->get_status());
        $this->assertSame($id, $response->get_data()['data']['id']);
    }

    // --- Events ---

    public function test_events_returns_grouped_events(): void
    {
        $response = $this->controller->events();

        $this->assertSame(200, $response->get_status());
        $data = $response->get_data()['data'];
        $this->assertArrayHasKey('Messages', $data);
        $this->assertArrayHasKey('Contacts', $data);
    }

    // --- Helpers ---

    private function createTestWebhook(string $name = 'Test'): string
    {
        return $this->repo->save([
            'name'      => $name,
            'url'       => 'https://hooks.example.com/abc',
            'events'    => ['message.sent'],
            'status'    => 'active',
            'secret'    => bin2hex(random_bytes(32)),
            'last_test' => null,
        ]);
    }
}
