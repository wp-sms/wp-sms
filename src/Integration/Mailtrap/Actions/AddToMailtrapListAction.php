<?php

namespace WSms\Integration\Mailtrap\Actions;

use WSms\Flow\Contracts\AbstractAction;
use WSms\Flow\Contracts\ActionResult;
use WSms\Integration\Mailtrap\MailtrapApiClient;

defined('ABSPATH') || exit;

class AddToMailtrapListAction extends AbstractAction
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
        return 'mailtrap_add_to_list';
    }

    public function getName(): string
    {
        return __('Add to Mailtrap List', 'wp-sms');
    }

    public function getDescription(): string
    {
        return __('Add or update a contact in a Mailtrap contact list.', 'wp-sms');
    }

    public function getGroup(): string
    {
        return __('Mailtrap', 'wp-sms');
    }

    public function getConfigSchema(): array
    {
        return [
            'list_id' => [
                'type'        => 'string',
                'label'       => __('List', 'wp-sms'),
                'description' => __('The Mailtrap list to add the contact to.', 'wp-sms'),
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
            'first_name' => [
                'type'        => 'string',
                'label'       => __('First Name', 'wp-sms'),
                'template'    => true,
                'example'     => '{{contact.first_name}}',
            ],
            'last_name' => [
                'type'        => 'string',
                'label'       => __('Last Name', 'wp-sms'),
                'template'    => true,
                'example'     => '{{contact.last_name}}',
            ],
        ];
    }

    public function getOutputSchema(): array
    {
        return [
            'contact_id' => ['type' => 'string', 'title' => 'Mailtrap Contact ID'],
            'email'      => ['type' => 'string', 'title' => 'Email'],
        ];
    }

    public function execute(array $payload, array $config): ActionResult
    {
        $listId = (int) ($config['list_id'] ?? 0);
        $email = $config['email'] ?? '';

        if ($listId <= 0 || empty($email)) {
            return ActionResult::failure('List ID and email are required');
        }

        $fields = [];
        if (!empty($config['first_name'])) {
            $fields['first_name'] = $config['first_name'];
        }
        if (!empty($config['last_name'])) {
            $fields['last_name'] = $config['last_name'];
        }

        try {
            $result = $this->client->upsertContact($listId, $email, $fields);

            return ActionResult::success([
                'contact_id' => (string) ($result['id'] ?? ''),
                'email'      => $email,
            ]);
        } catch (\RuntimeException $e) {
            return ActionResult::failure($e->getMessage());
        }
    }
}
