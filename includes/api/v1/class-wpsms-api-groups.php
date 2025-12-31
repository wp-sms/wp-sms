<?php

namespace WP_SMS\Api\V1;

use WP_REST_Request;
use WP_REST_Server;
use WP_SMS\Newsletter;
use WP_SMS\RestApi;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Groups REST API
 *
 * Provides CRUD operations for managing subscriber groups.
 *
 * @package WP_SMS\Api\V1
 */
class GroupsApi extends RestApi
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
        // GET /groups - List all groups
        register_rest_route($this->namespace . '/v1', '/groups', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'getItems'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'createItem'],
                'permission_callback' => [$this, 'checkPermission'],
                'args'                => [
                    'name' => [
                        'required'          => true,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                ],
            ],
        ]);

        // GET/PUT/DELETE /groups/{id}
        register_rest_route($this->namespace . '/v1', '/groups/(?P<id>\d+)', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'getItem'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
            [
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => [$this, 'updateItem'],
                'permission_callback' => [$this, 'checkPermission'],
                'args'                => [
                    'name' => [
                        'required'          => true,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                ],
            ],
            [
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => [$this, 'deleteItem'],
                'permission_callback' => [$this, 'checkPermission'],
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
     * Get all groups
     */
    public function getItems(WP_REST_Request $request)
    {
        $groups = Newsletter::getGroups();

        if (!is_array($groups)) {
            $groups = [];
        }

        $formatted = [];
        foreach ($groups as $group) {
            $subscriber_count = Newsletter::getTotal($group->ID);
            $active_count = $this->db->get_var(
                $this->db->prepare(
                    "SELECT COUNT(*) FROM {$this->tb_prefix}sms_subscribes WHERE group_ID = %d AND status = '1'",
                    $group->ID
                )
            );

            $formatted[] = [
                'id'               => (int) $group->ID,
                'name'             => $group->name,
                'subscriber_count' => (int) $subscriber_count,
                'active_count'     => (int) $active_count,
            ];
        }

        return self::response(__('Groups retrieved successfully', 'wp-sms'), 200, [
            'items'      => $formatted,
            'pagination' => [
                'total'        => count($formatted),
                'total_pages'  => 1,
                'current_page' => 1,
                'per_page'     => count($formatted),
            ],
        ]);
    }

    /**
     * Get single group
     */
    public function getItem(WP_REST_Request $request)
    {
        $id = (int) $request->get_param('id');

        $group = Newsletter::getGroup($id);

        if (!$group) {
            return self::response(__('Group not found', 'wp-sms'), 404);
        }

        $subscriber_count = Newsletter::getTotal($id);
        $active_count = $this->db->get_var(
            $this->db->prepare(
                "SELECT COUNT(*) FROM {$this->tb_prefix}sms_subscribes WHERE group_ID = %d AND status = '1'",
                $id
            )
        );

        return self::response(__('Group retrieved successfully', 'wp-sms'), 200, [
            'id'               => (int) $group->ID,
            'name'             => $group->name,
            'subscriber_count' => (int) $subscriber_count,
            'active_count'     => (int) $active_count,
        ]);
    }

    /**
     * Create group
     */
    public function createItem(WP_REST_Request $request)
    {
        $name = $request->get_param('name');

        if (empty($name)) {
            return self::response(__('Group name is required', 'wp-sms'), 400);
        }

        // Check if group name already exists
        $existing = $this->db->get_var(
            $this->db->prepare(
                "SELECT ID FROM {$this->tb_prefix}sms_subscribes_group WHERE name = %s",
                $name
            )
        );

        if ($existing) {
            return self::response(__('A group with this name already exists', 'wp-sms'), 400);
        }

        $result = Newsletter::addGroup($name);

        if (!$result) {
            return self::response(__('Failed to create group', 'wp-sms'), 500);
        }

        return self::response(__('Group created successfully', 'wp-sms'), 201, [
            'id'   => $result,
            'name' => $name,
        ]);
    }

    /**
     * Update group
     */
    public function updateItem(WP_REST_Request $request)
    {
        $id   = (int) $request->get_param('id');
        $name = $request->get_param('name');

        $group = Newsletter::getGroup($id);
        if (!$group) {
            return self::response(__('Group not found', 'wp-sms'), 404);
        }

        if (empty($name)) {
            return self::response(__('Group name is required', 'wp-sms'), 400);
        }

        // Check if name is taken by another group
        $existing = $this->db->get_var(
            $this->db->prepare(
                "SELECT ID FROM {$this->tb_prefix}sms_subscribes_group WHERE name = %s AND ID != %d",
                $name,
                $id
            )
        );

        if ($existing) {
            return self::response(__('A group with this name already exists', 'wp-sms'), 400);
        }

        $result = Newsletter::updateGroup($id, $name);

        if (!$result) {
            return self::response(__('Failed to update group', 'wp-sms'), 500);
        }

        return self::response(__('Group updated successfully', 'wp-sms'), 200, [
            'id'   => $id,
            'name' => $name,
        ]);
    }

    /**
     * Delete group
     */
    public function deleteItem(WP_REST_Request $request)
    {
        $id = (int) $request->get_param('id');

        $group = Newsletter::getGroup($id);
        if (!$group) {
            return self::response(__('Group not found', 'wp-sms'), 404);
        }

        // Check if group has subscribers
        $subscriber_count = Newsletter::getTotal($id);
        if ($subscriber_count > 0) {
            return self::response(
                sprintf(
                    __('Cannot delete group. It has %d subscriber(s). Please move or delete them first.', 'wp-sms'),
                    $subscriber_count
                ),
                400
            );
        }

        $result = Newsletter::deleteGroup($id);

        if (!$result) {
            return self::response(__('Failed to delete group', 'wp-sms'), 500);
        }

        return self::response(__('Group deleted successfully', 'wp-sms'), 200);
    }
}

new GroupsApi();
