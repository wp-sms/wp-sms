<?php

namespace WSms\Rest;

use WSms\Contact\Contracts\TagRepositoryInterface;
use WSms\Exception\ValidationException;

defined('ABSPATH') || exit;

class TagController extends Controller
{
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

    public function index(\WP_REST_Request $request): \WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            $tags = $this->tags->findAll();
            $counts = $this->tags->getContactCounts();

            foreach ($tags as &$tag) {
                $tag['contact_count'] = $counts[$tag['id']] ?? 0;
            }

            return $this->paginated($tags, count($tags));
        });
    }

    public function store(\WP_REST_Request $request): \WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            $name = $request->get_param('name');
            if (trim($name) === '') {
                throw ValidationException::field('name', __('Tag name cannot be empty', 'wp-sms'));
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

            return $this->created($tag);
        });
    }

    public function update(\WP_REST_Request $request): \WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            $id = $request->get_param('id');
            $this->tags->findOrFail($id);

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

            return $this->ok($this->tags->find($id));
        });
    }

    public function destroy(\WP_REST_Request $request): \WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            $id = $request->get_param('id');
            $this->tags->findOrFail($id);
            $this->tags->delete($id);

            return $this->ok();
        });
    }
}
