<?php

namespace WSms\Rest;

use WSms\Exception\NotFoundException;
use WSms\Exception\ValidationException;
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
        return $this->handle(function () use ($request) {
            $forms = $this->formRepository->findAll();

            return $this->paginated(
                array_map(fn(SubscriptionForm $f) => $f->toArray(), $forms),
                count($forms),
            );
        });
    }

    public function store(\WP_REST_Request $request): \WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            $params = $request->get_params();

            $this->validateFormData($params);

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

            $id = $this->formRepository->save($form);
            $saved = $this->formRepository->find($id);

            return $this->created($saved->toArray());
        });
    }

    public function show(\WP_REST_Request $request): \WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            $form = $this->formRepository->findOrFail($request->get_param('id'));

            return $this->ok($form->toArray());
        });
    }

    public function update(\WP_REST_Request $request): \WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            $id = $request->get_param('id');
            $existing = $this->formRepository->findOrFail($id);

            $params = $request->get_params();

            $this->validateFormData($params, $id);

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

            $this->formRepository->save($form);

            return $this->ok($form->toArray());
        });
    }

    public function destroy(\WP_REST_Request $request): \WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            $deleted = $this->formRepository->delete($request->get_param('id'));

            if (!$deleted) {
                throw new NotFoundException(__('Subscription form not found.', 'wp-sms'));
            }

            return $this->ok();
        });
    }

    public function duplicate(\WP_REST_Request $request): \WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            $original = $this->formRepository->findOrFail($request->get_param('id'));

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

            return $this->created($saved->toArray());
        });
    }

    /**
     * @throws ValidationException
     */
    private function validateFormData(array $params, ?string $existingId = null): void
    {
        if ($existingId === null && empty($params['name'])) {
            throw ValidationException::field('name', __('Form name is required.', 'wp-sms'));
        }

        if ($existingId === null && (empty($params['fields']) || !is_array($params['fields']))) {
            throw ValidationException::field('fields', __('At least one field is required.', 'wp-sms'));
        }

        if (!empty($params['fields']) && is_array($params['fields'])) {
            $validKeys = SubscriptionForm::VALID_FIELD_KEYS;
            foreach ($params['fields'] as $field) {
                if (!isset($field['key']) || !in_array($field['key'], $validKeys, true)) {
                    throw ValidationException::field('fields', sprintf(__('Field "%s" is not valid.', 'wp-sms'), $field['key'] ?? ''));
                }
            }
        }
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
