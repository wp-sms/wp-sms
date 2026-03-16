<?php

namespace WSms\Rest;

use WSms\Contact\Contracts\ContactRepositoryInterface;
use WSms\Contact\Contracts\SegmentEvaluatorInterface;

defined('ABSPATH') || exit;

class ContactController
{
    private const NAMESPACE = 'wsms/v1';

    public function __construct(
        private readonly ContactRepositoryInterface $contacts,
        private readonly SegmentEvaluatorInterface $segmentEvaluator,
    ) {
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/contacts', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'index'],
                'permission_callback' => [$this, 'canManage'],
                'args'                => [
                    'status'   => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                    'search'   => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                    'per_page' => ['type' => 'integer', 'default' => 50],
                    'offset'   => ['type' => 'integer', 'default' => 0],
                ],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'store'],
                'permission_callback' => [$this, 'canManage'],
                'args'                => [
                    'email'         => ['type' => 'string', 'sanitize_callback' => 'sanitize_email'],
                    'phone'         => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                    'first_name'    => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                    'last_name'     => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                    'status'        => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                    'source'        => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                    'custom_fields' => ['type' => 'object'],
                ],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/contacts/(?P<id>[A-Za-z0-9]+)', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'show'],
                'permission_callback' => [$this, 'canManage'],
            ],
            [
                'methods'             => 'PUT',
                'callback'            => [$this, 'update'],
                'permission_callback' => [$this, 'canManage'],
                'args'                => [
                    'email'         => ['type' => 'string', 'sanitize_callback' => 'sanitize_email'],
                    'phone'         => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                    'first_name'    => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                    'last_name'     => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                    'status'        => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                    'source'        => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                    'custom_fields' => ['type' => 'object'],
                ],
            ],
            [
                'methods'             => 'DELETE',
                'callback'            => [$this, 'destroy'],
                'permission_callback' => [$this, 'canManage'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/contacts/(?P<id>[A-Za-z0-9]+)/tags', [
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'addTag'],
                'permission_callback' => [$this, 'canManage'],
                'args'                => [
                    'tag_id' => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                ],
            ],
            [
                'methods'             => 'DELETE',
                'callback'            => [$this, 'removeTag'],
                'permission_callback' => [$this, 'canManage'],
                'args'                => [
                    'tag_id' => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                ],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/segments/preview', [
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'segmentPreview'],
                'permission_callback' => [$this, 'canManage'],
                'args'                => [
                    'conditions' => ['type' => 'array', 'default' => []],
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
        if ($request->get_param('status')) {
            $filters['status'] = $request->get_param('status');
        }
        if ($request->get_param('search')) {
            $filters['search'] = $request->get_param('search');
        }

        $contacts = $this->contacts->findAll(
            $filters,
            (int) $request->get_param('per_page'),
            (int) $request->get_param('offset'),
        );

        $total = $this->contacts->count($filters);

        return new \WP_REST_Response([
            'items' => $contacts,
            'total' => $total,
        ]);
    }

    public function store(\WP_REST_Request $request): \WP_REST_Response
    {
        $data = [];
        foreach (['email', 'phone', 'first_name', 'last_name', 'status', 'source', 'custom_fields'] as $field) {
            $value = $request->get_param($field);
            if ($value !== null) {
                $data[$field] = $value;
            }
        }

        $id = $this->contacts->create($data);
        $contact = $this->contacts->find($id);

        return new \WP_REST_Response([
            'success' => true,
            'data'    => $contact,
        ], 201);
    }

    public function show(\WP_REST_Request $request): \WP_REST_Response
    {
        $contact = $this->contacts->find($request->get_param('id'));

        if (!$contact) {
            return new \WP_REST_Response([
                'success' => false,
                'error'   => 'not_found',
                'message' => __('Contact not found', 'wp-sms'),
            ], 404);
        }

        $contact['tags'] = $this->contacts->getTags($contact['id']);

        return new \WP_REST_Response([
            'success' => true,
            'data'    => $contact,
        ]);
    }

    public function update(\WP_REST_Request $request): \WP_REST_Response
    {
        $id = $request->get_param('id');

        $data = [];
        foreach (['email', 'phone', 'first_name', 'last_name', 'status', 'source', 'custom_fields'] as $field) {
            $value = $request->get_param($field);
            if ($value !== null) {
                $data[$field] = $value;
            }
        }

        $this->contacts->update($id, $data);

        return new \WP_REST_Response([
            'success' => true,
            'data'    => $this->contacts->find($id),
        ]);
    }

    public function destroy(\WP_REST_Request $request): \WP_REST_Response
    {
        $this->contacts->delete($request->get_param('id'));

        return new \WP_REST_Response(['success' => true]);
    }

    public function addTag(\WP_REST_Request $request): \WP_REST_Response
    {
        $this->contacts->addTag($request->get_param('id'), $request->get_param('tag_id'));

        return new \WP_REST_Response(['success' => true]);
    }

    public function removeTag(\WP_REST_Request $request): \WP_REST_Response
    {
        $this->contacts->removeTag($request->get_param('id'), $request->get_param('tag_id'));

        return new \WP_REST_Response(['success' => true]);
    }

    public function segmentPreview(\WP_REST_Request $request): \WP_REST_Response
    {
        $conditions = $request->get_param('conditions') ?? [];
        $contacts = $this->segmentEvaluator->evaluate($conditions, 10);
        $total = $this->segmentEvaluator->count($conditions);

        return new \WP_REST_Response([
            'items' => $contacts,
            'total' => $total,
        ]);
    }
}
