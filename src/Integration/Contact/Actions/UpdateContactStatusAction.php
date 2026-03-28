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
        return __('Change a contact\'s subscription status or channel opt-out', 'wp-sms');
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
            'channel' => [
                'type'        => 'string',
                'label'       => __('Channel', 'wp-sms'),
                'description' => __('Optional: set per-channel opt-out/opt-in instead of global status (e.g. "sms", "email")', 'wp-sms'),
                'required'    => false,
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

        $channel = $config['channel'] ?? '';
        $status = $config['status'] ?? '';

        // Per-channel opt-out/opt-in via channel config
        if (!empty($channel)) {
            if ($status === ContactStatus::Subscribed->value) {
                $this->contactRepository->clearChannelOptOut($contactId, $channel);
            } else {
                $this->contactRepository->setChannelOptOut($contactId, $channel);
            }

            return ActionResult::success([
                'contact_id' => $contactId,
                'channel'    => $channel,
                'action'     => $status === ContactStatus::Subscribed->value ? 'opted_in' : 'opted_out',
            ]);
        }

        // Global status change
        if (empty($status) || !ContactStatus::tryFrom($status)) {
            return ActionResult::failure('Valid status is required');
        }

        // Idempotent: if already at target status, return success
        if ($contact['status'] === $status) {
            return ActionResult::success([
                'contact_id' => $contactId,
                'status'     => $status,
                'note'       => 'Status unchanged',
            ]);
        }

        $this->contactRepository->update($contactId, ['status' => $status]);

        return ActionResult::success([
            'contact_id'  => $contactId,
            'old_status'  => $contact['status'],
            'new_status'  => $status,
        ]);
    }

}
