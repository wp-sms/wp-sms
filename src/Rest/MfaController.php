<?php

namespace WSms\Rest;

use WP_REST_Request;
use WP_REST_Response;
use WSms\Auth\AuthOrchestrator;
use WSms\Auth\RateLimiter;
use WSms\Auth\ValueObjects\AuthResult;

defined('ABSPATH') || exit;

class MfaController
{
    private const NAMESPACE = 'wsms/v1';

    public function __construct(
        private AuthOrchestrator $orchestrator,
        private RateLimiter $rateLimiter,
    ) {
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/auth/mfa/send', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handleSendChallenge'],
            'permission_callback' => '__return_true',
            'args'                => [
                'challenge_token' => ['required' => true, 'type' => 'string'],
                'channel_id'     => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/auth/mfa/verify', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handleVerify'],
            'permission_callback' => '__return_true',
            'args'                => [
                'challenge_token' => ['required' => true, 'type' => 'string'],
                'code'            => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                'channel_id'     => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            ],
        ]);
    }

    public function handleSendChallenge(WP_REST_Request $request): WP_REST_Response
    {
        $rl = $this->rateLimiter->check('mfa_send', 3, 60);

        if (!$rl['allowed']) {
            return $this->toResponse(AuthResult::rateLimited($rl['retry_after']));
        }

        $result = $this->orchestrator->sendMfaChallenge(
            $request->get_param('challenge_token'),
            $request->get_param('channel_id'),
        );

        return $this->toResponse($result);
    }

    public function handleVerify(WP_REST_Request $request): WP_REST_Response
    {
        $rl = $this->rateLimiter->check('mfa_verify', 3, 10);

        if (!$rl['allowed']) {
            return $this->toResponse(AuthResult::rateLimited($rl['retry_after']));
        }

        $result = $this->orchestrator->verifyMfa(
            $request->get_param('challenge_token'),
            $request->get_param('code'),
            $request->get_param('channel_id'),
        );

        return $this->toResponse($result);
    }

    private function toResponse(AuthResult $result): WP_REST_Response
    {
        return new WP_REST_Response($result->toArray(), $result->toHttpStatus());
    }
}
