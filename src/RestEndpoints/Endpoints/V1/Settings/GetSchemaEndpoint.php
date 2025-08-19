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
            'args'                => [
                'include_hidden' => [
                    'description' => 'Include hidden groups in the response',
                    'type'        => 'boolean',
                    'default'     => false,
                ],
            ],
        ]);

        register_rest_route('wpsms/v1', '/settings/schema/category/(?P<category>[a-zA-Z0-9_-]+)', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'getCategory'],
            'permission_callback' => [__CLASS__, 'permissions_check'],
            'args'                => [
                'include_hidden' => [
                    'description' => 'Include hidden groups in the response',
                    'type'        => 'boolean',
                    'default'     => false,
                ],
            ],
        ]);

        register_rest_route('wpsms/v1', '/settings/schema/group/(?P<group>[a-zA-Z0-9_-]+)', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'getGroup'],
            'permission_callback' => [__CLASS__, 'permissions_check'],
            'args'                => [
                'include_hidden' => [
                    'description' => 'Include hidden groups in the response',
                    'type'        => 'boolean',
                    'default'     => false,
                ],
            ],
        ]);

        register_rest_route('wpsms/v1', '/settings/schema/nested/(?P<path>[a-zA-Z0-9_.-]+)', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'getNestedGroup'],
            'permission_callback' => [__CLASS__, 'permissions_check'],
            'args'                => [
                'include_hidden' => [
                    'description' => 'Include hidden groups in the response',
                    'type'        => 'boolean',
                    'default'     => false,
                ],
            ],
        ]);

        register_rest_route('wpsms/v1', '/settings/schema/list', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'getGroupList'],
            'permission_callback' => [__CLASS__, 'permissions_check'],
            'args'                => [
                'include_hidden' => [
                    'description' => 'Include hidden groups in the response',
                    'type'        => 'boolean',
                    'default'     => false,
                ],
            ],
        ]);
    }

    /**
     * GET /settings/schema
     * Return the entire schema with nested structure.
     *
     * @param WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public static function getAll(WP_REST_Request $request): \WP_REST_Response
    {
        $includeHidden = $request->get_param('include_hidden') == 'true';
        if ($includeHidden) {
            // Export all groups including hidden ones
            $schema = SchemaRegistry::instance()->exportIncludingHidden();
        } else {
            $schema = SchemaRegistry::instance()->export();
        }
        
        return self::success($schema);
    }

    /**
     * GET /settings/schema/category/{category}
     * Return all groups in a category (supports nested structure for integrations).
     *
     * @param WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public static function getCategory(WP_REST_Request $request): \WP_REST_Response
    {
        $category = $request->get_param('category');
        $includeHidden = $request->get_param('include_hidden') == 'true';
        
        if ($includeHidden) {
            $schema = SchemaRegistry::instance()->exportIncludingHidden();
        } else {
            $schema = SchemaRegistry::instance()->export();
        }
        
        if (!isset($schema[$category])) {
            return self::error("Category '{$category}' not found", 404);
        }

        return self::success($schema[$category]);
    }

    /**
     * GET /settings/schema/group/{group}
     * Return a specific group's schema by group name.
     *
     * @param WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public static function getGroup(WP_REST_Request $request): \WP_REST_Response
    {
        $group = $request->get_param('group');
        $includeHidden = $request->get_param('include_hidden') == 'true';
        
        if ($includeHidden) {
            $data = SchemaRegistry::instance()->exportGroupIncludingHidden($group);
        } else {
            $data = SchemaRegistry::instance()->exportGroup($group);
        }

        if ($data === null) {
            return self::error("Group '{$group}' not found", 404);
        }

        return self::success($data);
    }

    /**
     * GET /settings/schema/nested/{path}
     * Return a nested group by its full path (e.g., integrations.contact_forms.contact_form_7).
     *
     * @param WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public static function getNestedGroup(WP_REST_Request $request): \WP_REST_Response
    {
        $path = $request->get_param('path');
        $schema = SchemaRegistry::instance()->export();
        
        $data = self::getNestedData($schema, $path);
        
        if ($data === null) {
            return self::error("Nested path '{$path}' not found", 404);
        }

        return self::success($data);
    }

    /**
     * GET /settings/schema/list
     * Return list of group names and labels with nested structure.
     *
     * @param WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public static function getGroupList(WP_REST_Request $request): \WP_REST_Response
    {
        $includeHidden = $request->get_param('include_hidden') == 'true';
        if ($includeHidden) {
            return self::success(SchemaRegistry::instance()->exportGroupListIncludingHidden());
        }
        
        return self::success(SchemaRegistry::instance()->exportGroupList());
    }

    /**
     * Helper method to traverse nested structure and get data by path.
     *
     * @param array $data
     * @param string $path
     * @return array|null
     */
    protected static function getNestedData(array $data, string $path): ?array
    {
        $parts = explode('.', $path);
        $current = $data;

        foreach ($parts as $index => $part) {
            // Check if the current part exists as a direct key
            if (!isset($current[$part])) {
                return null;
            }
            
            $current = $current[$part];
            
            // If this is not the last part and current has children, move into children
            if ($index < count($parts) - 1 && isset($current['children']) && is_array($current['children'])) {
                $current = $current['children'];
            }
        }

        return $current;
    }
}
