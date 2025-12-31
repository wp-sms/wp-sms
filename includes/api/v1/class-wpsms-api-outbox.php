<?php

namespace WP_SMS\Api\V1;

use WP_REST_Request;
use WP_REST_Server;
use WP_SMS\RestApi;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Outbox REST API
 *
 * Provides endpoints for managing sent SMS messages.
 *
 * @package WP_SMS\Api\V1
 */
class OutboxApi extends RestApi
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
        // GET /outbox - List all messages
        register_rest_route($this->namespace . '/v1', '/outbox', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'getItems'],
                'permission_callback' => [$this, 'checkPermission'],
                'args'                => $this->getCollectionParams(),
            ],
        ]);

        // GET/DELETE /outbox/{id}
        register_rest_route($this->namespace . '/v1', '/outbox/(?P<id>\d+)', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'getItem'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
            [
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => [$this, 'deleteItem'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);

        // POST /outbox/{id}/resend - Resend message
        register_rest_route($this->namespace . '/v1', '/outbox/(?P<id>\d+)/resend', [
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'resendItem'],
                'permission_callback' => [$this, 'checkSendPermission'],
            ],
        ]);

        // POST /outbox/bulk - Bulk actions
        register_rest_route($this->namespace . '/v1', '/outbox/bulk', [
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'bulkAction'],
                'permission_callback' => [$this, 'checkPermission'],
                'args'                => [
                    'action' => [
                        'required' => true,
                        'type'     => 'string',
                        'enum'     => ['delete', 'resend'],
                    ],
                    'ids' => [
                        'required' => true,
                        'type'     => 'array',
                    ],
                ],
            ],
        ]);
    }

    /**
     * Check outbox permission
     */
    public function checkPermission()
    {
        return current_user_can('wpsms_outbox');
    }

    /**
     * Check send SMS permission
     */
    public function checkSendPermission()
    {
        return current_user_can('wpsms_sendsms');
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
            'status' => [
                'type' => 'string',
                'enum' => ['success', 'failed', 'all'],
            ],
            'date_from' => [
                'type'   => 'string',
                'format' => 'date',
            ],
            'date_to' => [
                'type'   => 'string',
                'format' => 'date',
            ],
            'orderby' => [
                'default' => 'date',
                'type'    => 'string',
                'enum'    => ['date', 'sender', 'recipient', 'status'],
            ],
            'order' => [
                'default' => 'desc',
                'type'    => 'string',
                'enum'    => ['asc', 'desc'],
            ],
        ];
    }

    /**
     * Get outbox messages
     */
    public function getItems(WP_REST_Request $request)
    {
        $page      = $request->get_param('page');
        $per_page  = min($request->get_param('per_page'), 100);
        $search    = $request->get_param('search');
        $status    = $request->get_param('status');
        $date_from = $request->get_param('date_from');
        $date_to   = $request->get_param('date_to');
        $orderby   = $request->get_param('orderby') ?: 'date';
        $order     = strtoupper($request->get_param('order') ?: 'DESC');

        $offset = ($page - 1) * $per_page;

        // Build WHERE clause
        $where = ['1=1'];
        $params = [];

        if ($search) {
            $search_like = '%' . $this->db->esc_like($search) . '%';
            $where[] = "(message LIKE %s OR recipient LIKE %s OR sender LIKE %s)";
            $params[] = $search_like;
            $params[] = $search_like;
            $params[] = $search_like;
        }

        if ($status && $status !== 'all') {
            $where[] = "status = %s";
            $params[] = $status;
        }

        if ($date_from) {
            $where[] = "DATE(date) >= %s";
            $params[] = $date_from;
        }

        if ($date_to) {
            $where[] = "DATE(date) <= %s";
            $params[] = $date_to;
        }

        $where_sql = implode(' AND ', $where);

        // Map orderby to column
        $orderby_map = [
            'date'      => 'date',
            'sender'    => 'sender',
            'recipient' => 'recipient',
            'status'    => 'status',
        ];
        $orderby_col = $orderby_map[$orderby] ?? 'date';

        // Get total count
        $count_sql = "SELECT COUNT(*) FROM {$this->tb_prefix}sms_send WHERE {$where_sql}";
        if (!empty($params)) {
            $count_sql = $this->db->prepare($count_sql, $params);
        }
        $total = (int) $this->db->get_var($count_sql);

        // Get stats
        $stats = $this->getStats($where_sql, $params);

        // Get items
        $query = "SELECT * FROM {$this->tb_prefix}sms_send
                  WHERE {$where_sql}
                  ORDER BY {$orderby_col} {$order}
                  LIMIT %d OFFSET %d";

        $query_params = array_merge($params, [$per_page, $offset]);
        $items = $this->db->get_results(
            $this->db->prepare($query, $query_params),
            ARRAY_A
        );

        // Format items
        $formatted = array_map(function ($item) {
            $recipients = explode(',', $item['recipient']);
            return [
                'id'              => (int) $item['ID'],
                'date'            => $item['date'],
                'sender'          => $item['sender'],
                'recipient'       => $item['recipient'],
                'recipient_count' => count($recipients),
                'message'         => $item['message'],
                'status'          => $item['status'],
                'response'        => $item['response'],
                'media'           => maybe_unserialize($item['media']) ?: [],
            ];
        }, $items ?: []);

        return self::response(__('Outbox retrieved successfully', 'wp-sms'), 200, [
            'items'      => $formatted,
            'pagination' => [
                'total'        => $total,
                'total_pages'  => ceil($total / $per_page),
                'current_page' => $page,
                'per_page'     => $per_page,
            ],
            'stats' => $stats,
        ]);
    }

    /**
     * Get outbox statistics
     */
    private function getStats($where_sql = '1=1', $params = [])
    {
        // Total messages
        $total_sql = "SELECT COUNT(*) FROM {$this->tb_prefix}sms_send WHERE {$where_sql}";
        if (!empty($params)) {
            $total_sql = $this->db->prepare($total_sql, $params);
        }
        $total = (int) $this->db->get_var($total_sql);

        // Success count
        $success_where = $where_sql . " AND status = 'success'";
        $success_sql = "SELECT COUNT(*) FROM {$this->tb_prefix}sms_send WHERE {$success_where}";
        if (!empty($params)) {
            $success_sql = $this->db->prepare($success_sql, $params);
        }
        $success = (int) $this->db->get_var($success_sql);

        // Failed count
        $failed_where = $where_sql . " AND status = 'failed'";
        $failed_sql = "SELECT COUNT(*) FROM {$this->tb_prefix}sms_send WHERE {$failed_where}";
        if (!empty($params)) {
            $failed_sql = $this->db->prepare($failed_sql, $params);
        }
        $failed = (int) $this->db->get_var($failed_sql);

        return [
            'total'   => $total,
            'success' => $success,
            'failed'  => $failed,
        ];
    }

    /**
     * Get single message
     */
    public function getItem(WP_REST_Request $request)
    {
        $id = (int) $request->get_param('id');

        $item = $this->db->get_row(
            $this->db->prepare(
                "SELECT * FROM {$this->tb_prefix}sms_send WHERE ID = %d",
                $id
            ),
            ARRAY_A
        );

        if (!$item) {
            return self::response(__('Message not found', 'wp-sms'), 404);
        }

        $recipients = explode(',', $item['recipient']);

        return self::response(__('Message retrieved successfully', 'wp-sms'), 200, [
            'id'              => (int) $item['ID'],
            'date'            => $item['date'],
            'sender'          => $item['sender'],
            'recipient'       => $item['recipient'],
            'recipients'      => $recipients,
            'recipient_count' => count($recipients),
            'message'         => $item['message'],
            'status'          => $item['status'],
            'response'        => $item['response'],
            'media'           => maybe_unserialize($item['media']) ?: [],
        ]);
    }

    /**
     * Delete message
     */
    public function deleteItem(WP_REST_Request $request)
    {
        $id = (int) $request->get_param('id');

        $exists = $this->db->get_var(
            $this->db->prepare(
                "SELECT ID FROM {$this->tb_prefix}sms_send WHERE ID = %d",
                $id
            )
        );

        if (!$exists) {
            return self::response(__('Message not found', 'wp-sms'), 404);
        }

        $result = $this->db->delete(
            $this->tb_prefix . 'sms_send',
            ['ID' => $id],
            ['%d']
        );

        if (!$result) {
            return self::response(__('Failed to delete message', 'wp-sms'), 500);
        }

        return self::response(__('Message deleted successfully', 'wp-sms'), 200);
    }

    /**
     * Resend message
     */
    public function resendItem(WP_REST_Request $request)
    {
        $id = (int) $request->get_param('id');

        $item = $this->db->get_row(
            $this->db->prepare(
                "SELECT * FROM {$this->tb_prefix}sms_send WHERE ID = %d",
                $id
            ),
            ARRAY_A
        );

        if (!$item) {
            return self::response(__('Message not found', 'wp-sms'), 404);
        }

        // Get SMS gateway
        global $sms;

        if (!$sms || !method_exists($sms, 'SendSMS')) {
            return self::response(__('SMS gateway not configured', 'wp-sms'), 500);
        }

        // Prepare recipients
        $recipients = array_filter(array_map('trim', explode(',', $item['recipient'])));

        if (empty($recipients)) {
            return self::response(__('No valid recipients found', 'wp-sms'), 400);
        }

        // Send SMS
        $sms->to  = $recipients;
        $sms->msg = $item['message'];

        if (!empty($item['sender'])) {
            $sms->from = $item['sender'];
        }

        // Handle media if present
        $media = maybe_unserialize($item['media']);
        if (!empty($media)) {
            $sms->media = $media;
        }

        try {
            $result = $sms->SendSMS();

            if (is_wp_error($result)) {
                return self::response($result->get_error_message(), 400);
            }

            // Get updated credit
            $credit = null;
            if (method_exists($sms, 'GetCredit')) {
                try {
                    $credit = $sms->GetCredit();
                } catch (\Exception $e) {
                    // Ignore credit errors
                }
            }

            return self::response(__('Message resent successfully', 'wp-sms'), 200, [
                'credit' => $credit,
            ]);
        } catch (\Exception $e) {
            return self::response($e->getMessage(), 500);
        }
    }

    /**
     * Bulk actions
     */
    public function bulkAction(WP_REST_Request $request)
    {
        $action = $request->get_param('action');
        $ids    = $request->get_param('ids');

        if (empty($ids) || !is_array($ids)) {
            return self::response(__('No items selected', 'wp-sms'), 400);
        }

        $ids = array_map('absint', $ids);
        $affected = 0;
        $errors = [];

        switch ($action) {
            case 'delete':
                foreach ($ids as $id) {
                    $result = $this->db->delete(
                        $this->tb_prefix . 'sms_send',
                        ['ID' => $id],
                        ['%d']
                    );
                    if ($result) {
                        $affected++;
                    }
                }
                break;

            case 'resend':
                // Check send permission for resend action
                if (!current_user_can('wpsms_sendsms')) {
                    return self::response(__('You do not have permission to send SMS', 'wp-sms'), 403);
                }

                global $sms;
                if (!$sms || !method_exists($sms, 'SendSMS')) {
                    return self::response(__('SMS gateway not configured', 'wp-sms'), 500);
                }

                foreach ($ids as $id) {
                    $item = $this->db->get_row(
                        $this->db->prepare(
                            "SELECT * FROM {$this->tb_prefix}sms_send WHERE ID = %d",
                            $id
                        ),
                        ARRAY_A
                    );

                    if (!$item) {
                        continue;
                    }

                    $recipients = array_filter(array_map('trim', explode(',', $item['recipient'])));
                    if (empty($recipients)) {
                        continue;
                    }

                    $sms->to  = $recipients;
                    $sms->msg = $item['message'];
                    if (!empty($item['sender'])) {
                        $sms->from = $item['sender'];
                    }

                    try {
                        $result = $sms->SendSMS();
                        if (!is_wp_error($result)) {
                            $affected++;
                        } else {
                            $errors[] = sprintf(__('Failed to resend message #%d: %s', 'wp-sms'), $id, $result->get_error_message());
                        }
                    } catch (\Exception $e) {
                        $errors[] = sprintf(__('Failed to resend message #%d: %s', 'wp-sms'), $id, $e->getMessage());
                    }
                }
                break;
        }

        $response_data = ['affected' => $affected];
        if (!empty($errors)) {
            $response_data['errors'] = $errors;
        }

        return self::response(
            sprintf(__('%d message(s) processed successfully', 'wp-sms'), $affected),
            200,
            $response_data
        );
    }
}

new OutboxApi();
