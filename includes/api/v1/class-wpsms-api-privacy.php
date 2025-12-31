<?php

namespace WP_SMS\Api\V1;

use WP_REST_Request;
use WP_REST_Server;
use WP_SMS\RestApi;
use WP_SMS\Components\NumberParser;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Privacy REST API
 *
 * Provides GDPR compliance endpoints for data export and deletion.
 *
 * @package WP_SMS\Api\V1
 */
class PrivacyApi extends RestApi
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
        // POST /privacy/search - Search for user data
        register_rest_route($this->namespace . '/v1', '/privacy/search', [
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'searchData'],
                'permission_callback' => [$this, 'checkPermission'],
                'args'                => [
                    'mobile' => [
                        'required'          => true,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                ],
            ],
        ]);

        // POST /privacy/export - Export user data
        register_rest_route($this->namespace . '/v1', '/privacy/export', [
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'exportData'],
                'permission_callback' => [$this, 'checkPermission'],
                'args'                => [
                    'mobile' => [
                        'required'          => true,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                ],
            ],
        ]);

        // POST /privacy/delete - Delete user data
        register_rest_route($this->namespace . '/v1', '/privacy/delete', [
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'deleteData'],
                'permission_callback' => [$this, 'checkPermission'],
                'args'                => [
                    'mobile' => [
                        'required'          => true,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                    'confirm' => [
                        'required' => true,
                        'type'     => 'boolean',
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
        return current_user_can('wpsms_setting');
    }

    /**
     * Parse and validate mobile number
     */
    private function parseNumber($mobile)
    {
        $numberParser = new NumberParser($mobile);
        return $numberParser->getValidNumber();
    }

    /**
     * Search for data associated with a mobile number
     */
    public function searchData(WP_REST_Request $request)
    {
        $mobile = $request->get_param('mobile');
        $number = $this->parseNumber($mobile);

        if (!$number) {
            return self::response(__('Invalid phone number', 'wp-sms'), 400);
        }

        $records = $this->findRecords($number);

        if (empty($records)) {
            return self::response(__('No data found for this phone number', 'wp-sms'), 200, [
                'found'   => false,
                'records' => [],
            ]);
        }

        return self::response(__('Data found', 'wp-sms'), 200, [
            'found'   => true,
            'records' => $records,
            'summary' => [
                'total_records'     => count($records),
                'wp_users'          => count(array_filter($records, fn($r) => $r['source'] === 'wp_users')),
                'subscribers'       => count(array_filter($records, fn($r) => $r['source'] === 'sms_subscribes')),
                'outbox_messages'   => count(array_filter($records, fn($r) => $r['source'] === 'sms_send')),
            ],
        ]);
    }

    /**
     * Find all records associated with a mobile number
     */
    private function findRecords($number)
    {
        $records = [];

        // Search in wp_users (user meta)
        $users = $this->db->get_results(
            $this->db->prepare(
                "SELECT u.ID, u.display_name, u.user_email, u.user_registered, um.meta_value as mobile
                 FROM {$this->db->users} u
                 INNER JOIN {$this->db->usermeta} um ON u.ID = um.user_id
                 WHERE um.meta_key = 'mobile' AND um.meta_value LIKE %s",
                '%' . $this->db->esc_like($number) . '%'
            ),
            ARRAY_A
        );

        foreach ($users as $user) {
            $records[] = [
                'source'       => 'wp_users',
                'id'           => (int) $user['ID'],
                'display_name' => $user['display_name'],
                'email'        => $user['email'] ?? '',
                'mobile'       => $user['mobile'],
                'created_at'   => $user['user_registered'],
            ];
        }

        // Search in sms_subscribes
        $subscribers = $this->db->get_results(
            $this->db->prepare(
                "SELECT s.*, g.name as group_name
                 FROM {$this->tb_prefix}sms_subscribes s
                 LEFT JOIN {$this->tb_prefix}sms_subscribes_group g ON s.group_ID = g.ID
                 WHERE s.mobile LIKE %s",
                '%' . $this->db->esc_like($number) . '%'
            ),
            ARRAY_A
        );

        foreach ($subscribers as $sub) {
            $records[] = [
                'source'       => 'sms_subscribes',
                'id'           => (int) $sub['ID'],
                'display_name' => $sub['name'],
                'mobile'       => $sub['mobile'],
                'group'        => $sub['group_name'] ?: '',
                'status'       => $sub['status'] === '1' ? 'Active' : 'Inactive',
                'created_at'   => $sub['date'],
            ];
        }

        // Search in sms_send (outbox)
        $messages = $this->db->get_results(
            $this->db->prepare(
                "SELECT * FROM {$this->tb_prefix}sms_send
                 WHERE recipient LIKE %s OR sender LIKE %s
                 ORDER BY date DESC
                 LIMIT 50",
                '%' . $this->db->esc_like($number) . '%',
                '%' . $this->db->esc_like($number) . '%'
            ),
            ARRAY_A
        );

        foreach ($messages as $msg) {
            $records[] = [
                'source'       => 'sms_send',
                'id'           => (int) $msg['ID'],
                'display_name' => __('SMS Message', 'wp-sms'),
                'mobile'       => $msg['recipient'],
                'sender'       => $msg['sender'],
                'message'      => substr($msg['message'], 0, 100) . (strlen($msg['message']) > 100 ? '...' : ''),
                'status'       => $msg['status'],
                'created_at'   => $msg['date'],
            ];
        }

        return $records;
    }

    /**
     * Export user data to CSV format
     */
    public function exportData(WP_REST_Request $request)
    {
        $mobile = $request->get_param('mobile');
        $number = $this->parseNumber($mobile);

        if (!$number) {
            return self::response(__('Invalid phone number', 'wp-sms'), 400);
        }

        $records = $this->findRecords($number);

        if (empty($records)) {
            return self::response(__('No data found for this phone number', 'wp-sms'), 404);
        }

        // Prepare CSV data
        $csv_data = [];
        $csv_data[] = ['Source', 'ID', 'Name', 'Mobile', 'Details', 'Date'];

        foreach ($records as $record) {
            $details = '';
            switch ($record['source']) {
                case 'wp_users':
                    $details = 'Email: ' . ($record['email'] ?? '');
                    break;
                case 'sms_subscribes':
                    $details = 'Group: ' . ($record['group'] ?? '') . ', Status: ' . ($record['status'] ?? '');
                    break;
                case 'sms_send':
                    $details = 'Message: ' . ($record['message'] ?? '');
                    break;
            }

            $csv_data[] = [
                $record['source'],
                $record['id'],
                $record['display_name'],
                $record['mobile'],
                $details,
                $record['created_at'],
            ];
        }

        return self::response(__('Export data ready', 'wp-sms'), 200, [
            'csv_data' => $csv_data,
            'filename' => 'wpsms-privacy-export-' . sanitize_file_name($number) . '-' . date('Y-m-d') . '.csv',
            'count'    => count($records),
        ]);
    }

    /**
     * Delete all user data
     */
    public function deleteData(WP_REST_Request $request)
    {
        $mobile  = $request->get_param('mobile');
        $confirm = $request->get_param('confirm');
        $number  = $this->parseNumber($mobile);

        if (!$number) {
            return self::response(__('Invalid phone number', 'wp-sms'), 400);
        }

        if (!$confirm) {
            return self::response(__('Deletion must be confirmed', 'wp-sms'), 400);
        }

        $deleted = [
            'wp_users'        => 0,
            'sms_subscribes'  => 0,
            'sms_send'        => 0,
        ];

        // Delete from user meta
        $users = $this->db->get_col(
            $this->db->prepare(
                "SELECT user_id FROM {$this->db->usermeta}
                 WHERE meta_key = 'mobile' AND meta_value LIKE %s",
                '%' . $this->db->esc_like($number) . '%'
            )
        );

        foreach ($users as $user_id) {
            $result = delete_user_meta($user_id, 'mobile');
            if ($result) {
                $deleted['wp_users']++;
            }
        }

        // Delete from subscribers
        $deleted['sms_subscribes'] = $this->db->query(
            $this->db->prepare(
                "DELETE FROM {$this->tb_prefix}sms_subscribes WHERE mobile LIKE %s",
                '%' . $this->db->esc_like($number) . '%'
            )
        );

        // Delete from outbox (as recipient or sender)
        $deleted['sms_send'] = $this->db->query(
            $this->db->prepare(
                "DELETE FROM {$this->tb_prefix}sms_send WHERE recipient LIKE %s OR sender LIKE %s",
                '%' . $this->db->esc_like($number) . '%',
                '%' . $this->db->esc_like($number) . '%'
            )
        );

        $total_deleted = array_sum($deleted);

        if ($total_deleted === 0) {
            return self::response(__('No data found to delete for this phone number', 'wp-sms'), 404);
        }

        return self::response(
            sprintf(__('Successfully deleted %d record(s) for %s', 'wp-sms'), $total_deleted, $number),
            200,
            [
                'deleted' => $deleted,
                'total'   => $total_deleted,
            ]
        );
    }
}

new PrivacyApi();
