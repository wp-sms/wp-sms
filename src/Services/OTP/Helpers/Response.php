<?php

namespace WP_SMS\Services\OTP\Helpers;

/**
 * Response Helper
 *
 * Provides standardized response formatting for authentication endpoints.
 */
class Response
{
    /**
     * Create a successful response
     */
    public static function success(string $message = '', array $data = [], string $code = 'success'): array
    {
        return [
            'ok' => true,
            'code' => $code,
            'message' => $message,
            'flow_id' => null,
            'data' => $data,
            'errors' => [],
        ];
    }

    /**
     * Create an error response
     */
    public static function error(string $message, string $code = 'error', array $errors = [], $flow_id = null): array
    {
        return [
            'ok' => false,
            'code' => $code,
            'message' => $message,
            'flow_id' => $flow_id,
            'data' => [],
            'errors' => $errors,
        ];
    }

    /**
     * Create a response with flow ID (for OTP/Magic Link flows)
     */
    public static function withFlow(string $message, string $flow_id, array $data = [], string $code = 'flow_created'): array
    {
        return [
            'ok' => true,
            'code' => $code,
            'message' => $message,
            'flow_id' => $flow_id,
            'data' => $data,
            'errors' => [],
        ];
    }

    /**
     * Create a validation error response
     */
    public static function validationError(array $errors, string $message = 'Validation failed'): array
    {
        return self::error($message, 'validation_error', $errors);
    }

    /**
     * Create a rate limit error response
     */
    public static function rateLimitError(string $message = 'Too many attempts'): array
    {
        return self::error($message, 'rate_limited');
    }

    /**
     * Create an authentication error response
     */
    public static function authError(string $message = 'Authentication failed'): array
    {
        return self::error($message, 'auth_failed');
    }
}
