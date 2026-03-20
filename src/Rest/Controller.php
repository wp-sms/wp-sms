<?php

namespace WSms\Rest;

use WP_REST_Response;
use WSms\Auth\ValueObjects\AuthResult;

defined('ABSPATH') || exit;

abstract class Controller
{
    protected const NAMESPACE = 'wsms/v1';

    abstract public function registerRoutes(): void;

    public function canManage(): bool
    {
        return current_user_can('manage_options');
    }

    protected function toAuthResponse(AuthResult $result): WP_REST_Response
    {
        return new WP_REST_Response($result->toArray(), $result->toHttpStatus());
    }

    protected function rateLimitedResponse(int $retryAfter): WP_REST_Response
    {
        $result = AuthResult::rateLimited($retryAfter);

        return new WP_REST_Response($result->toArray(), 429);
    }
}
