<?php

namespace WSms\Rest;

use WP_REST_Request;
use WP_REST_Response;
use WSms\Audit\AuditLogger;
use WSms\Auth\FreshnessManager;
use WSms\Auth\PolicyEngine;
use WSms\Auth\SettingsRepository;
use WSms\Auth\ValueObjects\AuthResult;
use WSms\Enums\EventType;
use WSms\Exception\ConflictException;
use WSms\Exception\NotFoundException;
use WSms\Exception\PersistenceException;
use WSms\Exception\ValidationException;
use WSms\Access\AccessManager;
use WSms\Bootstrap;
use WSms\Log\WpLogger;

defined('ABSPATH') || exit;

abstract class Controller
{
    protected const NAMESPACE = 'wsms/v1';

    abstract public function registerRoutes(): void;

    public function canManage(): bool
    {
        return current_user_can('manage_options');
    }

    protected function access(): AccessManager
    {
        return Bootstrap::get('access.manager');
    }

    protected function canViewSection(string $section): \Closure
    {
        return fn() => $this->access()->canViewSection($section);
    }

    protected function canManageSection(string $section): \Closure
    {
        return fn() => $this->access()->canManageSection($section);
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
            (new WpLogger('wsms'))->error($e->getMessage(), ['exception' => $e]);
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
        $data = $result->toArray();

        // Strip debug-only fields in production.
        if (!empty($data['meta']['debug_reason']) && !(defined('WP_DEBUG') && WP_DEBUG)) {
            unset($data['meta']['debug_reason']);
            if (empty($data['meta'])) {
                unset($data['meta']);
            }
        }

        return new WP_REST_Response($data, $result->toHttpStatus());
    }

    protected function rateLimitedResponse(int $retryAfter): WP_REST_Response
    {
        $result = AuthResult::rateLimited($retryAfter);

        return new WP_REST_Response($result->toArray(), 429);
    }

    /**
     * Enforce the step-up freshness window for a sensitive endpoint.
     *
     * Returns true when the caller is logged in AND their session is within
     * `auth.fresh_auth_window_seconds`; otherwise returns a WP_REST_Response
     * with HTTP 403 and the step-up contract the frontend expects.
     *
     * When $allowPasswordEscapeHatch is true and the request body carries a
     * non-empty `current_password`, the freshness check is skipped — the
     * downstream handler must still validate the password. This mirrors
     * Better Auth's delete-user escape hatch.
     */
    protected function requireFreshAuth(
        WP_REST_Request $request,
        bool $allowPasswordEscapeHatch = false,
    ): true|WP_REST_Response {
        $userId = get_current_user_id();

        if ($userId <= 0) {
            return new WP_REST_Response([
                'success' => false,
                'error'   => 'not_logged_in',
                'message' => __('Authentication required.', 'wp-sms'),
            ], 401);
        }

        if ($allowPasswordEscapeHatch) {
            $supplied = $request->get_param('current_password');
            if (is_string($supplied) && $supplied !== '') {
                return true;
            }
        }

        /** @var FreshnessManager|null $freshness */
        $freshness = Bootstrap::has('auth.freshness') ? Bootstrap::get('auth.freshness') : null;
        /** @var SettingsRepository|null $settings */
        $settings = Bootstrap::has('auth.settings') ? Bootstrap::get('auth.settings') : null;

        // If DI is not wired (e.g. tests that bypass the container), fail open
        // to the normal capability check rather than blocking the request.
        if (!$freshness || !$settings) {
            return true;
        }

        $window = $settings->getFreshAuthWindowSeconds();
        if ($freshness->isFresh($userId, $window)) {
            return true;
        }

        /** @var PolicyEngine|null $policy */
        $policy = Bootstrap::has('auth.policy') ? Bootstrap::get('auth.policy') : null;
        $methods = $policy ? $policy->stepUpMethodsFor($userId) : [];
        $age = $freshness->getFreshAge($userId);

        if (Bootstrap::has('audit.logger')) {
            $route = method_exists($request, 'get_route') ? $request->get_route() : '';
            /** @var AuditLogger $auditLogger */
            $auditLogger = Bootstrap::get('audit.logger');
            $auditLogger->log(EventType::FreshAuthGateTriggered, 'blocked', $userId, [
                'route' => $route,
                'age'   => $age,
            ]);
        }

        if (empty($methods)) {
            return new WP_REST_Response([
                'success' => false,
                'error'   => 'step_up_unavailable',
                'message' => __('Re-authentication is required but no step-up methods are available. Please contact support.', 'wp-sms'),
                'data'    => [
                    'fresh_auth_required' => true,
                    'step_up_methods'     => [],
                ],
            ], 409);
        }

        return new WP_REST_Response([
            'success' => false,
            'error'   => 'fresh_auth_required',
            'code'    => 'fresh_auth_required',
            'message' => __('This action requires recent re-authentication.', 'wp-sms'),
            'data'    => [
                'fresh_auth_required'   => true,
                'step_up_methods'       => $methods,
                'current_freshness_age' => $age,
            ],
        ], 403);
    }
}
