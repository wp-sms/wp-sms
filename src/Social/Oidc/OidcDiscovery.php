<?php

namespace WSms\Social\Oidc;

defined('ABSPATH') || exit;

class OidcDiscovery
{
    private const TRANSIENT_PREFIX = 'wsms_oidc_disco_';
    private const TTL = 86400; // 24 hours
    private const TIMEOUT = 10;

    private const REQUIRED_FIELDS = [
        'issuer',
        'authorization_endpoint',
        'token_endpoint',
        'jwks_uri',
    ];

    /**
     * Fetch and cache the OIDC discovery document.
     *
     * @return array Discovery document fields.
     * @throws \RuntimeException On fetch/validation failure.
     */
    public function fetch(string $discoveryUrl): array
    {
        $this->validateUrl($discoveryUrl);

        $cacheKey = self::TRANSIENT_PREFIX . md5($discoveryUrl);
        $cached = get_transient($cacheKey);

        if ($cached !== false) {
            return $cached;
        }

        $response = wp_remote_get($discoveryUrl, [
            'timeout' => self::TIMEOUT,
        ]);

        if (is_wp_error($response)) {
            $message = $response->get_error_message();

            if (str_contains($message, 'timed out') || str_contains($message, 'timeout')) {
                throw new \RuntimeException('discovery_timeout: OIDC discovery request timed out.');
            }

            throw new \RuntimeException('discovery_not_found: ' . $message);
        }

        $statusCode = wp_remote_retrieve_response_code($response);

        if ($statusCode !== 200) {
            throw new \RuntimeException("discovery_not_found: Discovery endpoint returned HTTP {$statusCode}.");
        }

        $doc = json_decode(wp_remote_retrieve_body($response), true);

        if (!is_array($doc)) {
            throw new \RuntimeException('discovery_not_found: Invalid JSON in discovery document.');
        }

        $this->validateRequiredFields($doc);
        $this->validateIssuerMatch($doc, $discoveryUrl);

        set_transient($cacheKey, $doc, self::TTL);

        return $doc;
    }

    /**
     * Clear cached discovery document.
     */
    public function clearCache(string $discoveryUrl): void
    {
        delete_transient(self::TRANSIENT_PREFIX . md5($discoveryUrl));
    }

    private function validateUrl(string $url): void
    {
        $scheme = parse_url($url, PHP_URL_SCHEME);

        if ($scheme !== 'https') {
            throw new \RuntimeException('discovery_not_found: Discovery URL must use HTTPS.');
        }
    }

    private function validateRequiredFields(array $doc): void
    {
        foreach (self::REQUIRED_FIELDS as $field) {
            if (empty($doc[$field])) {
                throw new \RuntimeException("missing_required_field: Discovery document missing '{$field}'.");
            }
        }
    }

    private function validateIssuerMatch(array $doc, string $discoveryUrl): void
    {
        $issuer = rtrim($doc['issuer'], '/');
        $expectedBase = preg_replace('#/\.well-known/openid-configuration$#', '', $discoveryUrl);
        $expectedBase = rtrim($expectedBase, '/');

        if ($issuer !== $expectedBase) {
            throw new \RuntimeException(
                "issuer_mismatch: Issuer '{$doc['issuer']}' does not match discovery URL base '{$expectedBase}'."
            );
        }
    }
}
