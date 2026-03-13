<?php

namespace WSms\Social\Providers;

use WSms\Social\Contracts\SocialProviderInterface;
use WSms\Social\OAuthStateManager;

defined('ABSPATH') || exit;

class GoogleProvider implements SocialProviderInterface
{
    private const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const USERINFO_URL = 'https://www.googleapis.com/oauth2/v2/userinfo';
    private const SCOPES = 'openid email profile';

    public function getId(): string
    {
        return 'google';
    }

    public function getName(): string
    {
        return 'Google';
    }

    public function getIconSvg(): string
    {
        return '<svg viewBox="0 0 24 24" width="16" height="16"><path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 0 1-2.2 3.32v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.1z" fill="#4285F4"/><path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/><path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/><path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/></svg>';
    }

    public function isTrustedEmailProvider(): bool
    {
        return true;
    }

    public function createAuthorizationURL(string $redirectUri, string $state, ?string $codeVerifier = null): array
    {
        $params = [
            'client_id'             => $this->getClientId(),
            'redirect_uri'          => $redirectUri,
            'response_type'         => 'code',
            'scope'                 => self::SCOPES,
            'state'                 => $state,
            'access_type'           => 'offline',
            'prompt'                => 'consent',
        ];

        if ($codeVerifier) {
            $params['code_challenge'] = OAuthStateManager::codeChallenge($codeVerifier);
            $params['code_challenge_method'] = 'S256';
        }

        return [
            'url'           => self::AUTH_URL . '?' . http_build_query($params),
            'state'         => $state,
            'code_verifier' => $codeVerifier,
        ];
    }

    public function exchangeCode(string $code, string $redirectUri, ?string $codeVerifier = null): array
    {
        $body = [
            'code'          => $code,
            'client_id'     => $this->getClientId(),
            'client_secret' => $this->getClientSecret(),
            'redirect_uri'  => $redirectUri,
            'grant_type'    => 'authorization_code',
        ];

        if ($codeVerifier) {
            $body['code_verifier'] = $codeVerifier;
        }

        $response = wp_remote_post(self::TOKEN_URL, [
            'body'    => $body,
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            throw new \RuntimeException('Token exchange failed: ' . $response->get_error_message());
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);

        if (empty($data['access_token'])) {
            $error = $data['error_description'] ?? $data['error'] ?? 'Unknown error';
            throw new \RuntimeException('Token exchange failed: ' . $error);
        }

        return $data;
    }

    public function getUserInfo(string $accessToken): array
    {
        $response = wp_remote_get(self::USERINFO_URL, [
            'headers' => ['Authorization' => 'Bearer ' . $accessToken],
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            throw new \RuntimeException('User info request failed: ' . $response->get_error_message());
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);

        if (empty($data['id'])) {
            throw new \RuntimeException('Invalid user info response from Google.');
        }

        return [
            'id'             => (string) $data['id'],
            'email'          => $data['email'] ?? '',
            'name'           => $data['name'] ?? '',
            'email_verified' => $data['verified_email'] ?? false,
            'given_name'     => $data['given_name'] ?? '',
            'family_name'    => $data['family_name'] ?? '',
        ];
    }

    private function getClientId(): string
    {
        $settings = get_option('wsms_auth_settings', []);

        return $settings['social']['google']['client_id'] ?? '';
    }

    private function getClientSecret(): string
    {
        $settings = get_option('wsms_auth_settings', []);

        return $settings['social']['google']['client_secret'] ?? '';
    }
}
