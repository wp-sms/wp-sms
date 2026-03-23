<?php

namespace WSms\Integration\EmailOctopus;

use WSms\Contact\Source\AbstractContactSource;

defined('ABSPATH') || exit;

class EmailOctopusContactSource extends AbstractContactSource
{
    private ?EmailOctopusApiClient $client = null;

    public function getType(): string
    {
        return 'emailoctopus';
    }

    public function getName(): string
    {
        return 'EmailOctopus';
    }

    public function getDescription(): string
    {
        return 'Import contacts from an EmailOctopus list into your contact database.';
    }

    public function getIcon(): string
    {
        return 'mail';
    }

    public function isAvailable(): bool
    {
        return $this->makeClient() !== null;
    }

    public function getDefaultFieldMapping(): array
    {
        return EmailOctopusIntegration::FIELD_MAPPING;
    }

    public function getAvailableFields(): array
    {
        return [
            'email_address' => ['label' => 'Email',      'type' => 'core'],
            'FirstName'     => ['label' => 'First Name', 'type' => 'field'],
            'LastName'      => ['label' => 'Last Name',  'type' => 'field'],
        ];
    }

    public function getConfigSchema(): array
    {
        return [
            'list_id' => [
                'type'        => 'select',
                'label'       => 'List',
                'description' => 'Select the EmailOctopus list to import from.',
                'required'    => true,
                'dynamic'     => true,
            ],
            'field_mapping' => [
                'type'  => 'field_mapping',
                'label' => 'Field Mapping',
            ],
            'auto_sync' => [
                'type'        => 'boolean',
                'label'       => 'Auto-sync',
                'default'     => false,
                'description' => 'Automatically sync new contacts from EmailOctopus on a recurring schedule.',
            ],
        ];
    }

    public function syncOne(mixed $externalId, array $config, bool $suppressEvents = false): ?string
    {
        $client = $this->makeClient();
        if (!$client) {
            return null;
        }

        $listId = $config['list_id'] ?? '';
        if (empty($listId)) {
            return null;
        }

        try {
            // externalId is the email (passed from getBatch), EO looks up via md5(email)
            $contact = $client->getContact($listId, (string) $externalId);
            if (!$contact) {
                return null;
            }
        } catch (\RuntimeException) {
            return null;
        }

        $fieldMapping = $config['field_mapping'] ?? $this->getDefaultFieldMapping();
        $sourceData = $this->extractContactData($contact);
        $contactData = $this->applyFieldMapping($sourceData, $fieldMapping);
        $contactData['source'] = $this->getType();

        return $this->upsertContact($contactData, $suppressEvents);
    }

    public function getBatch(array $config, int $batchSize, ?int $afterId): array
    {
        $client = $this->makeClient();
        if (!$client) {
            return [];
        }

        $listId = $config['list_id'] ?? '';
        if (empty($listId)) {
            return [];
        }

        try {
            $params = [
                'limit'  => $batchSize,
                'status' => 'subscribed',
            ];

            if ($afterId !== null) {
                $params['starting_after'] = (string) $afterId;
            }

            $response = $client->getContacts($listId, $params);
            $contacts = $response['data'] ?? [];

            return array_map(fn($c) => $c['email_address'] ?? '', $contacts);
        } catch (\RuntimeException) {
            return [];
        }
    }

    public function countAvailable(array $config): int
    {
        $client = $this->makeClient();
        if (!$client) {
            return 0;
        }

        $listId = $config['list_id'] ?? '';
        if (empty($listId)) {
            return 0;
        }

        try {
            $list = $client->getList($listId);

            return $list['counts']['subscribed'] ?? 0;
        } catch (\RuntimeException) {
            return 0;
        }
    }

    public function handleDeletion(mixed $externalId): void
    {
    }

    private function makeClient(): ?EmailOctopusApiClient
    {
        if ($this->client !== null) {
            return $this->client;
        }

        $configs = get_option('wsms_integration_configs', []);
        $apiKey = $configs['emailoctopus']['credentials']['api_key'] ?? '';

        if ($apiKey) {
            $this->client = new EmailOctopusApiClient($apiKey);
        }

        return $this->client;
    }

    private function extractContactData(array $eoContact): array
    {
        $data = [
            'email_address' => $eoContact['email_address'] ?? '',
        ];

        $fields = $eoContact['fields'] ?? [];
        foreach ($fields as $key => $value) {
            if ($value !== null && $value !== '') {
                $data[$key] = $value;
            }
        }

        return $data;
    }
}
