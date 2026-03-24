<?php

namespace WSms\Rest;

use WSms\Contact\ContactImporter;
use WSms\Contact\ContactExporter;
use WSms\Contact\Contracts\ContactRepositoryInterface;
use WSms\Contact\Contracts\SegmentEvaluatorInterface;
use WSms\Exception\NotFoundException;
use WSms\Exception\ValidationException;

defined('ABSPATH') || exit;

class ContactController extends Controller
{
    private const ALLOWED_STATUSES = ['subscribed', 'unsubscribed', 'bounced', 'complained'];

    public function __construct(
        private readonly ContactRepositoryInterface $contacts,
        private readonly SegmentEvaluatorInterface $segmentEvaluator,
        private readonly ContactImporter $importer,
        private readonly ContactExporter $exporter,
    ) {
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/contacts', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'index'],
                'permission_callback' => [$this, 'canManage'],
                'args'                => [
                    'status'   => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                    'search'   => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                    'per_page' => ['type' => 'integer', 'default' => 50],
                    'offset'   => ['type' => 'integer', 'default' => 0],
                ],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'store'],
                'permission_callback' => [$this, 'canManage'],
                'args'                => [
                    'email'         => ['type' => 'string', 'sanitize_callback' => 'sanitize_email'],
                    'phone'         => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                    'first_name'    => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                    'last_name'     => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                    'status'        => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                    'source'        => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                    'source_ref'    => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                    'custom_fields'   => ['type' => 'object'],
                    'email_verified'  => ['type' => 'boolean'],
                    'phone_verified'  => ['type' => 'boolean'],
                ],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/contacts/bulk', [
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'bulk'],
                'permission_callback' => [$this, 'canManage'],
                'args'                => [
                    'action' => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                    'ids'    => ['required' => true, 'type' => 'array'],
                ],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/contacts/import/preview', [
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'importPreview'],
                'permission_callback' => [$this, 'canManage'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/contacts/import', [
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'import'],
                'permission_callback' => [$this, 'canManage'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/contacts/export', [
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'export'],
                'permission_callback' => [$this, 'canManage'],
                'args'                => [
                    'status' => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                ],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/contacts/(?P<id>[A-Za-z0-9]+)', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'show'],
                'permission_callback' => [$this, 'canManage'],
            ],
            [
                'methods'             => 'PUT',
                'callback'            => [$this, 'update'],
                'permission_callback' => [$this, 'canManage'],
                'args'                => [
                    'email'           => ['type' => 'string', 'sanitize_callback' => 'sanitize_email'],
                    'phone'           => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                    'first_name'      => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                    'last_name'       => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                    'status'          => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                    'source'          => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                    'source_ref'      => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                    'custom_fields'   => ['type' => 'object'],
                    'email_verified'  => ['type' => 'boolean'],
                    'phone_verified'  => ['type' => 'boolean'],
                ],
            ],
            [
                'methods'             => 'DELETE',
                'callback'            => [$this, 'destroy'],
                'permission_callback' => [$this, 'canManage'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/contacts/(?P<id>[A-Za-z0-9]+)/tags', [
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'addTag'],
                'permission_callback' => [$this, 'canManage'],
                'args'                => [
                    'tag_id' => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                ],
            ],
            [
                'methods'             => 'DELETE',
                'callback'            => [$this, 'removeTag'],
                'permission_callback' => [$this, 'canManage'],
                'args'                => [
                    'tag_id' => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                ],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/contacts/(?P<id>[A-Za-z0-9]+)/activity', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'activity'],
                'permission_callback' => [$this, 'canManage'],
                'args'                => [
                    'per_page' => ['type' => 'integer', 'default' => 20],
                    'offset'   => ['type' => 'integer', 'default' => 0],
                ],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/segments/preview', [
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'segmentPreview'],
                'permission_callback' => [$this, 'canManage'],
                'args'                => [
                    'conditions' => ['type' => 'array', 'default' => []],
                ],
            ],
        ]);
    }

    public function index(\WP_REST_Request $request): \WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            $filters = [];
            if ($request->get_param('status')) {
                $filters['status'] = $request->get_param('status');
            }
            if ($request->get_param('search')) {
                $filters['search'] = $request->get_param('search');
            }

            $contacts = $this->contacts->findAll(
                $filters,
                (int) $request->get_param('per_page'),
                (int) $request->get_param('offset'),
            );

            $total = $this->contacts->count($filters);

            return $this->paginated($contacts, $total);
        });
    }

    public function store(\WP_REST_Request $request): \WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            $data = $this->extractContactData($request);

            $id = $this->contacts->create($data);
            $contact = $this->contacts->find($id);

            return $this->created($contact);
        });
    }

    public function show(\WP_REST_Request $request): \WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            $contact = $this->contacts->findOrFail($request->get_param('id'));
            $contact['tags'] = $this->contacts->getTags($contact['id']);

            // Enrich with WP user data if linked
            if (!empty($contact['wp_user_id'])) {
                $user = get_userdata((int) $contact['wp_user_id']);
                if ($user) {
                    $contact['wp_user'] = [
                        'username'   => $user->user_login,
                        'roles'      => $user->roles,
                        'registered' => $user->user_registered ?? '',
                        'edit_url'   => admin_url('user-edit.php?user_id=' . $contact['wp_user_id']),
                    ];
                }
            }

            return $this->ok($contact);
        });
    }

    public function update(\WP_REST_Request $request): \WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            $id = $request->get_param('id');
            $this->contacts->findOrFail($id);
            $this->contacts->update($id, $this->extractContactData($request));

            return $this->ok($this->contacts->find($id));
        });
    }

    public function destroy(\WP_REST_Request $request): \WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            $id = $request->get_param('id');
            if (!$this->contacts->delete($id)) {
                throw NotFoundException::entity('Contact', $id);
            }

            return $this->ok();
        });
    }

    public function addTag(\WP_REST_Request $request): \WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            $this->contacts->findOrFail($request->get_param('id'));
            $this->contacts->addTag($request->get_param('id'), $request->get_param('tag_id'));

            return $this->ok();
        });
    }

    public function removeTag(\WP_REST_Request $request): \WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            $this->contacts->removeTag($request->get_param('id'), $request->get_param('tag_id'));

            return $this->ok();
        });
    }

    public function bulk(\WP_REST_Request $request): \WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            $action = $request->get_param('action');
            $ids = $request->get_param('ids');

            if (empty($ids)) {
                throw ValidationException::field('ids', __('No contacts selected', 'wp-sms'));
            }

            $allowedActions = ['delete', 'status', 'tag', 'untag'];
            if (!in_array($action, $allowedActions, true)) {
                throw ValidationException::field('action', __('Unknown bulk action', 'wp-sms'));
            }

            $status = $request->get_param('status') ?? 'subscribed';
            if ($action === 'status' && !in_array($status, self::ALLOWED_STATUSES, true)) {
                throw ValidationException::field('status', __('Invalid status value', 'wp-sms'));
            }

            $count = match ($action) {
                'delete' => $this->contacts->bulkDelete($ids),
                'status' => $this->contacts->bulkUpdateStatus($ids, $status),
                'tag' => $this->contacts->bulkAddTag($ids, $request->get_param('tag_id') ?? ''),
                'untag' => $this->contacts->bulkRemoveTag($ids, $request->get_param('tag_id') ?? ''),
            };

            return $this->ok(['affected' => $count]);
        });
    }

    public function importPreview(\WP_REST_Request $request): \WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            $filePath = $this->getUploadedFilePath($request);
            if (!$filePath) {
                throw ValidationException::field('file', __('No file uploaded', 'wp-sms'));
            }

            return $this->ok($this->importer->previewCsv($filePath));
        });
    }

    public function import(\WP_REST_Request $request): \WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            $filePath = $this->getUploadedFilePath($request);
            if (!$filePath) {
                throw ValidationException::field('file', __('No file uploaded', 'wp-sms'));
            }

            $mapping = $request->get_param('field_mapping');
            if (is_string($mapping)) {
                $mapping = json_decode($mapping, true) ?? [];
            }

            $files = $request->get_file_params();
            $options = [
                'match_field'        => $request->get_param('match_field') ?? 'email',
                'duplicate_handling' => $request->get_param('duplicate_handling') ?? 'update',
                'source_ref'         => !empty($files['file']['name']) ? sanitize_file_name($files['file']['name']) : null,
            ];

            $result = $this->importer->importFromCsv($filePath, $mapping ?? [], $options);

            return $this->ok($result);
        });
    }

    public function export(\WP_REST_Request $request): \WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            $filters = [];
            if ($request->get_param('status')) {
                $filters['status'] = $request->get_param('status');
            }

            $uploadDir = wp_upload_dir();
            $exportDir = $uploadDir['basedir'] . '/wsms-exports';
            wp_mkdir_p($exportDir);

            $filename = 'contacts-' . gmdate('Y-m-d-His') . '-' . wp_generate_password(8, false) . '.csv';
            $filePath = $exportDir . '/' . $filename;

            $this->exporter->exportToCsv($filters, $filePath);

            return $this->ok([
                'url'      => $uploadDir['baseurl'] . '/wsms-exports/' . $filename,
                'filename' => $filename,
            ]);
        });
    }

    public function activity(\WP_REST_Request $request): \WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            $contact = $this->contacts->findOrFail($request->get_param('id'));

            $perPage = (int) $request->get_param('per_page');
            $offset = (int) $request->get_param('offset');

            $activities = $this->getContactActivities($contact, $perPage, $offset);

            return new \WP_REST_Response(['items' => $activities]);
        });
    }

    public function segmentPreview(\WP_REST_Request $request): \WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            $conditions = $request->get_param('conditions') ?? [];
            $contacts = $this->segmentEvaluator->evaluate($conditions, 10);
            $total = $this->segmentEvaluator->count($conditions);

            return $this->paginated($contacts, $total);
        });
    }

    private const CONTACT_FIELDS = [
        'email', 'phone', 'first_name', 'last_name', 'status',
        'source', 'source_ref', 'custom_fields', 'email_verified', 'phone_verified',
    ];

    /** @return array<string, mixed> */
    private function extractContactData(\WP_REST_Request $request): array
    {
        $data = [];
        foreach (self::CONTACT_FIELDS as $field) {
            $value = $request->get_param($field);
            if ($value !== null) {
                $data[$field] = $value;
            }
        }
        return $data;
    }

    private function getUploadedFilePath(\WP_REST_Request $request): ?string
    {
        $files = $request->get_file_params();
        return !empty($files['file']['tmp_name']) ? $files['file']['tmp_name'] : null;
    }

    private function getContactActivities(array $contact, int $limit, int $offset): array
    {
        global $wpdb;

        $activities = [];

        // Contact lifecycle events
        if ($offset === 0) {
            $activities[] = [
                'id'          => 'created-' . $contact['id'],
                'type'        => 'contact_created',
                'description' => 'Contact created',
                'meta'        => ['source' => $contact['source'] ?? 'unknown'],
                'created_at'  => $contact['created_at'],
            ];

            if ($contact['status'] === 'unsubscribed' && !empty($contact['opted_out_at'])) {
                $activities[] = [
                    'id'          => 'opted-out-' . $contact['id'],
                    'type'        => 'contact_opted_out',
                    'description' => 'Unsubscribed',
                    'meta'        => [],
                    'created_at'  => $contact['opted_out_at'],
                ];
            }

            if (($contact['updated_at'] ?? '') !== ($contact['created_at'] ?? '')) {
                $activities[] = [
                    'id'          => 'updated-' . $contact['id'],
                    'type'        => 'contact_updated',
                    'description' => 'Contact updated',
                    'meta'        => [],
                    'created_at'  => $contact['updated_at'],
                ];
            }
        }

        // Message logs matching this contact's email or phone
        $logTable = $wpdb->prefix . 'wsms_message_logs';
        static $logTableExists = null;
        if ($logTableExists === null) {
            $logTableExists = (bool) $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $logTable));
        }

        if ($logTableExists) {
            $conditions = [];
            $params = [];

            if (!empty($contact['email'])) {
                $conditions[] = 'recipient = %s';
                $params[] = $contact['email'];
            }
            if (!empty($contact['phone'])) {
                $conditions[] = 'recipient = %s';
                $params[] = $contact['phone'];
            }

            if (!empty($conditions)) {
                $where = implode(' OR ', $conditions);
                $params[] = $limit;
                $params[] = $offset;

                $logs = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT id, status, channel, gateway_id, body_preview, sent_at, created_at FROM {$logTable} WHERE ({$where}) ORDER BY created_at DESC LIMIT %d OFFSET %d",
                        ...$params,
                    ),
                    ARRAY_A,
                ) ?: [];

                foreach ($logs as $log) {
                    $activities[] = [
                        'id'          => 'msg-' . $log['id'],
                        'type'        => 'message_' . ($log['status'] ?? 'sent'),
                        'description' => 'Message ' . ($log['status'] ?? 'sent') . ' via ' . ($log['channel'] ?? 'unknown'),
                        'meta'        => [
                            'channel'      => $log['channel'] ?? '',
                            'gateway_id'   => $log['gateway_id'] ?? '',
                            'body_preview' => mb_substr($log['body_preview'] ?? '', 0, 100),
                        ],
                        'created_at' => $log['sent_at'] ?? $log['created_at'],
                    ];
                }
            }
        }

        // Sort by date descending
        usort($activities, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));

        return $activities;
    }
}
