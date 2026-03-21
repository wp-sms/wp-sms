<?php

namespace WSms\Integration\Contact\Actions;

use WSms\Contact\Contracts\ContactRepositoryInterface;
use WSms\Enums\ContactStatus;
use WSms\Flow\Contracts\AbstractAction;
use WSms\Flow\Contracts\ActionResult;

defined('ABSPATH') || exit;

class UpdateContactStatusAction extends AbstractAction
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
        return 'update_contact_status';
    }

    public function getName(): string
    {
        return __('Update Contact Status', 'wp-sms');
    }

    public function getDescription(): string
    {
        return __('Change a contact\'s subscription status', 'wp-sms');
    }

    public function getGroup(): string
    {
        return 'Contacts';
    }

    public function getOutputSchema(): array
    {
        return [
            'contact_id'  => ['type' => 'string', 'title' => 'Contact ID'],
            'old_status'  => ['type' => 'string', 'title' => 'Old Status'],
            'new_status'  => ['type' => 'string', 'title' => 'New Status'],
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
                'example'     => '{{contact_id}}',
            ],
            'status' => [
                'type'        => 'string',
                'label'       => __('Status', 'wp-sms'),
                'description' => __('The new subscription status', 'wp-sms'),
                'required'    => true,
                'enum'        => array_map(fn(ContactStatus $s) => $s->value, ContactStatus::cases()),
            ],
        ];
    }

    public function execute(array $payload, array $config): ActionResult
    {
        $contactId = $this->resolveContactId($config, $payload);
        if (!$contactId) {
            return ActionResult::failure('Contact not found');
        }

        $status = $config['status'] ?? '';
        if (empty($status) || !ContactStatus::tryFrom($status)) {
            return ActionResult::failure('Valid status is required');
        }

        $contact = $this->contactRepository->find($contactId);
        if (!$contact) {
            return ActionResult::failure('Contact not found');
        }

        // Idempotent: if already at target status, return success
        if ($contact['status'] === $status) {
            return ActionResult::success([
                'contact_id' => $contactId,
                'status'     => $status,
                'note'       => 'Status unchanged',
            ]);
        }

        $updateData = ['status' => $status];

        // Auto-manage opted_out_at timestamp
        if ($status === ContactStatus::Unsubscribed->value) {
            $updateData['opted_out_at'] = current_time('mysql');
        } elseif ($status === ContactStatus::Subscribed->value) {
            $updateData['opted_out_at'] = null;
        }

        $this->contactRepository->update($contactId, $updateData);

        return ActionResult::success([
            'contact_id'  => $contactId,
            'old_status'  => $contact['status'],
            'new_status'  => $status,
        ]);
    }

}
