<?php

namespace WSms\Rest;

use WP_REST_Request;
use WP_REST_Response;
use WSms\Privacy\PrivacyRequestService;

defined('ABSPATH') || exit;

class PrivacyController extends Controller
{
    public function __construct(
        private readonly PrivacyRequestService $privacyService,
    ) {
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/privacy/lookup', [
            'methods'             => 'POST',
            'callback'            => [$this, 'lookup'],
            'permission_callback' => $this->canManageSection('settings'),
            'args'                => [
                'identifier' => ['type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/privacy/export', [
            'methods'             => 'POST',
            'callback'            => [$this, 'export'],
            'permission_callback' => $this->canManageSection('settings'),
            'args'                => [
                'identifier' => ['type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/privacy/erase', [
            'methods'             => 'POST',
            'callback'            => [$this, 'erase'],
            'permission_callback' => $this->canManageSection('settings'),
            'args'                => [
                'identifier' => ['type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field'],
            ],
        ]);
    }

    public function lookup(WP_REST_Request $request): WP_REST_Response
    {
        return $this->handle(fn() => $this->ok(
            $this->privacyService->lookup($request['identifier']),
        ));
    }

    public function export(WP_REST_Request $request): WP_REST_Response
    {
        return $this->handle(fn() => $this->ok(
            $this->privacyService->export($request['identifier']),
        ));
    }

    public function erase(WP_REST_Request $request): WP_REST_Response
    {
        return $this->handle(fn() => $this->ok(
            $this->privacyService->erase($request['identifier']),
        ));
    }
}
