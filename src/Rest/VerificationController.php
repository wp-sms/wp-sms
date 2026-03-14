<?php

namespace WSms\Rest;

use WP_REST_Request;
use WP_REST_Response;
use WSms\Auth\RateLimiter;
use WSms\Verification\VerificationService;

defined('ABSPATH') || exit;

class VerificationController
{
    private const NAMESPACE = 'wsms/v1';

    public function __construct(
        private VerificationService $verificationService,
        private RateLimiter $rateLimiter,
    ) {
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/verify/send', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handleSend'],
            'permission_callback' => '__return_true',
            'args'                => [
                'channel'    => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'validate_callback' => fn($v) => in_array($v, ['email', 'phone'], true)],
                'identifier' => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/verify/check', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handleCheck'],
            'permission_callback' => '__return_true',
            'args'                => [
                'channel'    => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'validate_callback' => fn($v) => in_array($v, ['email', 'phone'], true)],
                'identifier' => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                'code'       => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/verify/status', [
            'methods'             => 'GET',
            'callback'            => [$this, 'handleStatus'],
            'permission_callback' => '__return_true',
            'args'                => [
                'channel'    => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'validate_callback' => fn($v) => in_array($v, ['email', 'phone'], true)],
                'identifier' => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            ],
        ]);
    }

    public function handleSend(WP_REST_Request $request): WP_REST_Response
    {
        $rateCheck = $this->rateLimiter->check('verify_send', 5, 60);
        if (!$rateCheck['allowed']) {
            return new WP_REST_Response([
                'success'     => false,
                'error'       => 'rate_limited',
                'message'     => 'Too many requests. Please try again later.',
                'retry_after' => $rateCheck['retry_after'],
            ], 429);
        }

        $channel = $request->get_param('channel');
        $identifier = $request->get_param('identifier');
        $sessionToken = $request->get_header('X-Verification-Session');
        $userId = get_current_user_id() ?: null;

        $result = $this->verificationService->sendCode($channel, $identifier, $sessionToken, $userId);

        $status = $result->success ? 200 : (in_array($result->error, ['rate_limited', 'cooldown'], true) ? 429 : 400);

        return new WP_REST_Response($result->toArray(), $status);
    }

    public function handleCheck(WP_REST_Request $request): WP_REST_Response
    {
        $rateCheck = $this->rateLimiter->check('verify_check', 10, 60);
        if (!$rateCheck['allowed']) {
            return new WP_REST_Response([
                'success'     => false,
                'error'       => 'rate_limited',
                'message'     => 'Too many requests. Please try again later.',
                'retry_after' => $rateCheck['retry_after'],
            ], 429);
        }

        $channel = $request->get_param('channel');
        $identifier = $request->get_param('identifier');
        $code = $request->get_param('code');
        $sessionToken = $request->get_header('X-Verification-Session');
        $userId = get_current_user_id() ?: null;

        if (empty($sessionToken)) {
            return new WP_REST_Response([
                'success' => false,
                'error'   => 'missing_session',
                'message' => 'Session token is required.',
            ], 400);
        }

        $result = $this->verificationService->verifyCode($channel, $identifier, $code, $sessionToken, $userId);

        $status = $result->success ? 200 : 400;

        return new WP_REST_Response($result->toArray(), $status);
    }

    public function handleStatus(WP_REST_Request $request): WP_REST_Response
    {
        $rateCheck = $this->rateLimiter->check('verify_status', 20, 60);
        if (!$rateCheck['allowed']) {
            return new WP_REST_Response([
                'success'     => false,
                'error'       => 'rate_limited',
                'message'     => 'Too many requests. Please try again later.',
                'retry_after' => $rateCheck['retry_after'],
            ], 429);
        }

        $channel = $request->get_param('channel');
        $identifier = $request->get_param('identifier');
        $sessionToken = $request->get_header('X-Verification-Session');

        if (empty($sessionToken)) {
            return new WP_REST_Response([
                'success'  => false,
                'verified' => false,
                'message'  => 'Session token is required.',
            ], 400);
        }

        $verified = $this->verificationService->isVerified($channel, $identifier, $sessionToken);

        return new WP_REST_Response([
            'success'  => true,
            'verified' => $verified,
        ]);
    }
}
