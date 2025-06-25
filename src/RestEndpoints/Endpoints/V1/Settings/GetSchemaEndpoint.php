<?php

namespace WP_SMS\RestEndpoints\Endpoints\V1\Settings;

use WP_SMS\RestEndpoints\Abstracts\AbstractSettingsEndpoint;
use WP_SMS\Settings\SchemaRegistry;
use WP_REST_Request;

/**
 * REST API: /wpsms/v1/settings/schema/*
 * Handles all schema read operations.
 */
class GetSchemaEndpoint extends AbstractSettingsEndpoint
{
    /**
     * Register all schema-related routes.
     */
    public static function register()
    {
        register_rest_route('wpsms/v1', '/settings/schema', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'getAll'],
            'permission_callback' => [__CLASS__, 'permissions_check'],
        ]);

        register_rest_route('wpsms/v1', '/settings/schema/category/(?P<category>[a-zA-Z0-9_-]+)', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'getCategory'],
            'permission_callback' => [__CLASS__, 'permissions_check'],
        ]);

        register_rest_route('wpsms/v1', '/settings/schema/group/(?P<group>[a-zA-Z0-9_-]+)', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'getGroup'],
            'permission_callback' => [__CLASS__, 'permissions_check'],
        ]);

        register_rest_route('wpsms/v1', '/settings/schema/list', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'getGroupList'],
            'permission_callback' => [__CLASS__, 'permissions_check'],
        ]);
    }

    /**
     * GET /settings/schema
     * Return the entire schema.
     *
     * @param WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public static function getAll(WP_REST_Request $request): \WP_REST_Response
    {
        return self::success(SchemaRegistry::instance()->export());
    }

    /**
     * GET /settings/schema/category/{category}
     * Return all groups in a category.
     *
     * @param WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public static function getCategory(WP_REST_Request $request): \WP_REST_Response
    {
        $category = $request->get_param('category');
        $data     = SchemaRegistry::instance()->exportCategory($category);
        if (empty($data)) {
            return self::error("No groups found for category '{$category}'", 404);
        }

        return self::success($data);
    }

    /**
     * GET /settings/schema/group/{group}
     * Return a specific group’s schema.
     *
     * @param WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public static function getGroup(WP_REST_Request $request): \WP_REST_Response
    {
        $group = $request->get_param('group');
        $data  = SchemaRegistry::instance()->exportGroup($group);

        if ($data === null) {
            return self::error("Group '{$group}' not found", 404);
        }

        return self::success($data);
    }

    /**
     * GET /settings/schema/list
     * Return list of group names and labels only.
     *
     * @param WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public static function getGroupList(WP_REST_Request $request): \WP_REST_Response
    {
        return self::success(SchemaRegistry::instance()->exportGroupList());
    }
}
