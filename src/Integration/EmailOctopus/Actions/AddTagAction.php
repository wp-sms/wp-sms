<?php

namespace WSms\Integration\EmailOctopus\Actions;

use WSms\Flow\Contracts\AbstractAction;
use WSms\Flow\Contracts\ActionResult;
use WSms\Integration\EmailOctopus\EmailOctopusApiClient;

defined('ABSPATH') || exit;

class AddTagAction extends AbstractAction
{
    use FetchesEmailOctopusLists;

    public function __construct(
        private readonly EmailOctopusApiClient $client,
    ) {
    }

    private function getClient(): EmailOctopusApiClient
    {
        return $this->client;
    }

    public function getId(): string
    {
        return 'emailoctopus_add_tag';
    }

    public function getName(): string
    {
        return __('Add Tag in EmailOctopus', 'wp-sms');
    }

    public function getDescription(): string
    {
        return __('Add a tag to a contact in EmailOctopus.', 'wp-sms');
    }

    public function getGroup(): string
    {
        return __('EmailOctopus', 'wp-sms');
    }

    public function getConfigSchema(): array
    {
        return [
            'list_id' => [
                'type'        => 'string',
                'label'       => __('List', 'wp-sms'),
                'description' => __('The EmailOctopus list the contact belongs to.', 'wp-sms'),
                'required'    => true,
                'dynamic'     => true,
            ],
            'email' => [
                'type'        => 'string',
                'label'       => __('Email', 'wp-sms'),
                'description' => __('Contact email address.', 'wp-sms'),
                'required'    => true,
                'template'    => true,
                'example'     => '{{contact.email}}',
            ],
            'tag_name' => [
                'type'        => 'string',
                'label'       => __('Tag Name', 'wp-sms'),
                'description' => __('The tag to add to the contact.', 'wp-sms'),
                'required'    => true,
                'template'    => true,
            ],
        ];
    }

    public function getOutputSchema(): array
    {
        return [
            'email'    => ['type' => 'string', 'title' => 'Email'],
            'tag_name' => ['type' => 'string', 'title' => 'Tag Name'],
        ];
    }

    public function execute(array $payload, array $config): ActionResult
    {
        $listId = $config['list_id'] ?? '';
        $email = $config['email'] ?? '';
        $tagName = $config['tag_name'] ?? '';

        if (empty($listId) || empty($email) || empty($tagName)) {
            return ActionResult::failure('List ID, email, and tag name are required');
        }

        try {
            $this->client->updateContact($listId, $email, [], [$tagName => true]);

            return ActionResult::success([
                'email'    => $email,
                'tag_name' => $tagName,
            ]);
        } catch (\RuntimeException $e) {
            return ActionResult::failure($e->getMessage());
        }
    }
}
