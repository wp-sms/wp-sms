<?php

namespace WSms\Rest;

use WP_REST_Request;
use WP_REST_Response;
use WSms\Auth\AuthOrchestrator;
use WSms\Auth\RateLimiter;
use WSms\Auth\TrustedDeviceManager;

defined('ABSPATH') || exit;

class MfaController extends Controller
{
    public function __construct(
        private AuthOrchestrator $orchestrator,
        private RateLimiter $rateLimiter,
        private ?TrustedDeviceManager $trustedDevices = null,
    ) {
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/auth/mfa/factors', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handleGetFactors'],
            'permission_callback' => '__return_true',
            'args'                => [
                'session_token' => ['required' => true, 'type' => 'string'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/auth/mfa/send', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handleSendChallenge'],
            'permission_callback' => '__return_true',
            'args'                => [
                'session_token' => ['required' => true, 'type' => 'string'],
                'channel_id'     => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/auth/mfa/verify', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handleVerify'],
            'permission_callback' => '__return_true',
            'args'                => [
                'session_token'  => ['required' => true, 'type' => 'string'],
                'code'           => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                'channel_id'     => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                'trust_device'   => ['required' => false, 'type' => 'boolean', 'default' => false],
            ],
        ]);
    }

    public function handleGetFactors(WP_REST_Request $request): WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            $rl = $this->rateLimiter->checkAction('mfa_factors');

            if (!$rl['allowed']) {
                return $this->rateLimitedResponse($rl['retry_after']);
            }

            $result = $this->orchestrator->getSessionMfaFactors(
                $request->get_param('session_token'),
            );

            return $this->toAuthResponse($result);
        });
    }

    public function handleSendChallenge(WP_REST_Request $request): WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            $rl = $this->rateLimiter->checkAction('mfa_send');

            if (!$rl['allowed']) {
                return $this->rateLimitedResponse($rl['retry_after']);
            }

            $result = $this->orchestrator->sendMfaChallenge(
                $request->get_param('session_token'),
                $request->get_param('channel_id'),
            );

            return $this->toAuthResponse($result);
        });
    }

    public function handleVerify(WP_REST_Request $request): WP_REST_Response
    {
        return $this->handle(function () use ($request) {
            $rl = $this->rateLimiter->checkAction('mfa_verify');

            if (!$rl['allowed']) {
                return $this->rateLimitedResponse($rl['retry_after']);
            }

            $result = $this->orchestrator->verifyMfa(
                $request->get_param('session_token'),
                $request->get_param('code'),
                $request->get_param('channel_id'),
            );

            if ($result->success && $request->get_param('trust_device') && $this->trustedDevices) {
                $this->trustedDevices->trust($result->userId);
            }

            return $this->toAuthResponse($result);
        });
    }
}
