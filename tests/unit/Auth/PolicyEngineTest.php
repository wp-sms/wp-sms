<?php

namespace WSms\Tests\Unit\Auth;

use PHPUnit\Framework\TestCase;
use WSms\Auth\PolicyEngine;

class PolicyEngineTest extends TestCase
{
    private PolicyEngine $engine;

    protected function setUp(): void
    {
        $this->engine = new PolicyEngine();
    }

    public function testValidatePolicyConflictsBlocksSameChannel(): void
    {
        // phone_otp as primary blocks 'sms' as MFA
        $this->assertFalse($this->engine->validatePolicyConflicts('phone_otp', 'sms'));

        // email_otp as primary blocks 'email_otp' as MFA
        $this->assertFalse($this->engine->validatePolicyConflicts('email_otp', 'email_otp'));

        // magic_link as primary blocks 'email_otp' as MFA
        $this->assertFalse($this->engine->validatePolicyConflicts('magic_link', 'email_otp'));
    }

    public function testValidatePolicyConflictsAllowsDifferentChannels(): void
    {
        // phone_otp as primary allows 'email_otp' as MFA
        $this->assertTrue($this->engine->validatePolicyConflicts('phone_otp', 'email_otp'));

        // email_otp as primary allows 'sms' as MFA
        $this->assertTrue($this->engine->validatePolicyConflicts('email_otp', 'sms'));

        // password has no conflicts
        $this->assertTrue($this->engine->validatePolicyConflicts('password', 'sms'));
        $this->assertTrue($this->engine->validatePolicyConflicts('password', 'email_otp'));
    }

    public function testIsMfaRequiredReturnsFalseWhenNoFactorsEnabled(): void
    {
        // Without WordPress loaded, get_option returns false/empty,
        // so mfa_factors will be empty => MFA not required.
        $this->assertFalse($this->engine->isMfaRequired(1));
    }
}
