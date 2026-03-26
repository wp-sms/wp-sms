<?php

namespace WSms\Integration\Mailtrap;

use WSms\Contact\Source\AbstractContactSource;

defined('ABSPATH') || exit;

class MailtrapContactSource extends AbstractContactSource
{
    private ?MailtrapApiClient $client = null;

    public function getType(): string
    {
        return 'mailtrap';
    }

    public function getName(): string
    {
        return 'Mailtrap';
    }

    public function getDescription(): string
    {
        return 'Import contacts from a Mailtrap contact list into your contact database.';
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
        return MailtrapIntegration::FIELD_MAPPING;
    }

    public function getAvailableFields(): array
    {
        return [
            'email'      => ['label' => 'Email',      'type' => 'core'],
            'first_name' => ['label' => 'First Name', 'type' => 'field'],
            'last_name'  => ['label' => 'Last Name',  'type' => 'field'],
        ];
    }

    public function getConfigSchema(): array
    {
        return [
            'list_id' => [
                'type'        => 'select',
                'label'       => 'List',
                'description' => 'Select the Mailtrap contact list to import from.',
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
                'description' => 'Automatically sync new contacts from Mailtrap on a recurring schedule.',
            ],
        ];
    }

    public function syncOne(mixed $externalId, array $config, bool $suppressEvents = false): ?string
    {
        $client = $this->makeClient();
        if (!$client) {
            return null;
        }

        $listId = (int) ($config['list_id'] ?? 0);
        if ($listId <= 0) {
            return null;
        }

        $contact = $client->findContactByEmail($listId, (string) $externalId);
        if (!$contact) {
            return null;
        }

        $fieldMapping = $config['field_mapping'] ?? $this->getDefaultFieldMapping();
        $sourceData = $this->extractContactData($contact);
        $contactData = $this->applyFieldMapping($sourceData, $fieldMapping);
        $contactData['source'] = $this->getType();
        $contactData['source_ref'] = (string) $listId;

        return $this->upsertContact($contactData, $suppressEvents);
    }

    public function getBatch(array $config, int $batchSize, ?int $afterId): array
    {
        $client = $this->makeClient();
        if (!$client) {
            return [];
        }

        $listId = (int) ($config['list_id'] ?? 0);
        if ($listId <= 0) {
            return [];
        }

        try {
            $params = ['limit' => $batchSize];

            if ($afterId !== null) {
                $params['cursor'] = (string) $afterId;
            }

            $response = $client->getContacts($listId, $params);
            $contacts = $response['data'] ?? $response;

            if (!is_array($contacts)) {
                return [];
            }

            return array_map(fn($c) => $c['email'] ?? '', $contacts);
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

        $listId = (int) ($config['list_id'] ?? 0);
        if ($listId <= 0) {
            return 0;
        }

        try {
            $lists = $client->getLists();

            foreach ($lists as $list) {
                if ((int) ($list['id'] ?? 0) === $listId) {
                    return $list['contacts_count'] ?? 0;
                }
            }

            return 0;
        } catch (\RuntimeException) {
            return 0;
        }
    }

    public function handleDeletion(mixed $externalId): void
    {
    }

    private function makeClient(): ?MailtrapApiClient
    {
        if ($this->client !== null) {
            return $this->client;
        }

        $this->client = MailtrapApiClient::fromGatewayConfig();

        return $this->client;
    }

    private function extractContactData(array $mtContact): array
    {
        $data = [
            'email' => $mtContact['email'] ?? '',
        ];

        $fields = $mtContact['fields'] ?? [];
        foreach ($fields as $key => $value) {
            if ($value !== null && $value !== '') {
                $data[$key] = $value;
            }
        }

        // Also map top-level fields
        if (!empty($mtContact['first_name'])) {
            $data['first_name'] = $mtContact['first_name'];
        }
        if (!empty($mtContact['last_name'])) {
            $data['last_name'] = $mtContact['last_name'];
        }

        return $data;
    }
}
