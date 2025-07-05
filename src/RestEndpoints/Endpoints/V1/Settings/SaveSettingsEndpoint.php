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

        list($validated, $errors) = SchemaValidator::validate($input);

        if (!empty($errors)) {
            return self::validation_error($errors);
        }

        // Save each key individually to avoid overwriting untouched fields
        foreach ($validated as $key => $value) {
            Option::updateOption($key, $value, false); // core-only for now
        }

        return self::success([
            'saved_keys' => array_keys($validated),
        ]);
    }
}
