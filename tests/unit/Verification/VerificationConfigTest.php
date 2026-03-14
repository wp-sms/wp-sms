<?php

namespace WSms\Tests\Unit\Verification;

use PHPUnit\Framework\TestCase;
use WSms\Verification\VerificationConfig;

class VerificationConfigTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['_test_options'] = [];
    }

    protected function tearDown(): void
    {
        $GLOBALS['_test_options'] = [];
    }

    public function testDefaultsReturnedWhenNoOptionSet(): void
    {
        $config = new VerificationConfig();
        $all = $config->all();

        $this->assertTrue($all['enabled']);
        $this->assertSame(1800, $all['session_ttl']);
        $this->assertTrue($all['email']['enabled']);
        $this->assertSame(6, $all['email']['code_length']);
        $this->assertSame(300, $all['email']['expiry']);
        $this->assertSame(3, $all['email']['max_attempts']);
        $this->assertSame(60, $all['email']['cooldown']);
    }

    public function testStoredOptionOverridesDefaults(): void
    {
        $GLOBALS['_test_options']['wsms_verification_settings'] = [
            'email' => ['code_length' => 4, 'expiry' => 600],
            'session_ttl' => 3600,
        ];

        $config = new VerificationConfig();
        $all = $config->all();

        $this->assertSame(4, $all['email']['code_length']);
        $this->assertSame(600, $all['email']['expiry']);
        $this->assertSame(3, $all['email']['max_attempts']); // default preserved
        $this->assertSame(3600, $all['session_ttl']);
    }

    public function testGetReturnsSpecificKey(): void
    {
        $config = new VerificationConfig();

        $this->assertSame(1800, $config->get('session_ttl'));
        $this->assertNull($config->get('nonexistent'));
        $this->assertSame('fallback', $config->get('nonexistent', 'fallback'));
    }

    public function testGetChannelConfigMergesDefaults(): void
    {
        $GLOBALS['_test_options']['wsms_verification_settings'] = [
            'phone' => ['code_length' => 4],
        ];

        $config = new VerificationConfig();
        $phoneConfig = $config->getChannelConfig('phone');

        $this->assertSame(4, $phoneConfig['code_length']);
        $this->assertTrue($phoneConfig['enabled']);
        $this->assertSame(300, $phoneConfig['expiry']);
    }

    public function testIsChannelEnabledReturnsCorrectly(): void
    {
        $config = new VerificationConfig();

        $this->assertTrue($config->isChannelEnabled('email'));
        $this->assertTrue($config->isChannelEnabled('phone'));
    }

    public function testIsChannelDisabled(): void
    {
        $GLOBALS['_test_options']['wsms_verification_settings'] = [
            'phone' => ['enabled' => false],
        ];

        $config = new VerificationConfig();

        $this->assertFalse($config->isChannelEnabled('phone'));
        $this->assertTrue($config->isChannelEnabled('email'));
    }

    public function testUnknownChannelReturnsEmptyConfig(): void
    {
        $config = new VerificationConfig();
        $unknown = $config->getChannelConfig('fax');

        $this->assertIsArray($unknown);
    }

    public function testGetChannelConfigEnforcesSafeMinimums(): void
    {
        $GLOBALS['_test_options']['wsms_verification_settings'] = [
            'email' => ['code_length' => 0, 'expiry' => -10, 'max_attempts' => 0, 'cooldown' => -5],
        ];

        $config = new VerificationConfig();
        $emailConfig = $config->getChannelConfig('email');

        $this->assertGreaterThanOrEqual(4, $emailConfig['code_length']);
        $this->assertGreaterThanOrEqual(60, $emailConfig['expiry']);
        $this->assertGreaterThanOrEqual(1, $emailConfig['max_attempts']);
        $this->assertGreaterThanOrEqual(0, $emailConfig['cooldown']);
    }
}
