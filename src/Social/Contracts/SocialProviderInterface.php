<?php

namespace WSms\Social\Contracts;

defined('ABSPATH') || exit;

interface SocialProviderInterface
{
    public function getId(): string;

    public function getName(): string;

    public function getIconSvg(): string;

    /**
     * Whether the provider's email can be trusted for auto-linking.
     * True for providers that verify email ownership (Google, Apple, Microsoft).
     */
    public function isTrustedEmailProvider(): bool;

    /**
     * Build the authorization URL for the OAuth flow.
     *
     * @return array{url: string, state: string, code_verifier?: string}
     */
    public function createAuthorizationURL(string $redirectUri, string $state, ?string $codeVerifier = null): array;

    /**
     * Exchange an authorization code for tokens.
     *
     * @return array{access_token: string, refresh_token?: string, expires_in?: int, token_type?: string}
     */
    public function exchangeCode(string $code, string $redirectUri, ?string $codeVerifier = null): array;

    /**
     * Fetch user info from the provider.
     *
     * @return array{id: string, email: string, name: string, email_verified: bool, given_name?: string, family_name?: string}
     */
    public function getUserInfo(string $accessToken): array;
}
