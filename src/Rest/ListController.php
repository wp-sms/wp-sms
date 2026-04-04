<?php

namespace WSms\Rest;

use WSms\Contact\Contracts\ListRepositoryInterface;
use WSms\Contact\Contracts\SegmentEvaluatorInterface;
use WSms\Contact\Contracts\ContactRepositoryInterface;

defined('ABSPATH') || exit;

class ListController extends Controller
{
    public function __construct(
        private readonly ListRepositoryInterface $lists,
        private readonly SegmentEvaluatorInterface $segmentEvaluator,
        private readonly ContactRepositoryInterface $contacts,
    ) {
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/lists', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'index'],
                'permission_callback' => $this->canViewSection('audience'),
                'args'                => [
                    'type' => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                ],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'store'],
                'permission_callback' => $this->canManageSection('audience'),
                'args'                => [
                    'name'        => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                    'type'        => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                    'conditions'  => [
                        'validate_callback' => '__return_true',
                    ],
                    'tag_id'      => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                    'description' => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                ],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/lists/(?P<id>[A-Za-z0-9]+)', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'show'],
                'permission_callback' => $this->canViewSection('audience'),
            ],
            [
                'methods'             => 'PUT',
                'callback'            => [$this, 'update'],
                'permission_callback' => $this->canManageSection('audience'),
                'args'                => [
                    'name'        => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                    'type'        => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                    'conditions'  => [
                        'validate_callback' => '__return_true',
                    ],
                    'tag_id'      => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                    'description' => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                ],
            ],
            [
                'methods'             => 'DELETE',
                'callback'            => [$this, 'destroy'],
                'permission_callback' => $this->canManageSection('audience'),
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/lists/(?P<id>[A-Za-z0-9]+)/contacts', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'contacts'],
                'permission_callback' => $this->canViewSection('audience'),
                'args'                => [
                    'per_page' => ['type' => 'integer', 'default' => 50],
                    'offset'   => ['type' => 'integer', 'default' => 0],
                ],
            ],
        ]);
    }

    public function index(\WP_REST_Request $request): \WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            $type = $request->get_param('type');
            $lists = $this->lists->findAll($type ?: null);

            return $this->paginated($lists, count($lists));
        });
    }

    public function store(\WP_REST_Request $request): \WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            $data = ['name' => $request->get_param('name')];

            foreach (['type', 'conditions', 'tag_id', 'description'] as $field) {
                $value = $request->get_param($field);
                if ($value !== null) {
                    $data[$field] = $value;
                }
            }

            $id = $this->lists->create($data);
            $list = $this->lists->find($id);

            return $this->created($list);
        });
    }

    public function show(\WP_REST_Request $request): \WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            $list = $this->lists->findOrFail($request->get_param('id'));

            return $this->ok($list);
        });
    }

    public function update(\WP_REST_Request $request): \WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            $id = $request->get_param('id');
            $this->lists->findOrFail($id);

            $data = [];
            foreach (['name', 'type', 'conditions', 'tag_id', 'description'] as $field) {
                $value = $request->get_param($field);
                if ($value !== null) {
                    $data[$field] = $value;
                }
            }

            $this->lists->update($id, $data);

            return $this->ok($this->lists->find($id));
        });
    }

    public function destroy(\WP_REST_Request $request): \WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            $id = $request->get_param('id');
            $this->lists->findOrFail($id);
            $this->lists->delete($id);

            return $this->ok();
        });
    }

    public function contacts(\WP_REST_Request $request): \WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            $id = $request->get_param('id');
            $list = $this->lists->findOrFail($id);

            $perPage = (int) $request->get_param('per_page');
            $offset = (int) $request->get_param('offset');

            if ($list['type'] === 'static' && !empty($list['tag_id'])) {
                $contacts = $this->contacts->findByTag($list['tag_id'], $perPage, $offset);
                $total = $this->contacts->countByTag($list['tag_id']);
            } elseif (!empty($list['conditions'])) {
                $conditions = $list['conditions'];
                $contacts = $this->segmentEvaluator->evaluate($conditions, $perPage, $offset);
                $total = $this->segmentEvaluator->count($conditions);
            } else {
                $contacts = [];
                $total = 0;
            }

            // Update cached count
            $this->lists->updateContactCount($id, $total);

            return $this->paginated($contacts, $total);
        });
    }
}
