<?php

namespace WSms\Tests\Unit\Mfa;

use PHPUnit\Framework\TestCase;
use WSms\Mfa\Contracts\ChannelInterface;
use WSms\Mfa\MfaManager;
use WSms\Mfa\ValueObjects\ChallengeResult;
use WSms\Mfa\ValueObjects\EnrollmentResult;

class MfaManagerTest extends TestCase
{
    private MfaManager $manager;

    protected function setUp(): void
    {
        $this->manager = new MfaManager();
    }

    public function testRegisterChannelStoresChannel(): void
    {
        $channel = $this->createMockChannel('sms', 'SMS OTP');
        $this->manager->registerChannel($channel);

        $this->assertSame($channel, $this->manager->getChannel('sms'));
    }

    public function testGetChannelReturnsRegisteredChannel(): void
    {
        $channel = $this->createMockChannel('email_otp', 'Email OTP');
        $this->manager->registerChannel($channel);

        $result = $this->manager->getChannel('email_otp');

        $this->assertSame($channel, $result);
        $this->assertSame('email_otp', $result->getId());
    }

    public function testGetChannelReturnsNullForUnknown(): void
    {
        $this->assertNull($this->manager->getChannel('nonexistent'));
    }

    public function testGetAvailableChannelsReturnsAll(): void
    {
        $sms = $this->createMockChannel('sms', 'SMS OTP');
        $email = $this->createMockChannel('email_otp', 'Email OTP');

        $this->manager->registerChannel($sms);
        $this->manager->registerChannel($email);

        $channels = $this->manager->getAvailableChannels();

        $this->assertCount(2, $channels);
        $this->assertContains($sms, $channels);
        $this->assertContains($email, $channels);
    }

    public function testRegisterChannelOverwritesSameId(): void
    {
        $channel1 = $this->createMockChannel('sms', 'SMS v1');
        $channel2 = $this->createMockChannel('sms', 'SMS v2');

        $this->manager->registerChannel($channel1);
        $this->manager->registerChannel($channel2);

        $this->assertSame($channel2, $this->manager->getChannel('sms'));
        $this->assertCount(1, $this->manager->getAvailableChannels());
    }

    private function createMockChannel(string $id, string $name): ChannelInterface
    {
        $channel = $this->createMock(ChannelInterface::class);
        $channel->method('getId')->willReturn($id);
        $channel->method('getName')->willReturn($name);

        return $channel;
    }
}
