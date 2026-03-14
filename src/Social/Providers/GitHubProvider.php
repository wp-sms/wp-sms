<?php

namespace WSms\Social\Providers;

use WSms\Social\Contracts\SocialProviderInterface;
use WSms\Social\OAuthStateManager;

defined('ABSPATH') || exit;

class GitHubProvider implements SocialProviderInterface
{
    private const AUTH_URL = 'https://github.com/login/oauth/authorize';
    private const TOKEN_URL = 'https://github.com/login/oauth/access_token';
    private const USER_URL = 'https://api.github.com/user';
    private const EMAILS_URL = 'https://api.github.com/user/emails';
    private const SCOPES = 'read:user user:email';

    public function getId(): string
    {
        return 'github';
    }

    public function getName(): string
    {
        return 'GitHub';
    }

    public function getIconSvg(): string
    {
        return '<svg viewBox="0 0 24 24" width="16" height="16"><path d="M12 .297c-6.63 0-12 5.373-12 12 0 5.303 3.438 9.8 8.205 11.385.6.113.82-.258.82-.577 0-.285-.01-1.04-.015-2.04-3.338.724-4.042-1.61-4.042-1.61C4.422 18.07 3.633 17.7 3.633 17.7c-1.087-.744.084-.729.084-.729 1.205.084 1.838 1.236 1.838 1.236 1.07 1.835 2.809 1.305 3.495.998.108-.776.417-1.305.76-1.605-2.665-.3-5.466-1.332-5.466-5.93 0-1.31.465-2.38 1.235-3.22-.135-.303-.54-1.523.105-3.176 0 0 1.005-.322 3.3 1.23.96-.267 1.98-.399 3-.405 1.02.006 2.04.138 3 .405 2.28-1.552 3.285-1.23 3.285-1.23.645 1.653.24 2.873.12 3.176.765.84 1.23 1.91 1.23 3.22 0 4.61-2.805 5.625-5.475 5.92.42.36.81 1.096.81 2.22 0 1.606-.015 2.896-.015 3.286 0 .315.21.69.825.57C20.565 22.092 24 17.592 24 12.297c0-6.627-5.373-12-12-12" fill="currentColor"/></svg>';
    }

    public function isTrustedEmailProvider(): bool
    {
        return true;
    }

    public function createAuthorizationURL(string $redirectUri, string $state, ?string $codeVerifier = null): array
    {
        $params = [
            'client_id'     => $this->getClientId(),
            'redirect_uri'  => $redirectUri,
            'response_type' => 'code',
            'scope'         => self::SCOPES,
            'state'         => $state,
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
        ];

        if ($codeVerifier) {
            $body['code_verifier'] = $codeVerifier;
        }

        $response = wp_remote_post(self::TOKEN_URL, [
            'body'    => $body,
            'headers' => ['Accept' => 'application/json'],
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
        $headers = [
            'Authorization' => 'Bearer ' . $accessToken,
            'User-Agent'    => 'WP-SMS-Plugin',
            'Accept'        => 'application/vnd.github+json',
        ];

        $response = wp_remote_get(self::USER_URL, [
            'headers' => $headers,
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            throw new \RuntimeException('User info request failed: ' . $response->get_error_message());
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);

        if (empty($data['id'])) {
            throw new \RuntimeException('Invalid user info response from GitHub.');
        }

        $email = $data['email'] ?? null;

        // GitHub may return null email when the user has a private email.
        // Fetch from /user/emails endpoint to get the primary verified email.
        if ($email === null) {
            [$email, $emailVerified] = $this->fetchPrimaryEmail($headers);
        } else {
            $emailVerified = true;
        }

        return [
            'id'             => (string) $data['id'],
            'email'          => $email ?? '',
            'name'           => $data['name'] ?? $data['login'] ?? '',
            'email_verified' => $emailVerified,
            'picture'        => $data['avatar_url'] ?? '',
        ];
    }

    /**
     * Fetch the primary verified email from GitHub's /user/emails endpoint.
     *
     * @return array{0: ?string, 1: bool} [email, verified]
     */
    private function fetchPrimaryEmail(array $headers): array
    {
        $response = wp_remote_get(self::EMAILS_URL, [
            'headers' => $headers,
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            return [null, false];
        }

        $emails = json_decode(wp_remote_retrieve_body($response), true);

        if (!is_array($emails) || empty($emails)) {
            return [null, false];
        }

        // Prefer primary email, then first verified, then first available.
        $primary = null;
        $firstVerified = null;

        foreach ($emails as $entry) {
            if (!empty($entry['primary'])) {
                $primary = $entry;
                break;
            }
            if ($firstVerified === null && !empty($entry['verified'])) {
                $firstVerified = $entry;
            }
        }

        $chosen = $primary ?? $firstVerified ?? $emails[0];

        return [$chosen['email'] ?? null, !empty($chosen['verified'])];
    }

    private function getClientId(): string
    {
        $settings = get_option('wsms_auth_settings', []);

        return $settings['social'][$this->getId()]['client_id'] ?? '';
    }

    private function getClientSecret(): string
    {
        $settings = get_option('wsms_auth_settings', []);

        return $settings['social'][$this->getId()]['client_secret'] ?? '';
    }
}
