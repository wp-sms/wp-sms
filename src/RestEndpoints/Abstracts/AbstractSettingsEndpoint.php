<?php

namespace WP_SMS\RestEndpoints\Abstracts;

use WP_REST_Response;
use WP_REST_Request;
use WP_Error;

abstract class AbstractSettingsEndpoint
{
    /**
     * Register this endpoint with the REST API.
     *
     * @return void
     */
    abstract public static function register();

    /**
     * Standard permission check — restrict to administrators.
     *
     * @return bool
     */
    public static function permissions_check(): bool
    {
        return current_user_can('manage_options');
    }

    /**
     * Return a standardized success response.
     *
     * @param mixed $data
     * @param int   $status
     * @return WP_REST_Response
     */
    protected static function success($data, int $status = 200): WP_REST_Response
    {
        return new WP_REST_Response([
            'success' => true,
            'data'    => $data,
        ], $status);
    }

    /**
     * Return a standardized error response.
     *
     * @param string $message
     * @param int    $status
     * @param array  $additional
     * @return WP_REST_Response
     */
    protected static function error(string $message, int $status = 400, array $additional = []): WP_REST_Response
    {
        return new WP_REST_Response(array_merge([
            'success' => false,
            'message' => $message,
        ], $additional), $status);
    }

    /**
     * Return a WP_Error for validation failures.
     *
     * @param array $fieldErrors
     * @param int $status
     * @return WP_Error
     */
    protected static function validation_error(array $fieldErrors, int $status = 422): WP_Error
    {
        return new WP_Error(
            'invalid_settings',
            'One or more settings are invalid.',
            [
                'status' => $status,
                'fields' => $fieldErrors,
            ]
        );
    }

    /**
     * Get sanitized JSON body from request.
     *
     * @param WP_REST_Request $request
     * @return array
     */
    protected static function get_json(WP_REST_Request $request): array
    {
        $json = $request->get_json_params();
        return is_array($json) ? $json : [];
    }
}
