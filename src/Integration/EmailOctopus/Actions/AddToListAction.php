<?php

namespace WSms\Integration\EmailOctopus\Actions;

use WSms\Flow\Contracts\AbstractAction;
use WSms\Flow\Contracts\ActionResult;
use WSms\Integration\EmailOctopus\EmailOctopusApiClient;

defined('ABSPATH') || exit;

class AddToListAction extends AbstractAction
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
        return 'emailoctopus_add_to_list';
    }

    public function getName(): string
    {
        return __('Add to EmailOctopus List', 'wp-sms');
    }

    public function getDescription(): string
    {
        return __('Add or update a contact in an EmailOctopus list.', 'wp-sms');
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
                'description' => __('The EmailOctopus list to add the contact to.', 'wp-sms'),
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
            'contact_id' => ['type' => 'string', 'title' => 'EmailOctopus Contact ID'],
            'email'      => ['type' => 'string', 'title' => 'Email'],
        ];
    }

    public function execute(array $payload, array $config): ActionResult
    {
        $listId = $config['list_id'] ?? '';
        $email = $config['email'] ?? '';

        if (empty($listId) || empty($email)) {
            return ActionResult::failure('List ID and email are required');
        }

        $fields = [];
        if (!empty($config['first_name'])) {
            $fields['FirstName'] = $config['first_name'];
        }
        if (!empty($config['last_name'])) {
            $fields['LastName'] = $config['last_name'];
        }

        try {
            $result = $this->client->upsertContact($listId, $email, $fields);

            return ActionResult::success([
                'contact_id' => $result['id'] ?? EmailOctopusApiClient::contactId($email),
                'email'      => $email,
            ]);
        } catch (\RuntimeException $e) {
            return ActionResult::failure($e->getMessage());
        }
    }
}
