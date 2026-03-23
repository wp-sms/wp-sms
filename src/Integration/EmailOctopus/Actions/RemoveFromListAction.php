<?php

namespace WSms\Integration\EmailOctopus\Actions;

use WSms\Flow\Contracts\AbstractAction;
use WSms\Flow\Contracts\ActionResult;
use WSms\Integration\EmailOctopus\EmailOctopusApiClient;

defined('ABSPATH') || exit;

class RemoveFromListAction extends AbstractAction
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
        return 'emailoctopus_remove_from_list';
    }

    public function getName(): string
    {
        return __('Remove from EmailOctopus List', 'wp-sms');
    }

    public function getDescription(): string
    {
        return __('Remove a contact from an EmailOctopus list.', 'wp-sms');
    }

    public function getGroup(): string
    {
        return 'EmailOctopus';
    }

    public function getConfigSchema(): array
    {
        return [
            'list_id' => [
                'type'        => 'string',
                'label'       => __('List', 'wp-sms'),
                'description' => __('The EmailOctopus list to remove the contact from.', 'wp-sms'),
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
        ];
    }

    public function getOutputSchema(): array
    {
        return [
            'email' => ['type' => 'string', 'title' => 'Email'],
        ];
    }

    public function execute(array $payload, array $config): ActionResult
    {
        $listId = $config['list_id'] ?? '';
        $email = $config['email'] ?? '';

        if (empty($listId) || empty($email)) {
            return ActionResult::failure('List ID and email are required');
        }

        try {
            $this->client->deleteContact($listId, $email);

            return ActionResult::success(['email' => $email]);
        } catch (\RuntimeException $e) {
            if (EmailOctopusApiClient::isNotFoundError($e)) {
                return ActionResult::success(['email' => $email]);
            }

            return ActionResult::failure($e->getMessage());
        }
    }
}
