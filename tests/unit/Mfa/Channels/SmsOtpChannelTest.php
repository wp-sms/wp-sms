<?php

namespace WSms\Tests\Unit\Mfa\Channels;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use WSms\Audit\AuditLogger;
use WSms\Enums\ChannelStatus;
use WSms\Mfa\Channels\SmsOtpChannel;
use WSms\Mfa\OtpGenerator;
use WSms\Mfa\ValueObjects\EnrollmentResult;

class SmsOtpChannelTest extends TestCase
{
    private SmsOtpChannel $channel;
    private MockObject&OtpGenerator $otpGenerator;
    private MockObject&AuditLogger $auditLogger;
    private object $wpdb;

    protected function setUp(): void
    {
        $this->otpGenerator = $this->createMock(OtpGenerator::class);
        $this->auditLogger = $this->createMock(AuditLogger::class);
        $this->channel = new SmsOtpChannel($this->otpGenerator, $this->auditLogger);

        // Mock global $wpdb.
        $this->wpdb = new \stdClass();
        $this->wpdb->prefix = 'wp_';
        $this->wpdb->insert_id = 1;
        $this->wpdb->rows_affected = 1;
        $GLOBALS['wpdb'] = $this->wpdb;
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['wpdb']);
    }

    public function testGetIdReturnsSms(): void
    {
        $this->assertSame('sms', $this->channel->getId());
    }

    public function testGetNameReturnsSmsOtp(): void
    {
        $this->assertSame('SMS OTP', $this->channel->getName());
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
        $this->mockWpdbGetRow(null); // No existing factor
        $this->mockWpdbInsert();     // createFactor
        $this->mockWpdbQuery();      // invalidate existing
        $this->mockWpdbInsert();     // insert verification

        $this->otpGenerator->method('generate')->willReturn('123456');
        $this->otpGenerator->method('hash')->willReturn('hashed');

        $result = $this->channel->enroll(1, ['phone' => '+12025551234']);

        $this->assertTrue($result->success);
        $this->assertArrayHasKey('requires_confirmation', $result->data);
        $this->assertTrue($result->data['requires_confirmation']);
    }

    public function testEnrollRejectsAlreadyEnrolled(): void
    {
        $this->mockWpdbGetRow($this->makeFactorRow(ChannelStatus::Active));

        $result = $this->channel->enroll(1, ['phone' => '+12025551234']);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Already enrolled', $result->message);
    }

    public function testIsEnrolledReturnsTrueForActiveFactor(): void
    {
        $this->mockWpdbGetRow($this->makeFactorRow(ChannelStatus::Active));

        $this->assertTrue($this->channel->isEnrolled(1));
    }

    public function testIsEnrolledReturnsFalseForPendingFactor(): void
    {
        $this->mockWpdbGetRow($this->makeFactorRow(ChannelStatus::Pending));

        $this->assertFalse($this->channel->isEnrolled(1));
    }

    public function testIsEnrolledReturnsFalseWhenNoFactor(): void
    {
        $this->mockWpdbGetRow(null);

        $this->assertFalse($this->channel->isEnrolled(1));
    }

    public function testUnenrollDeletesPhoneMeta(): void
    {
        $this->mockWpdbGetRow($this->makeFactorRow(ChannelStatus::Active));
        $this->mockWpdbUpdate();

        $result = $this->channel->unenroll(1);

        $this->assertTrue($result);
    }

    public function testSendChallengeFailsWhenNotEnrolled(): void
    {
        $this->mockWpdbGetRow(null);

        $result = $this->channel->sendChallenge(1);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('not enrolled', $result->message);
    }

    public function testGetEnrollmentInfoWhenNotEnrolled(): void
    {
        $this->mockWpdbGetRow(null);

        $info = $this->channel->getEnrollmentInfo(1);

        $this->assertFalse($info['enrolled']);
    }

    public function testConfirmEnrollmentFailsWithNoPendingFactor(): void
    {
        $this->mockWpdbGetRow(null);

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
            'channel_id' => 'sms',
            'status'     => $status->value,
            'meta'       => json_encode($meta),
            'created_at' => '2025-01-01 00:00:00',
            'updated_at' => '2025-01-01 00:00:00',
        ];
    }

    private function mockWpdbGetRow(?object $result): void
    {
        $this->wpdb->get_row = fn() => $result;

        // Use a closure-based mock since stdClass can't use ->method().
        $mock = $this->wpdb;
        $returnVal = $result;

        if (!method_exists($mock, 'get_row')) {
            $mock->get_row_return = $returnVal;
            // We'll override via __call or direct property. For simplicity, mock the object:
        }

        // Replace with a proper mock object.
        $wpdb = $this->getMockBuilder(\stdClass::class)
            ->addMethods(['get_row', 'prepare', 'insert', 'update', 'query', 'get_var', 'get_results'])
            ->getMock();
        $wpdb->prefix = 'wp_';
        $wpdb->insert_id = 1;
        $wpdb->rows_affected = 1;

        $wpdb->method('prepare')->willReturnCallback(fn(string $q) => $q);
        $wpdb->method('get_row')->willReturn($result);
        $wpdb->method('insert')->willReturn(1);
        $wpdb->method('update')->willReturn(1);
        $wpdb->method('query')->willReturn(1);
        $wpdb->method('get_var')->willReturn(0);

        $this->wpdb = $wpdb;
        $GLOBALS['wpdb'] = $wpdb;
    }

    private function mockWpdbInsert(): void
    {
        // Already handled via mockWpdbGetRow's full mock setup.
    }

    private function mockWpdbUpdate(): void
    {
        // Already handled via mockWpdbGetRow's full mock setup.
    }

    private function mockWpdbQuery(): void
    {
        // Already handled via mockWpdbGetRow's full mock setup.
    }
}
