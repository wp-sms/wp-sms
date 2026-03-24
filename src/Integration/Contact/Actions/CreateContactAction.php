<?php

namespace WSms\Integration\Contact\Actions;

use WSms\Contact\Contracts\ContactRepositoryInterface;
use WSms\Flow\Contracts\AbstractAction;
use WSms\Flow\Contracts\ActionResult;

defined('ABSPATH') || exit;

class CreateContactAction extends AbstractAction
{
    public function __construct(
        private readonly ContactRepositoryInterface $contactRepository,
    ) {
    }

    public function getId(): string
    {
        return 'create_contact';
    }

    public function getName(): string
    {
        return __('Create Contact', 'wp-sms');
    }

    public function getDescription(): string
    {
        return __('Create a new contact in the WSMS contact list', 'wp-sms');
    }

    public function getGroup(): string
    {
        return 'Contacts';
    }

    public function getOutputSchema(): array
    {
        return [
            'contact_id' => ['type' => 'integer', 'title' => 'Contact ID'],
            'action'     => ['type' => 'string', 'title' => 'Action Taken'],
        ];
    }

    public function getConfigSchema(): array
    {
        return [
            'email' => [
                'type'        => 'string',
                'label'       => __('Email', 'wp-sms'),
                'description' => __('Email address of the new contact', 'wp-sms'),
                'template'    => true,
                'example'     => '{{user.email}}',
            ],
            'phone' => [
                'type'        => 'string',
                'label'       => __('Phone', 'wp-sms'),
                'description' => __('Phone number of the new contact', 'wp-sms'),
                'template'    => true,
                'example'     => '{{user.phone}}',
            ],
            'first_name' => [
                'type'        => 'string',
                'label'       => __('First Name', 'wp-sms'),
                'template'    => true,
                'example'     => '{{user.first_name}}',
            ],
            'last_name' => [
                'type'        => 'string',
                'label'       => __('Last Name', 'wp-sms'),
                'template'    => true,
                'example'     => '{{user.last_name}}',
            ],
            'source' => [
                'type'        => 'string',
                'label'       => __('Source', 'wp-sms'),
                'description' => __('Where this contact came from', 'wp-sms'),
                'default'     => 'flow',
                'example'     => 'flow',
            ],
            'email_verified' => [
                'type'        => 'boolean',
                'label'       => __('Email Verified', 'wp-sms'),
                'description' => __('Mark the contact email as verified', 'wp-sms'),
                'default'     => false,
            ],
            'phone_verified' => [
                'type'        => 'boolean',
                'label'       => __('Phone Verified', 'wp-sms'),
                'description' => __('Mark the contact phone as verified', 'wp-sms'),
                'default'     => false,
            ],
            'on_duplicate' => [
                'type'        => 'string',
                'label'       => __('On Duplicate', 'wp-sms'),
                'description' => __('What to do if a contact with the same email already exists', 'wp-sms'),
                'enum'        => ['fail', 'update', 'skip'],
                'default'     => 'skip',
            ],
        ];
    }

    public function execute(array $payload, array $config): ActionResult
    {
        $email = $config['email'] ?? '';
        $phone = $config['phone'] ?? '';

        if (empty($email) && empty($phone)) {
            return ActionResult::failure('Email or phone is required');
        }

        // Check for duplicate by email
        if (!empty($email)) {
            $existing = $this->contactRepository->findByEmail($email);
            if ($existing) {
                return $this->handleDuplicate($existing, $config);
            }
        }

        $data = array_filter([
            'email'      => $email ?: null,
            'phone'      => $phone ?: null,
            'first_name' => $config['first_name'] ?? null,
            'last_name'  => $config['last_name'] ?? null,
            'source'     => $config['source'] ?? 'flow',
            'source_ref' => $payload['_flow_name'] ?? null,
        ], fn($v) => $v !== null && $v !== '');

        if (!empty($config['email_verified'])) {
            $data['email_verified'] = 1;
        }
        if (!empty($config['phone_verified'])) {
            $data['phone_verified'] = 1;
        }

        // Auto-link WP user if email matches
        if (!empty($email)) {
            $wpUser = get_user_by('email', $email);
            if ($wpUser) {
                $data['wp_user_id'] = $wpUser->ID;
            }
        }

        $contactId = $this->contactRepository->create($data);

        return ActionResult::success([
            'contact_id' => $contactId,
            'action'     => 'created',
        ]);
    }

    private function handleDuplicate(array $existing, array $config): ActionResult
    {
        $onDuplicate = $config['on_duplicate'] ?? 'skip';

        if ($onDuplicate === 'fail') {
            return ActionResult::failure('Contact with this email already exists');
        }

        if ($onDuplicate === 'skip') {
            return ActionResult::success([
                'contact_id' => $existing['id'],
                'action'     => 'skipped',
                'note'       => 'Contact already exists',
            ]);
        }

        // on_duplicate === 'update'
        $updateData = array_filter([
            'phone'      => $config['phone'] ?? null,
            'first_name' => $config['first_name'] ?? null,
            'last_name'  => $config['last_name'] ?? null,
        ], fn($v) => $v !== null && $v !== '');

        if (!empty($config['email_verified'])) {
            $updateData['email_verified'] = 1;
        }
        if (!empty($config['phone_verified'])) {
            $updateData['phone_verified'] = 1;
        }

        if (!empty($updateData)) {
            $this->contactRepository->update($existing['id'], $updateData);
        }

        return ActionResult::success([
            'contact_id' => $existing['id'],
            'action'     => 'updated',
        ]);
    }
}
