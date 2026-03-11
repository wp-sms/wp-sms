<?php

namespace WSms\Tests\Unit\Mfa\Channels;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use WSms\Audit\AuditLogger;
use WSms\Enums\ChannelStatus;
use WSms\Mfa\Channels\EmailOtpChannel;
use WSms\Mfa\OtpGenerator;

class EmailOtpChannelTest extends TestCase
{
    private EmailOtpChannel $channel;
    private MockObject&OtpGenerator $otpGenerator;
    private MockObject&AuditLogger $auditLogger;

    protected function setUp(): void
    {
        $this->otpGenerator = $this->createMock(OtpGenerator::class);
        $this->auditLogger = $this->createMock(AuditLogger::class);
        $this->channel = new EmailOtpChannel($this->otpGenerator, $this->auditLogger);

        unset($GLOBALS['_test_userdata']);
        $this->setupWpdbMock(null);
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['wpdb'], $GLOBALS['_test_userdata']);
    }

    public function testGetIdReturnsEmailOtp(): void
    {
        $this->assertSame('email_otp', $this->channel->getId());
    }

    public function testGetNameReturnsEmailOtp(): void
    {
        $this->assertSame('Email OTP', $this->channel->getName());
    }

    public function testSupportsPrimaryAuth(): void
    {
        $this->assertTrue($this->channel->supportsPrimaryAuth());
    }

    public function testSupportsMfa(): void
    {
        $this->assertTrue($this->channel->supportsMfa());
    }

    public function testEnrollAutoActivates(): void
    {
        // Override get_userdata to return a user with email.
        $this->overrideGetUserdata(1, 'user@example.com');
        $this->setupWpdbMock(null);

        $result = $this->channel->enroll(1, []);

        $this->assertTrue($result->success);
        $this->assertArrayHasKey('masked_email', $result->data);
    }

    public function testEnrollFailsWhenNoEmail(): void
    {
        // get_userdata returns false (default stub).
        $result = $this->channel->enroll(1, []);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('No email', $result->message);
    }

    public function testEnrollRejectsAlreadyEnrolled(): void
    {
        $this->overrideGetUserdata(1, 'user@example.com');
        $this->setupWpdbMock($this->makeFactorRow(ChannelStatus::Active));

        $result = $this->channel->enroll(1, []);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Already enrolled', $result->message);
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

    public function testSendChallengeFailsWhenNotEnrolled(): void
    {
        $this->setupWpdbMock(null);

        $result = $this->channel->sendChallenge(1);

        $this->assertFalse($result->success);
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

    public function testGetEnrollmentInfoWhenNotEnrolled(): void
    {
        $this->setupWpdbMock(null);

        $info = $this->channel->getEnrollmentInfo(1);

        $this->assertFalse($info['enrolled']);
    }

    // -- Helpers --

    private function makeFactorRow(ChannelStatus $status): object
    {
        return (object) [
            'id'         => 1,
            'user_id'    => 1,
            'channel_id' => 'email_otp',
            'status'     => $status->value,
            'meta'       => json_encode(['email' => 'user@example.com']),
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

    private function overrideGetUserdata(int $userId, string $email): void
    {
        // Override the global function for this test.
        // Since we can't redefine, we rely on the stub in tests/bootstrap.php.
        // We need to use a global to pass data to the stub.
        $GLOBALS['_test_userdata'] = (object) [
            'ID'         => $userId,
            'user_email' => $email,
        ];
    }
}
