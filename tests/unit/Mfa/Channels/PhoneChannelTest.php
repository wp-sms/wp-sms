<?php

namespace WSms\Tests\Unit\Mfa\Channels;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use WSms\Audit\AuditLogger;
use WSms\Enums\ChannelStatus;
use WSms\Mfa\Channels\MagicLinkChannel;
use WSms\Mfa\Channels\PhoneChannel;
use WSms\Mfa\OtpGenerator;

class PhoneChannelTest extends TestCase
{
    private PhoneChannel $channel;
    private MockObject&OtpGenerator $otpGenerator;
    private MockObject&AuditLogger $auditLogger;
    private MockObject&MagicLinkChannel $magicLink;
    private object $wpdb;

    protected function setUp(): void
    {
        $this->otpGenerator = $this->createMock(OtpGenerator::class);
        $this->auditLogger = $this->createMock(AuditLogger::class);
        $this->magicLink = $this->createMock(MagicLinkChannel::class);
        $this->channel = new PhoneChannel($this->otpGenerator, $this->auditLogger, $this->magicLink);

        // Mock global $wpdb.
        $this->setupWpdbMock(null);
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['wpdb']);
    }

    public function testGetIdReturnsPhone(): void
    {
        $this->assertSame('phone', $this->channel->getId());
    }

    public function testGetNameReturnsPhone(): void
    {
        $this->assertSame('Phone', $this->channel->getName());
    }

    public function testSupportsPrimaryAuth(): void
    {
        $this->assertTrue($this->channel->supportsPrimaryAuth());
    }

    public function testSupportsMfa(): void
    {
        $this->assertTrue($this->channel->supportsMfa());
    }

    public function testEnrollRejectsInvalidPhone(): void
    {
        $result = $this->channel->enroll(1, ['phone' => '12345']);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('E.164', $result->message);
    }

    public function testEnrollRejectsEmptyPhone(): void
    {
        $result = $this->channel->enroll(1, []);

        $this->assertFalse($result->success);
    }

    public function testEnrollAcceptsValidE164Phone(): void
    {
        $this->setupWpdbMock(null);

        $this->otpGenerator->method('generate')->willReturn('123456');
        $this->otpGenerator->method('hash')->willReturn('hashed');

        $result = $this->channel->enroll(1, ['phone' => '+12025551234']);

        $this->assertTrue($result->success);
        $this->assertArrayHasKey('requires_confirmation', $result->data);
        $this->assertTrue($result->data['requires_confirmation']);
    }

    public function testEnrollRejectsAlreadyEnrolled(): void
    {
        $this->setupWpdbMock($this->makeFactorRow(ChannelStatus::Active));

        $result = $this->channel->enroll(1, ['phone' => '+12025551234']);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Already enrolled', $result->message);
    }

    public function testIsEnrolledReturnsTrueForActiveFactor(): void
    {
        $this->setupWpdbMock($this->makeFactorRow(ChannelStatus::Active));

        $this->assertTrue($this->channel->isEnrolled(1));
    }

    public function testIsEnrolledReturnsFalseForPendingFactor(): void
    {
        $this->setupWpdbMock($this->makeFactorRow(ChannelStatus::Pending));

        $this->assertFalse($this->channel->isEnrolled(1));
    }

    public function testIsEnrolledReturnsFalseWhenNoFactor(): void
    {
        $this->setupWpdbMock(null);

        $this->assertFalse($this->channel->isEnrolled(1));
    }

    public function testUnenrollDeletesPhoneMeta(): void
    {
        $this->setupWpdbMock($this->makeFactorRow(ChannelStatus::Active));

        $result = $this->channel->unenroll(1);

        $this->assertTrue($result);
    }

    public function testGetEnrollmentInfoWhenNotEnrolled(): void
    {
        $this->setupWpdbMock(null);

        $info = $this->channel->getEnrollmentInfo(1);

        $this->assertFalse($info['enrolled']);
    }

    public function testConfirmEnrollmentFailsWithNoPendingFactor(): void
    {
        $this->setupWpdbMock(null);

        $result = $this->channel->confirmEnrollment(1, '123456');

        $this->assertFalse($result->success);
        $this->assertStringContainsString('No pending enrollment', $result->message);
    }

    // -- Helpers --

    private function makeFactorRow(ChannelStatus $status, array $meta = ['phone' => '+12025551234']): object
    {
        return (object) [
            'id'         => 1,
            'user_id'    => 1,
            'channel_id' => 'phone',
            'status'     => $status->value,
            'meta'       => json_encode($meta),
            'created_at' => '2025-01-01 00:00:00',
            'updated_at' => '2025-01-01 00:00:00',
        ];
    }

    private function setupWpdbMock(?object $getRowReturn): void
    {
        $wpdb = $this->getMockBuilder(\stdClass::class)
            ->addMethods(['get_row', 'prepare', 'insert', 'update', 'query', 'get_var', 'get_results'])
            ->getMock();
        $wpdb->prefix = 'wp_';
        $wpdb->insert_id = 1;
        $wpdb->rows_affected = 1;

        $wpdb->method('prepare')->willReturnCallback(fn(string $q) => $q);
        $wpdb->method('get_row')->willReturn($getRowReturn);
        $wpdb->method('insert')->willReturn(1);
        $wpdb->method('update')->willReturn(1);
        $wpdb->method('query')->willReturn(1);
        $wpdb->method('get_var')->willReturn(0);

        $this->wpdb = $wpdb;
        $GLOBALS['wpdb'] = $wpdb;
    }
}
