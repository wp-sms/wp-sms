<?php

namespace WSms\Rest;

use WSms\Auth\ProfileFieldRegistry;
use WSms\Auth\RegistrationForm;
use WSms\Auth\RegistrationFormRepository;
use WSms\Exception\NotFoundException;
use WSms\Exception\ValidationException;

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
        return $this->handle(function () {
            $forms = $this->formRepository->findAll();

            return $this->paginated(
                array_map(fn(RegistrationForm $f) => $f->toArray(), $forms),
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

            $this->formRepository->save($form);

            return $this->ok($form->toArray());
        });
    }

    public function destroy(\WP_REST_Request $request): \WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            $deleted = $this->formRepository->delete($request->get_param('id'));

            if (!$deleted) {
                throw new NotFoundException(__('Registration form not found', 'wp-sms'));
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

            return $this->created($saved->toArray());
        });
    }

    /**
     * @throws ValidationException
     */
    private function validateFormData(array $params, ?string $existingId = null): void
    {
        // Name required on create.
        if ($existingId === null && empty($params['name'])) {
            throw ValidationException::field('name', __('Form name is required.', 'wp-sms'));
        }

        // Fields must not be empty on create.
        if ($existingId === null && (empty($params['fields']) || !is_array($params['fields']))) {
            throw ValidationException::field('fields', __('At least one field is required.', 'wp-sms'));
        }

        // Validate field IDs exist in registry.
        if (!empty($params['fields']) && is_array($params['fields'])) {
            $allFields = $this->fieldRegistry->getAllFields();
            $validIds = array_map(fn($f) => $f->id, $allFields);

            foreach ($params['fields'] as $field) {
                if (!isset($field['id']) || !in_array($field['id'], $validIds, true)) {
                    throw ValidationException::field('fields', sprintf(__('Field "%s" does not exist.', 'wp-sms'), $field['id'] ?? ''));
                }
            }
        }

        // Validate user role.
        if (!empty($params['user_role'])) {
            $roles = wp_roles()->get_names();
            if (!isset($roles[$params['user_role']])) {
                throw ValidationException::field('user_role', __('The specified user role does not exist.', 'wp-sms'));
            }
        }
    }
}
