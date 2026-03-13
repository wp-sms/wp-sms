<?php

namespace WSms\Social;

defined('ABSPATH') || exit;

class OAuthStateManager
{
    private const TRANSIENT_PREFIX = 'wsms_oauth_state_';
    private const TTL = 600; // 10 minutes

    /**
     * Create a new OAuth state with PKCE verifier.
     *
     * @return array{state: string, code_verifier: string}
     */
    public function create(?int $linkUserId = null): array
    {
        $state = bin2hex(random_bytes(16));
        $codeVerifier = $this->generateCodeVerifier();

        $data = [
            'code_verifier' => $codeVerifier,
            'created_at'    => time(),
        ];

        if ($linkUserId !== null) {
            $data['link_user_id'] = $linkUserId;
        }

        set_transient(self::TRANSIENT_PREFIX . $state, $data, self::TTL);

        return [
            'state'         => $state,
            'code_verifier' => $codeVerifier,
        ];
    }

    /**
     * Validate and consume a state parameter. Returns null if invalid/expired.
     *
     * @return array{code_verifier: string, link_user_id?: int}|null
     */
    public function consume(string $state): ?array
    {
        $data = get_transient(self::TRANSIENT_PREFIX . $state);

        if ($data === false) {
            return null;
        }

        // Consume immediately — one-time use.
        delete_transient(self::TRANSIENT_PREFIX . $state);

        return $data;
    }

    /**
     * Generate a PKCE code verifier (RFC 7636).
     */
    private function generateCodeVerifier(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    /**
     * Generate a PKCE code challenge from a verifier.
     */
    public static function codeChallenge(string $verifier): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    }
}
