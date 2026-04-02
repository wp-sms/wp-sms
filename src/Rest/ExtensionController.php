<?php

namespace WSms\Rest;

use WP_REST_Response;
use WSms\Extension\ExtensionRegistry;

defined('ABSPATH') || exit;

class ExtensionController extends Controller
{
    public function __construct(
        private readonly ExtensionRegistry $registry,
    ) {
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/extensions', [
            'methods'             => 'GET',
            'callback'            => [$this, 'handleList'],
            'permission_callback' => [$this, 'canManage'],
        ]);
    }

    public function handleList(): WP_REST_Response
    {
        return $this->handle(fn() => new WP_REST_Response([
            'success'    => true,
            'extensions' => array_values($this->registry->getAll()),
        ]));
    }
}
