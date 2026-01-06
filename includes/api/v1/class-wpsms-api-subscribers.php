<?php

namespace WP_SMS\Api\V1;

use WP_REST_Request;
use WP_REST_Server;
use WP_SMS\Newsletter;
use WP_SMS\RestApi;
use WP_SMS\Components\NumberParser;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Subscribers REST API
 *
 * Provides CRUD operations for managing SMS subscribers.
 *
 * @package WP_SMS\Api\V1
 */
class SubscribersApi extends RestApi
{
    public function __construct()
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
        parent::__construct();
    }

    /**
     * Register routes
     */
    public function registerRoutes()
    {
        // GET /subscribers - List all subscribers
        register_rest_route($this->namespace . '/v1', '/subscribers', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'getItems'],
                'permission_callback' => [$this, 'checkPermission'],
                'args'                => $this->getCollectionParams(),
            ],
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'createItem'],
                'permission_callback' => [$this, 'checkPermission'],
                'args'                => $this->getCreateParams(),
            ],
        ]);

        // GET/PUT/DELETE /subscribers/{id}
        register_rest_route($this->namespace . '/v1', '/subscribers/(?P<id>\d+)', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'getItem'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
            [
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => [$this, 'updateItem'],
                'permission_callback' => [$this, 'checkPermission'],
                'args'                => $this->getUpdateParams(),
            ],
            [
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => [$this, 'deleteItem'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);

        // POST /subscribers/bulk - Bulk actions
        register_rest_route($this->namespace . '/v1', '/subscribers/bulk', [
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'bulkAction'],
                'permission_callback' => [$this, 'checkPermission'],
                'args'                => [
                    'action' => [
                        'required' => true,
                        'type'     => 'string',
                        'enum'     => ['delete', 'activate', 'deactivate', 'move'],
                    ],
                    'ids' => [
                        'required' => true,
                        'type'     => 'array',
                    ],
                    'group_id' => [
                        'required' => false,
                        'type'     => 'integer',
                    ],
                ],
            ],
        ]);

        // GET /subscribers/export - Export to CSV
        register_rest_route($this->namespace . '/v1', '/subscribers/export', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'exportSubscribers'],
                'permission_callback' => [$this, 'checkPermission'],
                'args'                => [
                    'group_id' => [
                        'required' => false,
                        'type'     => 'integer',
                    ],
                    'status' => [
                        'required' => false,
                        'type'     => 'string',
                        'enum'     => ['active', 'inactive', 'all'],
                    ],
                ],
            ],
        ]);

        // POST /subscribers/import - Import from CSV
        register_rest_route($this->namespace . '/v1', '/subscribers/import', [
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'importSubscribers'],
                'permission_callback' => [$this, 'checkPermission'],
                'args'                => [
                    'group_id' => [
                        'required' => false,
                        'type'     => 'integer',
                    ],
                    'skip_duplicates' => [
                        'required' => false,
                        'type'     => 'string',
                        'default'  => '1',
                    ],
                ],
            ],
        ]);
    }

    /**
     * Check permission
     */
    public function checkPermission()
    {
        return current_user_can('wpsms_subscribers');
    }

    /**
     * Get collection parameters
     */
    protected function getCollectionParams()
    {
        return [
            'page' => [
                'default'           => 1,
                'type'              => 'integer',
                'sanitize_callback' => 'absint',
            ],
            'per_page' => [
                'default'           => 20,
                'type'              => 'integer',
                'sanitize_callback' => 'absint',
                'maximum'           => 100,
            ],
            'search' => [
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'group_id' => [
                'type'              => 'integer',
                'sanitize_callback' => 'absint',
            ],
            'status' => [
                'type' => 'string',
                'enum' => ['active', 'inactive', 'all'],
            ],
            'country_code' => [
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'orderby' => [
                'default' => 'date',
                'type'    => 'string',
                'enum'    => ['date', 'name', 'mobile', 'status'],
            ],
            'order' => [
                'default' => 'desc',
                'type'    => 'string',
                'enum'    => ['asc', 'desc'],
            ],
        ];
    }

    /**
     * Get create parameters
     */
    protected function getCreateParams()
    {
        return [
            'name' => [
                'required'          => true,
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'mobile' => [
                'required'          => true,
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'group_id' => [
                'required' => false,
                'type'     => ['integer', 'array'],
            ],
            'status' => [
                'required' => false,
                'type'     => 'string',
                'default'  => '1',
                'enum'     => ['0', '1'],
            ],
            'custom_fields' => [
                'required' => false,
                'type'     => 'object',
            ],
        ];
    }

    /**
     * Get update parameters
     */
    protected function getUpdateParams()
    {
        return [
            'name' => [
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'mobile' => [
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'group_id' => [
                'type' => ['integer', 'array'],
            ],
            'status' => [
                'type' => 'string',
                'enum' => ['0', '1'],
            ],
            'custom_fields' => [
                'type' => 'object',
            ],
        ];
    }

    /**
     * Get subscribers list
     */
    public function getItems(WP_REST_Request $request)
    {
        $page         = $request->get_param('page');
        $per_page     = min($request->get_param('per_page'), 100);
        $search       = $request->get_param('search');
        $group_id     = $request->get_param('group_id');
        $status       = $request->get_param('status');
        $country_code = $request->get_param('country_code');
        $orderby      = $request->get_param('orderby') ?: 'date';
        $order        = strtoupper($request->get_param('order') ?: 'DESC');

        $offset = ($page - 1) * $per_page;

        // Build WHERE clause
        $where = ['1=1'];
        $params = [];

        if ($search) {
            $search_like = '%' . $this->db->esc_like($search) . '%';
            $where[] = "(s.name LIKE %s OR s.mobile LIKE %s)";
            $params[] = $search_like;
            $params[] = $search_like;
        }

        if ($group_id) {
            $where[] = "group_ID = %d";
            $params[] = $group_id;
        }

        if ($status && $status !== 'all') {
            $status_value = $status === 'active' ? '1' : '0';
            $where[] = "status = %s";
            $params[] = $status_value;
        }

        // Filter by country code (match dial code prefix in mobile number)
        if ($country_code && $country_code !== 'all') {
            $dialCodes = wp_sms_countries()->getAllDialCodesByCode();
            if (isset($dialCodes[$country_code]) && is_array($dialCodes[$country_code])) {
                $dialCodeConditions = [];
                foreach ($dialCodes[$country_code] as $dialCode) {
                    $dialCodeConditions[] = "s.mobile LIKE %s";
                    $params[] = $this->db->esc_like($dialCode) . '%';
                }
                if (!empty($dialCodeConditions)) {
                    $where[] = '(' . implode(' OR ', $dialCodeConditions) . ')';
                }
            }
        }

        $where_sql = implode(' AND ', $where);

        // Map orderby to column
        $orderby_map = [
            'date'   => 'date',
            'name'   => 'name',
            'mobile' => 'mobile',
            'status' => 'status',
        ];
        $orderby_col = $orderby_map[$orderby] ?? 'date';

        // Get total count (using alias 's' to match WHERE clause)
        $count_sql = "SELECT COUNT(*) FROM {$this->tb_prefix}sms_subscribes s WHERE {$where_sql}";
        if (!empty($params)) {
            $count_sql = $this->db->prepare($count_sql, $params);
        }
        $total = (int) $this->db->get_var($count_sql);

        // Get items
        $query = "SELECT s.*, g.name as group_name
                  FROM {$this->tb_prefix}sms_subscribes s
                  LEFT JOIN {$this->tb_prefix}sms_subscribes_group g ON s.group_ID = g.ID
                  WHERE {$where_sql}
                  ORDER BY s.{$orderby_col} {$order}
                  LIMIT %d OFFSET %d";

        $query_params = array_merge($params, [$per_page, $offset]);
        $items = $this->db->get_results(
            $this->db->prepare($query, $query_params),
            ARRAY_A
        );

        // Format items
        $formatted = array_map(function ($item) {
            return [
                'id'            => (int) $item['ID'],
                'name'          => $item['name'],
                'mobile'        => $item['mobile'],
                'group_id'      => (int) $item['group_ID'],
                'group_name'    => $item['group_name'] ?: '',
                'status'        => $item['status'],
                'date'          => $item['date'],
                'custom_fields' => maybe_unserialize($item['custom_fields']) ?: [],
            ];
        }, $items ?: []);

        // Get stats (total, active, inactive counts - unfiltered)
        $stats_total = (int) $this->db->get_var(
            "SELECT COUNT(*) FROM {$this->tb_prefix}sms_subscribes"
        );
        $stats_active = (int) $this->db->get_var(
            "SELECT COUNT(*) FROM {$this->tb_prefix}sms_subscribes WHERE status = '1'"
        );
        $stats_inactive = $stats_total - $stats_active;

        return self::response(__('Subscribers retrieved successfully', 'wp-sms'), 200, [
            'items'      => $formatted,
            'pagination' => [
                'total'       => $total,
                'total_pages' => ceil($total / $per_page),
                'current_page' => $page,
                'per_page'    => $per_page,
            ],
            'stats'      => [
                'total'    => $stats_total,
                'active'   => $stats_active,
                'inactive' => $stats_inactive,
            ],
        ]);
    }

    /**
     * Get single subscriber
     */
    public function getItem(WP_REST_Request $request)
    {
        $id = (int) $request->get_param('id');

        $subscriber = Newsletter::getSubscriber($id);

        if (!$subscriber) {
            return self::response(__('Subscriber not found', 'wp-sms'), 404);
        }

        $group = Newsletter::getGroup($subscriber->group_ID);

        return self::response(__('Subscriber retrieved successfully', 'wp-sms'), 200, [
            'id'            => (int) $subscriber->ID,
            'name'          => $subscriber->name,
            'mobile'        => $subscriber->mobile,
            'group_id'      => (int) $subscriber->group_ID,
            'group_name'    => $group ? $group->name : '',
            'status'        => $subscriber->status,
            'date'          => $subscriber->date,
            'custom_fields' => maybe_unserialize($subscriber->custom_fields) ?: [],
        ]);
    }

    /**
     * Create subscriber
     */
    public function createItem(WP_REST_Request $request)
    {
        $name          = $request->get_param('name');
        $mobile        = $request->get_param('mobile');
        $group_id      = $request->get_param('group_id') ?: 0;
        $status        = $request->get_param('status') ?: '1';
        $custom_fields = $request->get_param('custom_fields') ?: [];

        // Parse phone number
        $numberParser = new NumberParser($mobile);
        $mobile = $numberParser->getValidNumber();

        if (is_wp_error($mobile)) {
            return self::response($mobile->get_error_message(), 400);
        }

        // Check if subscriber exists
        $existing = Newsletter::getSubscriberByMobile($mobile);
        if ($existing) {
            return self::response(__('A subscriber with this phone number already exists', 'wp-sms'), 400);
        }

        // Add subscriber
        $result = Newsletter::addSubscriber(
            $name,
            $mobile,
            is_array($group_id) ? $group_id[0] : $group_id,
            $status,
            '',
            $custom_fields
        );

        if (!$result) {
            return self::response(__('Failed to create subscriber', 'wp-sms'), 500);
        }

        return self::response(__('Subscriber created successfully', 'wp-sms'), 201, [
            'id' => $result,
        ]);
    }

    /**
     * Update subscriber
     */
    public function updateItem(WP_REST_Request $request)
    {
        $id = (int) $request->get_param('id');

        $subscriber = Newsletter::getSubscriber($id);
        if (!$subscriber) {
            return self::response(__('Subscriber not found', 'wp-sms'), 404);
        }

        $name     = $request->get_param('name') ?: $subscriber->name;
        $mobile   = $request->get_param('mobile') ?: $subscriber->mobile;
        $group_id = $request->get_param('group_id');
        $status   = $request->get_param('status');

        if ($group_id === null) {
            $group_id = $subscriber->group_ID;
        }
        if ($status === null) {
            $status = $subscriber->status;
        }

        // Parse phone number if changed
        if ($mobile !== $subscriber->mobile) {
            $numberParser = new NumberParser($mobile);
            $mobile = $numberParser->getValidNumber();

            if (is_wp_error($mobile)) {
                return self::response($mobile->get_error_message(), 400);
            }

            // Check if another subscriber already has this phone number
            $existing = Newsletter::getSubscriberByMobile($mobile);
            if ($existing && (int) $existing->ID !== $id) {
                return self::response(__('A subscriber with this phone number already exists', 'wp-sms'), 400);
            }
        }

        // Update subscriber
        $result = Newsletter::updateSubscriber(
            $id,
            $name,
            $mobile,
            is_array($group_id) ? $group_id[0] : $group_id,
            $status
        );

        // Update custom fields if provided
        $custom_fields = $request->get_param('custom_fields');
        if ($custom_fields !== null) {
            $this->db->update(
                $this->tb_prefix . 'sms_subscribes',
                ['custom_fields' => maybe_serialize($custom_fields)],
                ['ID' => $id],
                ['%s'],
                ['%d']
            );
        }

        return self::response(__('Subscriber updated successfully', 'wp-sms'), 200);
    }

    /**
     * Delete subscriber
     */
    public function deleteItem(WP_REST_Request $request)
    {
        $id = (int) $request->get_param('id');

        $subscriber = Newsletter::getSubscriber($id);
        if (!$subscriber) {
            return self::response(__('Subscriber not found', 'wp-sms'), 404);
        }

        $result = $this->db->delete(
            $this->tb_prefix . 'sms_subscribes',
            ['ID' => $id],
            ['%d']
        );

        if (!$result) {
            return self::response(__('Failed to delete subscriber', 'wp-sms'), 500);
        }

        return self::response(__('Subscriber deleted successfully', 'wp-sms'), 200);
    }

    /**
     * Bulk actions
     */
    public function bulkAction(WP_REST_Request $request)
    {
        $action   = $request->get_param('action');
        $ids      = $request->get_param('ids');
        $group_id = $request->get_param('group_id');

        if (empty($ids) || !is_array($ids)) {
            return self::response(__('No items selected', 'wp-sms'), 400);
        }

        $ids = array_map('absint', $ids);
        $affected = 0;

        switch ($action) {
            case 'delete':
                foreach ($ids as $id) {
                    $result = $this->db->delete(
                        $this->tb_prefix . 'sms_subscribes',
                        ['ID' => $id],
                        ['%d']
                    );
                    if ($result) {
                        $affected++;
                    }
                }
                break;

            case 'activate':
                $affected = $this->db->query(
                    $this->db->prepare(
                        "UPDATE {$this->tb_prefix}sms_subscribes SET status = '1' WHERE ID IN (" . implode(',', array_fill(0, count($ids), '%d')) . ")",
                        $ids
                    )
                );
                break;

            case 'deactivate':
                $affected = $this->db->query(
                    $this->db->prepare(
                        "UPDATE {$this->tb_prefix}sms_subscribes SET status = '0' WHERE ID IN (" . implode(',', array_fill(0, count($ids), '%d')) . ")",
                        $ids
                    )
                );
                break;

            case 'move':
                if (!$group_id) {
                    return self::response(__('Group ID is required for move action', 'wp-sms'), 400);
                }
                $params = array_merge([$group_id], $ids);
                $affected = $this->db->query(
                    $this->db->prepare(
                        "UPDATE {$this->tb_prefix}sms_subscribes SET group_ID = %d WHERE ID IN (" . implode(',', array_fill(0, count($ids), '%d')) . ")",
                        $params
                    )
                );
                break;
        }

        return self::response(
            sprintf(__('%d subscriber(s) updated successfully', 'wp-sms'), $affected),
            200,
            ['affected' => $affected]
        );
    }

    /**
     * Export subscribers
     */
    public function exportSubscribers(WP_REST_Request $request)
    {
        $group_id = $request->get_param('group_id');
        $status   = $request->get_param('status');

        $where = ['1=1'];
        $params = [];

        if ($group_id) {
            $where[] = "group_ID = %d";
            $params[] = $group_id;
        }

        if ($status && $status !== 'all') {
            $status_value = $status === 'active' ? '1' : '0';
            $where[] = "status = %s";
            $params[] = $status_value;
        }

        $where_sql = implode(' AND ', $where);

        $query = "SELECT s.*, g.name as group_name
                  FROM {$this->tb_prefix}sms_subscribes s
                  LEFT JOIN {$this->tb_prefix}sms_subscribes_group g ON s.group_ID = g.ID
                  WHERE {$where_sql}
                  ORDER BY s.date DESC";

        if (!empty($params)) {
            $query = $this->db->prepare($query, $params);
        }

        $subscribers = $this->db->get_results($query, ARRAY_A);

        // Format for CSV export
        $csv_data = [];
        $csv_data[] = ['Name', 'Mobile', 'Group', 'Status', 'Date']; // Header

        foreach ($subscribers as $sub) {
            $csv_data[] = [
                $sub['name'],
                $sub['mobile'],
                $sub['group_name'] ?: '',
                $sub['status'] === '1' ? 'Active' : 'Inactive',
                $sub['date'],
            ];
        }

        return self::response(__('Export data ready', 'wp-sms'), 200, [
            'csv_data' => $csv_data,
            'count'    => count($subscribers),
        ]);
    }

    /**
     * Import subscribers from CSV
     */
    public function importSubscribers(WP_REST_Request $request)
    {
        $files = $request->get_file_params();

        if (empty($files['file'])) {
            return self::response(__('No file uploaded', 'wp-sms'), 400);
        }

        $file = $files['file'];

        // Validate file type
        $allowed_types = ['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        // Also check extension as fallback
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($mime_type, $allowed_types) && $extension !== 'csv') {
            return self::response(__('Invalid file type. Please upload a CSV file.', 'wp-sms'), 400);
        }

        // Parse CSV
        $handle = fopen($file['tmp_name'], 'r');
        if ($handle === false) {
            return self::response(__('Failed to read uploaded file', 'wp-sms'), 500);
        }

        $group_id = $request->get_param('group_id') ?: 0;
        $skip_duplicates = $request->get_param('skip_duplicates') === '1';

        $imported = 0;
        $skipped = 0;
        $errors = [];
        $row_num = 0;
        $header = null;

        while (($row = fgetcsv($handle)) !== false) {
            $row_num++;

            // First row is header
            if ($row_num === 1) {
                $header = array_map('strtolower', array_map('trim', $row));
                continue;
            }

            // Skip empty rows
            if (empty(array_filter($row))) {
                continue;
            }

            // Map columns by header or position
            $name_index = array_search('name', $header);
            $mobile_index = array_search('mobile', $header);

            // Fallback to position if headers not found
            if ($mobile_index === false) {
                $mobile_index = 1; // Second column
            }
            if ($name_index === false) {
                $name_index = 0; // First column
            }

            $name = isset($row[$name_index]) ? sanitize_text_field(trim($row[$name_index])) : '';
            $mobile = isset($row[$mobile_index]) ? sanitize_text_field(trim($row[$mobile_index])) : '';

            if (empty($mobile)) {
                $errors[] = sprintf(__('Row %d: Mobile number is required', 'wp-sms'), $row_num);
                $skipped++;
                continue;
            }

            // Parse phone number
            $numberParser = new NumberParser($mobile);
            $parsed_mobile = $numberParser->getValidNumber();

            if (is_wp_error($parsed_mobile)) {
                $errors[] = sprintf(__('Row %d: %s', 'wp-sms'), $row_num, $parsed_mobile->get_error_message());
                $skipped++;
                continue;
            }

            // Check for duplicates
            $existing = Newsletter::getSubscriberByMobile($parsed_mobile);
            if ($existing) {
                if ($skip_duplicates) {
                    $skipped++;
                    continue;
                } else {
                    $errors[] = sprintf(__('Row %d: Subscriber already exists', 'wp-sms'), $row_num);
                    $skipped++;
                    continue;
                }
            }

            // Add subscriber
            $result = Newsletter::addSubscriber($name, $parsed_mobile, $group_id, '1');

            if ($result) {
                $imported++;
            } else {
                $errors[] = sprintf(__('Row %d: Failed to add subscriber', 'wp-sms'), $row_num);
                $skipped++;
            }
        }

        fclose($handle);

        return self::response(
            sprintf(__('Import completed: %d imported, %d skipped', 'wp-sms'), $imported, $skipped),
            200,
            [
                'imported' => $imported,
                'skipped'  => $skipped,
                'errors'   => array_slice($errors, 0, 10), // Limit errors to first 10
            ]
        );
    }
}

new SubscribersApi();
