<?php

namespace WSms\Integration\Contact\Actions;

use WSms\Contact\Contracts\ContactRepositoryInterface;
use WSms\Contact\Contracts\TagRepositoryInterface;
use WSms\Flow\Contracts\AbstractAction;
use WSms\Flow\Contracts\ActionResult;

defined('ABSPATH') || exit;

class AddTagAction extends AbstractAction
{
    use ResolvesContactId;

    public function __construct(
        private readonly ContactRepositoryInterface $contactRepository,
        private readonly TagRepositoryInterface $tagRepository,
    ) {
    }

    private function getContactRepository(): ContactRepositoryInterface
    {
        return $this->contactRepository;
    }

    public function getId(): string
    {
        return 'add_tag';
    }

    public function getName(): string
    {
        return __('Add Tag to Contact', 'wp-sms');
    }

    public function getDescription(): string
    {
        return __('Add a tag to a contact', 'wp-sms');
    }

    public function getGroup(): string
    {
        return __('Contacts', 'wp-sms');
    }

    public function getOutputSchema(): array
    {
        return [
            'contact_id' => ['type' => 'string', 'title' => 'Contact ID'],
            'tag_id'     => ['type' => 'string', 'title' => 'Tag ID'],
            'tag_name'   => ['type' => 'string', 'title' => 'Tag Name'],
        ];
    }

    public function getConfigSchema(): array
    {
        return [
            'contact_id' => [
                'type'        => 'string',
                'label'       => __('Contact ID', 'wp-sms'),
                'description' => __('The contact to tag. Use {{contact_id}} from trigger data, or leave empty to resolve from user_id.', 'wp-sms'),
                'template'    => true,
                'example'     => '{{contact_id}}',
            ],
            'tag_id' => [
                'type'        => 'string',
                'label'       => __('Tag', 'wp-sms'),
                'description' => __('The tag to add', 'wp-sms'),
                'required'    => true,
                'dynamic'     => true,
            ],
        ];
    }

    public function getConfigOptions(string $fieldKey, array $context = []): array
    {
        if ($fieldKey === 'tag_id') {
            return array_map(fn(array $tag) => [
                'value' => $tag['id'],
                'label' => $tag['name'],
            ], $this->tagRepository->findAll());
        }

        return [];
    }

    public function execute(array $payload, array $config): ActionResult
    {
        $contactId = $this->resolveContactId($config, $payload);
        if (!$contactId) {
            return ActionResult::failure('Contact not found');
        }

        $tagId = $config['tag_id'] ?? '';
        if (empty($tagId)) {
            return ActionResult::failure('Tag ID is required');
        }

        $tag = $this->tagRepository->find($tagId);
        if (!$tag) {
            return ActionResult::failure('Tag not found');
        }

        $contact = $this->contactRepository->find($contactId);
        if (!$contact) {
            return ActionResult::failure('Contact not found');
        }

        $this->contactRepository->addTag($contactId, $tagId);

        return ActionResult::success([
            'contact_id' => $contactId,
            'tag_id'     => $tagId,
            'tag_name'   => $tag['name'],
        ]);
    }

}
