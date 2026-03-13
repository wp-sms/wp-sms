<?php

namespace WSms\Social\Oidc;

use WSms\Dependencies\Firebase\JWT\JWT;
use WSms\Dependencies\Firebase\JWT\JWK;
use WSms\Dependencies\Firebase\JWT\SignatureInvalidException;
use WSms\Dependencies\Firebase\JWT\ExpiredException;
use WSms\Dependencies\Firebase\JWT\BeforeValidException;

defined('ABSPATH') || exit;

class JwtValidator
{
    private const JWKS_TRANSIENT_PREFIX = 'wsms_oidc_jwks_';
    private const JWKS_TTL = 3600; // 1 hour
    private const CLOCK_SKEW = 60; // seconds
    private const MAX_TOKEN_AGE = 3600; // 1 hour

    /**
     * Validate a JWT id_token using JWKS.
     *
     * @return array Decoded payload claims.
     * @throws \RuntimeException On validation failure.
     */
    public function validate(string $jwt, string $jwksUri, string $expectedIssuer, string $expectedAudience): array
    {
        $previousLeeway = JWT::$leeway;
        JWT::$leeway = self::CLOCK_SKEW;

        try {
            $decoded = $this->decodeJwt($jwt, $jwksUri);
        } catch (\InvalidArgumentException|\UnexpectedValueException $e) {
            if (!$this->isKeyNotFoundError($e)) {
                throw new \RuntimeException('Invalid JWT: ' . $e->getMessage());
            }

            // Key not found or empty JWKS — try cache-busting refetch.
            $this->clearJwksCache($jwksUri);
            try {
                $decoded = $this->decodeJwt($jwt, $jwksUri);
            } catch (\Exception $retryEx) {
                throw new \RuntimeException('JWT key not found in JWKS.');
            }
        } finally {
            JWT::$leeway = $previousLeeway;
        }

        $payload = (array) $decoded;
        $this->validateClaims($payload, $expectedIssuer, $expectedAudience);

        return $payload;
    }

    /**
     * @throws SignatureInvalidException|ExpiredException|BeforeValidException
     * @throws \InvalidArgumentException|\UnexpectedValueException
     */
    private function decodeJwt(string $jwt, string $jwksUri): \stdClass
    {
        $keys = $this->getKeys($jwksUri);

        try {
            return JWT::decode($jwt, $keys);
        } catch (SignatureInvalidException $e) {
            throw new \RuntimeException('JWT signature verification failed.');
        } catch (ExpiredException $e) {
            throw new \RuntimeException('JWT has expired.');
        } catch (BeforeValidException $e) {
            throw new \RuntimeException('JWT is not yet valid.');
        }
    }

    /**
     * Check if an exception indicates a JWKS key lookup failure (vs a JWT structure error).
     *
     * InvalidArgumentException comes from JWK::parseKeySet() for empty key sets.
     * UnexpectedValueException with "kid" comes from JWT::getKey() for missing keys.
     */
    private function isKeyNotFoundError(\InvalidArgumentException|\UnexpectedValueException $e): bool
    {
        return $e instanceof \InvalidArgumentException
            || str_contains($e->getMessage(), '"kid"');
    }

    private function getKeys(string $jwksUri): array
    {
        $jwks = $this->fetchJwks($jwksUri);

        return JWK::parseKeySet($jwks, 'RS256');
    }

    private function fetchJwks(string $jwksUri): array
    {
        $cacheKey = self::JWKS_TRANSIENT_PREFIX . md5($jwksUri);
        $cached = get_transient($cacheKey);

        if ($cached !== false) {
            return $cached;
        }

        $response = wp_remote_get($jwksUri, ['timeout' => 10]);

        if (is_wp_error($response)) {
            throw new \RuntimeException('Failed to fetch JWKS: ' . $response->get_error_message());
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);

        if (!is_array($data) || !isset($data['keys'])) {
            throw new \RuntimeException('Invalid JWKS response.');
        }

        set_transient($cacheKey, $data, self::JWKS_TTL);

        return $data;
    }

    private function clearJwksCache(string $jwksUri): void
    {
        delete_transient(self::JWKS_TRANSIENT_PREFIX . md5($jwksUri));
    }

    private function validateClaims(array $payload, string $expectedIssuer, string $expectedAudience): void
    {
        // Issuer.
        $issuer = rtrim($payload['iss'] ?? '', '/');
        $expected = rtrim($expectedIssuer, '/');

        if ($issuer !== $expected) {
            throw new \RuntimeException("JWT issuer mismatch: expected '{$expected}', got '{$issuer}'.");
        }

        // Audience.
        $aud = $payload['aud'] ?? '';
        $audiences = is_array($aud) ? $aud : [$aud];

        if (!in_array($expectedAudience, $audiences, true)) {
            throw new \RuntimeException('JWT audience mismatch.');
        }

        // Issued at — max token age (library handles exp and future iat).
        $now = time();
        $iat = $payload['iat'] ?? 0;

        if ($iat < $now - self::MAX_TOKEN_AGE - self::CLOCK_SKEW) {
            throw new \RuntimeException('JWT is too old.');
        }
    }
}
