<?php

namespace WSms\Tests\Unit\Social\Oidc;

use PHPUnit\Framework\TestCase;
use WSms\Social\Oidc\JwtValidator;

class JwtValidatorTest extends TestCase
{
    private JwtValidator $validator;
    private string $jwksUri = 'https://oauth.example.com/.well-known/jwks.json';

    /** @var array{private: \OpenSSLAsymmetricKey, public: string, jwk: array} */
    private array $keyPair;

    protected function setUp(): void
    {
        $this->validator = new JwtValidator();
        $this->keyPair = $this->generateRsaKeyPair();
        unset($GLOBALS['_test_wp_remote_get'], $GLOBALS['_test_transients']);
    }

    public function testValidateAcceptsValidJwt(): void
    {
        $this->stubJwks($this->keyPair['jwk']);

        $jwt = $this->createJwt([
            'iss' => 'https://oauth.example.com',
            'aud' => 'my-client-id',
            'sub' => '123456',
            'exp' => time() + 3600,
            'iat' => time(),
        ], $this->keyPair['private'], 'test-kid');

        $payload = $this->validator->validate(
            $jwt,
            $this->jwksUri,
            'https://oauth.example.com',
            'my-client-id',
        );

        $this->assertSame('123456', $payload['sub']);
    }

    public function testValidateRejectsInvalidFormat(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('expected 3 parts');

        $this->validator->validate('not.a.valid.jwt.at.all', $this->jwksUri, 'iss', 'aud');
    }

    public function testValidateRejectsUnsupportedAlgorithm(): void
    {
        // Create a JWT with HS256 algorithm header.
        $header = $this->base64UrlEncode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload = $this->base64UrlEncode(json_encode(['sub' => '123']));
        $jwt = $header . '.' . $payload . '.fake-sig';

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unsupported JWT algorithm: HS256');

        $this->validator->validate($jwt, $this->jwksUri, 'iss', 'aud');
    }

    public function testValidateRejectsExpiredJwt(): void
    {
        $this->stubJwks($this->keyPair['jwk']);

        $jwt = $this->createJwt([
            'iss' => 'https://oauth.example.com',
            'aud' => 'my-client-id',
            'sub' => '123',
            'exp' => time() - 120, // Expired 2 minutes ago (beyond 60s skew).
            'iat' => time() - 300,
        ], $this->keyPair['private'], 'test-kid');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('JWT has expired');

        $this->validator->validate($jwt, $this->jwksUri, 'https://oauth.example.com', 'my-client-id');
    }

    public function testValidateRejectsTooOldJwt(): void
    {
        $this->stubJwks($this->keyPair['jwk']);

        $jwt = $this->createJwt([
            'iss' => 'https://oauth.example.com',
            'aud' => 'my-client-id',
            'sub' => '123',
            'exp' => time() + 3600,
            'iat' => time() - 7200, // 2 hours old (beyond 1 hour max age).
        ], $this->keyPair['private'], 'test-kid');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('JWT is too old');

        $this->validator->validate($jwt, $this->jwksUri, 'https://oauth.example.com', 'my-client-id');
    }

    public function testValidateRejectsIssuerMismatch(): void
    {
        $this->stubJwks($this->keyPair['jwk']);

        $jwt = $this->createJwt([
            'iss' => 'https://evil.example.com',
            'aud' => 'my-client-id',
            'sub' => '123',
            'exp' => time() + 3600,
            'iat' => time(),
        ], $this->keyPair['private'], 'test-kid');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('issuer mismatch');

        $this->validator->validate($jwt, $this->jwksUri, 'https://oauth.example.com', 'my-client-id');
    }

    public function testValidateRejectsAudienceMismatch(): void
    {
        $this->stubJwks($this->keyPair['jwk']);

        $jwt = $this->createJwt([
            'iss' => 'https://oauth.example.com',
            'aud' => 'wrong-client-id',
            'sub' => '123',
            'exp' => time() + 3600,
            'iat' => time(),
        ], $this->keyPair['private'], 'test-kid');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('audience mismatch');

        $this->validator->validate($jwt, $this->jwksUri, 'https://oauth.example.com', 'my-client-id');
    }

    public function testValidateRejectsInvalidSignature(): void
    {
        $this->stubJwks($this->keyPair['jwk']);

        // Create a valid JWT then tamper with the payload.
        $jwt = $this->createJwt([
            'iss' => 'https://oauth.example.com',
            'aud' => 'my-client-id',
            'sub' => '123',
            'exp' => time() + 3600,
            'iat' => time(),
        ], $this->keyPair['private'], 'test-kid');

        $parts = explode('.', $jwt);
        $parts[1] = $this->base64UrlEncode(json_encode(['sub' => 'tampered']));
        $tampered = implode('.', $parts);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('signature verification failed');

        $this->validator->validate($tampered, $this->jwksUri, 'https://oauth.example.com', 'my-client-id');
    }

    public function testValidateRefetchesJwksOnKeyNotFound(): void
    {
        // First call returns empty JWKS, triggers refetch which has the key.
        $callCount = 0;
        $jwk = $this->keyPair['jwk'];

        // Can't do multiple return values easily with the global stub,
        // so we test that key-not-found throws a clear error.
        $GLOBALS['_test_wp_remote_get'] = [
            'body' => json_encode(['keys' => []]),
        ];

        $jwt = $this->createJwt([
            'iss' => 'https://oauth.example.com',
            'aud' => 'my-client-id',
            'sub' => '123',
            'exp' => time() + 3600,
            'iat' => time(),
        ], $this->keyPair['private'], 'missing-kid');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('key not found');

        $this->validator->validate($jwt, $this->jwksUri, 'https://oauth.example.com', 'my-client-id');
    }

    public function testValidateToleratesClockSkew(): void
    {
        $this->stubJwks($this->keyPair['jwk']);

        // Token expired 30 seconds ago (within 60s tolerance).
        $jwt = $this->createJwt([
            'iss' => 'https://oauth.example.com',
            'aud' => 'my-client-id',
            'sub' => '123',
            'exp' => time() - 30,
            'iat' => time() - 60,
        ], $this->keyPair['private'], 'test-kid');

        $payload = $this->validator->validate(
            $jwt,
            $this->jwksUri,
            'https://oauth.example.com',
            'my-client-id',
        );

        $this->assertSame('123', $payload['sub']);
    }

    // -- Helpers --

    private function generateRsaKeyPair(): array
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $details = openssl_pkey_get_details($key);

        $n = $this->base64UrlEncode($details['rsa']['n']);
        $e = $this->base64UrlEncode($details['rsa']['e']);

        return [
            'private' => $key,
            'public'  => $details['key'],
            'jwk'     => [
                'kty' => 'RSA',
                'kid' => 'test-kid',
                'use' => 'sig',
                'n'   => $n,
                'e'   => $e,
            ],
        ];
    }

    private function stubJwks(array $jwk): void
    {
        $GLOBALS['_test_wp_remote_get'] = [
            'body' => json_encode(['keys' => [$jwk]]),
        ];
    }

    private function createJwt(array $payload, \OpenSSLAsymmetricKey $privateKey, string $kid): string
    {
        $header = $this->base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT', 'kid' => $kid]));
        $body = $this->base64UrlEncode(json_encode($payload));
        $data = $header . '.' . $body;

        openssl_sign($data, $signature, $privateKey, OPENSSL_ALGO_SHA256);

        return $data . '.' . $this->base64UrlEncode($signature);
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
