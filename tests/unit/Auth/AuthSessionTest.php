<?php

namespace WSms\Tests\Unit\Auth;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use WSms\Auth\AuthSession;
use WSms\Enums\SessionStage;
use WSms\Mfa\OtpGenerator;

class AuthSessionTest extends TestCase
{
    private AuthSession $session;
    private MockObject&OtpGenerator $otpGenerator;

    protected function setUp(): void
    {
        $this->otpGenerator = $this->createMock(OtpGenerator::class);
        $this->session = new AuthSession($this->otpGenerator);
        $GLOBALS['_test_transients'] = [];
    }

    protected function tearDown(): void
    {
        $GLOBALS['_test_transients'] = [];
    }

    public function testCreateReturnsBase64Token(): void
    {
        $this->otpGenerator->method('generateToken')->willReturn('abc123sessionkey');

        $token = $this->session->create(1, 'password', SessionStage::PrimaryVerified);

        $this->assertNotEmpty($token);
        $this->assertNotFalse(base64_decode($token, true));
    }

    public function testValidateReturnsSessionData(): void
    {
        $this->otpGenerator->method('generateToken')->willReturn('sessionkey123456');

        $token = $this->session->create(42, 'password', SessionStage::PrimaryVerified);
        $data = $this->session->validate($token);

        $this->assertNotNull($data);
        $this->assertSame(42, $data['user_id']);
        $this->assertSame('password', $data['method']);
        $this->assertSame('primary_verified', $data['stage']);
        $this->assertArrayHasKey('session_key', $data);
    }

    public function testValidateReturnsNullForInvalidToken(): void
    {
        $this->assertNull($this->session->validate('not-valid-base64!!!'));
    }

    public function testValidateReturnsNullForTamperedSignature(): void
    {
        $this->otpGenerator->method('generateToken')->willReturn('sessionkey654321');

        $token = $this->session->create(1, 'password', SessionStage::PrimaryVerified);

        // Decode, tamper with signature, re-encode.
        $decoded = base64_decode($token, true);
        $parts = explode('|', $decoded);
        $parts[3] = str_repeat('a', 64); // fake signature
        $tampered = base64_encode(implode('|', $parts));

        $this->assertNull($this->session->validate($tampered));
    }

    public function testValidateReturnsNullForExpiredToken(): void
    {
        $this->otpGenerator->method('generateToken')->willReturn('sessionkeyexpire');

        $token = $this->session->create(1, 'password', SessionStage::PrimaryVerified);

        // Decode and set expiry to past.
        $decoded = base64_decode($token, true);
        $parts = explode('|', $decoded);
        $parts[2] = (string) (time() - 100); // expired
        $payload = $parts[0] . '|' . $parts[1] . '|' . $parts[2];
        $signature = hash_hmac('sha256', $payload, AUTH_KEY);
        $forged = base64_encode($payload . '|' . $signature);

        $this->assertNull($this->session->validate($forged));
    }

    public function testUpdateModifiesSessionData(): void
    {
        $this->otpGenerator->method('generateToken')->willReturn('sessionkeyupdate');

        $token = $this->session->create(1, 'password', SessionStage::PrimaryVerified);
        $data = $this->session->validate($token);

        $this->session->update($data['session_key'], ['stage' => SessionStage::MfaPending->value, 'mfa_channel_id' => 'sms']);

        $updated = $this->session->validate($token);
        $this->assertSame('mfa_pending', $updated['stage']);
        $this->assertSame('sms', $updated['mfa_channel_id']);
    }

    public function testDestroyInvalidatesSession(): void
    {
        $this->otpGenerator->method('generateToken')->willReturn('sessionkeydestry');

        $token = $this->session->create(1, 'password', SessionStage::PrimaryVerified);
        $data = $this->session->validate($token);

        $this->session->destroy($data['session_key']);

        $this->assertNull($this->session->validate($token));
    }

    public function testCreateStoresChannelIdFromContext(): void
    {
        $this->otpGenerator->method('generateToken')->willReturn('sessionkeychanid');

        $token = $this->session->create(1, 'phone_otp', SessionStage::ChallengePending, [
            'channel_id' => 'sms',
        ]);

        $data = $this->session->validate($token);
        $this->assertSame('sms', $data['channel_id']);
    }
}
