<?php

namespace WSms\Integration\Auth\Triggers;

use WSms\Auth\RegistrationForm;
use WSms\Auth\RegistrationFormRepository;
use WSms\Flow\Contracts\AbstractTrigger;
use WSms\Integration\PayloadSchemas;

defined('ABSPATH') || exit;

class FormRegistrationTrigger extends AbstractTrigger
{
    public function __construct(
        private RegistrationFormRepository $formRepo,
    ) {
    }

    public function getId(): string
    {
        return 'auth.form_registration';
    }

    public function getName(): string
    {
        return __('User Registered via Form', 'wp-sms');
    }

    public function getDescription(): string
    {
        return __('Fires when a user registers through a specific registration form', 'wp-sms');
    }

    public function getGroup(): string
    {
        return 'Authentication';
    }

    public function getPayloadSchema(): array
    {
        return [
            'user_id' => [
                'type'        => 'integer',
                'label'       => __('User ID', 'wp-sms'),
                'description' => __('The WordPress user ID of the new user', 'wp-sms'),
                'example'     => 42,
            ],
            'form_id' => [
                'type'        => 'string',
                'label'       => __('Form ID', 'wp-sms'),
                'description' => __('The ULID of the registration form used', 'wp-sms'),
                'example'     => '01HXYZ1234567890ABCDEF',
            ],
            'form_name' => [
                'type'        => 'string',
                'label'       => __('Form Name', 'wp-sms'),
                'description' => __('The name of the registration form', 'wp-sms'),
                'example'     => 'Vendor Registration',
            ],
            'form_slug' => [
                'type'        => 'string',
                'label'       => __('Form Slug', 'wp-sms'),
                'description' => __('The slug of the registration form', 'wp-sms'),
                'example'     => 'vendor-registration',
            ],
            'role' => [
                'type'        => 'string',
                'label'       => __('Role', 'wp-sms'),
                'description' => __('The role assigned to the user', 'wp-sms'),
                'example'     => 'vendor',
            ],
            'user' => [
                'type'        => 'object',
                'label'       => __('User Data', 'wp-sms'),
                'description' => __('User profile data', 'wp-sms'),
                'properties'  => PayloadSchemas::wpUser(),
                'example'     => [
                    'email'        => 'vendor@example.com',
                    'phone'        => '+1234567890',
                    'login'        => 'vendor_user',
                    'display_name' => 'Vendor User',
                    'first_name'   => 'Vendor',
                    'last_name'    => 'User',
                    'roles'        => ['vendor'],
                ],
            ],
        ];
    }

    public function getFilterSchema(): array
    {
        return [
            'form_id' => [
                'type'        => 'string',
                'label'       => __('Registration Form', 'wp-sms'),
                'description' => __('Only trigger for this specific form', 'wp-sms'),
                'dynamic'     => true,
            ],
        ];
    }

    public function getFilterOptions(string $fieldKey): array
    {
        if ($fieldKey === 'form_id') {
            return array_map(fn(RegistrationForm $f) => [
                'value' => $f->getId(),
                'label' => $f->getName(),
            ], $this->formRepo->findAll());
        }

        return [];
    }

    public function subscribe(callable $callback): void
    {
        add_action('wsms_form_registration', function (int $userId, RegistrationForm $form) use ($callback) {
            $user = get_userdata($userId);
            if ($user) {
                $callback([
                    'user_id'   => $userId,
                    'form_id'   => $form->getId(),
                    'form_name' => $form->getName(),
                    'form_slug' => $form->getSlug(),
                    'role'      => $user->roles[0] ?? '',
                    'user'      => PayloadSchemas::extractWpUser($user),
                ]);
            }
        }, 10, 2);
    }
}
