<?php

namespace WSms\Rest;

use WP_REST_Request;
use WP_REST_Response;
use WSms\Auth\RateLimiter;
use WSms\Social\SocialAuthOrchestrator;
use WSms\Social\SocialAuthManager;

defined('ABSPATH') || exit;

class SocialAuthController
{
    private const NAMESPACE = 'wsms/v1';

    public function __construct(
        private SocialAuthOrchestrator $orchestrator,
        private SocialAuthManager $socialManager,
        private RateLimiter $rateLimiter,
    ) {
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/auth/social/authorize/(?P<provider>[a-z]+)', [
            'methods'             => 'GET',
            'callback'            => [$this, 'handleAuthorize'],
            'permission_callback' => '__return_true',
            'args'                => [
                'provider' => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                'intent'   => ['required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/auth/social/callback/(?P<provider>[a-z]+)', [
            'methods'             => 'GET',
            'callback'            => [$this, 'handleCallback'],
            'permission_callback' => '__return_true',
            'args'                => [
                'provider' => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                'code'     => ['required' => false, 'type' => 'string'],
                'state'    => ['required' => false, 'type' => 'string'],
                'error'    => ['required' => false, 'type' => 'string'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/auth/social/link/(?P<provider>[a-z]+)', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handleLink'],
            'permission_callback' => 'is_user_logged_in',
            'args'                => [
                'provider' => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/auth/social/unlink/(?P<provider>[a-z]+)', [
            'methods'             => 'DELETE',
            'callback'            => [$this, 'handleUnlink'],
            'permission_callback' => 'is_user_logged_in',
            'args'                => [
                'provider' => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/auth/social/accounts', [
            'methods'             => 'GET',
            'callback'            => [$this, 'handleListAccounts'],
            'permission_callback' => 'is_user_logged_in',
        ]);
    }

    /**
     * OAuth authorize — browser redirect. Returns HTTP 302, not JSON.
     */
    public function handleAuthorize(WP_REST_Request $request): WP_REST_Response
    {
        $intent = $request->get_param('intent');
        $intent = in_array($intent, ['login', 'register'], true) ? $intent : 'login';
        $errorPage = $intent === 'register' ? '/register' : '/login';

        $rl = $this->rateLimiter->check('social_auth', 10, 60);

        if (!$rl['allowed']) {
            return $this->redirectTo($this->getAuthBaseUrl() . $errorPage . '?social_error=rate_limited');
        }

        $providerId = $request->get_param('provider');

        try {
            $data = $this->orchestrator->initiateAuthorize($providerId, null, $intent);
        } catch (\InvalidArgumentException $e) {
            return $this->redirectTo($this->getAuthBaseUrl() . $errorPage . '?social_error=invalid_provider');
        }

        return $this->redirectTo($data['authorize_url']);
    }

    /**
     * OAuth callback — browser redirect. Returns HTTP 302, not JSON.
     */
    public function handleCallback(WP_REST_Request $request): WP_REST_Response
    {
        $providerId = $request->get_param('provider');
        $baseUrl  = $this->getAuthBaseUrl();
        $loginUrl = $baseUrl . '/login';

        // Provider returned an error.
        if ($request->get_param('error')) {
            $errorCode = sanitize_text_field($request->get_param('error'));

            return $this->redirectTo($loginUrl . '?social_error=' . urlencode($errorCode));
        }

        $code = $request->get_param('code');
        $state = $request->get_param('state');

        if (empty($code) || empty($state)) {
            return $this->redirectTo($loginUrl . '?social_error=missing_params');
        }

        $callbackResult = $this->orchestrator->handleCallback($providerId, $code, $state);
        $result = $callbackResult['result'];
        $resultArray = $result->toArray();
        $intent = $callbackResult['intent'] ?? 'login';

        // Success — user is authenticated.
        if (!empty($resultArray['success']) && ($resultArray['status'] ?? '') === 'authenticated') {
            return $this->redirectTo($baseUrl . '/profile');
        }

        // MFA required — redirect with session token.
        if (($resultArray['status'] ?? '') === 'mfa_required') {
            return $this->redirectTo($loginUrl . '?social_mfa=' . urlencode($resultArray['session_token']));
        }

        // Account linking success — redirect to security page.
        if (!empty($resultArray['success']) && isset($callbackResult['user_id'])) {
            return $this->redirectTo($baseUrl . '/security?linked=' . $providerId);
        }

        // Error — redirect to the page matching the original intent.
        $errorCode = $resultArray['error'] ?? 'unknown_error';
        $errorPage = $intent === 'register' ? '/register' : '/login';

        return $this->redirectTo($baseUrl . $errorPage . '?social_error=' . urlencode($errorCode));
    }

    public function handleLink(WP_REST_Request $request): WP_REST_Response
    {
        $providerId = $request->get_param('provider');
        $userId = get_current_user_id();

        try {
            $data = $this->orchestrator->initiateAuthorize($providerId, $userId);
        } catch (\InvalidArgumentException $e) {
            return new WP_REST_Response(['success' => false, 'error' => 'invalid_provider', 'message' => $e->getMessage()], 400);
        }

        return new WP_REST_Response([
            'success'       => true,
            'authorize_url' => $data['authorize_url'],
        ]);
    }

    public function handleUnlink(WP_REST_Request $request): WP_REST_Response
    {
        $providerId = $request->get_param('provider');
        $userId = get_current_user_id();

        $result = $this->orchestrator->unlinkAccount($userId, $providerId);

        $status = $result['success'] ? 200 : 400;

        return new WP_REST_Response($result, $status);
    }

    public function handleListAccounts(WP_REST_Request $request): WP_REST_Response
    {
        $userId = get_current_user_id();

        $accounts = $this->orchestrator->getLinkedAccounts($userId);

        return new WP_REST_Response([
            'success'  => true,
            'accounts' => $accounts,
        ]);
    }

    private function getAuthBaseUrl(): string
    {
        $settings = get_option('wsms_auth_settings', []);

        return get_site_url() . ($settings['auth_base_url'] ?? '/account');
    }

    private function redirectTo(string $url): WP_REST_Response
    {
        wp_redirect($url);

        return new WP_REST_Response(null, 302);
    }
}
