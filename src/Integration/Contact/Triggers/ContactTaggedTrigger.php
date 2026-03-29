<?php

namespace WSms\Integration\Contact\Triggers;

use WSms\Contact\Contracts\TagRepositoryInterface;
use WSms\Flow\Contracts\AbstractTrigger;

defined('ABSPATH') || exit;

class ContactTaggedTrigger extends AbstractTrigger
{
    public function __construct(
        private readonly TagRepositoryInterface $tagRepository,
    ) {
    }

    public function getId(): string
    {
        return 'wsms.contact_tagged';
    }

    public function getName(): string
    {
        return __('Contact Tagged', 'wp-sms');
    }

    public function getDescription(): string
    {
        return __('Fires when a tag is added to a contact', 'wp-sms');
    }

    public function getGroup(): string
    {
        return __('WSMS', 'wp-sms');
    }

    public function getPayloadSchema(): array
    {
        return [
            'contact_id' => [
                'type'        => 'string',
                'label'       => __('Contact ID', 'wp-sms'),
                'description' => __('The ULID of the tagged contact', 'wp-sms'),
                'example'     => '01HY0000000000000000000000',
            ],
            'tag_id' => [
                'type'        => 'string',
                'label'       => __('Tag ID', 'wp-sms'),
                'description' => __('The ULID of the tag that was added', 'wp-sms'),
                'example'     => '01HY0000000000000000000001',
            ],
            'tag_name' => [
                'type'        => 'string',
                'label'       => __('Tag Name', 'wp-sms'),
                'description' => __('The name of the tag that was added', 'wp-sms'),
                'example'     => 'VIP',
            ],
        ];
    }

    public function getFilterSchema(): array
    {
        return [
            'tag_id' => [
                'type'        => 'string',
                'label'       => __('Tag', 'wp-sms'),
                'description' => __('Only trigger for this specific tag', 'wp-sms'),
                'dynamic'     => true,
            ],
        ];
    }

    public function getFilterOptions(string $fieldKey): array
    {
        if ($fieldKey === 'tag_id') {
            return array_map(fn(array $tag) => [
                'value' => $tag['id'],
                'label' => $tag['name'],
            ], $this->tagRepository->findAll());
        }

        return [];
    }

    public function subscribe(callable $callback): void
    {
        add_action('wsms_contact_tagged', function (string $contactId, string $tagId) use ($callback) {
            $tag = $this->tagRepository->find($tagId);

            $callback([
                'contact_id' => $contactId,
                'tag_id'     => $tagId,
                'tag_name'   => $tag['name'] ?? '',
            ]);
        }, 10, 2);
    }
}
