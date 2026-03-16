<?php

namespace WSms\Tests\Unit\Mfa\Channels;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use WSms\Audit\AuditLogger;
use WSms\Enums\ChannelStatus;
use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Message\EmailMessage;
use WSms\Messaging\MessageDispatcher;
use WSms\Mfa\Channels\EmailChannel;
use WSms\Mfa\Channels\MagicLinkChannel;
use WSms\Mfa\OtpGenerator;
use WSms\Verification\OtpService;
use WSms\Verification\VerificationRepository;

class EmailChannelTest extends TestCase
{
    private EmailChannel $channel;
    private MockObject&OtpGenerator $otpGenerator;
    private MockObject&AuditLogger $auditLogger;
    private MockObject&MagicLinkChannel $magicLink;
    private MockObject&MessageDispatcher $dispatcher;
    private MockObject&VerificationRepository $verificationRepo;

    protected function setUp(): void
    {
        $this->otpGenerator = $this->createMock(OtpGenerator::class);
        $this->auditLogger = $this->createMock(AuditLogger::class);
        $this->magicLink = $this->createMock(MagicLinkChannel::class);
        $this->dispatcher = $this->createMock(MessageDispatcher::class);
        $this->dispatcher->method('sendImmediate')->willReturn(DeliveryResult::sent());
        $this->verificationRepo = $this->createMock(VerificationRepository::class);
        $otpService = $this->createMock(OtpService::class);
        $otpService->method('createOtp')->willReturn('123456');
        $this->channel = new EmailChannel($this->otpGenerator, $this->auditLogger, $this->dispatcher, $this->magicLink, $this->verificationRepo, $otpService);

        unset($GLOBALS['_test_userdata']);
        $this->setupWpdbMock(null);
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['wpdb'], $GLOBALS['_test_userdata']);
    }

    public function testGetIdReturnsEmail(): void
    {
        $this->assertSame('email', $this->channel->getId());
    }

    public function testGetNameReturnsEmail(): void
    {
        $this->assertSame('Email', $this->channel->getName());
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
        $this->overrideGetUserdata(1, 'user@example.com');
        $this->setupWpdbMock(null);

        $result = $this->channel->enroll(1, []);

        $this->assertTrue($result->success);
        $this->assertArrayHasKey('masked_email', $result->data);
    }

    public function testEnrollFailsWhenNoEmail(): void
    {
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

    public function testSendChallengeSendsEmailViaDispatcher(): void
    {
        $this->overrideGetUserdata(1, 'user@example.com');

        $factorRow = $this->makeFactorRow(ChannelStatus::Active);

        // Factor lookup returns active row, then no cooldown.
        $wpdb = $this->getMockBuilder(\stdClass::class)
            ->addMethods(['get_row', 'prepare', 'insert', 'update', 'query', 'get_var'])
            ->getMock();
        $wpdb->prefix = 'wp_';
        $wpdb->insert_id = 1;
        $wpdb->rows_affected = 1;
        $wpdb->method('prepare')->willReturnCallback(fn(string $q) => $q);
        $wpdb->method('get_row')->willReturn($factorRow);
        $wpdb->method('insert')->willReturn(1);
        $wpdb->method('update')->willReturn(1);
        $wpdb->method('query')->willReturn(1);
        $wpdb->method('get_var')->willReturn(0);
        $GLOBALS['wpdb'] = $wpdb;

        $this->otpGenerator->method('generate')->willReturn('112233');
        $this->otpGenerator->method('hash')->willReturn('hashed');

        $dispatcher = $this->createMock(MessageDispatcher::class);
        $dispatcher->expects($this->once())
            ->method('sendImmediate')
            ->with($this->callback(fn($msg) => $msg instanceof EmailMessage
                && $msg->getRecipient() === 'user@example.com'
                && str_contains($msg->getBody(), '112233')
            ))
            ->willReturn(DeliveryResult::sent());

        $magicLink = $this->createMock(MagicLinkChannel::class);
        $verificationRepo = $this->createMock(VerificationRepository::class);
        $otpSvc = $this->createMock(OtpService::class);
        $otpSvc->method('createOtp')->willReturn('112233');
        $channel = new EmailChannel($this->otpGenerator, $this->auditLogger, $dispatcher, $magicLink, $verificationRepo, $otpSvc);
        $result = $channel->sendChallenge(1);

        $this->assertTrue($result->success);
    }

    // -- Helpers --

    private function makeFactorRow(ChannelStatus $status): object
    {
        return (object) [
            'id'         => 1,
            'user_id'    => 1,
            'channel_id' => 'email',
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
        $GLOBALS['_test_userdata'] = (object) [
            'ID'         => $userId,
            'user_email' => $email,
        ];
    }
}
