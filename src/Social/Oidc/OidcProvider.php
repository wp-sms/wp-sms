<?php

namespace WSms\Social\Oidc;

use WSms\Social\Contracts\SocialProviderInterface;
use WSms\Social\OAuthStateManager;

defined('ABSPATH') || exit;

class OidcProvider implements SocialProviderInterface
{
    private ?string $idToken = null;

    public function __construct(
        private OidcConfig $config,
        private OidcDiscovery $discovery,
        private JwtValidator $jwtValidator,
    ) {
    }

    public function getId(): string
    {
        return $this->config->id;
    }

    public function getName(): string
    {
        return $this->config->name;
    }

    public function getIconSvg(): string
    {
        return $this->config->iconSvg;
    }

    public function isTrustedEmailProvider(): bool
    {
        return $this->config->isTrustedEmail;
    }

    public function createAuthorizationURL(string $redirectUri, string $state, ?string $codeVerifier = null): array
    {
        $doc = $this->discovery->fetch($this->config->discoveryUrl);

        $params = [
            'client_id'     => $this->config->clientId,
            'redirect_uri'  => $redirectUri,
            'response_type' => 'code',
            'scope'         => implode(' ', $this->config->scopes),
            'state'         => $state,
        ];

        if ($codeVerifier) {
            $params['code_challenge'] = OAuthStateManager::codeChallenge($codeVerifier);
            $params['code_challenge_method'] = 'S256';
        }

        return [
            'url'           => $doc['authorization_endpoint'] . '?' . http_build_query($params),
            'state'         => $state,
            'code_verifier' => $codeVerifier,
        ];
    }

    public function exchangeCode(string $code, string $redirectUri, ?string $codeVerifier = null): array
    {
        $doc = $this->discovery->fetch($this->config->discoveryUrl);

        $body = [
            'grant_type'   => 'authorization_code',
            'code'         => $code,
            'redirect_uri' => $redirectUri,
            'client_id'    => $this->config->clientId,
        ];

        if ($codeVerifier) {
            $body['code_verifier'] = $codeVerifier;
        }

        $authMethod = $this->resolveTokenAuthMethod($doc);
        $headers = ['Content-Type' => 'application/x-www-form-urlencoded'];

        if ($authMethod === 'basic') {
            $headers['Authorization'] = 'Basic ' . base64_encode(
                $this->config->clientId . ':' . $this->config->clientSecret
            );
        } else {
            $body['client_secret'] = $this->config->clientSecret;
        }

        $response = wp_remote_post($doc['token_endpoint'], [
            'body'    => $body,
            'headers' => $headers,
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

        // Store id_token for getUserInfo().
        if (!empty($data['id_token'])) {
            $this->idToken = $data['id_token'];
        }

        return $data;
    }

    public function getUserInfo(string $accessToken): array
    {
        if ($this->idToken === null) {
            throw new \RuntimeException('No id_token available. Call exchangeCode() first.');
        }

        $doc = $this->discovery->fetch($this->config->discoveryUrl);

        $payload = $this->jwtValidator->validate(
            $this->idToken,
            $doc['jwks_uri'],
            $doc['issuer'],
            $this->config->clientId,
        );

        // Normalize phone to E.164 (Telegram returns digits without '+' prefix).
        $phone = $payload['phone_number'] ?? null;

        if ($phone !== null && $phone !== '' && $phone[0] !== '+') {
            $phone = '+' . $phone;
        }

        return [
            'id'                  => $payload['sub'] ?? (string) ($payload['id'] ?? ''),
            'email'               => $payload['email'] ?? '',
            'name'                => $payload['name'] ?? '',
            'email_verified'      => $payload['email_verified'] ?? false,
            'given_name'          => $payload['given_name'] ?? $payload['name'] ?? '',
            'family_name'         => $payload['family_name'] ?? '',
            'phone_number'        => $phone,
            'picture'             => $payload['picture'] ?? null,
            'preferred_username'  => $payload['preferred_username'] ?? null,
        ];
    }

    private function resolveTokenAuthMethod(array $doc): string
    {
        // Explicit override from config.
        if ($this->config->tokenAuthMethod !== null) {
            return $this->config->tokenAuthMethod;
        }

        // Auto-detect from discovery document.
        $supported = $doc['token_endpoint_auth_methods_supported'] ?? ['client_secret_basic'];

        if (in_array('client_secret_basic', $supported, true)) {
            return 'basic';
        }

        return 'post';
    }
}
