<?php

namespace WSms\Integration\Mailtrap;

use WSms\Contact\Contracts\ContactRepositoryInterface;
use WSms\Integration\Contracts\ContactImportHelper;
use WSms\Integration\Contracts\IntegrationCapability;
use WSms\Integration\Contracts\IntegrationInterface;
use WSms\Integration\Marketing\ImportSyncManager;
use WSms\Integration\Contracts\SupportsContactImport;
use WSms\Integration\Contracts\SupportsContactSync;
use WSms\Integration\Contracts\SupportsListManagement;
use WSms\Integration\Contracts\SupportsSuppressionSync;
use WSms\Integration\Contracts\SyncResult;
use WSms\Integration\Mailtrap\Actions\AddToMailtrapListAction;
use WSms\Integration\Mailtrap\Actions\RemoveFromMailtrapListAction;

defined('ABSPATH') || exit;

class MailtrapIntegration implements
    IntegrationInterface,
    SupportsContactSync,
    SupportsContactImport,
    SupportsListManagement,
    SupportsSuppressionSync
{
    public const FIELD_MAPPING = [
        'email'      => 'email',
        'first_name' => 'first_name',
        'last_name'  => 'last_name',
    ];

    private const SUPPRESSION_REASON_MAP = [
        'bounce'      => 'bounced',
        'hard_bounce' => 'bounced',
        'soft_bounce' => 'bounced',
        'complaint'   => 'complained',
        'spam'        => 'complained',
        'unsubscribe' => 'unsubscribed',
        'manual'      => 'unsubscribed',
    ];

    private ?MailtrapApiClient $client = null;

    public function __construct(
        private readonly ?ContactRepositoryInterface $contacts = null,
    ) {
    }

    public function getId(): string
    {
        return 'mailtrap_marketing';
    }

    public function getName(): string
    {
        return 'Mailtrap';
    }

    public function getDescription(): string
    {
        return __('Sync contacts and suppression lists with Mailtrap\'s promotional platform. Email sending is handled separately by the Mailtrap gateway.', 'wp-sms');
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
        return 'gateway';
    }

    public function getAuthSchema(): array
    {
        return [];
    }

    public function getMetadata(): array
    {
        return [
            'rate_limit' => ['tokens_per_second' => 3, 'burst' => 10],
            'website'    => 'https://mailtrap.io',
        ];
    }

    public function connect(array $credentials): array
    {
        $gatewayConfig = $this->getGatewayConfig();
        $apiToken = $gatewayConfig['shared']['api_token'] ?? '';

        if (empty($apiToken)) {
            throw new \RuntimeException(__('Configure the Mailtrap gateway first. The integration uses the same API token.', 'wp-sms'));
        }

        $client = new MailtrapApiClient($apiToken);

        try {
            $accounts = $client->getAccounts();
        } catch (\RuntimeException $e) {
            if (str_contains($e->getMessage(), '(403)')) {
                throw new \RuntimeException(
                    __('Your API token doesn\'t have access to the promotional API. Create a token with full permissions in Mailtrap settings.', 'wp-sms'),
                );
            }
            throw new \RuntimeException(__('Invalid API token: ', 'wp-sms') . $e->getMessage());
        }

        if (empty($accounts)) {
            throw new \RuntimeException(__('No accounts found for this API token.', 'wp-sms'));
        }

        $accountId = (string) ($accounts[0]['id'] ?? '');
        if (empty($accountId)) {
            throw new \RuntimeException(__('Could not determine account ID.', 'wp-sms'));
        }

        $configs = get_option(MailtrapApiClient::GATEWAY_CONFIG_OPTION, []);
        $configs[MailtrapApiClient::GATEWAY_ID]['shared']['account_id'] = $accountId;
        update_option(MailtrapApiClient::GATEWAY_CONFIG_OPTION, $configs);

        return ['account_id' => $accountId];
    }

    public function disconnect(): void
    {
        $state = get_option(ImportSyncManager::STATE_KEY, []);
        unset($state[$this->getId()]);
        update_option(ImportSyncManager::STATE_KEY, $state);

        $configs = get_option(MailtrapApiClient::GATEWAY_CONFIG_OPTION, []);
        unset($configs[MailtrapApiClient::GATEWAY_ID]['shared']['account_id']);
        update_option(MailtrapApiClient::GATEWAY_CONFIG_OPTION, $configs);

        as_unschedule_all_actions('wsms_suppression_poll', ['integration_id' => $this->getId()], 'wsms');
    }

    public function isConnected(): bool
    {
        $config = $this->getGatewayConfig();

        return !empty($config['shared']['api_token'])
            && !empty($config['shared']['account_id']);
    }

    public function getTriggers(): array
    {
        return [];
    }

    public function getActions(): array
    {
        $client = $this->makeClient() ?? new MailtrapApiClient('');

        return [
            new AddToMailtrapListAction($client),
            new RemoveFromMailtrapListAction($client),
        ];
    }

    public function getCapabilities(): array
    {
        return [
            ['id' => IntegrationCapability::CONTACT_SYNC,    'supported' => true],
            ['id' => IntegrationCapability::LIST_MANAGEMENT,  'supported' => true],
            ['id' => IntegrationCapability::TAGS,             'supported' => false, 'note' => 'Mailtrap has no tags API'],
            ['id' => IntegrationCapability::AUTOMATIONS,      'supported' => false, 'note' => 'Mailtrap has no automations API'],
            ['id' => IntegrationCapability::SUPPRESSION_SYNC, 'supported' => true],
            ['id' => IntegrationCapability::EMAIL_GATEWAY,    'supported' => true, 'gateway_id' => 'mailtrap'],
            ['id' => IntegrationCapability::ENGAGEMENT_DATA,  'supported' => false],
            ['id' => IntegrationCapability::CONTACT_IMPORT,  'supported' => true],
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

        $listId = (int) ($config['default_list_id'] ?? 0);
        if ($listId <= 0) {
            return SyncResult::failure('No default list configured');
        }

        $email = $contact['email'] ?? '';
        if (empty($email)) {
            return SyncResult::skipped('No email address');
        }

        try {
            $fields = $this->mapContactFields($contact);
            $result = $client->upsertContact($listId, $email, $fields);
            $providerId = (string) ($result['id'] ?? '');

            return SyncResult::success($providerId);
        } catch (\RuntimeException $e) {
            if (MailtrapApiClient::isNotFoundError($e)) {
                return SyncResult::failure('List not found in Mailtrap');
            }

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

        $listId = (int) ($config['default_list_id'] ?? 0);
        if ($listId <= 0) {
            return SyncResult::failure('No default list configured');
        }

        $pushed = 0;
        $failed = 0;
        $skipped = 0;
        $errors = [];

        foreach ($contacts as $contact) {
            $email = $contact['email'] ?? '';
            if (empty($email)) {
                $skipped++;
                continue;
            }

            $result = $this->pushContact($contact, $config);
            if ($result->success) {
                if ($result->status === 'skipped') {
                    $skipped++;
                } else {
                    $pushed++;
                }
            } else {
                $failed++;
                if ($result->error) {
                    $errors[] = $result->error;
                }
            }
        }

        return SyncResult::batch($pushed, $failed, $skipped, $errors);
    }

    public function removeContact(string $email, array $config): SyncResult
    {
        $client = $this->makeClient();
        if (!$client) {
            return SyncResult::failure('Not connected');
        }

        $listId = (int) ($config['default_list_id'] ?? 0);
        if ($listId <= 0) {
            return SyncResult::failure('No default list configured');
        }

        try {
            $existing = $client->findContactByEmail($listId, $email);
            if (!$existing) {
                return SyncResult::skipped('Contact not found on provider');
            }

            $client->deleteContact((int) $existing['id']);

            return SyncResult::success();
        } catch (\RuntimeException $e) {
            if (MailtrapApiClient::isNotFoundError($e)) {
                return SyncResult::skipped('Contact not found on provider');
            }

            return SyncResult::failure($e->getMessage());
        }
    }

    public function updateContactStatus(string $email, string $status, array $config): SyncResult
    {
        return SyncResult::skipped('Mailtrap does not support programmatic status updates');
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
                'id'            => $list['id'],
                'name'          => $list['name'],
                'contact_count' => $list['contacts_count'] ?? 0,
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
                $mtKey = $this->getFieldMapping()[$key] ?? $key;
                $mappedFields[$mtKey] = $value;
            }
            unset($mappedFields['email']);

            $result = $client->upsertContact((int) $listId, $email, $mappedFields);

            return SyncResult::success((string) ($result['id'] ?? ''));
        } catch (\RuntimeException $e) {
            return SyncResult::failure($e->getMessage());
        }
    }

    public function removeFromList(string $listId, string $email, array $config): SyncResult
    {
        $client = $this->makeClient();
        if (!$client) {
            return SyncResult::failure('Not connected');
        }

        try {
            $existing = $client->findContactByEmail((int) $listId, $email);
            if (!$existing) {
                return SyncResult::skipped('Contact not found on list');
            }

            $client->deleteContact((int) $existing['id']);

            return SyncResult::success();
        } catch (\RuntimeException $e) {
            if (MailtrapApiClient::isNotFoundError($e)) {
                return SyncResult::skipped('Contact not found on list');
            }

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

        $params = [];
        if ($cursor !== null) {
            $params['cursor'] = $cursor;
        }

        try {
            $response = $client->getSuppressions($params);
            $suppressions = $response['data'] ?? $response;

            if (!is_array($suppressions)) {
                return ['events' => [], 'cursor' => $cursor];
            }

            $events = [];
            $lastId = $cursor;

            foreach ($suppressions as $s) {
                $email = $s['email'] ?? '';
                if (empty($email)) {
                    continue;
                }

                $reason = $s['type'] ?? $s['reason'] ?? 'unknown';
                $status = self::SUPPRESSION_REASON_MAP[$reason] ?? 'unsubscribed';

                $events[] = [
                    'email'      => $email,
                    'status'     => $status,
                    'changed_at' => $s['created_at'] ?? '',
                ];

                $id = (string) ($s['id'] ?? '');
                if ($id !== '') {
                    $lastId = $id;
                }
            }

            $nextCursor = $response['next_cursor'] ?? $lastId;

            return ['events' => $events, 'cursor' => $nextCursor];
        } catch (\RuntimeException) {
            return ['events' => [], 'cursor' => $cursor];
        }
    }

    // --- SupportsContactImport ---

    public function getAvailableImportFields(): array
    {
        return [
            'email'      => ['label' => __('Email', 'wp-sms'),      'type' => 'core'],
            'first_name' => ['label' => __('First Name', 'wp-sms'), 'type' => 'field'],
            'last_name'  => ['label' => __('Last Name', 'wp-sms'),  'type' => 'field'],
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
                'description' => __('Select the Mailtrap contact list to import from.', 'wp-sms'),
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
                'description' => __('Automatically sync new contacts from Mailtrap on a recurring schedule.', 'wp-sms'),
            ],
        ];
    }

    public function importOne(mixed $externalId, array $config, bool $suppressEvents = false): ?string
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

        $fieldMapping = $config['field_mapping'] ?? $this->getDefaultImportFieldMapping();
        $sourceData = $this->extractImportContactData($contact);
        $contactData = ContactImportHelper::applyFieldMapping($sourceData, $fieldMapping);
        $contactData['source'] = $this->getId();
        $contactData['source_ref'] = (string) $listId;

        return ContactImportHelper::upsertContact($this->contacts, $contactData, $suppressEvents);
    }

    public function getImportBatch(array $config, int $batchSize, mixed $afterCursor = null): array
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

            if ($afterCursor !== null) {
                $params['cursor'] = (string) $afterCursor;
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

    public function countImportable(array $config): int
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

    public function handleImportDeletion(mixed $externalId): void
    {
    }

    // --- Helpers ---

    private function getGatewayConfig(): array
    {
        $configs = get_option(MailtrapApiClient::GATEWAY_CONFIG_OPTION, []);

        return $configs[MailtrapApiClient::GATEWAY_ID] ?? [];
    }

    private function makeClient(): ?MailtrapApiClient
    {
        if ($this->client !== null) {
            return $this->client;
        }

        $this->client = MailtrapApiClient::fromGatewayConfig();

        return $this->client;
    }

    private function mapContactFields(array $contact): array
    {
        $fields = [];

        foreach (self::FIELD_MAPPING as $pluginField => $mtField) {
            if ($pluginField === 'email') {
                continue;
            }
            if (isset($contact[$pluginField]) && $contact[$pluginField] !== '' && $contact[$pluginField] !== null) {
                $fields[$mtField] = $contact[$pluginField];
            }
        }

        return $fields;
    }

    private function extractImportContactData(array $mtContact): array
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

        if (!empty($mtContact['first_name'])) {
            $data['first_name'] = $mtContact['first_name'];
        }
        if (!empty($mtContact['last_name'])) {
            $data['last_name'] = $mtContact['last_name'];
        }

        return $data;
    }
}
