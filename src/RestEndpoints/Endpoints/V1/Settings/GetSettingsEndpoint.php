<?php

namespace WP_SMS\RestEndpoints\Endpoints\V1\Settings;

use WP_SMS\Settings\Option;
use WP_SMS\RestEndpoints\Abstracts\AbstractSettingsEndpoint;
use WP_SMS\Settings\Abstracts\AbstractSettingGroup;
use WP_SMS\Settings\SchemaRegistry;
use WP_REST_Request;

/**
 * REST API: /wpsms/v1/settings/values/*
 * Returns current saved values merged with schema defaults.
 */
class GetSettingsEndpoint extends AbstractSettingsEndpoint
{
    /**
     * Register all GET settings value endpoints.
     */
    public static function register()
    {
        register_rest_route('wpsms/v1', '/settings/values', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'getAll'],
            'permission_callback' => [__CLASS__, 'permissions_check'],
        ]);

        register_rest_route('wpsms/v1', '/settings/values/group/(?P<group>[a-zA-Z0-9_-]+)', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'getGroup'],
            'permission_callback' => [__CLASS__, 'permissions_check'],
        ]);

        register_rest_route('wpsms/v1', '/settings/values/category/(?P<category>[a-zA-Z0-9_-]+)', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'getCategory'],
            'permission_callback' => [__CLASS__, 'permissions_check'],
        ]);
    }

    /**
     * GET /settings/values
     */
    public static function getAll(WP_REST_Request $request): \WP_REST_Response
    {
        return self::success(self::resolveValues(SchemaRegistry::instance()->all()));
    }

    /**
     * GET /settings/values/group/{group}
     */
    public static function getGroup(WP_REST_Request $request): \WP_REST_Response
    {
        $name = $request->get_param('group');
        $group = SchemaRegistry::instance()->getGroup($name);

        if (!$group) {
            return self::error("Group '{$name}' not found.", 404);
        }

        return self::success(self::resolveValues([$group]));
    }

    /**
     * GET /settings/values/category/{category}
     */
    public static function getCategory(WP_REST_Request $request): \WP_REST_Response
    {
        $category = $request->get_param('category');
        $groups = SchemaRegistry::instance()->getCategory($category);

        if (empty($groups)) {
            return self::error("No groups found in category '{$category}'.", 404);
        }

        return self::success(self::resolveValues($groups));
    }

    /**
     * Merges stored values with schema defaults for a list of groups.
     *
     * @param AbstractSettingGroup[] $groups
     * @return array
     */
    protected static function resolveValues(array $groups): array
    {
        $saved = Option::getOptions(false);
        $values = [];

        foreach ($groups as $group) {
            foreach ($group->getFields() as $field) {
                $key = $field->getKey();
                $values[$key] = $saved[$key] ?? $field->default;
            }
        }

        return $values;
    }
}
