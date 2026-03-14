<?php

namespace WSms\Tests\Unit\Verification;

use PHPUnit\Framework\TestCase;
use WSms\Verification\VerificationConfig;
use WSms\Verification\VerificationSession;

class VerificationSessionTest extends TestCase
{
    private VerificationSession $session;

    protected function setUp(): void
    {
        $GLOBALS['_test_options'] = [];
        $GLOBALS['_test_transients'] = [];
        $this->session = new VerificationSession(new VerificationConfig());
    }

    protected function tearDown(): void
    {
        $GLOBALS['_test_options'] = [];
        $GLOBALS['_test_transients'] = [];
    }

    public function testCreateReturnsSessionIdAndToken(): void
    {
        $result = $this->session->create();

        $this->assertArrayHasKey('session_id', $result);
        $this->assertArrayHasKey('token', $result);
        $this->assertNotEmpty($result['session_id']);
        $this->assertNotEmpty($result['token']);
    }

    public function testValidateReturnsSessionData(): void
    {
        $created = $this->session->create();
        $data = $this->session->validate($created['token']);

        $this->assertNotNull($data);
        $this->assertSame($created['session_id'], $data['session_id']);
        $this->assertArrayHasKey('verified', $data);
        $this->assertArrayHasKey('created_at', $data);
    }

    public function testValidateReturnsNullForInvalidToken(): void
    {
        $this->assertNull($this->session->validate('not-valid-base64!!!'));
    }

    public function testValidateReturnsNullForTamperedSignature(): void
    {
        $created = $this->session->create();

        $decoded = base64_decode($created['token'], true);
        $parts = explode('|', $decoded);
        $parts[2] = str_repeat('a', 64); // fake signature
        $tampered = base64_encode(implode('|', $parts));

        $this->assertNull($this->session->validate($tampered));
    }

    public function testValidateReturnsNullForExpiredToken(): void
    {
        $created = $this->session->create();

        // Forge a token with past expiry.
        $decoded = base64_decode($created['token'], true);
        $parts = explode('|', $decoded);
        $parts[1] = (string) (time() - 100);
        $payload = $parts[0] . '|' . $parts[1];
        $signature = hash_hmac('sha256', $payload, AUTH_KEY);
        $forged = base64_encode($payload . '|' . $signature);

        $this->assertNull($this->session->validate($forged));
    }

    public function testMarkVerifiedAndIsVerified(): void
    {
        $created = $this->session->create();
        $sessionId = $created['session_id'];

        // Before marking — not verified.
        $data = $this->session->validate($created['token']);
        $this->assertEmpty($data['verified']['email']);

        $this->session->markVerified($sessionId, 'email', 'test@example.com');

        // After marking — verified for that identifier only.
        $data = $this->session->validate($created['token']);
        $this->assertArrayHasKey('test@example.com', $data['verified']['email']);
        $this->assertEmpty($data['verified']['phone']);
    }

    public function testDestroyInvalidatesSession(): void
    {
        $created = $this->session->create();

        $this->session->destroy($created['session_id']);

        $this->assertNull($this->session->validate($created['token']));
    }

    public function testMarkVerifiedWithPreloadedData(): void
    {
        $created = $this->session->create();
        $sessionId = $created['session_id'];
        $sessionData = $this->session->validate($created['token']);

        $this->session->markVerified($sessionId, 'email', 'test@example.com', $sessionData);

        $data = $this->session->validate($created['token']);
        $this->assertArrayHasKey('test@example.com', $data['verified']['email']);
    }

    public function testCreateStoresExpiresAt(): void
    {
        $created = $this->session->create();
        $data = $this->session->validate($created['token']);

        $this->assertArrayHasKey('expires_at', $data);
        $this->assertGreaterThan(time(), $data['expires_at']);
    }

    public function testMultipleVerificationsOnSameSession(): void
    {
        $created = $this->session->create();
        $sessionId = $created['session_id'];

        $this->session->markVerified($sessionId, 'email', 'test@example.com');
        $this->session->markVerified($sessionId, 'phone', '+1234567890');

        $data = $this->session->validate($created['token']);
        $this->assertArrayHasKey('test@example.com', $data['verified']['email']);
        $this->assertArrayHasKey('+1234567890', $data['verified']['phone']);
    }

    public function testSeparateSessionsAreIndependent(): void
    {
        $session1 = $this->session->create();
        $session2 = $this->session->create();

        $this->session->markVerified($session1['session_id'], 'email', 'test@example.com');

        $data1 = $this->session->validate($session1['token']);
        $data2 = $this->session->validate($session2['token']);

        $this->assertArrayHasKey('test@example.com', $data1['verified']['email']);
        $this->assertEmpty($data2['verified']['email']);
    }
}
