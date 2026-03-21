<?php

namespace WSms\Integration\Contact\Actions;

use WSms\Contact\Contracts\ContactRepositoryInterface;
use WSms\Flow\Contracts\AbstractAction;
use WSms\Flow\Contracts\ActionResult;

defined('ABSPATH') || exit;

class UpdateContactAction extends AbstractAction
{
    use ResolvesContactId;

    public function __construct(
        private readonly ContactRepositoryInterface $contactRepository,
    ) {
    }

    private function getContactRepository(): ContactRepositoryInterface
    {
        return $this->contactRepository;
    }

    public function getId(): string
    {
        return 'update_contact';
    }

    public function getName(): string
    {
        return __('Update Contact', 'wp-sms');
    }

    public function getDescription(): string
    {
        return __('Update fields on an existing contact', 'wp-sms');
    }

    public function getGroup(): string
    {
        return 'Contacts';
    }

    public function getOutputSchema(): array
    {
        return [
            'contact_id'     => ['type' => 'string', 'title' => 'Contact ID'],
            'updated_fields' => ['type' => 'array', 'title' => 'Updated Fields'],
        ];
    }

    public function getConfigSchema(): array
    {
        return [
            'contact_id' => [
                'type'        => 'string',
                'label'       => __('Contact ID', 'wp-sms'),
                'description' => __('The contact to update. Use {{contact_id}} from trigger data, or leave empty to resolve from user_id.', 'wp-sms'),
                'template'    => true,
                'required'    => true,
                'example'     => '{{contact_id}}',
            ],
            'first_name' => [
                'type'        => 'string',
                'label'       => __('First Name', 'wp-sms'),
                'description' => __('Leave empty to keep current value', 'wp-sms'),
                'template'    => true,
            ],
            'last_name' => [
                'type'        => 'string',
                'label'       => __('Last Name', 'wp-sms'),
                'description' => __('Leave empty to keep current value', 'wp-sms'),
                'template'    => true,
            ],
            'email' => [
                'type'        => 'string',
                'label'       => __('Email', 'wp-sms'),
                'description' => __('Leave empty to keep current value', 'wp-sms'),
                'template'    => true,
            ],
            'phone' => [
                'type'        => 'string',
                'label'       => __('Phone', 'wp-sms'),
                'description' => __('Leave empty to keep current value', 'wp-sms'),
                'template'    => true,
            ],
            'custom_fields' => [
                'type'        => 'object',
                'label'       => __('Custom Fields', 'wp-sms'),
                'description' => __('Key-value pairs to merge into custom fields', 'wp-sms'),
            ],
        ];
    }

    public function execute(array $payload, array $config): ActionResult
    {
        $contactId = $this->resolveContactId($config, $payload);
        if (!$contactId) {
            return ActionResult::failure('Contact not found');
        }

        $contact = $this->contactRepository->find($contactId);
        if (!$contact) {
            return ActionResult::failure('Contact not found');
        }

        // Only update fields that are explicitly set (non-empty)
        $updateData = [];
        foreach (['first_name', 'last_name', 'email', 'phone'] as $field) {
            $value = $config[$field] ?? '';
            if ($value !== '') {
                $updateData[$field] = $value;
            }
        }

        // Merge custom fields instead of overwriting
        if (!empty($config['custom_fields']) && is_array($config['custom_fields'])) {
            $existing = $contact['custom_fields'] ?? [];
            $updateData['custom_fields'] = array_merge($existing, $config['custom_fields']);
        }

        if (empty($updateData)) {
            return ActionResult::success([
                'contact_id' => $contactId,
                'note'       => 'No fields to update',
            ]);
        }

        $this->contactRepository->update($contactId, $updateData);

        return ActionResult::success([
            'contact_id'     => $contactId,
            'updated_fields' => array_keys($updateData),
        ]);
    }

}
