<?php

namespace WSms\Rest;

use WSms\Contact\ContactImporter;
use WSms\Contact\ContactExporter;
use WSms\Contact\Contracts\ContactRepositoryInterface;
use WSms\Contact\Contracts\SegmentEvaluatorInterface;

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
                    'custom_fields' => ['type' => 'object'],
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
                    'email'         => ['type' => 'string', 'sanitize_callback' => 'sanitize_email'],
                    'phone'         => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                    'first_name'    => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                    'last_name'     => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                    'status'        => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                    'source'        => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                    'custom_fields' => ['type' => 'object'],
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

        return new \WP_REST_Response([
            'items' => $contacts,
            'total' => $total,
        ]);
    }

    public function store(\WP_REST_Request $request): \WP_REST_Response
    {
        $data = [];
        foreach (['email', 'phone', 'first_name', 'last_name', 'status', 'source', 'custom_fields'] as $field) {
            $value = $request->get_param($field);
            if ($value !== null) {
                $data[$field] = $value;
            }
        }

        $id = $this->contacts->create($data);
        $contact = $this->contacts->find($id);

        return new \WP_REST_Response([
            'success' => true,
            'data'    => $contact,
        ], 201);
    }

    public function show(\WP_REST_Request $request): \WP_REST_Response
    {
        $contact = $this->contacts->find($request->get_param('id'));

        if (!$contact) {
            return new \WP_REST_Response([
                'success' => false,
                'error'   => 'not_found',
                'message' => __('Contact not found', 'wp-sms'),
            ], 404);
        }

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

        return new \WP_REST_Response([
            'success' => true,
            'data'    => $contact,
        ]);
    }

    public function update(\WP_REST_Request $request): \WP_REST_Response
    {
        $id = $request->get_param('id');
        $contact = $this->contacts->find($id);

        if (!$contact) {
            return new \WP_REST_Response([
                'success' => false,
                'error'   => 'not_found',
                'message' => __('Contact not found', 'wp-sms'),
            ], 404);
        }

        $data = [];
        foreach (['email', 'phone', 'first_name', 'last_name', 'status', 'source', 'custom_fields'] as $field) {
            $value = $request->get_param($field);
            if ($value !== null) {
                $data[$field] = $value;
            }
        }

        $this->contacts->update($id, $data);

        return new \WP_REST_Response([
            'success' => true,
            'data'    => $this->contacts->find($id),
        ]);
    }

    public function destroy(\WP_REST_Request $request): \WP_REST_Response
    {
        $id = $request->get_param('id');
        $contact = $this->contacts->find($id);

        if (!$contact) {
            return new \WP_REST_Response([
                'success' => false,
                'error'   => 'not_found',
                'message' => __('Contact not found', 'wp-sms'),
            ], 404);
        }

        $this->contacts->delete($id);

        return new \WP_REST_Response(['success' => true]);
    }

    public function addTag(\WP_REST_Request $request): \WP_REST_Response
    {
        $this->contacts->addTag($request->get_param('id'), $request->get_param('tag_id'));

        return new \WP_REST_Response(['success' => true]);
    }

    public function removeTag(\WP_REST_Request $request): \WP_REST_Response
    {
        $this->contacts->removeTag($request->get_param('id'), $request->get_param('tag_id'));

        return new \WP_REST_Response(['success' => true]);
    }

    public function bulk(\WP_REST_Request $request): \WP_REST_Response
    {
        $action = $request->get_param('action');
        $ids = $request->get_param('ids');

        if (empty($ids)) {
            return new \WP_REST_Response([
                'success' => false,
                'error'   => 'invalid_request',
                'message' => __('No contacts selected', 'wp-sms'),
            ], 400);
        }

        $allowedActions = ['delete', 'status', 'tag', 'untag'];
        if (!in_array($action, $allowedActions, true)) {
            return new \WP_REST_Response([
                'success' => false,
                'error'   => 'invalid_action',
                'message' => __('Unknown bulk action', 'wp-sms'),
            ], 400);
        }

        $status = $request->get_param('status') ?? 'subscribed';
        if ($action === 'status' && !in_array($status, self::ALLOWED_STATUSES, true)) {
            return new \WP_REST_Response([
                'success' => false,
                'error'   => 'invalid_status',
                'message' => __('Invalid status value', 'wp-sms'),
            ], 400);
        }

        $count = match ($action) {
            'delete' => $this->contacts->bulkDelete($ids),
            'status' => $this->contacts->bulkUpdateStatus($ids, $status),
            'tag' => $this->bulkAddTag($ids, $request->get_param('tag_id') ?? ''),
            'untag' => $this->bulkRemoveTag($ids, $request->get_param('tag_id') ?? ''),
        };

        return new \WP_REST_Response(['success' => true, 'affected' => $count]);
    }

    public function importPreview(\WP_REST_Request $request): \WP_REST_Response
    {
        $filePath = $this->getUploadedFilePath($request);
        if (!$filePath) {
            return self::noFileResponse();
        }

        $preview = $this->importer->previewCsv($filePath);

        return new \WP_REST_Response(['success' => true, 'data' => $preview]);
    }

    public function import(\WP_REST_Request $request): \WP_REST_Response
    {
        $filePath = $this->getUploadedFilePath($request);
        if (!$filePath) {
            return self::noFileResponse();
        }

        $mapping = $request->get_param('field_mapping');
        if (is_string($mapping)) {
            $mapping = json_decode($mapping, true) ?? [];
        }

        $options = [
            'match_field'       => $request->get_param('match_field') ?? 'email',
            'duplicate_handling' => $request->get_param('duplicate_handling') ?? 'update',
        ];

        $result = $this->importer->importFromCsv($filePath, $mapping ?? [], $options);

        return new \WP_REST_Response(['success' => true, 'data' => $result]);
    }

    public function export(\WP_REST_Request $request): \WP_REST_Response
    {
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

        return new \WP_REST_Response([
            'success'  => true,
            'data'     => [
                'url'      => $uploadDir['baseurl'] . '/wsms-exports/' . $filename,
                'filename' => $filename,
            ],
        ]);
    }

    public function activity(\WP_REST_Request $request): \WP_REST_Response
    {
        $contact = $this->contacts->find($request->get_param('id'));

        if (!$contact) {
            return new \WP_REST_Response([
                'success' => false,
                'error'   => 'not_found',
                'message' => __('Contact not found', 'wp-sms'),
            ], 404);
        }

        $perPage = (int) $request->get_param('per_page');
        $offset = (int) $request->get_param('offset');

        $activities = $this->getContactActivities($contact, $perPage, $offset);

        return new \WP_REST_Response(['items' => $activities]);
    }

    public function segmentPreview(\WP_REST_Request $request): \WP_REST_Response
    {
        $conditions = $request->get_param('conditions') ?? [];
        $contacts = $this->segmentEvaluator->evaluate($conditions, 10);
        $total = $this->segmentEvaluator->count($conditions);

        return new \WP_REST_Response([
            'items' => $contacts,
            'total' => $total,
        ]);
    }

    private function getUploadedFilePath(\WP_REST_Request $request): ?string
    {
        $files = $request->get_file_params();
        return !empty($files['file']['tmp_name']) ? $files['file']['tmp_name'] : null;
    }

    private static function noFileResponse(): \WP_REST_Response
    {
        return new \WP_REST_Response([
            'success' => false,
            'error'   => 'no_file',
            'message' => __('No file uploaded', 'wp-sms'),
        ], 400);
    }

    private function bulkAddTag(array $contactIds, string $tagId): int
    {
        if (empty($tagId) || empty($contactIds)) {
            return 0;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'wsms_contact_tag';
        $now = current_time('mysql');
        $values = [];
        $params = [];

        foreach ($contactIds as $contactId) {
            $values[] = '(%s, %s, %s)';
            $params[] = $contactId;
            $params[] = $tagId;
            $params[] = $now;
        }

        $sql = "INSERT IGNORE INTO {$table} (contact_id, tag_id, created_at) VALUES " . implode(', ', $values);

        return (int) $wpdb->query($wpdb->prepare($sql, ...$params));
    }

    private function bulkRemoveTag(array $contactIds, string $tagId): int
    {
        if (empty($tagId) || empty($contactIds)) {
            return 0;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'wsms_contact_tag';
        $placeholders = implode(',', array_fill(0, count($contactIds), '%s'));

        return (int) $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table} WHERE tag_id = %s AND contact_id IN ({$placeholders})",
                $tagId,
                ...$contactIds,
            ),
        );
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
