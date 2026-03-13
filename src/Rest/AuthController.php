<?php

namespace WSms\Rest;

use WP_REST_Request;
use WP_REST_Response;
use WSms\Auth\AuthOrchestrator;
use WSms\Auth\CaptchaGuard;
use WSms\Auth\PolicyEngine;
use WSms\Auth\RateLimiter;
use WSms\Auth\ValueObjects\AuthResult;
use WSms\Social\SocialAuthManager;

defined('ABSPATH') || exit;

class AuthController
{
    private const NAMESPACE = 'wsms/v1';

    public function __construct(
        private AuthOrchestrator $orchestrator,
        private RateLimiter $rateLimiter,
        private PolicyEngine $policy,
        private CaptchaGuard $captchaGuard,
        private SocialAuthManager $socialManager,
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

        register_rest_route(self::NAMESPACE, '/auth/identify', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handleIdentify'],
            'permission_callback' => '__return_true',
            'args'                => [
                'identifier' => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/auth/verification/complete', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handleVerificationComplete'],
            'permission_callback' => '__return_true',
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

        $captcha = $this->captchaGuard->verify($request, 'login');
        if ($captcha === false) {
            return CaptchaGuard::failedResponse();
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

        $captcha = $this->captchaGuard->verify($request, 'login');
        if ($captcha === false) {
            return CaptchaGuard::failedResponse();
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

    public function handleIdentify(WP_REST_Request $request): WP_REST_Response
    {
        $rl = $this->rateLimiter->check('identify', 10, 60);

        if (!$rl['allowed']) {
            return $this->rateLimitedResponse($rl['retry_after']);
        }

        $result = $this->orchestrator->identify(
            $request->get_param('identifier'),
        );

        return new WP_REST_Response($result->toArray(), 200);
    }

    public function handleVerificationComplete(WP_REST_Request $request): WP_REST_Response
    {
        $token = $request->get_header('X-Verification-Token');

        if (empty($token)) {
            return new WP_REST_Response(['success' => false, 'error' => 'missing_token'], 400);
        }

        $result = $this->orchestrator->completeVerification($token);

        return $this->toResponse($result);
    }

    public function handleConfig(WP_REST_Request $request): WP_REST_Response
    {
        $primaryMethods = $this->policy->getAvailablePrimaryMethods();

        $config = array_merge(
            [
                'primary_methods'     => $primaryMethods,
                'method_details'      => $this->policy->getMethodDetails($primaryMethods),
                'mfa_enabled'         => !empty($this->policy->getAvailableMfaFactors()),
                'base_url'            => $this->policy->getSetting('auth_base_url', '/account'),
                'registration_fields' => $this->policy->getEffectiveRegistrationFields(),
            ],
            $this->policy->getVerificationRequirements(),
        );

        $socialProviders = [];
        foreach ($this->socialManager->getEnabledProviders() as $p) {
            $socialProviders[] = [
                'id'            => $p->getId(),
                'name'          => $p->getName(),
                'icon'          => $p->getIconSvg(),
                'authorize_url' => rest_url('wsms/v1/auth/social/authorize/' . $p->getId()),
            ];
        }
        if (!empty($socialProviders)) {
            $config['social_providers'] = $socialProviders;
        }

        $captchaConfig = $this->captchaGuard->getPublicConfig();
        if ($captchaConfig) {
            $config['captcha'] = $captchaConfig;
        }

        return new WP_REST_Response($config);
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
