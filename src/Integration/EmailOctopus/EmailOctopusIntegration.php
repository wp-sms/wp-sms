<?php

namespace WSms\Integration\EmailOctopus;

use WSms\Contact\Contracts\ContactRepositoryInterface;
use WSms\Integration\Contracts\ContactImportHelper;
use WSms\Integration\Contracts\IntegrationCapability;
use WSms\Integration\Contracts\IntegrationInterface;
use WSms\Integration\Marketing\ImportSyncManager;
use WSms\Integration\Contracts\SupportsContactImport;
use WSms\Integration\Contracts\SupportsContactSync;
use WSms\Integration\Contracts\SupportsListManagement;
use WSms\Integration\Contracts\SupportsAutomations;
use WSms\Integration\Contracts\SupportsSuppressionSync;
use WSms\Integration\Contracts\SyncResult;
use WSms\Integration\EmailOctopus\Actions\AddToListAction;
use WSms\Integration\EmailOctopus\Actions\RemoveFromListAction;
use WSms\Integration\EmailOctopus\Actions\QueueAutomationAction;
use WSms\Integration\EmailOctopus\Actions\AddTagAction;
use WSms\Integration\EmailOctopus\Actions\RemoveTagAction;

defined('ABSPATH') || exit;

class EmailOctopusIntegration implements
    IntegrationInterface,
    SupportsContactSync,
    SupportsContactImport,
    SupportsListManagement,
    SupportsAutomations,
    SupportsSuppressionSync
{
    private const CONFIG_OPTION = 'wsms_integration_configs';

    public const FIELD_MAPPING = [
        'email'      => 'email_address',
        'first_name' => 'FirstName',
        'last_name'  => 'LastName',
    ];

    private ?EmailOctopusApiClient $client = null;

    public function __construct(
        private readonly ?ContactRepositoryInterface $contacts = null,
    ) {
    }

    public function getId(): string
    {
        return 'emailoctopus';
    }

    public function getName(): string
    {
        return 'EmailOctopus';
    }

    public function getDescription(): string
    {
        return __('Sync contacts, manage lists, and queue automations with EmailOctopus.', 'wp-sms');
    }

    public function getCategory(): string
    {
        return 'email_marketing';
    }

    public function getIcon(): string
    {
        return 'mail';
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function getAuthType(): string
    {
        return 'api_key';
    }

    public function getAuthSchema(): array
    {
        return [
            'api_key' => [
                'type'        => 'string',
                'title'       => __('API Key', 'wp-sms'),
                'description' => __('Find your API key in EmailOctopus under Account Settings > API Keys.', 'wp-sms'),
                'required'    => true,
            ],
        ];
    }

    public function getMetadata(): array
    {
        return [
            'rate_limit' => ['tokens_per_second' => 10, 'burst' => 100],
            'website'    => 'https://emailoctopus.com',
        ];
    }

    public function connect(array $credentials): array
    {
        $apiKey = $credentials['api_key'] ?? '';
        if (empty($apiKey)) {
            throw new \RuntimeException(__('API key is required.', 'wp-sms'));
        }

        $client = new EmailOctopusApiClient($apiKey);

        try {
            $client->validateKey();
        } catch (\RuntimeException $e) {
            throw new \RuntimeException(__('Invalid API key: ', 'wp-sms') . $e->getMessage());
        }

        return ['api_key' => $apiKey];
    }

    public function disconnect(): void
    {
        $state = get_option(ImportSyncManager::STATE_KEY, []);
        unset($state[$this->getId()]);
        update_option(ImportSyncManager::STATE_KEY, $state, false);

        as_unschedule_all_actions('wsms_suppression_poll', ['integration_id' => $this->getId()], 'wsms');
    }

    public function isConnected(): bool
    {
        $configs = get_option(self::CONFIG_OPTION, []);

        return !empty($configs['emailoctopus']['credentials']['api_key']);
    }

    public function getTriggers(): array
    {
        return [];
    }

    public function getActions(): array
    {
        $client = $this->makeClient() ?? new EmailOctopusApiClient('');

        return [
            new AddToListAction($client),
            new RemoveFromListAction($client),
            new QueueAutomationAction($client),
            new AddTagAction($client),
            new RemoveTagAction($client),
        ];
    }

    public function getCapabilities(): array
    {
        return [
            ['id' => IntegrationCapability::CONTACT_SYNC,       'supported' => true],
            ['id' => IntegrationCapability::LIST_MANAGEMENT,     'supported' => true],
            ['id' => IntegrationCapability::TAGS,                'supported' => true],
            ['id' => IntegrationCapability::AUTOMATIONS,         'supported' => true, 'note' => 'Manual ID'],
            ['id' => IntegrationCapability::SUPPRESSION_SYNC,    'supported' => true],
            ['id' => IntegrationCapability::EMAIL_GATEWAY,       'supported' => false, 'note' => 'No OTP, auth, or transactional emails'],
            ['id' => IntegrationCapability::ENGAGEMENT_DATA,     'supported' => false, 'note' => 'No open/click tracking'],
            ['id' => IntegrationCapability::CONTACT_IMPORT,      'supported' => true],
        ];
    }

    public function boot(): void
    {
    }

    // --- SupportsContactSync ---

    public function pushContact(array $contact, array $config): SyncResult
    {
        $client = $this->makeClient();
        if (!$client) {
            return SyncResult::failure('Not connected');
        }

        $listId = $config['default_list_id'] ?? '';
        if (empty($listId)) {
            return SyncResult::failure('No default list configured');
        }

        $email = $contact['email'] ?? '';
        if (empty($email)) {
            return SyncResult::skipped('No email address');
        }

        try {
            $fields = $this->mapContactFields($contact);
            $tags = $this->buildTagList($contact['tags'] ?? [], $config);

            $eoStatus = $this->resolveProviderStatus($contact);
            $result = $client->upsertContact($listId, $email, $fields, $tags, $eoStatus);
            $providerId = $result['id'] ?? EmailOctopusApiClient::contactId($email);

            return SyncResult::success($providerId);
        } catch (\RuntimeException $e) {
            $retryable = str_contains($e->getMessage(), '429')
                || preg_match('/\(5\d{2}\)/', $e->getMessage())
                || str_contains($e->getMessage(), 'timeout');

            return SyncResult::failure($e->getMessage(), retryable: $retryable);
        }
    }

    public function pushContactBatch(array $contacts, array $config): SyncResult
    {
        $client = $this->makeClient();
        if (!$client) {
            return SyncResult::failure('Not connected');
        }

        $listId = $config['default_list_id'] ?? '';
        if (empty($listId)) {
            return SyncResult::failure('No default list configured');
        }

        $members = [];
        $skipped = 0;

        foreach ($contacts as $contact) {
            $email = $contact['email'] ?? '';
            if (empty($email)) {
                $skipped++;
                continue;
            }

            $member = [
                'email_address' => $email,
                'fields'        => (object) $this->mapContactFields($contact),
                'status'        => $this->resolveProviderStatus($contact),
            ];

            $tags = $this->buildTagList($contact['tags'] ?? [], $config);
            if (!empty($tags)) {
                $member['tags'] = $tags;
            }

            $members[] = $member;
        }

        if (empty($members)) {
            return SyncResult::batch(0, 0, $skipped);
        }

        try {
            $result = $client->batchContacts($listId, $members);
            $pushed = count($result['success'] ?? []);
            $errors = $result['errors'] ?? [];
            $failed = count($errors);

            return SyncResult::batch($pushed, $failed, $skipped, array_map(
                fn($e) => $e['error']['message'] ?? 'Unknown error',
                $errors,
            ));
        } catch (\RuntimeException $e) {
            return SyncResult::failure($e->getMessage(), retryable: true);
        }
    }

    public function removeContact(string $email, array $config): SyncResult
    {
        $listId = $config['default_list_id'] ?? '';
        if (empty($listId)) {
            return SyncResult::failure('No default list configured');
        }

        return $this->doDeleteContact($listId, $email, 'Contact not found on provider');
    }

    public function updateContactStatus(string $email, string $status, array $config): SyncResult
    {
        $client = $this->makeClient();
        if (!$client) {
            return SyncResult::failure('Not connected');
        }

        $listId = $config['default_list_id'] ?? '';
        if (empty($listId)) {
            return SyncResult::failure('No default list configured');
        }

        $eoStatus = match ($status) {
            'subscribed'            => 'subscribed',
            'bounced', 'complained' => 'unsubscribed',
            default                 => null,
        };

        if ($eoStatus === null) {
            return SyncResult::skipped("Unmapped status: {$status}");
        }

        try {
            $client->updateContact($listId, $email, [], [], $eoStatus);

            return SyncResult::success();
        } catch (\RuntimeException $e) {
            return SyncResult::failure($e->getMessage());
        }
    }

    public function getFieldMapping(): array
    {
        return self::FIELD_MAPPING;
    }

    // --- SupportsListManagement ---

    public function getLists(array $config): array
    {
        $client = $this->makeClient();
        if (!$client) {
            return [];
        }

        try {
            $lists = $client->getLists();

            return array_map(fn($list) => [
                'id'           => $list['id'],
                'name'         => $list['name'],
                'contact_count' => $list['counts']['subscribed'] ?? 0,
            ], $lists);
        } catch (\RuntimeException) {
            return [];
        }
    }

    public function addToList(string $listId, string $email, array $fields, array $config): SyncResult
    {
        $client = $this->makeClient();
        if (!$client) {
            return SyncResult::failure('Not connected');
        }

        try {
            $mappedFields = [];
            foreach ($fields as $key => $value) {
                $eoKey = $this->getFieldMapping()[$key] ?? $key;
                $mappedFields[$eoKey] = $value;
            }

            $client->upsertContact($listId, $email, $mappedFields);

            return SyncResult::success(EmailOctopusApiClient::contactId($email));
        } catch (\RuntimeException $e) {
            return SyncResult::failure($e->getMessage());
        }
    }

    public function removeFromList(string $listId, string $email, array $config): SyncResult
    {
        return $this->doDeleteContact($listId, $email, 'Contact not found on list');
    }

    // --- SupportsAutomations ---

    public function getAutomations(array $config): array
    {
        return [];
    }

    public function queueIntoAutomation(string $automationId, string $email, array $fields, array $config): SyncResult
    {
        $client = $this->makeClient();
        if (!$client) {
            return SyncResult::failure('Not connected');
        }

        try {
            $client->queueIntoAutomation($automationId, $email);

            return SyncResult::success();
        } catch (\RuntimeException $e) {
            return SyncResult::failure($e->getMessage());
        }
    }

    // --- SupportsSuppressionSync ---

    public function pollSuppressions(array $config, ?string $cursor = null): array
    {
        $client = $this->makeClient();
        if (!$client) {
            return ['events' => [], 'cursor' => null];
        }

        $listId = $config['default_list_id'] ?? '';
        if (empty($listId)) {
            return ['events' => [], 'cursor' => null];
        }

        $params = ['status' => 'unsubscribed'];
        if ($cursor !== null) {
            $params['last_updated_at.gte'] = $cursor;
        }

        try {
            $contacts = $client->paginateContacts($listId, $params);

            $events = array_map(fn($c) => [
                'email'      => $c['email_address'] ?? '',
                'status'     => 'unsubscribed',
                'changed_at' => $c['last_updated_at'] ?? $c['created_at'] ?? '',
            ], $contacts);

            // Use the latest last_updated_at as the new cursor
            $newCursor = null;
            foreach ($contacts as $c) {
                $ts = $c['last_updated_at'] ?? null;
                if ($ts !== null && ($newCursor === null || $ts > $newCursor)) {
                    $newCursor = $ts;
                }
            }

            return ['events' => $events, 'cursor' => $newCursor];
        } catch (\RuntimeException) {
            return ['events' => [], 'cursor' => $cursor];
        }
    }

    // --- SupportsContactImport ---

    public function getAvailableImportFields(): array
    {
        return [
            'email_address' => ['label' => __('Email', 'wp-sms'),      'type' => 'core'],
            'FirstName'     => ['label' => __('First Name', 'wp-sms'), 'type' => 'field'],
            'LastName'      => ['label' => __('Last Name', 'wp-sms'),  'type' => 'field'],
        ];
    }

    public function getDefaultImportFieldMapping(): array
    {
        return self::FIELD_MAPPING;
    }

    public function getImportConfigSchema(): array
    {
        return [
            'list_id' => [
                'type'        => 'select',
                'label'       => __('List', 'wp-sms'),
                'description' => __('Select the EmailOctopus list to import from.', 'wp-sms'),
                'required'    => true,
                'dynamic'     => true,
            ],
            'field_mapping' => [
                'type'  => 'field_mapping',
                'label' => __('Field Mapping', 'wp-sms'),
            ],
            'auto_sync' => [
                'type'        => 'boolean',
                'label'       => __('Auto-sync', 'wp-sms'),
                'default'     => false,
                'description' => __('Automatically sync new contacts from EmailOctopus on a recurring schedule.', 'wp-sms'),
            ],
        ];
    }

    public function importOne(mixed $externalId, array $config, bool $suppressEvents = false): ?string
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
            $contact = $client->getContact($listId, (string) $externalId);
            if (!$contact) {
                return null;
            }
        } catch (\RuntimeException) {
            return null;
        }

        $fieldMapping = $config['field_mapping'] ?? $this->getDefaultImportFieldMapping();
        $sourceData = $this->extractImportContactData($contact);
        $contactData = ContactImportHelper::applyFieldMapping($sourceData, $fieldMapping);
        $contactData['source'] = $this->getId();
        $contactData['source_ref'] = $listId;

        return ContactImportHelper::upsertContact($this->contacts, $contactData, $suppressEvents);
    }

    public function getImportBatch(array $config, int $batchSize, mixed $afterCursor = null): array
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

            if ($afterCursor !== null) {
                $params['starting_after'] = (string) $afterCursor;
            }

            $response = $client->getContacts($listId, $params);
            $contacts = $response['data'] ?? [];

            return array_map(fn($c) => $c['email_address'] ?? '', $contacts);
        } catch (\RuntimeException) {
            return [];
        }
    }

    public function countImportable(array $config): int
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

    public function handleImportDeletion(mixed $externalId): void
    {
    }

    // --- Helpers ---

    private function makeClient(): ?EmailOctopusApiClient
    {
        if ($this->client !== null) {
            return $this->client;
        }

        $configs = get_option(self::CONFIG_OPTION, []);
        $apiKey = $configs['emailoctopus']['credentials']['api_key'] ?? '';

        if ($apiKey) {
            $this->client = new EmailOctopusApiClient($apiKey);
        }

        return $this->client;
    }

    private function mapContactFields(array $contact): array
    {
        $fields = [];

        foreach (self::FIELD_MAPPING as $pluginField => $eoField) {
            if ($pluginField === 'email') {
                continue;
            }
            if (isset($contact[$pluginField]) && $contact[$pluginField] !== '' && $contact[$pluginField] !== null) {
                $fields[$eoField] = $contact[$pluginField];
            }
        }

        return $fields;
    }

    private function doDeleteContact(string $listId, string $email, string $skipMessage): SyncResult
    {
        $client = $this->makeClient();
        if (!$client) {
            return SyncResult::failure('Not connected');
        }

        try {
            $client->deleteContact($listId, $email);

            return SyncResult::success();
        } catch (\RuntimeException $e) {
            if (EmailOctopusApiClient::isNotFoundError($e)) {
                return SyncResult::skipped($skipMessage);
            }

            return SyncResult::failure($e->getMessage());
        }
    }

    private function resolveProviderStatus(array $contact): string
    {
        $status = $contact['status'] ?? 'subscribed';
        if (in_array($status, ['bounced', 'complained'], true)) {
            return 'unsubscribed';
        }

        $optOuts = $contact['channel_opt_outs'] ?? [];
        if (!empty($optOuts['email'])) {
            return 'unsubscribed';
        }

        return 'subscribed';
    }

    private function buildTagList(array $tags, array $config): array
    {
        if (empty($config['push_tags']) || empty($tags)) {
            return [];
        }

        $list = [];
        foreach ($tags as $tag) {
            $name = is_array($tag) ? ($tag['name'] ?? '') : (string) $tag;
            if ($name !== '') {
                $list[] = $name;
            }
        }

        return $list;
    }

    private function extractImportContactData(array $eoContact): array
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
