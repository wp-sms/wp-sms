<?php

namespace WSms\Integration\Contact\Actions;

use WSms\Contact\Contracts\ContactRepositoryInterface;
use WSms\Contact\Contracts\ListRepositoryInterface;
use WSms\Flow\Contracts\AbstractAction;
use WSms\Flow\Contracts\ActionResult;

defined('ABSPATH') || exit;

abstract class AbstractListAction extends AbstractAction
{
    use ResolvesContactId;

    public function __construct(
        protected readonly ContactRepositoryInterface $contactRepository,
        protected readonly ListRepositoryInterface $listRepository,
    ) {
    }

    private function getContactRepository(): ContactRepositoryInterface
    {
        return $this->contactRepository;
    }

    public function getGroup(): string
    {
        return __('Contacts', 'wp-sms');
    }

    public function getOutputSchema(): array
    {
        return [
            'contact_id' => ['type' => 'string', 'title' => 'Contact ID'],
            'list_id'    => ['type' => 'string', 'title' => 'List ID'],
            'list_name'  => ['type' => 'string', 'title' => 'List Name'],
        ];
    }

    public function getConfigSchema(): array
    {
        return [
            'contact_id' => [
                'type'        => 'string',
                'label'       => __('Contact ID', 'wp-sms'),
                'description' => $this->getContactIdDescription(),
                'template'    => true,
                'example'     => '{{contact_id}}',
            ],
            'list_id' => [
                'type'        => 'string',
                'label'       => __('List', 'wp-sms'),
                'description' => $this->getListIdDescription(),
                'required'    => true,
                'dynamic'     => true,
            ],
        ];
    }

    public function getConfigOptions(string $fieldKey, array $context = []): array
    {
        if ($fieldKey === 'list_id') {
            return array_map(fn(array $list) => [
                'value' => $list['id'],
                'label' => $list['name'],
            ], $this->listRepository->findAll('static'));
        }

        return [];
    }

    public function execute(array $payload, array $config): ActionResult
    {
        $contactId = $this->resolveContactId($config, $payload);
        if (!$contactId) {
            return ActionResult::failure('Contact not found');
        }

        $listId = $config['list_id'] ?? '';
        if (empty($listId)) {
            return ActionResult::failure('List ID is required');
        }

        $list = $this->listRepository->find($listId);
        if (!$list) {
            return ActionResult::failure('List not found');
        }

        if (empty($list['tag_id'])) {
            return ActionResult::failure('List has no associated tag');
        }

        $contact = $this->contactRepository->find($contactId);
        if (!$contact) {
            return ActionResult::failure('Contact not found');
        }

        $this->applyTagChange($contactId, $list['tag_id']);

        return ActionResult::success([
            'contact_id' => $contactId,
            'list_id'    => $list['id'],
            'list_name'  => $list['name'],
        ]);
    }

    abstract protected function applyTagChange(string $contactId, string $tagId): void;

    abstract protected function getContactIdDescription(): string;

    abstract protected function getListIdDescription(): string;
}
