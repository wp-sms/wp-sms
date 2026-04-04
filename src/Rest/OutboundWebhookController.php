<?php

namespace WSms\Rest;

use WSms\Exception\NotFoundException;
use WSms\Exception\ValidationException;
use WSms\Webhook\WebhookEventMap;
use WSms\Webhook\WebhookHttpClient;
use WSms\Webhook\WebhookRepository;

defined('ABSPATH') || exit;

class OutboundWebhookController extends Controller
{
    public function __construct(
        private readonly WebhookRepository $repository,
    ) {
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/outbound-webhooks', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'index'],
                'permission_callback' => $this->canViewSection('channels'),
            ],
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'store'],
                'permission_callback' => $this->canManageSection('channels'),
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/outbound-webhooks/events', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'events'],
                'permission_callback' => $this->canViewSection('channels'),
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/outbound-webhooks/(?P<id>[A-Za-z0-9]+)', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'show'],
                'permission_callback' => $this->canViewSection('channels'),
            ],
            [
                'methods'             => 'PUT',
                'callback'            => [$this, 'update'],
                'permission_callback' => $this->canManageSection('channels'),
            ],
            [
                'methods'             => 'DELETE',
                'callback'            => [$this, 'destroy'],
                'permission_callback' => $this->canManageSection('channels'),
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/outbound-webhooks/(?P<id>[A-Za-z0-9]+)/toggle', [
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'toggle'],
                'permission_callback' => $this->canManageSection('channels'),
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/outbound-webhooks/(?P<id>[A-Za-z0-9]+)/test-connection', [
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'testConnection'],
                'permission_callback' => $this->canManageSection('channels'),
            ],
        ]);
    }

    public function index(): \WP_REST_Response
    {
        return $this->handle(function () {
            $webhooks = array_values($this->repository->findAll());

            return $this->ok($webhooks);
        });
    }

    public function store(\WP_REST_Request $request): \WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            $data = $request->get_json_params();

            $this->validateWebhookData($data);

            $webhook = [
                'name'        => sanitize_text_field($data['name']),
                'url'         => esc_url_raw($data['url']),
                'secret'      => bin2hex(random_bytes(32)),
                'events'      => $data['events'],
                'status'      => 'active',
                'description' => isset($data['description']) ? sanitize_textarea_field($data['description']) : null,
                'last_test'   => null,
            ];

            $id = $this->repository->save($webhook);

            return $this->created($this->repository->find($id));
        });
    }

    public function show(\WP_REST_Request $request): \WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            $webhook = $this->findOrFail($request->get_param('id'));

            return $this->ok($webhook);
        });
    }

    public function update(\WP_REST_Request $request): \WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            $webhook = $this->findOrFail($request->get_param('id'));
            $data = $request->get_json_params();

            if (isset($data['name'])) {
                $this->validateName($data['name']);
                $webhook['name'] = sanitize_text_field($data['name']);
            }

            if (isset($data['url'])) {
                $this->validateUrl($data['url']);
                $webhook['url'] = esc_url_raw($data['url']);
            }

            if (isset($data['events'])) {
                $this->validateEvents($data['events']);
                $webhook['events'] = $data['events'];
            }

            if (array_key_exists('description', $data)) {
                $webhook['description'] = $data['description'] !== null
                    ? sanitize_textarea_field($data['description'])
                    : null;
            }

            if (isset($data['regenerate_secret']) && $data['regenerate_secret'] === true) {
                $webhook['secret'] = bin2hex(random_bytes(32));
            }

            $this->repository->save($webhook);

            return $this->ok($webhook);
        });
    }

    public function destroy(\WP_REST_Request $request): \WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            $this->findOrFail($request->get_param('id'));
            $this->repository->delete($request->get_param('id'));

            return $this->ok();
        });
    }

    public function toggle(\WP_REST_Request $request): \WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            $webhook = $this->findOrFail($request->get_param('id'));
            $webhook['status'] = $webhook['status'] === 'active' ? 'paused' : 'active';
            $this->repository->save($webhook);

            return $this->ok($webhook);
        });
    }

    public function testConnection(\WP_REST_Request $request): \WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            $webhook = $this->findOrFail($request->get_param('id'));

            $firstEvent = $webhook['events'][0] ?? 'message.sent';

            $envelope = [
                'event'      => $firstEvent,
                'timestamp'  => gmdate('Y-m-d\TH:i:s\Z'),
                'webhook_id' => $webhook['id'],
                'test'       => true,
                'data'       => WebhookEventMap::getSamplePayload($firstEvent),
            ];

            $startTime = microtime(true);

            $result = WebhookHttpClient::send(
                $webhook['url'],
                wp_json_encode($envelope),
                $webhook['secret'],
                $firstEvent,
                $webhook['id'],
            );

            $elapsed = round((microtime(true) - $startTime) * 1000);
            $response = $result['response'];

            if (is_wp_error($response)) {
                $webhook['last_test'] = [
                    'success' => false,
                    'message' => $response->get_error_message(),
                    'at'      => gmdate('Y-m-d\TH:i:s\Z'),
                ];
                $this->repository->save($webhook);

                return new \WP_REST_Response([
                    'success' => false,
                    'message' => $response->get_error_message(),
                    'details' => ['response_time_ms' => $elapsed],
                ]);
            }

            $code = wp_remote_retrieve_response_code($response);
            $success = WebhookHttpClient::isSuccess($response);
            $message = $success
                ? "Connection successful \u2014 HTTP {$code} in {$elapsed}ms"
                : "Connection failed \u2014 HTTP {$code}";

            $webhook['last_test'] = [
                'success' => $success,
                'message' => $message,
                'at'      => gmdate('Y-m-d\TH:i:s\Z'),
            ];
            $this->repository->save($webhook);

            return new \WP_REST_Response([
                'success' => $success,
                'message' => $message,
                'details' => [
                    'status_code'      => $code,
                    'response_time_ms' => $elapsed,
                ],
            ]);
        });
    }

    public function events(): \WP_REST_Response
    {
        return $this->handle(function () {
            return $this->ok(WebhookEventMap::getGroupedEvents());
        });
    }

    private function findOrFail(string $id): array
    {
        $webhook = $this->repository->find($id);

        if (!$webhook) {
            throw NotFoundException::entity('Webhook', $id);
        }

        return $webhook;
    }

    private function validateWebhookData(array $data): void
    {
        $errors = [];

        if (empty(trim($data['name'] ?? ''))) {
            $errors['name'] = __('Name is required.', 'wp-sms');
        }

        if (empty($data['url']) || !filter_var($data['url'], FILTER_VALIDATE_URL)) {
            $errors['url'] = __('A valid URL is required.', 'wp-sms');
        }

        if (empty($data['events']) || !is_array($data['events'])) {
            $errors['events'] = __('At least one event is required.', 'wp-sms');
        } else {
            $this->validateEvents($data['events']);
        }

        if (!empty($errors)) {
            throw new ValidationException($errors);
        }
    }

    private function validateName(string $name): void
    {
        if (empty(trim($name))) {
            throw ValidationException::field('name', __('Name is required.', 'wp-sms'));
        }
    }

    private function validateUrl(string $url): void
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw ValidationException::field('url', __('A valid URL is required.', 'wp-sms'));
        }
    }

    private function validateEvents(array $events): void
    {
        $valid = WebhookEventMap::getAllEventNames();

        foreach ($events as $event) {
            if (!in_array($event, $valid, true)) {
                throw ValidationException::field('events', sprintf(
                    __('Unknown event: %s', 'wp-sms'),
                    $event
                ));
            }
        }
    }
}
