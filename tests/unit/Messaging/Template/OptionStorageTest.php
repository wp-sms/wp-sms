<?php

namespace WSms\Tests\Unit\Messaging\Template;

use PHPUnit\Framework\TestCase;
use WSms\Messaging\Template\Storage\OptionStorage;
use WSms\Messaging\Template\ValueObjects\ChannelContent;

class OptionStorageTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['_test_options'] = [];
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['_test_options']);
    }

    public function testGetOverrideReturnsNullWhenNoData(): void
    {
        $storage = new OptionStorage();

        $this->assertNull($storage->getOverride('otp', 'email'));
    }

    public function testSaveAndGetOverride(): void
    {
        $storage = new OptionStorage();
        $content = new ChannelContent(body: 'Custom body', subject: 'Custom subject');

        $storage->saveOverride('otp', 'email', $content);

        $result = $storage->getOverride('otp', 'email');
        $this->assertNotNull($result);
        $this->assertEquals('Custom body', $result->body);
        $this->assertEquals('Custom subject', $result->subject);
    }

    public function testSaveNullRemovesOverride(): void
    {
        $storage = new OptionStorage();
        $storage->saveOverride('otp', 'email', new ChannelContent(body: 'x'));

        $this->assertNotNull($storage->getOverride('otp', 'email'));

        $storage->saveOverride('otp', 'email', null);

        // Need a fresh instance to avoid cache.
        $storage2 = new OptionStorage();
        $this->assertNull($storage2->getOverride('otp', 'email'));
    }

    public function testGetAllOverridesReturnsStructuredData(): void
    {
        $storage = new OptionStorage();
        $storage->saveOverride('otp', 'email', new ChannelContent(body: 'OTP email'));
        $storage->saveOverride('otp', 'sms', new ChannelContent(body: 'OTP sms'));
        $storage->saveOverride('magic_link', 'email', new ChannelContent(body: 'Magic link'));

        $all = $storage->getAllOverrides();

        $this->assertArrayHasKey('otp', $all);
        $this->assertArrayHasKey('email', $all['otp']);
        $this->assertArrayHasKey('sms', $all['otp']);
        $this->assertArrayHasKey('magic_link', $all);
        $this->assertEquals('OTP email', $all['otp']['email']->body);
    }

    public function testPersistsAcrossInstances(): void
    {
        $storage1 = new OptionStorage();
        $storage1->saveOverride('test', 'sms', new ChannelContent(body: 'persistent'));

        $storage2 = new OptionStorage();
        $result = $storage2->getOverride('test', 'sms');

        $this->assertNotNull($result);
        $this->assertEquals('persistent', $result->body);
    }
}
