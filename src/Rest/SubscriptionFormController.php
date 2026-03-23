<?php

namespace WSms\Rest;

use WSms\SubscriptionForm\SubscriptionForm;
use WSms\SubscriptionForm\SubscriptionFormRepository;

defined('ABSPATH') || exit;

class SubscriptionFormController extends Controller
{
    public function __construct(
        private readonly SubscriptionFormRepository $formRepository,
    ) {
    }

    public function registerRoutes(): void
    {
        $formArgs = [
            'name'            => ['required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            'slug'            => ['required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_title'],
            'status'          => ['required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            'fields'          => ['required' => false, 'type' => 'array'],
            'list_id'         => ['required' => false, 'type' => ['string', 'null'], 'sanitize_callback' => 'sanitize_text_field'],
            'tag_id'          => ['required' => false, 'type' => ['string', 'null'], 'sanitize_callback' => 'sanitize_text_field'],
            'double_optin'    => ['required' => false, 'type' => 'boolean'],
            'optin_channel'   => ['required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            'appearance'      => ['required' => false, 'type' => 'object'],
            'success_message' => ['required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            'redirect_url'    => ['required' => false, 'type' => ['string', 'null']],
        ];

        register_rest_route(self::NAMESPACE, '/subscription-forms', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'index'],
                'permission_callback' => [$this, 'canManage'],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'store'],
                'permission_callback' => [$this, 'canManage'],
                'args'                => array_merge($formArgs, [
                    'name' => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                ]),
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/subscription-forms/(?P<id>[A-Za-z0-9]+)', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'show'],
                'permission_callback' => [$this, 'canManage'],
            ],
            [
                'methods'             => 'PUT',
                'callback'            => [$this, 'update'],
                'permission_callback' => [$this, 'canManage'],
                'args'                => $formArgs,
            ],
            [
                'methods'             => 'DELETE',
                'callback'            => [$this, 'destroy'],
                'permission_callback' => [$this, 'canManage'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/subscription-forms/(?P<id>[A-Za-z0-9]+)/duplicate', [
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'duplicate'],
                'permission_callback' => [$this, 'canManage'],
            ],
        ]);
    }

    public function index(\WP_REST_Request $request): \WP_REST_Response
    {
        $forms = $this->formRepository->findAll();

        return new \WP_REST_Response([
            'items' => array_map(fn(SubscriptionForm $f) => $f->toArray(), $forms),
            'total' => count($forms),
        ]);
    }

    public function store(\WP_REST_Request $request): \WP_REST_Response
    {
        $params = $request->get_params();

        $error = $this->validateFormData($params);
        if ($error) {
            return $error;
        }

        $slug = !empty($params['slug'])
            ? sanitize_title($params['slug'])
            : sanitize_title($params['name']);

        $form = new SubscriptionForm(
            id: '',
            name: $params['name'],
            slug: $slug,
            status: $params['status'] ?? 'active',
            fields: $this->sanitizeFields($params['fields'] ?? []),
            listId: $params['list_id'] ?? null,
            tagId: $params['tag_id'] ?? null,
            doubleOptin: !empty($params['double_optin']),
            optinChannel: $params['optin_channel'] ?? 'email',
            appearance: $params['appearance'] ?? [],
            successMessage: $params['success_message'] ?? '',
            redirectUrl: !empty($params['redirect_url']) ? esc_url_raw($params['redirect_url']) : null,
            createdBy: get_current_user_id(),
        );

        try {
            $id = $this->formRepository->save($form);
        } catch (\InvalidArgumentException $e) {
            return new \WP_REST_Response([
                'success' => false,
                'error'   => 'validation_error',
                'message' => $e->getMessage(),
            ], 400);
        }

        $saved = $this->formRepository->find($id);

        return new \WP_REST_Response([
            'success' => true,
            'data'    => $saved->toArray(),
        ], 201);
    }

    public function show(\WP_REST_Request $request): \WP_REST_Response
    {
        $form = $this->formRepository->find($request->get_param('id'));

        if (!$form) {
            return new \WP_REST_Response([
                'success' => false,
                'error'   => 'not_found',
                'message' => __('Subscription form not found.', 'wp-sms'),
            ], 404);
        }

        return new \WP_REST_Response([
            'success' => true,
            'data'    => $form->toArray(),
        ]);
    }

    public function update(\WP_REST_Request $request): \WP_REST_Response
    {
        $id = $request->get_param('id');
        $existing = $this->formRepository->find($id);

        if (!$existing) {
            return new \WP_REST_Response([
                'success' => false,
                'error'   => 'not_found',
                'message' => __('Subscription form not found.', 'wp-sms'),
            ], 404);
        }

        $params = $request->get_params();

        $error = $this->validateFormData($params, $id);
        if ($error) {
            return $error;
        }

        $form = new SubscriptionForm(
            id: $id,
            name: $params['name'] ?? $existing->getName(),
            slug: isset($params['slug']) ? sanitize_title($params['slug']) : $existing->getSlug(),
            status: $params['status'] ?? $existing->getStatus(),
            fields: isset($params['fields']) ? $this->sanitizeFields($params['fields']) : $existing->getFields(),
            listId: array_key_exists('list_id', $params) ? $params['list_id'] : $existing->getListId(),
            tagId: array_key_exists('tag_id', $params) ? $params['tag_id'] : $existing->getTagId(),
            doubleOptin: array_key_exists('double_optin', $params) ? !empty($params['double_optin']) : $existing->requiresDoubleOptIn(),
            optinChannel: $params['optin_channel'] ?? $existing->getOptInChannel(),
            appearance: $params['appearance'] ?? $existing->getAppearance(),
            successMessage: $params['success_message'] ?? $existing->getSuccessMessage(),
            redirectUrl: isset($params['redirect_url']) ? ($params['redirect_url'] ? esc_url_raw($params['redirect_url']) : null) : $existing->getRedirectUrl(),
            createdBy: $existing->getCreatedBy(),
            createdAt: $existing->getCreatedAt(),
            updatedAt: gmdate('c'),
        );

        try {
            $this->formRepository->save($form);
        } catch (\InvalidArgumentException $e) {
            return new \WP_REST_Response([
                'success' => false,
                'error'   => 'validation_error',
                'message' => $e->getMessage(),
            ], 400);
        }

        return new \WP_REST_Response([
            'success' => true,
            'data'    => $form->toArray(),
        ]);
    }

    public function destroy(\WP_REST_Request $request): \WP_REST_Response
    {
        $deleted = $this->formRepository->delete($request->get_param('id'));

        if (!$deleted) {
            return new \WP_REST_Response([
                'success' => false,
                'error'   => 'not_found',
                'message' => __('Subscription form not found.', 'wp-sms'),
            ], 404);
        }

        return new \WP_REST_Response(['success' => true]);
    }

    public function duplicate(\WP_REST_Request $request): \WP_REST_Response
    {
        $original = $this->formRepository->find($request->get_param('id'));

        if (!$original) {
            return new \WP_REST_Response([
                'success' => false,
                'error'   => 'not_found',
                'message' => __('Subscription form not found.', 'wp-sms'),
            ], 404);
        }

        $baseName = $original->getName() . ' (Copy)';
        $baseSlug = $original->getSlug() . '-copy';

        $slug = $baseSlug;
        $counter = 1;
        while ($this->formRepository->findBySlug($slug)) {
            $counter++;
            $slug = $baseSlug . '-' . $counter;
        }

        $copy = new SubscriptionForm(
            id: '',
            name: $baseName,
            slug: $slug,
            status: 'draft',
            fields: $original->getFields(),
            listId: $original->getListId(),
            tagId: $original->getTagId(),
            doubleOptin: $original->requiresDoubleOptIn(),
            optinChannel: $original->getOptInChannel(),
            appearance: $original->getAppearance(),
            successMessage: $original->getSuccessMessage(),
            redirectUrl: $original->getRedirectUrl(),
            createdBy: get_current_user_id(),
        );

        $id = $this->formRepository->save($copy);
        $saved = $this->formRepository->find($id);

        return new \WP_REST_Response([
            'success' => true,
            'data'    => $saved->toArray(),
        ], 201);
    }

    private function validateFormData(array $params, ?string $existingId = null): ?\WP_REST_Response
    {
        if ($existingId === null && empty($params['name'])) {
            return new \WP_REST_Response([
                'success' => false,
                'error'   => 'missing_name',
                'message' => __('Form name is required.', 'wp-sms'),
            ], 400);
        }

        if ($existingId === null && (empty($params['fields']) || !is_array($params['fields']))) {
            return new \WP_REST_Response([
                'success' => false,
                'error'   => 'missing_fields',
                'message' => __('At least one field is required.', 'wp-sms'),
            ], 400);
        }

        if (!empty($params['fields']) && is_array($params['fields'])) {
            $validKeys = SubscriptionForm::VALID_FIELD_KEYS;
            foreach ($params['fields'] as $field) {
                if (!isset($field['key']) || !in_array($field['key'], $validKeys, true)) {
                    return new \WP_REST_Response([
                        'success' => false,
                        'error'   => 'invalid_field',
                        'message' => sprintf(__('Field "%s" is not valid.', 'wp-sms'), $field['key'] ?? ''),
                    ], 400);
                }
            }
        }

        return null;
    }

    private function sanitizeFields(array $fields): array
    {
        return array_map(function (array $field) {
            return [
                'key' => sanitize_text_field($field['key'] ?? ''),
                'required' => !empty($field['required']),
                'label' => sanitize_text_field($field['label'] ?? ''),
            ];
        }, $fields);
    }
}
