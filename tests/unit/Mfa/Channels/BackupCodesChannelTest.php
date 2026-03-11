<?php

namespace WSms\Tests\Unit\Mfa\Channels;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use WSms\Audit\AuditLogger;
use WSms\Enums\ChannelStatus;
use WSms\Mfa\Channels\BackupCodesChannel;
use WSms\Mfa\OtpGenerator;

class BackupCodesChannelTest extends TestCase
{
    private BackupCodesChannel $channel;
    private MockObject&OtpGenerator $otpGenerator;
    private MockObject&AuditLogger $auditLogger;

    protected function setUp(): void
    {
        $this->otpGenerator = $this->createMock(OtpGenerator::class);
        $this->auditLogger = $this->createMock(AuditLogger::class);
        $this->channel = new BackupCodesChannel($this->otpGenerator, $this->auditLogger);

        $this->setupWpdbMock(null);
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['wpdb']);
    }

    public function testGetIdReturnsBackupCodes(): void
    {
        $this->assertSame('backup_codes', $this->channel->getId());
    }

    public function testGetNameReturnsBackupCodes(): void
    {
        $this->assertSame('Backup Codes', $this->channel->getName());
    }

    public function testDoesNotSupportPrimaryAuth(): void
    {
        $this->assertFalse($this->channel->supportsPrimaryAuth());
    }

    public function testSupportsMfa(): void
    {
        $this->assertTrue($this->channel->supportsMfa());
    }

    public function testEnrollGeneratesCodesInCorrectFormat(): void
    {
        $this->otpGenerator->method('hash')->willReturnCallback(fn($v) => hash('sha256', $v));

        $this->setupWpdbMock(null);

        $result = $this->channel->enroll(1, []);

        $this->assertTrue($result->success);

        foreach ($result->data['codes'] as $code) {
            $this->assertMatchesRegularExpression('/^[A-Z2-9]{5}-[A-Z2-9]{5}$/', $code);
        }
    }

    public function testEnrollGeneratesUniqueCodes(): void
    {
        $this->otpGenerator->method('hash')->willReturnCallback(fn($v) => hash('sha256', $v));

        $this->setupWpdbMock(null);

        $result = $this->channel->enroll(1, []);

        $this->assertSame(10, count(array_unique($result->data['codes'])));
    }

    public function testEnrollReturnsCodesOnSuccess(): void
    {
        $this->otpGenerator->method('hash')->willReturnCallback(fn($v) => hash('sha256', $v));

        $this->setupWpdbMock(null);

        $result = $this->channel->enroll(1, []);

        $this->assertTrue($result->success);
        $this->assertArrayHasKey('codes', $result->data);
        $this->assertCount(10, $result->data['codes']); // Default count.
    }

    public function testEnrollRejectsAlreadyEnrolled(): void
    {
        $this->setupWpdbMock($this->makeFactorRow(ChannelStatus::Active, ['codes' => ['hash1']]));

        $result = $this->channel->enroll(1, []);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Already enrolled', $result->message);
    }

    public function testSendChallengeReturnsRemainingCount(): void
    {
        $this->setupWpdbMock($this->makeFactorRow(ChannelStatus::Active, ['codes' => ['h1', 'h2', 'h3']]));

        $result = $this->channel->sendChallenge(1);

        $this->assertTrue($result->success);
        $this->assertSame(3, $result->meta['remaining']);
    }

    public function testSendChallengeFailsWhenNotEnrolled(): void
    {
        $this->setupWpdbMock(null);

        $result = $this->channel->sendChallenge(1);

        $this->assertFalse($result->success);
    }

    public function testVerifyMatchesCode(): void
    {
        $plainCode = 'ABCDE-FGHIJ';
        $normalized = 'ABCDEFGHIJ';
        $hashed = hash('sha256', $normalized);

        // verify() now delegates to OtpGenerator::verify().
        $this->otpGenerator->method('verify')
            ->willReturnCallback(fn($code, $hash) => hash_equals($hash, hash('sha256', $code)));

        $factorRow = $this->makeFactorRow(ChannelStatus::Active, ['codes' => [$hashed, 'otherhash']]);

        $wpdb = $this->getMockBuilder(\stdClass::class)
            ->addMethods(['get_row', 'prepare', 'update'])
            ->getMock();
        $wpdb->prefix = 'wp_';
        $wpdb->method('prepare')->willReturnCallback(fn(string $q) => $q);
        $wpdb->method('get_row')->willReturn($factorRow);
        $wpdb->method('update')->willReturn(1);
        $GLOBALS['wpdb'] = $wpdb;

        $this->assertTrue($this->channel->verify(1, $plainCode));
    }

    public function testVerifyRejectsInvalidCode(): void
    {
        $this->otpGenerator->method('verify')->willReturn(false);

        $this->setupWpdbMock($this->makeFactorRow(ChannelStatus::Active, ['codes' => ['storedHash1']]));

        $this->assertFalse($this->channel->verify(1, 'WRONG-CODES'));
    }

    public function testVerifyFailsWhenNotEnrolled(): void
    {
        $this->setupWpdbMock(null);

        $this->assertFalse($this->channel->verify(1, 'ABCDE-FGHIJ'));
    }

    public function testVerifyFailsWhenNoCodes(): void
    {
        $this->setupWpdbMock($this->makeFactorRow(ChannelStatus::Active, ['codes' => []]));

        $this->otpGenerator->method('verify')->willReturn(false);

        $this->assertFalse($this->channel->verify(1, 'ABCDE-FGHIJ'));
    }

    public function testIsEnrolledReturnsTrueForActiveFactor(): void
    {
        $this->setupWpdbMock($this->makeFactorRow(ChannelStatus::Active));

        $this->assertTrue($this->channel->isEnrolled(1));
    }

    public function testIsEnrolledReturnsFalseWhenNoFactor(): void
    {
        $this->setupWpdbMock(null);

        $this->assertFalse($this->channel->isEnrolled(1));
    }

    public function testUnenrollSucceeds(): void
    {
        $this->setupWpdbMock($this->makeFactorRow(ChannelStatus::Active));

        $this->assertTrue($this->channel->unenroll(1));
    }

    public function testUnenrollFailsWhenNotEnrolled(): void
    {
        $this->setupWpdbMock(null);

        $this->assertFalse($this->channel->unenroll(1));
    }

    public function testGetEnrollmentInfoShowsRemaining(): void
    {
        $this->setupWpdbMock($this->makeFactorRow(ChannelStatus::Active, ['codes' => ['a', 'b']]));

        $info = $this->channel->getEnrollmentInfo(1);

        $this->assertTrue($info['enrolled']);
        $this->assertSame(2, $info['remaining']);
    }

    public function testGetEnrollmentInfoWhenNotEnrolled(): void
    {
        $this->setupWpdbMock(null);

        $info = $this->channel->getEnrollmentInfo(1);

        $this->assertFalse($info['enrolled']);
    }

    // -- Helpers --

    private function makeFactorRow(ChannelStatus $status, array $meta = ['codes' => []]): object
    {
        return (object) [
            'id'         => 1,
            'user_id'    => 1,
            'channel_id' => 'backup_codes',
            'status'     => $status->value,
            'meta'       => json_encode($meta),
            'created_at' => '2025-01-01 00:00:00',
            'updated_at' => '2025-01-01 00:00:00',
        ];
    }

    private function setupWpdbMock(?object $getRowReturn): void
    {
        $wpdb = $this->getMockBuilder(\stdClass::class)
            ->addMethods(['get_row', 'prepare', 'insert', 'update', 'query', 'get_var'])
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

        $GLOBALS['wpdb'] = $wpdb;
    }
}
