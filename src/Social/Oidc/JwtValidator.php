<?php

namespace WSms\Social\Oidc;

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
        $parts = explode('.', $jwt);

        if (count($parts) !== 3) {
            throw new \RuntimeException('Invalid JWT: expected 3 parts.');
        }

        [$headerB64, $payloadB64, $signatureB64] = $parts;

        $header = $this->base64UrlDecode($headerB64, true);
        $payload = $this->base64UrlDecode($payloadB64, true);

        if (!is_array($header) || !is_array($payload)) {
            throw new \RuntimeException('Invalid JWT: unable to decode header or payload.');
        }

        $alg = $header['alg'] ?? '';

        if ($alg !== 'RS256') {
            throw new \RuntimeException("Unsupported JWT algorithm: {$alg}. Only RS256 is supported.");
        }

        $kid = $header['kid'] ?? null;

        // Verify signature.
        $pem = $this->getPublicKey($jwksUri, $kid);
        $signedData = $headerB64 . '.' . $payloadB64;
        $signature = $this->base64UrlDecode($signatureB64);

        $verified = openssl_verify($signedData, $signature, $pem, OPENSSL_ALGO_SHA256);

        if ($verified !== 1) {
            throw new \RuntimeException('JWT signature verification failed.');
        }

        // Validate claims.
        $this->validateClaims($payload, $expectedIssuer, $expectedAudience);

        return $payload;
    }

    private function getPublicKey(string $jwksUri, ?string $kid): string
    {
        $jwks = $this->fetchJwks($jwksUri);
        $key = $this->findKey($jwks, $kid);

        if ($key === null) {
            // Key rotation: refetch JWKS and try again.
            $this->clearJwksCache($jwksUri);
            $jwks = $this->fetchJwks($jwksUri);
            $key = $this->findKey($jwks, $kid);

            if ($key === null) {
                throw new \RuntimeException('JWT key not found in JWKS' . ($kid ? " (kid: {$kid})" : '') . '.');
            }
        }

        return $this->jwkToPem($key);
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

    private function findKey(array $jwks, ?string $kid): ?array
    {
        foreach ($jwks['keys'] ?? [] as $key) {
            if ($kid === null || ($key['kid'] ?? null) === $kid) {
                if (($key['kty'] ?? '') === 'RSA' && ($key['use'] ?? 'sig') === 'sig') {
                    return $key;
                }
            }
        }

        return null;
    }

    private function jwkToPem(array $jwk): string
    {
        if (empty($jwk['n']) || empty($jwk['e'])) {
            throw new \RuntimeException('Invalid RSA JWK: missing n or e.');
        }

        $n = $this->base64UrlDecode($jwk['n']);
        $e = $this->base64UrlDecode($jwk['e']);

        // Build DER-encoded RSA public key.
        $modulus = $this->encodeAsn1Integer($n);
        $exponent = $this->encodeAsn1Integer($e);

        $rsaPublicKey = chr(0x30) . $this->encodeAsn1Length(strlen($modulus) + strlen($exponent))
            . $modulus . $exponent;

        // Wrap in SubjectPublicKeyInfo.
        $rsaOid = hex2bin('300d06092a864886f70d0101010500');
        $bitString = chr(0x03) . $this->encodeAsn1Length(strlen($rsaPublicKey) + 1) . chr(0x00) . $rsaPublicKey;

        $der = chr(0x30) . $this->encodeAsn1Length(strlen($rsaOid) + strlen($bitString))
            . $rsaOid . $bitString;

        return "-----BEGIN PUBLIC KEY-----\n"
            . chunk_split(base64_encode($der), 64, "\n")
            . "-----END PUBLIC KEY-----\n";
    }

    private function encodeAsn1Integer(string $data): string
    {
        // Prepend 0x00 if high bit is set (positive integer).
        if (ord($data[0]) & 0x80) {
            $data = chr(0x00) . $data;
        }

        return chr(0x02) . $this->encodeAsn1Length(strlen($data)) . $data;
    }

    private function encodeAsn1Length(int $length): string
    {
        if ($length < 0x80) {
            return chr($length);
        }

        $bytes = '';
        $temp = $length;

        while ($temp > 0) {
            $bytes = chr($temp & 0xFF) . $bytes;
            $temp >>= 8;
        }

        return chr(0x80 | strlen($bytes)) . $bytes;
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

        $now = time();

        // Expiration.
        $exp = $payload['exp'] ?? 0;

        if ($exp < $now - self::CLOCK_SKEW) {
            throw new \RuntimeException('JWT has expired.');
        }

        // Issued at — max token age.
        $iat = $payload['iat'] ?? 0;

        if ($iat < $now - self::MAX_TOKEN_AGE - self::CLOCK_SKEW) {
            throw new \RuntimeException('JWT is too old.');
        }
    }

    /**
     * @return ($asJson is true ? array|null : string)
     */
    private function base64UrlDecode(string $input, bool $asJson = false): array|string|null
    {
        $decoded = base64_decode(strtr($input, '-_', '+/'), true);

        if ($decoded === false) {
            return $asJson ? null : '';
        }

        if ($asJson) {
            return json_decode($decoded, true);
        }

        return $decoded;
    }
}
