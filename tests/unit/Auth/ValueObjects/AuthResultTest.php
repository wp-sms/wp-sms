<?php

namespace WSms\Tests\Unit\Auth\ValueObjects;

use PHPUnit\Framework\TestCase;
use WSms\Auth\ValueObjects\AuthResult;

class AuthResultTest extends TestCase
{
    public function testAuthenticatedResult(): void
    {
        $user = ['id' => 1, 'email' => 'test@example.com'];
        $result = AuthResult::authenticated(1, $user);

        $this->assertTrue($result->success);
        $this->assertSame('authenticated', $result->status);
        $this->assertSame(1, $result->userId);
        $this->assertSame($user, $result->user);
    }

    public function testMfaRequiredResult(): void
    {
        $factors = [['channel_id' => 'sms', 'name' => 'SMS']];
        $result = AuthResult::mfaRequired('token123', $factors);

        $this->assertTrue($result->success);
        $this->assertSame('mfa_required', $result->status);
        $this->assertSame('token123', $result->sessionToken);
        $this->assertSame(['available_factors' => $factors], $result->meta);
    }

    public function testChallengeSentResult(): void
    {
        $result = AuthResult::challengeSent('token456', ['masked_to' => '+12*****34']);

        $this->assertTrue($result->success);
        $this->assertSame('challenge_sent', $result->status);
        $this->assertSame('token456', $result->sessionToken);
        $this->assertSame(['masked_to' => '+12*****34'], $result->meta);
    }

    public function testFailedResult(): void
    {
        $result = AuthResult::failed('invalid_credentials', 'Bad password.');

        $this->assertFalse($result->success);
        $this->assertSame('failed', $result->status);
        $this->assertSame('invalid_credentials', $result->error);
        $this->assertSame('Bad password.', $result->message);
    }

    public function testRateLimitedResult(): void
    {
        $result = AuthResult::rateLimited(30);

        $this->assertFalse($result->success);
        $this->assertSame('rate_limited', $result->status);
        $this->assertSame(['retry_after' => 30], $result->meta);
    }

    public function testExpiredResult(): void
    {
        $result = AuthResult::expired();

        $this->assertFalse($result->success);
        $this->assertSame('expired', $result->status);
    }

    public function testInvalidTokenResult(): void
    {
        $result = AuthResult::invalidToken();

        $this->assertFalse($result->success);
        $this->assertSame('invalid_token', $result->status);
    }

    public function testToArrayIncludesOnlyNonNullFields(): void
    {
        $result = AuthResult::failed('bad', 'Error');
        $array = $result->toArray();

        $this->assertSame(false, $array['success']);
        $this->assertSame('failed', $array['status']);
        $this->assertSame('bad', $array['error']);
        $this->assertSame('Error', $array['message']);
        $this->assertArrayNotHasKey('challenge_token', $array);
        $this->assertArrayNotHasKey('user', $array);
        $this->assertArrayNotHasKey('meta', $array);
    }

    public function testToArrayIncludesSessionTokenAndMeta(): void
    {
        $result = AuthResult::challengeSent('tok', ['key' => 'val']);
        $array = $result->toArray();

        $this->assertSame('tok', $array['challenge_token']);
        $this->assertSame(['key' => 'val'], $array['meta']);
    }

    public function testToHttpStatusMapping(): void
    {
        $this->assertSame(200, AuthResult::authenticated(1, [])->toHttpStatus());
        $this->assertSame(200, AuthResult::mfaRequired('t', [])->toHttpStatus());
        $this->assertSame(200, AuthResult::challengeSent('t')->toHttpStatus());
        $this->assertSame(429, AuthResult::rateLimited(30)->toHttpStatus());
        $this->assertSame(401, AuthResult::expired()->toHttpStatus());
        $this->assertSame(401, AuthResult::invalidToken()->toHttpStatus());
        $this->assertSame(401, AuthResult::failed('invalid_credentials', 'Bad')->toHttpStatus());
        $this->assertSame(400, AuthResult::failed('other_error', 'Err')->toHttpStatus());
    }

    public function testToArrayIncludesUser(): void
    {
        $user = ['id' => 5, 'email' => 'a@b.com'];
        $result = AuthResult::authenticated(5, $user);
        $array = $result->toArray();

        $this->assertSame($user, $array['user']);
    }
}
