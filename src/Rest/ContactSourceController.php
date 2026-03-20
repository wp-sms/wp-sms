<?php

namespace WSms\Rest;

use WP_REST_Request;
use WP_REST_Response;
use WSms\Contact\Source\ContactSourceManager;
use WSms\Contact\Source\ContactSourceRegistry;

defined('ABSPATH') || exit;

class ContactSourceController extends Controller
{
    public function __construct(
        private readonly ContactSourceRegistry $registry,
        private readonly ContactSourceManager $manager,
    ) {
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/contact-sources', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'index'],
                'permission_callback' => [$this, 'canManage'],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'connect'],
                'permission_callback' => [$this, 'canManage'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/contact-sources/(?P<type>[a-z_]+)', [
            [
                'methods'             => 'PUT',
                'callback'            => [$this, 'update'],
                'permission_callback' => [$this, 'canManage'],
            ],
            [
                'methods'             => 'DELETE',
                'callback'            => [$this, 'disconnect'],
                'permission_callback' => [$this, 'canManage'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/contact-sources/(?P<type>[a-z_]+)/sync', [
            'methods'             => 'POST',
            'callback'            => [$this, 'sync'],
            'permission_callback' => [$this, 'canManage'],
        ]);

        register_rest_route(self::NAMESPACE, '/contact-sources/(?P<type>[a-z_]+)/status', [
            'methods'             => 'GET',
            'callback'            => [$this, 'status'],
            'permission_callback' => [$this, 'canManage'],
        ]);

        register_rest_route(self::NAMESPACE, '/contact-sources/fields/(?P<type>[a-z_]+)', [
            'methods'             => 'GET',
            'callback'            => [$this, 'fields'],
            'permission_callback' => [$this, 'canManage'],
        ]);
    }

    public function index(): WP_REST_Response
    {
        $stored = $this->manager->getAll();
        $items = [];

        foreach ($this->registry->all() as $type => $source) {
            $data = $stored[$type] ?? null;

            $items[] = [
                'type'          => $source->getType(),
                'name'          => $source->getName(),
                'description'   => $source->getDescription(),
                'icon'          => $source->getIcon(),
                'available'     => $source->isAvailable(),
                'status'        => $data['status'] ?? 'disconnected',
                'config'        => $data['config'] ?? null,
                'stats'         => $data['stats'] ?? null,
                'contact_count' => $data ? $this->manager->getContactCount($type) : 0,
            ];
        }

        return new WP_REST_Response(['items' => $items]);
    }

    public function connect(WP_REST_Request $request): WP_REST_Response
    {
        $type = $request->get_param('type');
        $config = $request->get_param('config') ?? [];

        $source = $this->registry->get($type);
        if (!$source) {
            return new WP_REST_Response(['error' => 'Unknown source type.'], 400);
        }

        if (!$source->isAvailable()) {
            return new WP_REST_Response(['error' => 'This source is not available.'], 400);
        }

        // Merge defaults
        if (empty($config['field_mapping'])) {
            $config['field_mapping'] = $source->getDefaultFieldMapping();
        }
        if (!isset($config['auto_sync'])) {
            $config['auto_sync'] = true;
        }

        $this->manager->save($type, [
            'status' => 'connected',
            'config' => $config,
            'stats'  => [
                'total_synced'     => 0,
                'last_synced_at'   => null,
                'sync_in_progress' => false,
                'sync_progress'    => null,
                'last_error'       => null,
            ],
        ]);

        return new WP_REST_Response(['success' => true], 201);
    }

    public function update(WP_REST_Request $request): WP_REST_Response
    {
        $type = $request->get_param('type');
        $config = $request->get_param('config');

        $existing = $this->manager->get($type);
        if (!$existing) {
            return new WP_REST_Response(['error' => 'Source not connected.'], 404);
        }

        if ($config !== null) {
            $existing['config'] = $config;
        }

        $this->manager->save($type, $existing);

        return new WP_REST_Response(['success' => true]);
    }

    public function disconnect(WP_REST_Request $request): WP_REST_Response
    {
        $type = $request->get_param('type');

        $existing = $this->manager->get($type);
        if (!$existing) {
            return new WP_REST_Response(['error' => 'Source not connected.'], 404);
        }

        $this->manager->delete($type);

        return new WP_REST_Response(['success' => true]);
    }

    public function sync(WP_REST_Request $request): WP_REST_Response
    {
        $type = $request->get_param('type');

        $existing = $this->manager->get($type);
        if (!$existing) {
            return new WP_REST_Response(['error' => 'Source not connected.'], 404);
        }

        if ($this->manager->isSyncing($type)) {
            return new WP_REST_Response(['error' => 'Sync already in progress.'], 409);
        }

        $source = $this->registry->get($type);
        if (!$source) {
            return new WP_REST_Response(['error' => 'Unknown source type.'], 400);
        }

        $this->manager->startSync($type);

        return new WP_REST_Response([
            'success'         => true,
            'total_available' => $source->countAvailable($existing['config'] ?? []),
        ]);
    }

    public function status(WP_REST_Request $request): WP_REST_Response
    {
        $type = $request->get_param('type');

        $existing = $this->manager->get($type);
        if (!$existing) {
            return new WP_REST_Response(['error' => 'Source not connected.'], 404);
        }

        return new WP_REST_Response([
            'status'        => $existing['status'],
            'stats'         => $existing['stats'] ?? null,
            'contact_count' => $this->manager->getContactCount($type),
        ]);
    }

    public function fields(WP_REST_Request $request): WP_REST_Response
    {
        $type = $request->get_param('type');

        $source = $this->registry->get($type);
        if (!$source) {
            return new WP_REST_Response(['error' => 'Unknown source type.'], 400);
        }

        return new WP_REST_Response([
            'fields'          => $source->getAvailableFields(),
            'default_mapping' => $source->getDefaultFieldMapping(),
            'config_schema'   => $source->getConfigSchema(),
        ]);
    }
}
