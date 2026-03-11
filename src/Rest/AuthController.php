<?php

namespace WSms\Rest;

use WP_REST_Request;
use WP_REST_Response;
use WSms\Auth\AuthOrchestrator;
use WSms\Auth\RateLimiter;
use WSms\Auth\ValueObjects\AuthResult;

defined('ABSPATH') || exit;

class AuthController
{
    private const NAMESPACE = 'wsms/v1';

    public function __construct(
        private AuthOrchestrator $orchestrator,
        private RateLimiter $rateLimiter,
    ) {
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/auth/login', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handleLogin'],
            'permission_callback' => '__return_true',
            'args'                => [
                'username' => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                'password' => ['required' => true, 'type' => 'string'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/auth/login/passwordless', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handlePasswordless'],
            'permission_callback' => '__return_true',
            'args'                => [
                'method'     => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                'identifier' => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/auth/verify', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handleVerify'],
            'permission_callback' => '__return_true',
            'args'                => [
                'challenge_token' => ['required' => true, 'type' => 'string'],
                'code'            => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/auth/verify-magic-link', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handleVerifyMagicLink'],
            'permission_callback' => '__return_true',
            'args'                => [
                'token' => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/auth/resend', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handleResend'],
            'permission_callback' => '__return_true',
            'args'                => [
                'challenge_token' => ['required' => true, 'type' => 'string'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/auth/config', [
            'methods'             => 'GET',
            'callback'            => [$this, 'handleConfig'],
            'permission_callback' => '__return_true',
        ]);
    }

    public function handleLogin(WP_REST_Request $request): WP_REST_Response
    {
        $rl = $this->rateLimiter->check('login', 5, 60);

        if (!$rl['allowed']) {
            return $this->rateLimitedResponse($rl['retry_after']);
        }

        $result = $this->orchestrator->loginWithPassword(
            $request->get_param('username'),
            $request->get_param('password'),
        );

        return $this->toResponse($result);
    }

    public function handlePasswordless(WP_REST_Request $request): WP_REST_Response
    {
        $rl = $this->rateLimiter->check('login_passwordless', 3, 60);

        if (!$rl['allowed']) {
            return $this->rateLimitedResponse($rl['retry_after']);
        }

        $result = $this->orchestrator->loginPasswordless(
            $request->get_param('method'),
            $request->get_param('identifier'),
        );

        return $this->toResponse($result);
    }

    public function handleVerify(WP_REST_Request $request): WP_REST_Response
    {
        $rl = $this->rateLimiter->check('verify', 3, 10);

        if (!$rl['allowed']) {
            return $this->rateLimitedResponse($rl['retry_after']);
        }

        $result = $this->orchestrator->verifyPrimary(
            $request->get_param('challenge_token'),
            $request->get_param('code'),
        );

        return $this->toResponse($result);
    }

    public function handleVerifyMagicLink(WP_REST_Request $request): WP_REST_Response
    {
        $rl = $this->rateLimiter->check('verify', 3, 10);

        if (!$rl['allowed']) {
            return $this->rateLimitedResponse($rl['retry_after']);
        }

        $result = $this->orchestrator->verifyMagicLink(
            $request->get_param('token'),
        );

        return $this->toResponse($result);
    }

    public function handleResend(WP_REST_Request $request): WP_REST_Response
    {
        $rl = $this->rateLimiter->check('resend', 1, 60);

        if (!$rl['allowed']) {
            return $this->rateLimitedResponse($rl['retry_after']);
        }

        $result = $this->orchestrator->resendChallenge(
            $request->get_param('challenge_token'),
        );

        return $this->toResponse($result);
    }

    public function handleConfig(WP_REST_Request $request): WP_REST_Response
    {
        $settings = get_option('wsms_auth_settings', []);

        return new WP_REST_Response([
            'primary_methods'     => $settings['primary_methods'] ?? ['password'],
            'mfa_enabled'         => !empty($settings['mfa_factors']),
            'base_url'            => $settings['auth_base_url'] ?? '/account',
            'registration_fields' => $settings['registration_fields'] ?? ['email', 'password'],
        ]);
    }

    private function toResponse(AuthResult $result): WP_REST_Response
    {
        return new WP_REST_Response($result->toArray(), $result->toHttpStatus());
    }

    private function rateLimitedResponse(int $retryAfter): WP_REST_Response
    {
        $result = AuthResult::rateLimited($retryAfter);

        return new WP_REST_Response($result->toArray(), 429);
    }
}
