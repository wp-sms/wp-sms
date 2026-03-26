<?php

namespace WSms\Rest;

use WSms\System\SystemHealthService;

defined('ABSPATH') || exit;

class SystemHealthController extends Controller
{
    public function __construct(
        private readonly SystemHealthService $health,
    ) {
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/system/health', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'index'],
                'permission_callback' => [$this, 'canManage'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/system/jobs/(?P<id>\d+)/retry', [
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'retry'],
                'permission_callback' => [$this, 'canManage'],
                'args'                => [
                    'id' => ['type' => 'integer', 'required' => true],
                ],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/system/jobs/(?P<id>\d+)/dismiss', [
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'dismiss'],
                'permission_callback' => [$this, 'canManage'],
                'args'                => [
                    'id' => ['type' => 'integer', 'required' => true],
                ],
            ],
        ]);
    }

    public function index(\WP_REST_Request $request): \WP_REST_Response
    {
        return $this->handle(fn() => $this->ok($this->health->getHealthData()));
    }

    public function retry(\WP_REST_Request $request): \WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            $this->health->retryFailedJob((int) $request->get_param('id'));

            return $this->ok(['retried' => true]);
        });
    }

    public function dismiss(\WP_REST_Request $request): \WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            $this->health->dismissFailedJob((int) $request->get_param('id'));

            return $this->ok(['dismissed' => true]);
        });
    }
}
