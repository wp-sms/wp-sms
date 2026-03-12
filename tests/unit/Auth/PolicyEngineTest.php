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

    public function testValidatePolicyConflictsAlwaysReturnsTrue(): void
    {
        // In the channel-centric model, conflicts are eliminated by design
        // because usage is mutually exclusive per channel (login OR mfa).
        $this->assertTrue($this->engine->validatePolicyConflicts('phone', 'phone'));
        $this->assertTrue($this->engine->validatePolicyConflicts('email', 'email'));
        $this->assertTrue($this->engine->validatePolicyConflicts('password', 'phone'));
        $this->assertTrue($this->engine->validatePolicyConflicts('password', 'email'));
    }

    public function testIsMfaRequiredReturnsFalseWhenNoFactorsEnabled(): void
    {
        // Without WordPress loaded, get_option returns false/empty,
        // so no channels configured for MFA => MFA not required.
        $this->assertFalse($this->engine->isMfaRequired(1));
    }
}
