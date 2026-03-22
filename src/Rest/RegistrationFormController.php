<?php

namespace WSms\Rest;

use WSms\Auth\ProfileFieldRegistry;
use WSms\Auth\RegistrationForm;
use WSms\Auth\RegistrationFormRepository;

defined('ABSPATH') || exit;

class RegistrationFormController extends Controller
{
    public function __construct(
        private RegistrationFormRepository $formRepository,
        private ProfileFieldRegistry $fieldRegistry,
    ) {
    }

    public function registerRoutes(): void
    {
        $formArgs = [
            'name'           => ['required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            'slug'           => ['required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_title'],
            'description'    => ['required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_textarea_field'],
            'fields'         => ['required' => false, 'type' => 'array'],
            'auth_overrides' => ['required' => false, 'type' => 'object'],
            'user_role'      => ['required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            'redirect_url'   => ['required' => false, 'type' => 'string'],
            'branding'       => ['required' => false, 'type' => 'object'],
            'status'         => ['required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
        ];

        register_rest_route(self::NAMESPACE, '/auth/admin/registration-forms', [
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

        register_rest_route(self::NAMESPACE, '/auth/admin/registration-forms/(?P<id>[A-Za-z0-9]+)', [
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

        register_rest_route(self::NAMESPACE, '/auth/admin/registration-forms/(?P<id>[A-Za-z0-9]+)/duplicate', [
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
            'items' => array_map(fn(RegistrationForm $f) => $f->toArray(), $forms),
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

        $form = new RegistrationForm(
            id: '',
            name: $params['name'],
            slug: $slug,
            fields: $params['fields'] ?? [],
            authOverrides: $params['auth_overrides'] ?? [],
            userRole: $params['user_role'] ?? '',
            redirectUrl: !empty($params['redirect_url']) ? esc_url_raw($params['redirect_url']) : '',
            branding: $params['branding'] ?? [],
            status: $params['status'] ?? 'active',
            description: $params['description'] ?? null,
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
                'message' => __('Registration form not found', 'wp-sms'),
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
                'message' => __('Registration form not found', 'wp-sms'),
            ], 404);
        }

        $params = $request->get_params();

        $error = $this->validateFormData($params, $id);
        if ($error) {
            return $error;
        }

        $form = new RegistrationForm(
            id: $id,
            name: $params['name'] ?? $existing->getName(),
            slug: isset($params['slug']) ? sanitize_title($params['slug']) : $existing->getSlug(),
            fields: $params['fields'] ?? $existing->getFields(),
            authOverrides: $params['auth_overrides'] ?? $existing->getAuthOverrides(),
            userRole: $params['user_role'] ?? $existing->getUserRole(),
            redirectUrl: isset($params['redirect_url']) ? esc_url_raw($params['redirect_url']) : $existing->getRedirectUrl(),
            branding: $params['branding'] ?? $existing->getBrandingOverrides(),
            status: $params['status'] ?? $existing->getStatus(),
            description: array_key_exists('description', $params) ? $params['description'] : $existing->getDescription(),
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
                'message' => __('Registration form not found', 'wp-sms'),
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
                'message' => __('Registration form not found', 'wp-sms'),
            ], 404);
        }

        $baseName = $original->getName() . ' (Copy)';
        $baseSlug = $original->getSlug() . '-copy';

        // Ensure unique slug.
        $slug = $baseSlug;
        $counter = 1;
        while ($this->formRepository->findBySlug($slug)) {
            $counter++;
            $slug = $baseSlug . '-' . $counter;
        }

        $copy = new RegistrationForm(
            id: '',
            name: $baseName,
            slug: $slug,
            fields: $original->getFields(),
            authOverrides: $original->getAuthOverrides(),
            userRole: $original->getUserRole(),
            redirectUrl: $original->getRedirectUrl(),
            branding: $original->getBrandingOverrides(),
            status: 'draft',
            description: $original->getDescription(),
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
        // Name required on create.
        if ($existingId === null && empty($params['name'])) {
            return new \WP_REST_Response([
                'success' => false,
                'error'   => 'missing_name',
                'message' => __('Form name is required.', 'wp-sms'),
            ], 400);
        }

        // Fields must not be empty on create.
        if ($existingId === null && (empty($params['fields']) || !is_array($params['fields']))) {
            return new \WP_REST_Response([
                'success' => false,
                'error'   => 'missing_fields',
                'message' => __('At least one field is required.', 'wp-sms'),
            ], 400);
        }

        // Validate field IDs exist in registry.
        if (!empty($params['fields']) && is_array($params['fields'])) {
            $allFields = $this->fieldRegistry->getAllFields();
            $validIds = array_map(fn($f) => $f->id, $allFields);

            foreach ($params['fields'] as $field) {
                if (!isset($field['id']) || !in_array($field['id'], $validIds, true)) {
                    return new \WP_REST_Response([
                        'success' => false,
                        'error'   => 'invalid_field',
                        'message' => sprintf(__('Field "%s" does not exist.', 'wp-sms'), $field['id'] ?? ''),
                    ], 400);
                }
            }
        }

        // Validate user role.
        if (!empty($params['user_role'])) {
            $roles = wp_roles()->get_names();
            if (!isset($roles[$params['user_role']])) {
                return new \WP_REST_Response([
                    'success' => false,
                    'error'   => 'invalid_role',
                    'message' => __('The specified user role does not exist.', 'wp-sms'),
                ], 400);
            }
        }

        return null;
    }
}
