<?php

namespace WSms\Integration\Mailtrap\Actions;

use WSms\Flow\Contracts\AbstractAction;
use WSms\Flow\Contracts\ActionResult;
use WSms\Integration\Mailtrap\MailtrapApiClient;

defined('ABSPATH') || exit;

class RemoveFromMailtrapListAction extends AbstractAction
{
    use FetchesMailtrapLists;

    public function __construct(
        private readonly MailtrapApiClient $client,
    ) {
    }

    private function getClient(): MailtrapApiClient
    {
        return $this->client;
    }

    public function getId(): string
    {
        return 'mailtrap_remove_from_list';
    }

    public function getName(): string
    {
        return __('Remove from Mailtrap List', 'wp-sms');
    }

    public function getDescription(): string
    {
        return __('Remove a contact from a Mailtrap contact list.', 'wp-sms');
    }

    public function getGroup(): string
    {
        return 'Mailtrap';
    }

    public function getConfigSchema(): array
    {
        return [
            'list_id' => [
                'type'        => 'string',
                'label'       => __('List', 'wp-sms'),
                'description' => __('The Mailtrap list to remove the contact from.', 'wp-sms'),
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
        $listId = (int) ($config['list_id'] ?? 0);
        $email = $config['email'] ?? '';

        if ($listId <= 0 || empty($email)) {
            return ActionResult::failure('List ID and email are required');
        }

        try {
            $existing = $this->client->findContactByEmail($listId, $email);
            if (!$existing) {
                return ActionResult::success(['email' => $email]);
            }

            $this->client->deleteContact((int) $existing['id']);

            return ActionResult::success(['email' => $email]);
        } catch (\RuntimeException $e) {
            if (MailtrapApiClient::isNotFoundError($e)) {
                return ActionResult::success(['email' => $email]);
            }

            return ActionResult::failure($e->getMessage());
        }
    }
}
