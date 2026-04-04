<?php

namespace WSms\Rest;

use WSms\Service\Dashboard\DashboardService;

defined('ABSPATH') || exit;

class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardService $dashboard,
    ) {
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/dashboard/summary', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'summary'],
                'permission_callback' => $this->canViewSection('dashboard'),
                'args'                => [
                    'range' => [
                        'type'              => 'integer',
                        'default'           => 30,
                        'sanitize_callback' => 'absint',
                    ],
                ],
            ],
        ]);
    }

    public function summary(\WP_REST_Request $request): \WP_REST_Response
    {
        return $this->handle(fn() => $this->ok(
            $this->dashboard->getSummary((int) $request->get_param('range'))
        ));
    }
}
