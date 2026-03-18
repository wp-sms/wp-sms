<?php

namespace WSms\Rest;

use WSms\Contact\Contracts\TagRepositoryInterface;

defined('ABSPATH') || exit;

class TagController
{
    private const NAMESPACE = 'wsms/v1';

    public function __construct(
        private readonly TagRepositoryInterface $tags,
    ) {
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/tags', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'index'],
                'permission_callback' => [$this, 'canManage'],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'store'],
                'permission_callback' => [$this, 'canManage'],
                'args'                => [
                    'name'  => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                    'slug'  => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                    'color' => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                ],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/tags/(?P<id>[A-Za-z0-9]+)', [
            [
                'methods'             => 'PUT',
                'callback'            => [$this, 'update'],
                'permission_callback' => [$this, 'canManage'],
                'args'                => [
                    'name'  => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                    'slug'  => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                    'color' => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                ],
            ],
            [
                'methods'             => 'DELETE',
                'callback'            => [$this, 'destroy'],
                'permission_callback' => [$this, 'canManage'],
            ],
        ]);
    }

    public function canManage(): bool
    {
        return current_user_can('manage_options');
    }

    public function index(): \WP_REST_Response
    {
        $tags = $this->tags->findAll();

        foreach ($tags as &$tag) {
            $tag['contact_count'] = $this->tags->getContactCount($tag['id']);
        }

        return new \WP_REST_Response(['items' => $tags]);
    }

    public function store(\WP_REST_Request $request): \WP_REST_Response
    {
        $name = $request->get_param('name');
        if (trim($name) === '') {
            return new \WP_REST_Response([
                'success' => false,
                'error'   => 'invalid_name',
                'message' => __('Tag name cannot be empty', 'wp-sms'),
            ], 400);
        }

        $data = ['name' => $name];

        if ($request->get_param('slug') !== null) {
            $data['slug'] = $request->get_param('slug');
        }
        if ($request->get_param('color') !== null) {
            $data['color'] = $request->get_param('color');
        }

        $id = $this->tags->create($data);
        $tag = $this->tags->find($id);
        $tag['contact_count'] = 0;

        return new \WP_REST_Response(['success' => true, 'data' => $tag], 201);
    }

    public function update(\WP_REST_Request $request): \WP_REST_Response
    {
        $id = $request->get_param('id');
        $tag = $this->tags->find($id);

        if (!$tag) {
            return new \WP_REST_Response([
                'success' => false,
                'error'   => 'not_found',
                'message' => __('Tag not found', 'wp-sms'),
            ], 404);
        }

        $data = [];
        if ($request->get_param('name') !== null) {
            $data['name'] = $request->get_param('name');
        }
        if ($request->get_param('slug') !== null) {
            $data['slug'] = $request->get_param('slug');
        }
        if ($request->get_param('color') !== null) {
            $data['color'] = $request->get_param('color');
        }

        $this->tags->update($id, $data);

        return new \WP_REST_Response(['success' => true, 'data' => $this->tags->find($id)]);
    }

    public function destroy(\WP_REST_Request $request): \WP_REST_Response
    {
        $id = $request->get_param('id');
        $tag = $this->tags->find($id);

        if (!$tag) {
            return new \WP_REST_Response([
                'success' => false,
                'error'   => 'not_found',
                'message' => __('Tag not found', 'wp-sms'),
            ], 404);
        }

        $this->tags->delete($id);

        return new \WP_REST_Response(['success' => true]);
    }
}
