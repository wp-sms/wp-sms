<?php

namespace WSms\Rest;

use WSms\Log\Contracts\MessageLoggerInterface;

defined('ABSPATH') || exit;

class MessageLogController
{
    private const NAMESPACE = 'wsms/v1';

    public function __construct(
        private readonly MessageLoggerInterface $messageLogger,
    ) {
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/message-logs', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'index'],
                'permission_callback' => [$this, 'canManage'],
                'args'                => [
                    'channel'    => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                    'status'     => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                    'recipient'  => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                    'gateway_id' => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                    'per_page'   => ['type' => 'integer', 'default' => 50],
                    'offset'     => ['type' => 'integer', 'default' => 0],
                ],
            ],
        ]);
    }

    public function canManage(): bool
    {
        return current_user_can('manage_options');
    }

    public function index(\WP_REST_Request $request): \WP_REST_Response
    {
        $filters = [];
        foreach (['channel', 'status', 'recipient', 'gateway_id'] as $key) {
            if ($request->get_param($key)) {
                $filters[$key] = $request->get_param($key);
            }
        }

        $logs = $this->messageLogger->findAll(
            $filters,
            (int) $request->get_param('per_page'),
            (int) $request->get_param('offset'),
        );

        $total = $this->messageLogger->count($filters);

        return new \WP_REST_Response([
            'items' => $logs,
            'total' => $total,
        ]);
    }
}
