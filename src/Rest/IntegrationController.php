<?php

namespace WSms\Rest;

use WSms\Contact\Contracts\ContactRepositoryInterface;
use WSms\Exception\NotFoundException;
use WSms\Exception\ValidationException;
use WSms\Flow\Action\ActionRegistry;
use WSms\Flow\Trigger\TriggerRegistry;
use WSms\Integration\IntegrationRegistry;
use WSms\Integration\Contracts\IntegrationCapability;
use WSms\Integration\Contracts\IntegrationInterface;
use WSms\Integration\Contracts\SupportsContactImport;
use WSms\Integration\Contracts\SupportsContactSync;
use WSms\Integration\Contracts\SupportsListManagement;
use WSms\Integration\Contracts\SupportsSuppressionSync;
use WSms\Integration\Marketing\ImportSyncManager;
use WSms\Integration\Marketing\SuppressionPoller;
use WSms\Integration\Webhook\WebhookIntegration;
use WSms\Queue\Contracts\QueueInterface;
use WSms\Queue\Job\MarketingPushContactJob;

defined('ABSPATH') || exit;

class IntegrationController extends Controller
{
    private const CONFIG_OPTION = 'wsms_integration_configs';
    private const INTERNAL_IDS = ['wp_sms', 'wsms_contacts', 'wsms_auth', 'wsms_schedule', 'webhook'];

    public function __construct(
        private readonly IntegrationRegistry $integrationRegistry,
        private readonly TriggerRegistry $triggerRegistry,
        private readonly ActionRegistry $actionRegistry,
        private readonly ?ContactRepositoryInterface $contacts = null,
        private readonly ?QueueInterface $queue = null,
        private readonly ?SuppressionPoller $suppressionPoller = null,
        private readonly ?ImportSyncManager $importSyncManager = null,
    ) {
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/integrations', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'index'],
                'permission_callback' => [$this, 'canManage'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/integrations/(?P<id>[\w]+)', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'show'],
                'permission_callback' => [$this, 'canManage'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/integrations/(?P<id>[\w]+)/config', [
            [
                'methods'             => 'PUT',
                'callback'            => [$this, 'saveConfig'],
                'permission_callback' => [$this, 'canManage'],
            ],
            [
                'methods'             => 'DELETE',
                'callback'            => [$this, 'deleteConfig'],
                'permission_callback' => [$this, 'canManage'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/triggers', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'triggers'],
                'permission_callback' => [$this, 'canManage'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/triggers/(?P<triggerId>[\\w.]+)/filter-options/(?P<field>[\\w]+)', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'triggerFilterOptions'],
                'permission_callback' => [$this, 'canManage'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/actions', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'actions'],
                'permission_callback' => [$this, 'canManage'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/actions/(?P<actionId>[\\w_]+)/config-options/(?P<field>[\\w]+)', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'actionConfigOptions'],
                'permission_callback' => [$this, 'canManage'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/integrations/(?P<id>[\w]+)/sync-settings', [
            [
                'methods'             => 'PUT',
                'callback'            => [$this, 'saveSyncSettings'],
                'permission_callback' => [$this, 'canManage'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/integrations/(?P<id>[\w]+)/sync', [
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'triggerSync'],
                'permission_callback' => [$this, 'canManage'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/integrations/(?P<id>[\w]+)/poll', [
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'triggerPoll'],
                'permission_callback' => [$this, 'canManage'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/integrations/(?P<id>[\w]+)/lists', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'integrationLists'],
                'permission_callback' => [$this, 'canManage'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/integrations/(?P<id>[\w]+)/import-fields', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'importFields'],
                'permission_callback' => [$this, 'canManage'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/integrations/(?P<id>[\w]+)/import-settings', [
            [
                'methods'             => 'PUT',
                'callback'            => [$this, 'saveImportSettings'],
                'permission_callback' => [$this, 'canManage'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/integrations/(?P<id>[\w]+)/import', [
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'startImport'],
                'permission_callback' => [$this, 'canManage'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/integrations/(?P<id>[\w]+)/import-status', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'importStatus'],
                'permission_callback' => [$this, 'canManage'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/integrations/webhook/endpoints', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'listWebhookEndpoints'],
                'permission_callback' => [$this, 'canManage'],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'createWebhookEndpoint'],
                'permission_callback' => [$this, 'canManage'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/integrations/webhook/endpoints/(?P<endpointId>[A-Za-z0-9]+)', [
            [
                'methods'             => 'DELETE',
                'callback'            => [$this, 'deleteWebhookEndpoint'],
                'permission_callback' => [$this, 'canManage'],
            ],
        ]);
    }

    public function index(): \WP_REST_Response
    {
        return $this->handle(function () {
            $configs = get_option(self::CONFIG_OPTION, []);
            $syncState = get_option(ImportSyncManager::STATE_KEY, []);
            $integrations = [];

            foreach ($this->integrationRegistry->getAll() as $integration) {
                if (in_array($integration->getId(), self::INTERNAL_IDS, true)) {
                    continue;
                }
                $integrations[] = $this->formatIntegrationSummary($integration, $configs, $syncState);
            }

            return $this->paginated($integrations, count($integrations));
        });
    }

    public function show(\WP_REST_Request $request): \WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            $integration = $this->resolveIntegrationOrFail($request);

            $configs = get_option(self::CONFIG_OPTION, []);
            $syncState = get_option(ImportSyncManager::STATE_KEY, []);
            $base = $this->formatIntegrationBase($integration, $configs, $syncState);

            $base['triggers'] = array_values(array_map(fn($t) => [
                'id'          => $t->getId(),
                'name'        => $t->getName(),
                'description' => $t->getDescription(),
            ], $integration->getTriggers()));

            $base['actions'] = array_values(array_map(fn($a) => [
                'id'          => $a->getId(),
                'name'        => $a->getName(),
                'description' => $a->getDescription(),
            ], $integration->getActions()));

            return new \WP_REST_Response($base);
        });
    }

    public function saveConfig(\WP_REST_Request $request): \WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            $integration = $this->resolveIntegrationOrFail($request);

            $id = $integration->getId();
            $body = $request->get_json_params();
            $credentials = $body['credentials'] ?? [];

            try {
                $credentials = $integration->connect($credentials);
            } catch (\RuntimeException $e) {
                throw ValidationException::field('credentials', $e->getMessage());
            }

            $configs = get_option(self::CONFIG_OPTION, []);
            $configs[$id] = [
                'enabled'     => true,
                'credentials' => $credentials,
            ];
            update_option(self::CONFIG_OPTION, $configs);

            return $this->ok();
        });
    }

    public function deleteConfig(\WP_REST_Request $request): \WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            $integration = $this->resolveIntegrationOrFail($request);

            $integration->disconnect();

            $id = $integration->getId();
            $configs = get_option(self::CONFIG_OPTION, []);
            unset($configs[$id]);
            update_option(self::CONFIG_OPTION, $configs);

            return $this->ok();
        });
    }

    /**
     * Resolve integration from request or throw NotFoundException.
     */
    private function resolveIntegrationOrFail(\WP_REST_Request $request): IntegrationInterface
    {
        $id = $request->get_param('id');
        $integration = $this->integrationRegistry->get($id);

        if (!$integration) {
            throw NotFoundException::entity('Integration', $id);
        }

        return $integration;
    }

    public function triggers(): \WP_REST_Response
    {
        return $this->handle(function () {
            $iconMap = $this->buildIconMap('getTriggers');
            $triggers = [];

            foreach ($this->triggerRegistry->all() as $trigger) {
                $triggers[] = [
                    'id'             => $trigger->getId(),
                    'name'           => $trigger->getName(),
                    'description'    => $trigger->getDescription(),
                    'group'          => $trigger->getGroup(),
                    'icon'           => $iconMap[$trigger->getId()] ?? '',
                    'payload_schema' => ['type' => 'object', 'properties' => $trigger->getPayloadSchema()],
                    'filter_schema'  => $trigger->getFilterSchema(),
                ];
            }

            return $this->paginated($triggers, count($triggers));
        });
    }

    public function triggerFilterOptions(\WP_REST_Request $request): \WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            $triggerId = $request->get_param('triggerId');
            $field = $request->get_param('field');

            $trigger = $this->triggerRegistry->get($triggerId);
            if (!$trigger) {
                throw NotFoundException::entity('Trigger', $triggerId);
            }

            $options = $trigger->getFilterOptions($field);

            return new \WP_REST_Response([
                'options' => $options,
            ]);
        });
    }

    public function actions(): \WP_REST_Response
    {
        return $this->handle(function () {
            $iconMap = $this->buildIconMap('getActions');
            $actions = [];
            $triggerIds = array_map(
                fn ($t) => $t->getId(),
                $this->triggerRegistry->all(),
            );

            foreach ($this->actionRegistry->all() as $action) {
                $schema = $action->getConfigSchema();
                $actions[] = [
                    'id'            => $action->getId(),
                    'name'          => $action->getName(),
                    'description'   => $action->getDescription(),
                    'group'         => $action->getGroup(),
                    'icon'          => $iconMap[$action->getId()] ?? '',
                    'config_schema' => [
                        'type'       => 'object',
                        'properties' => $schema,
                        'required'   => array_keys(array_filter($schema, fn($prop) => ($prop['required'] ?? false) === true)),
                    ],
                    'output_schema' => [
                        'type'       => 'object',
                        'properties' => $action->getOutputSchema(),
                    ],
                    'placeholders'  => $this->collectPlaceholders($action, $triggerIds),
                ];
            }

            return $this->paginated($actions, count($actions));
        });
    }

    /** @param 'getTriggers'|'getActions' $method */
    private function buildIconMap(string $method): array
    {
        $map = [];
        foreach ($this->integrationRegistry->getAll() as $integration) {
            foreach ($integration->$method() as $item) {
                $map[$item->getId()] = $integration->getIcon();
            }
        }
        return $map;
    }

    private function collectPlaceholders(\WSms\Flow\Contracts\ActionInterface $action, array $triggerIds): array
    {
        $placeholders = [];
        foreach ($triggerIds as $triggerId) {
            $map = $action->getPlaceholders($triggerId);
            if ($map) {
                $placeholders[$triggerId] = $map;
            }
        }
        return $placeholders;
    }

    public function actionConfigOptions(\WP_REST_Request $request): \WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            $actionId = $request->get_param('actionId');
            $field = $request->get_param('field');

            $action = $this->actionRegistry->get($actionId);
            if (!$action) {
                throw NotFoundException::entity('Action', $actionId);
            }

            $context = $request->get_query_params();
            unset($context['actionId'], $context['field']);
            $options = $action->getConfigOptions($field, $context);

            return new \WP_REST_Response([
                'options' => $options,
            ]);
        });
    }

    public function saveSyncSettings(\WP_REST_Request $request): \WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            $integration = $this->resolveIntegrationOrFail($request);

            if (!$integration instanceof SupportsContactSync) {
                throw ValidationException::field('integration', __('Integration does not support sync', 'wp-sms'));
            }

            $body = $request->get_json_params();
            $id = $integration->getId();

            $state = get_option(ImportSyncManager::STATE_KEY, []);
            $state[$id]['sync_settings'] = [
                'auto_push'        => !empty($body['auto_push']),
                'push_tags'        => !empty($body['push_tags']),
                'poll_interval'    => (int) ($body['poll_interval'] ?? 3600),
                'poll_enabled'     => !empty($body['poll_enabled']),
                'default_list_id'  => sanitize_text_field($body['default_list_id'] ?? ''),
                'remove_on_delete' => !empty($body['remove_on_delete']),
            ];
            update_option(ImportSyncManager::STATE_KEY, $state);

            if ($integration instanceof SupportsSuppressionSync) {
                as_unschedule_all_actions('wsms_suppression_poll', ['integration_id' => $id], 'wsms');
                if (!empty($body['poll_enabled'])) {
                    $interval = (int) ($body['poll_interval'] ?? 3600);
                    as_schedule_recurring_action(
                        time() + $interval,
                        $interval,
                        'wsms_suppression_poll',
                        ['integration_id' => $id],
                        'wsms',
                    );
                }
            }

            return $this->ok();
        });
    }

    public function triggerSync(\WP_REST_Request $request): \WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            $integration = $this->resolveIntegrationOrFail($request);

            if (!$integration instanceof SupportsContactSync || !$this->queue || !$this->contacts) {
                throw ValidationException::field('integration', __('Integration does not support sync', 'wp-sms'));
            }

            $id = $integration->getId();
            $state = get_option(ImportSyncManager::STATE_KEY, []);
            $config = $state[$id]['sync_settings'] ?? [];

            if (empty($config['default_list_id'])) {
                throw ValidationException::field('default_list_id', __('No default list configured', 'wp-sms'));
            }

            $dispatched = 0;
            $queue = $this->queue;

            $this->contacts->eachSubscribedWithEmail(function (array $batch) use ($id, $queue, &$dispatched) {
                foreach ($batch as $contact) {
                    $queue->dispatch(new MarketingPushContactJob($id, $contact));
                    $dispatched++;
                }
            });

            return $this->ok(['dispatched' => $dispatched]);
        });
    }

    public function triggerPoll(\WP_REST_Request $request): \WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            $integration = $this->resolveIntegrationOrFail($request);

            if (!$integration instanceof SupportsSuppressionSync || !$this->suppressionPoller) {
                throw ValidationException::field('integration', __('Integration does not support suppression polling', 'wp-sms'));
            }

            $events = $this->suppressionPoller->poll($integration->getId());

            return $this->ok(['events' => $events]);
        });
    }

    public function integrationLists(\WP_REST_Request $request): \WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            $integration = $this->resolveIntegrationOrFail($request);

            if (!$integration instanceof SupportsListManagement) {
                throw ValidationException::field('integration', __('Integration does not support list management', 'wp-sms'));
            }

            $state = get_option(ImportSyncManager::STATE_KEY, []);
            $config = $state[$integration->getId()]['sync_settings'] ?? [];

            return new \WP_REST_Response([
                'lists' => $integration->getLists($config),
            ]);
        });
    }

    public function listWebhookEndpoints(): \WP_REST_Response
    {
        return $this->handle(function () {
            $secrets = get_option(WebhookIntegration::SECRETS_OPTION, []);
            $endpoints = [];

            foreach ($secrets as $id => $entry) {
                $endpoints[] = [
                    'id'         => $id,
                    'label'      => $entry['label'] ?? '',
                    'url'        => rest_url('wsms/v1/webhook/' . $id),
                    'created_at' => $entry['created_at'] ?? '',
                ];
            }

            return new \WP_REST_Response(['endpoints' => $endpoints]);
        });
    }

    public function createWebhookEndpoint(\WP_REST_Request $request): \WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            $body = $request->get_json_params();
            $label = sanitize_text_field($body['label'] ?? '');

            if (empty($label)) {
                throw ValidationException::field('label', __('Label is required', 'wp-sms'));
            }

            $id = bin2hex(random_bytes(4));
            $secret = bin2hex(random_bytes(32));
            $createdAt = gmdate('c');

            $secrets = get_option(WebhookIntegration::SECRETS_OPTION, []);
            $secrets[$id] = [
                'secret'     => $secret,
                'label'      => $label,
                'created_at' => $createdAt,
            ];
            update_option(WebhookIntegration::SECRETS_OPTION, $secrets);

            return $this->created([
                'id'         => $id,
                'label'      => $label,
                'url'        => rest_url('wsms/v1/webhook/' . $id),
                'secret'     => $secret,
                'created_at' => $createdAt,
            ]);
        });
    }

    public function deleteWebhookEndpoint(\WP_REST_Request $request): \WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            $endpointId = $request->get_param('endpointId');
            $secrets = get_option(WebhookIntegration::SECRETS_OPTION, []);

            if (!isset($secrets[$endpointId])) {
                throw NotFoundException::entity('WebhookEndpoint', $endpointId);
            }

            unset($secrets[$endpointId]);
            update_option(WebhookIntegration::SECRETS_OPTION, $secrets);

            return $this->ok();
        });
    }

    // ── Contact Import endpoints ──

    public function importFields(\WP_REST_Request $request): \WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            $integration = $this->resolveImportIntegrationOrFail($request);

            return new \WP_REST_Response([
                'fields'          => $integration->getAvailableImportFields(),
                'default_mapping' => $integration->getDefaultImportFieldMapping(),
                'config_schema'   => $integration->getImportConfigSchema(),
            ]);
        });
    }

    public function saveImportSettings(\WP_REST_Request $request): \WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            $integration = $this->resolveImportIntegrationOrFail($request);

            $body = $request->get_json_params();
            $this->importSyncManager->saveImportSettings($integration->getId(), $body);

            return $this->ok();
        });
    }

    public function startImport(\WP_REST_Request $request): \WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            $integration = $this->resolveImportIntegrationOrFail($request);

            $id = $integration->getId();
            if ($this->importSyncManager->isImporting($id)) {
                throw ValidationException::field('import', __('Import already in progress', 'wp-sms'));
            }

            $total = $this->importSyncManager->startImport($id);

            return $this->ok(['total_available' => $total]);
        });
    }

    public function importStatus(\WP_REST_Request $request): \WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            $integration = $this->resolveImportIntegrationOrFail($request);

            $intState = $this->importSyncManager->getImportState($integration->getId()) ?? [];

            return new \WP_REST_Response([
                'import_stats'    => $intState['import_stats'] ?? [],
                'import_settings' => $intState['import_settings'] ?? [],
                'contact_count'   => $intState['import_stats']['total_synced'] ?? 0,
            ]);
        });
    }

    private function resolveImportIntegrationOrFail(\WP_REST_Request $request): SupportsContactImport&IntegrationInterface
    {
        $integration = $this->resolveIntegrationOrFail($request);

        if (!$integration instanceof SupportsContactImport) {
            throw ValidationException::field('integration', __('Integration does not support contact import', 'wp-sms'));
        }

        return $integration;
    }

    private function formatIntegrationBase(IntegrationInterface $integration, array $configs, ?array $syncState = null): array
    {
        $base = [
            'id'          => $integration->getId(),
            'name'        => $integration->getName(),
            'description' => $integration->getDescription(),
            'category'    => $integration->getCategory(),
            'icon'        => $integration->getIcon(),
            'available'   => $integration->isAvailable(),
            'connected'   => $integration->isConnected(),
            'auth_type'   => $integration->getAuthType(),
            'auth_schema' => $integration->getAuthSchema(),
        ];

        $config = $configs[$integration->getId()] ?? null;
        if ($config && !empty($config['credentials'])) {
            $safe = array_diff_key($config['credentials'], array_flip(['bot_token', 'webhook_secret', 'api_key']));
            if ($safe) {
                $base['config'] = $safe;
            }
        }

        $base['capabilities'] = $this->getCapabilities($integration);

        if ($integration instanceof SupportsContactSync) {
            $syncState ??= get_option(ImportSyncManager::STATE_KEY, []);
            $intState = $syncState[$integration->getId()] ?? [];
            $base['sync_settings'] = $intState['sync_settings'] ?? null;
            $base['sync_status'] = $intState['stats'] ?? null;
        }

        if ($integration instanceof SupportsContactImport) {
            $syncState ??= get_option(ImportSyncManager::STATE_KEY, []);
            $intState = $syncState[$integration->getId()] ?? [];
            $base['import_settings'] = $intState['import_settings'] ?? null;
            $base['import_stats'] = $intState['import_stats'] ?? null;
        }

        return $base;
    }

    private function formatIntegrationSummary(IntegrationInterface $integration, array $configs, ?array $syncState = null): array
    {
        return $this->formatIntegrationBase($integration, $configs, $syncState) + [
            'triggers' => count($integration->getTriggers()),
            'actions'  => count($integration->getActions()),
        ];
    }

    private function getCapabilities(IntegrationInterface $integration): array
    {
        return array_map(fn(array $cap) => [
            'id'         => $cap['id'],
            'name'       => IntegrationCapability::LABELS[$cap['id']] ?? $cap['id'],
            'supported'  => $cap['supported'],
            'note'       => $cap['note'] ?? null,
            'gateway_id' => $cap['gateway_id'] ?? null,
        ], $integration->getCapabilities());
    }
}
