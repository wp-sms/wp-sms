<?php

namespace WSms\Rest;

use WP_REST_Request;
use WP_REST_Response;
use WSms\Auth\AccountManager;
use WSms\Auth\AuthSession;
use WSms\Auth\CaptchaGuard;
use WSms\Auth\RateLimiter;
use WSms\Auth\ValueObjects\AuthResult;

defined('ABSPATH') || exit;

class AccountController
{
    private const NAMESPACE = 'wsms/v1';

    public function __construct(
        private AccountManager $accountManager,
        private RateLimiter $rateLimiter,
        private AuthSession $authSession,
        private CaptchaGuard $captchaGuard,
    ) {
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/auth/register', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handleRegister'],
            'permission_callback' => '__return_true',
            'args'                => [
                'email'        => ['required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_email'],
                'password'     => ['required' => false, 'type' => 'string'],
                'username'     => ['required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_user'],
                'display_name' => ['required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                'first_name'   => ['required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                'last_name'    => ['required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                'phone'        => ['required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/auth/forgot-password', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handleForgotPassword'],
            'permission_callback' => '__return_true',
            'args'                => [
                'email' => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_email'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/auth/reset-password', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handleResetPassword'],
            'permission_callback' => '__return_true',
            'args'                => [
                'token'    => ['required' => true, 'type' => 'string'],
                'password' => ['required' => true, 'type' => 'string'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/auth/verify-email', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handleVerifyEmail'],
            'permission_callback' => '__return_true',
            'args'                => [
                'token' => ['required' => true, 'type' => 'string'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/auth/profile', [
            'methods'             => 'PUT',
            'callback'            => [$this, 'handleUpdateProfile'],
            'permission_callback' => [$this, 'checkAuthenticated'],
            'args'                => [
                'display_name' => ['required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                'first_name'   => ['required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                'last_name'    => ['required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                'phone'        => ['required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                'email'        => ['required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_email'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/auth/password', [
            'methods'             => 'PUT',
            'callback'            => [$this, 'handleChangePassword'],
            'permission_callback' => [$this, 'checkAuthenticated'],
            'args'                => [
                'current_password' => ['required' => true, 'type' => 'string'],
                'new_password'     => ['required' => true, 'type' => 'string'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/auth/logout', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handleLogout'],
            'permission_callback' => [$this, 'checkAuthenticated'],
        ]);

        // --- Generic registration verification endpoints ---
        $verifyArgs = [
            'code' => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
        ];

        register_rest_route(self::NAMESPACE, '/auth/register/verify/(?P<channel>[a-z_]+)', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handleRegisterVerifyChannel'],
            'permission_callback' => '__return_true',
            'args'                => $verifyArgs,
        ]);

        register_rest_route(self::NAMESPACE, '/auth/register/resend/(?P<channel>[a-z_]+)', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handleRegisterResendChannel'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(self::NAMESPACE, '/auth/register/status', [
            'methods'             => 'GET',
            'callback'            => [$this, 'handleVerificationStatus'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(self::NAMESPACE, '/auth/profile/pending-change/(?P<channel>[a-z_]+)', [
            'methods'             => 'DELETE',
            'callback'            => [$this, 'handleCancelPendingChange'],
            'permission_callback' => [$this, 'checkAuthenticated'],
        ]);

        // --- Generic profile verification endpoints ---
        register_rest_route(self::NAMESPACE, '/auth/profile/send-verification/(?P<channel>[a-z_]+)', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handleProfileSendVerification'],
            'permission_callback' => [$this, 'checkAuthenticated'],
        ]);

        register_rest_route(self::NAMESPACE, '/auth/profile/verify/(?P<channel>[a-z_]+)', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handleProfileVerifyChannel'],
            'permission_callback' => [$this, 'checkAuthenticated'],
            'args'                => $verifyArgs,
        ]);

    }

    public function checkAuthenticated(WP_REST_Request $request): bool
    {
        return is_user_logged_in();
    }

    // --- Registration ---

    public function handleRegister(WP_REST_Request $request): WP_REST_Response
    {
        $rl = $this->rateLimiter->check('register', 3, 60);

        if (!$rl['allowed']) {
            return $this->rateLimitedResponse($rl['retry_after']);
        }

        $captcha = $this->captchaGuard->verify($request, 'register');
        if ($captcha === false) {
            return CaptchaGuard::failedResponse();
        }

        $result = $this->accountManager->registerUser([
            'email'        => $request->get_param('email'),
            'password'     => $request->get_param('password'),
            'username'     => $request->get_param('username'),
            'display_name' => $request->get_param('display_name'),
            'first_name'   => $request->get_param('first_name'),
            'last_name'    => $request->get_param('last_name'),
            'phone'        => $request->get_param('phone'),
        ]);

        return new WP_REST_Response($result, $result['success'] ? 201 : 400);
    }

    // --- Password ---

    public function handleForgotPassword(WP_REST_Request $request): WP_REST_Response
    {
        $rl = $this->rateLimiter->check('forgot_password', 3, 60);

        if (!$rl['allowed']) {
            return $this->rateLimitedResponse($rl['retry_after']);
        }

        $captcha = $this->captchaGuard->verify($request, 'forgot_password');
        if ($captcha === false) {
            return CaptchaGuard::failedResponse();
        }

        $this->accountManager->initiatePasswordReset($request->get_param('email'));

        return new WP_REST_Response([
            'success' => true,
            'message' => 'If that email exists, a reset link has been sent.',
        ]);
    }

    public function handleResetPassword(WP_REST_Request $request): WP_REST_Response
    {
        $rl = $this->rateLimiter->check('reset_password', 5, 60);

        if (!$rl['allowed']) {
            return $this->rateLimitedResponse($rl['retry_after']);
        }

        $result = $this->accountManager->completePasswordReset(
            $request->get_param('token'),
            $request->get_param('password'),
        );

        return new WP_REST_Response($result, $result['success'] ? 200 : 400);
    }

    public function handleVerifyEmail(WP_REST_Request $request): WP_REST_Response
    {
        $rl = $this->rateLimiter->check('verify_email', 5, 60);

        if (!$rl['allowed']) {
            return $this->rateLimitedResponse($rl['retry_after']);
        }

        $result = $this->accountManager->verifyEmail($request->get_param('token'));

        return new WP_REST_Response($result, $result['success'] ? 200 : 400);
    }

    // --- Profile ---

    public function handleUpdateProfile(WP_REST_Request $request): WP_REST_Response
    {
        $data = array_filter([
            'display_name' => $request->get_param('display_name'),
            'first_name'   => $request->get_param('first_name'),
            'last_name'    => $request->get_param('last_name'),
            'phone'        => $request->get_param('phone'),
            'email'        => $request->get_param('email'),
        ], fn($v) => $v !== null);

        $result = $this->accountManager->updateProfile(get_current_user_id(), $data);

        return new WP_REST_Response($result, $result['success'] ? 200 : 400);
    }

    public function handleChangePassword(WP_REST_Request $request): WP_REST_Response
    {
        $rl = $this->rateLimiter->check('change_password', 5, 60);

        if (!$rl['allowed']) {
            return $this->rateLimitedResponse($rl['retry_after']);
        }

        $result = $this->accountManager->changePassword(
            get_current_user_id(),
            $request->get_param('current_password'),
            $request->get_param('new_password'),
        );

        return new WP_REST_Response($result, $result['success'] ? 200 : 400);
    }

    public function handleLogout(WP_REST_Request $request): WP_REST_Response
    {
        $this->accountManager->logout();

        return new WP_REST_Response([
            'success' => true,
            'message' => 'Logged out successfully.',
        ]);
    }

    // --- Generic registration verification ---

    public function handleRegisterVerifyChannel(WP_REST_Request $request): WP_REST_Response
    {
        $channel = $request->get_param('channel');
        $rl = $this->rateLimiter->check("register_verify_{$channel}", 10, 60);

        if (!$rl['allowed']) {
            return $this->rateLimitedResponse($rl['retry_after']);
        }

        $session = $this->validateVerificationToken($request);

        if (!$session) {
            return $this->invalidTokenResponse();
        }

        $result = $this->accountManager->verifyChannelOtp($session['user_id'], $channel, $request->get_param('code'));

        return new WP_REST_Response($result, $result['success'] ? 200 : 400);
    }

    public function handleRegisterResendChannel(WP_REST_Request $request): WP_REST_Response
    {
        $channel = $request->get_param('channel');
        $rl = $this->rateLimiter->check("register_resend_{$channel}", 3, 60);

        if (!$rl['allowed']) {
            return $this->rateLimitedResponse($rl['retry_after']);
        }

        $session = $this->validateVerificationToken($request);

        if (!$session) {
            return $this->invalidTokenResponse();
        }

        $result = $this->accountManager->resendVerification($session['user_id'], $channel);

        return new WP_REST_Response($result, $result['success'] ? 200 : 400);
    }

    // --- Pending change cancellation ---

    public function handleCancelPendingChange(WP_REST_Request $request): WP_REST_Response
    {
        $channel = $request->get_param('channel');

        if (!in_array($channel, ['phone', 'email'], true)) {
            return new WP_REST_Response([
                'success' => false,
                'error'   => 'invalid_channel',
                'message' => 'Invalid channel.',
            ], 400);
        }

        $this->accountManager->cancelPendingChange(get_current_user_id(), $channel);

        return new WP_REST_Response(['success' => true, 'message' => 'Pending change cancelled.']);
    }

    // --- Generic profile verification ---

    public function handleProfileSendVerification(WP_REST_Request $request): WP_REST_Response
    {
        $channel = $request->get_param('channel');
        $rl = $this->rateLimiter->check("profile_send_{$channel}", 3, 60);

        if (!$rl['allowed']) {
            return $this->rateLimitedResponse($rl['retry_after']);
        }

        $this->accountManager->sendVerificationChallenge(get_current_user_id(), $channel);

        return new WP_REST_Response(['success' => true, 'message' => 'Verification sent.']);
    }

    public function handleProfileVerifyChannel(WP_REST_Request $request): WP_REST_Response
    {
        $channel = $request->get_param('channel');
        $rl = $this->rateLimiter->check("profile_verify_{$channel}", 10, 60);

        if (!$rl['allowed']) {
            return $this->rateLimitedResponse($rl['retry_after']);
        }

        $result = $this->accountManager->verifyChannelOtp(
            get_current_user_id(),
            $channel,
            $request->get_param('code'),
        );

        return new WP_REST_Response($result, $result['success'] ? 200 : 400);
    }

    public function handleVerificationStatus(WP_REST_Request $request): WP_REST_Response
    {
        $rl = $this->rateLimiter->check('register_status', 20, 60);

        if (!$rl['allowed']) {
            return $this->rateLimitedResponse($rl['retry_after']);
        }

        $session = $this->validateVerificationToken($request);

        if (!$session) {
            return $this->invalidTokenResponse();
        }

        $result = $this->accountManager->getVerificationStatus($session['user_id']);

        return new WP_REST_Response(array_merge(['success' => true], $result));
    }

    // --- Shared helpers ---

    private function invalidTokenResponse(): WP_REST_Response
    {
        return new WP_REST_Response([
            'success' => false,
            'error'   => 'invalid_token',
            'message' => 'Invalid or expired verification token.',
        ], 401);
    }

    private function validateVerificationToken(WP_REST_Request $request): ?array
    {
        $token = $request->get_header('X-Verification-Token')
              ?? $request->get_header('X-Registration-Token');

        if (empty($token)) {
            return null;
        }

        $session = $this->authSession->validate($token);

        if (!$session || !in_array($session['stage'] ?? '', ['registration_verify', 'verification_pending'], true)) {
            return null;
        }

        return $session;
    }

    private function rateLimitedResponse(int $retryAfter): WP_REST_Response
    {
        $result = AuthResult::rateLimited($retryAfter);

        return new WP_REST_Response($result->toArray(), 429);
    }
}
