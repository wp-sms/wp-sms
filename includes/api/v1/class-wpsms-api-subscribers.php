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
        $page     = $request->get_param('page');
        $per_page = min($request->get_param('per_page'), 100);
        $search   = $request->get_param('search');
        $group_id = $request->get_param('group_id');
        $status   = $request->get_param('status');
        $orderby  = $request->get_param('orderby') ?: 'date';
        $order    = strtoupper($request->get_param('order') ?: 'DESC');

        $offset = ($page - 1) * $per_page;

        // Build WHERE clause
        $where = ['1=1'];
        $params = [];

        if ($search) {
            $search_like = '%' . $this->db->esc_like($search) . '%';
            $where[] = "(name LIKE %s OR mobile LIKE %s)";
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

        $where_sql = implode(' AND ', $where);

        // Map orderby to column
        $orderby_map = [
            'date'   => 'date',
            'name'   => 'name',
            'mobile' => 'mobile',
            'status' => 'status',
        ];
        $orderby_col = $orderby_map[$orderby] ?? 'date';

        // Get total count
        $count_sql = "SELECT COUNT(*) FROM {$this->tb_prefix}sms_subscribes WHERE {$where_sql}";
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

        return self::response(__('Subscribers retrieved successfully', 'wp-sms'), 200, [
            'items'      => $formatted,
            'pagination' => [
                'total'       => $total,
                'total_pages' => ceil($total / $per_page),
                'current_page' => $page,
                'per_page'    => $per_page,
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

        if (!$mobile) {
            return self::response(__('Invalid phone number', 'wp-sms'), 400);
        }

        // Check if subscriber exists
        $existing = Newsletter::getSubscriberByNumber($mobile);
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

            if (!$mobile) {
                return self::response(__('Invalid phone number', 'wp-sms'), 400);
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
}

new SubscribersApi();
