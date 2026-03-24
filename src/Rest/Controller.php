<?php

namespace WSms\Rest;

use WP_REST_Response;
use WSms\Auth\ValueObjects\AuthResult;
use WSms\Exception\ConflictException;
use WSms\Exception\NotFoundException;
use WSms\Exception\PersistenceException;
use WSms\Exception\ValidationException;

defined('ABSPATH') || exit;

abstract class Controller
{
    protected const NAMESPACE = 'wsms/v1';

    abstract public function registerRoutes(): void;

    public function canManage(): bool
    {
        return current_user_can('manage_options');
    }

    /**
     * Wrap a controller action with centralized exception-to-HTTP mapping.
     *
     * Domain exceptions are caught at this REST boundary and converted
     * to appropriate HTTP responses. Keeps controller methods clean.
     */
    protected function handle(callable $action): WP_REST_Response
    {
        try {
            return $action();
        } catch (NotFoundException $e) {
            return new WP_REST_Response([
                'success' => false,
                'error'   => 'not_found',
                'message' => $e->getMessage(),
            ], 404);
        } catch (ValidationException $e) {
            return new WP_REST_Response([
                'success' => false,
                'error'   => 'validation_failed',
                'errors'  => $e->getErrors(),
            ], 422);
        } catch (ConflictException $e) {
            return new WP_REST_Response([
                'success' => false,
                'error'   => 'conflict',
                'message' => $e->getMessage(),
            ], 409);
        } catch (PersistenceException $e) {
            error_log('[WSMS] ' . $e->getMessage());
            return new WP_REST_Response([
                'success' => false,
                'error'   => 'internal_error',
                'message' => __('An internal error occurred.', 'wp-sms'),
            ], 500);
        }
    }

    protected function ok(mixed $data = null): WP_REST_Response
    {
        return new WP_REST_Response(
            $data !== null ? ['success' => true, 'data' => $data] : ['success' => true],
        );
    }

    protected function created(mixed $data): WP_REST_Response
    {
        return new WP_REST_Response(['success' => true, 'data' => $data], 201);
    }

    protected function paginated(array $items, int $total): WP_REST_Response
    {
        return new WP_REST_Response(['items' => $items, 'total' => $total]);
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
