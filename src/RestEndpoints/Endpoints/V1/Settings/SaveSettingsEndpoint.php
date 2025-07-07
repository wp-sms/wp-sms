<?php

namespace WP_SMS\RestEndpoints\Endpoints\V1\Settings;

use WP_SMS\Settings\Option;
use WP_SMS\RestEndpoints\Abstracts\AbstractSettingsEndpoint;
use WP_SMS\Settings\SchemaValidator;
use WP_REST_Request;
use WP_Error;

/**
 * REST API: /wpsms/v1/settings/save
 * Saves validated settings data.
 */
class SaveSettingsEndpoint extends AbstractSettingsEndpoint
{


    /**
     * Register the save endpoint.
     */
    public static function register()
    {
        register_rest_route('wpsms/v1', '/settings/save', [
            'methods'             => ['POST', 'PUT'],
            'callback'            => [__CLASS__, 'handle'],
            'permission_callback' => [__CLASS__, 'permissions_check'],
        ]);
    }

    /**
     * Handle the save request.
     *
     * @param WP_REST_Request $request
     * @return \WP_REST_Response|WP_Error
     */
    public static function handle(WP_REST_Request $request)
    {
        $input = self::get_json($request);
        
        // Extract addon parameter from request body
        $addon = $input['addon'] ?? null;
        $settings = $input['settings'] ?? $input; // Support both formats for backward compatibility
        
        // Remove addon from settings if it was included
        unset($settings['addon']);

        list($validated, $errors) = SchemaValidator::validate($settings);

        if (!empty($errors)) {
            return self::validation_error($errors);
        }

        $savedKeys = [];

        // Save all settings with the specified addon
        foreach ($validated as $key => $value) {
            Option::updateOption($key, $value, $addon);
            $savedKeys[] = $key;
        }

        return self::success([
            'saved_keys' => $savedKeys,
        ]);
    }
}
