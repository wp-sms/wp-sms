<?php

namespace WSms\Tests\Unit\Rest;

use PHPUnit\Framework\TestCase;
use WSms\Contact\Source\ContactSourceManager;
use WSms\Contact\Source\ContactSourceRegistry;
use WSms\Contact\Source\Contracts\ContactSourceInterface;
use WSms\Rest\ContactSourceController;

class ContactSourceControllerTest extends TestCase
{
    private ContactSourceRegistry $registry;
    private ContactSourceManager $manager;
    private ContactSourceController $controller;
    private ContactSourceInterface $source;

    protected function setUp(): void
    {
        $this->registry = new ContactSourceRegistry();
        $this->manager = $this->createMock(ContactSourceManager::class);
        $this->controller = new ContactSourceController($this->registry, $this->manager);

        // Register a mock source
        $this->source = $this->createMock(ContactSourceInterface::class);
        $this->source->method('getType')->willReturn('wordpress_users');
        $this->source->method('getName')->willReturn('WordPress Users');
        $this->source->method('getDescription')->willReturn('Sync WP users.');
        $this->source->method('getIcon')->willReturn('wordpress');
        $this->source->method('isAvailable')->willReturn(true);
        $this->source->method('getDefaultFieldMapping')->willReturn([
            'email' => 'user_email',
            'first_name' => 'first_name',
        ]);
        $this->source->method('getAvailableFields')->willReturn([
            'user_email' => ['label' => 'Email', 'type' => 'core'],
        ]);
        $this->source->method('getConfigSchema')->willReturn([]);
        $this->registry->register($this->source);
    }

    // --- GET /contact-sources ---

    public function test_index_lists_all_sources(): void
    {
        $this->manager->method('getAll')->willReturn([
            'wordpress_users' => [
                'status' => 'connected',
                'config' => ['auto_sync' => true],
                'stats'  => ['total_synced' => 10],
            ],
        ]);
        $this->manager->method('getContactCount')->willReturn(10);

        $response = $this->controller->index();

        $data = $response->get_data();
        $this->assertCount(1, $data['items']);
        $this->assertSame('wordpress_users', $data['items'][0]['type']);
        $this->assertSame('connected', $data['items'][0]['status']);
        $this->assertSame(10, $data['items'][0]['contact_count']);
    }

    public function test_index_shows_disconnected_for_unconfigured_source(): void
    {
        $this->manager->method('getAll')->willReturn([]);

        $response = $this->controller->index();

        $data = $response->get_data();
        $this->assertSame('disconnected', $data['items'][0]['status']);
        $this->assertNull($data['items'][0]['config']);
    }

    // --- POST /contact-sources ---

    public function test_connect_creates_source(): void
    {
        $request = new \WP_REST_Request('POST');
        $request->set_param('type', 'wordpress_users');
        $request->set_param('config', ['roles' => ['subscriber']]);

        $this->manager->expects($this->once())->method('save')
            ->with('wordpress_users', $this->callback(function (array $data) {
                return $data['status'] === 'connected'
                    && $data['config']['roles'] === ['subscriber']
                    && !empty($data['config']['field_mapping']);
            }));

        $response = $this->controller->connect($request);

        $this->assertSame(201, $response->get_status());
    }

    public function test_connect_rejects_unknown_type(): void
    {
        $request = new \WP_REST_Request('POST');
        $request->set_param('type', 'unknown_source');

        $response = $this->controller->connect($request);

        $this->assertSame(422, $response->get_status());
    }

    // --- PUT /contact-sources/{type} ---

    public function test_update_modifies_config(): void
    {
        $this->manager->method('get')->with('wordpress_users')
            ->willReturn(['status' => 'connected', 'config' => ['auto_sync' => true]]);

        $this->manager->expects($this->once())->method('save');

        $request = new \WP_REST_Request('PUT');
        $request->set_param('type', 'wordpress_users');
        $request->set_param('config', ['auto_sync' => false, 'field_mapping' => ['email' => 'user_email']]);

        $response = $this->controller->update($request);

        $this->assertSame(200, $response->get_status());
    }

    public function test_update_returns_404_if_not_connected(): void
    {
        $this->manager->method('get')->willReturn(null);

        $request = new \WP_REST_Request('PUT');
        $request->set_param('type', 'wordpress_users');

        $response = $this->controller->update($request);

        $this->assertSame(404, $response->get_status());
    }

    // --- DELETE /contact-sources/{type} ---

    public function test_disconnect_removes_source(): void
    {
        $this->manager->method('get')->with('wordpress_users')
            ->willReturn(['status' => 'connected']);

        $this->manager->expects($this->once())->method('delete')->with('wordpress_users');

        $request = new \WP_REST_Request('DELETE');
        $request->set_param('type', 'wordpress_users');

        $response = $this->controller->disconnect($request);

        $this->assertSame(200, $response->get_status());
    }

    // --- POST /contact-sources/{type}/sync ---

    public function test_sync_starts_sync_process(): void
    {
        $this->manager->method('get')->with('wordpress_users')
            ->willReturn(['status' => 'connected', 'config' => []]);
        $this->manager->method('isSyncing')->willReturn(false);
        $this->manager->expects($this->once())->method('startSync')->with('wordpress_users');

        $this->source->method('countAvailable')->willReturn(500);

        $request = new \WP_REST_Request('POST');
        $request->set_param('type', 'wordpress_users');

        $response = $this->controller->sync($request);

        $data = $response->get_data();
        $this->assertTrue($data['success']);
        $this->assertSame(500, $data['data']['total_available']);
    }

    public function test_sync_returns_409_if_already_syncing(): void
    {
        $this->manager->method('get')->willReturn(['status' => 'syncing']);
        $this->manager->method('isSyncing')->willReturn(true);

        $request = new \WP_REST_Request('POST');
        $request->set_param('type', 'wordpress_users');

        $response = $this->controller->sync($request);

        $this->assertSame(409, $response->get_status());
    }

    // --- GET /contact-sources/{type}/status ---

    public function test_status_returns_current_state(): void
    {
        $this->manager->method('get')->with('wordpress_users')
            ->willReturn([
                'status' => 'syncing',
                'stats'  => ['sync_progress' => ['synced' => 50]],
            ]);
        $this->manager->method('getContactCount')->willReturn(50);

        $request = new \WP_REST_Request('GET');
        $request->set_param('type', 'wordpress_users');

        $response = $this->controller->status($request);

        $data = $response->get_data();
        $this->assertSame('syncing', $data['status']);
        $this->assertSame(50, $data['contact_count']);
    }

    // --- GET /contact-sources/fields/{type} ---

    public function test_fields_returns_available_fields(): void
    {
        $request = new \WP_REST_Request('GET');
        $request->set_param('type', 'wordpress_users');

        $response = $this->controller->fields($request);

        $data = $response->get_data();
        $this->assertArrayHasKey('user_email', $data['fields']);
        $this->assertArrayHasKey('email', $data['default_mapping']);
    }

    public function test_fields_returns_400_for_unknown_type(): void
    {
        $request = new \WP_REST_Request('GET');
        $request->set_param('type', 'unknown');

        $response = $this->controller->fields($request);

        $this->assertSame(422, $response->get_status());
    }
}
