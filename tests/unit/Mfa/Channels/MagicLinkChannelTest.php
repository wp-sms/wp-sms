<?php

namespace WSms\Tests\Unit\Mfa\Channels;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use WSms\Audit\AuditLogger;
use WSms\Enums\ChannelStatus;
use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Message\EmailMessage;
use WSms\Messaging\MessageDispatcher;
use WSms\Mfa\Channels\MagicLinkChannel;
use WSms\Verification\OtpGenerator;
use WSms\Verification\OtpService;
use WSms\Verification\VerificationRepository;

class MagicLinkChannelTest extends TestCase
{
    private MagicLinkChannel $channel;
    private MockObject&OtpGenerator $otpGenerator;
    private MockObject&AuditLogger $auditLogger;
    private MockObject&MessageDispatcher $dispatcher;
    private MockObject&VerificationRepository $verificationRepo;

    protected function setUp(): void
    {
        $this->otpGenerator = $this->createMock(OtpGenerator::class);
        $this->auditLogger = $this->createMock(AuditLogger::class);
        $this->dispatcher = $this->createMock(MessageDispatcher::class);
        $this->dispatcher->method('sendImmediate')->willReturn(DeliveryResult::sent());
        $this->verificationRepo = $this->createMock(VerificationRepository::class);
        $otpService = $this->createMock(OtpService::class);
        $otpService->method('createToken')->willReturn('abc123token');
        $this->channel = new MagicLinkChannel($this->otpGenerator, $this->auditLogger, $this->dispatcher, $this->verificationRepo, $otpService);

        $this->setupWpdbMock(null);
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['wpdb'], $GLOBALS['_test_userdata']);
    }

    public function testGetIdReturnsMagicLink(): void
    {
        $this->assertSame('magic_link', $this->channel->getId());
    }

    public function testGetNameReturnsMagicLink(): void
    {
        $this->assertSame('Magic Link', $this->channel->getName());
    }

    public function testSupportsPrimaryAuth(): void
    {
        $this->assertTrue($this->channel->supportsPrimaryAuth());
    }

    public function testDoesNotSupportMfa(): void
    {
        $this->assertFalse($this->channel->supportsMfa());
    }

    public function testEnrollFailsWhenNoEmail(): void
    {
        $result = $this->channel->enroll(1, []);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('No email', $result->message);
    }

    public function testEnrollSucceedsWithEmail(): void
    {
        $this->overrideGetUserdata(1, 'user@example.com');
        $this->setupWpdbMock(null);

        $result = $this->channel->enroll(1, []);

        $this->assertTrue($result->success);
        $this->assertArrayHasKey('masked_email', $result->data);
    }

    public function testEnrollRejectsAlreadyEnrolled(): void
    {
        $this->overrideGetUserdata(1, 'user@example.com');
        $this->setupWpdbMock($this->makeFactorRow(ChannelStatus::Active));

        $result = $this->channel->enroll(1, []);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Already enrolled', $result->message);
    }

    public function testVerifySucceedsWithValidToken(): void
    {
        $token = 'abc123';
        $hashedToken = hash('sha256', $token);

        $this->otpGenerator->method('hash')->willReturn($hashedToken);

        $verification = (object) [
            'id'         => 1,
            'user_id'    => 1,
            'channel_id' => 'magic_link',
            'code'       => $hashedToken,
            'expires_at' => gmdate('Y-m-d H:i:s', time() + 600),
            'used_at'    => null,
        ];

        $this->verificationRepo->method('findByCodeAndChannel')->willReturn($verification);
        $this->verificationRepo->method('markUsed')->willReturn(true);

        $this->assertTrue($this->channel->verify(1, $token));
    }

    public function testVerifyFailsWithExpiredToken(): void
    {
        $token = 'abc123';
        $hashedToken = hash('sha256', $token);

        $this->otpGenerator->method('hash')->willReturn($hashedToken);

        $verification = (object) [
            'id'         => 1,
            'user_id'    => 1,
            'channel_id' => 'magic_link',
            'code'       => $hashedToken,
            'expires_at' => gmdate('Y-m-d H:i:s', time() - 10),
            'used_at'    => null,
        ];

        $this->verificationRepo->method('findByCodeAndChannel')->willReturn($verification);

        $this->assertFalse($this->channel->verify(1, $token));
    }

    public function testVerifyFailsWhenNoVerification(): void
    {
        $this->otpGenerator->method('hash')->willReturn('somehash');
        $this->verificationRepo->method('findByCodeAndChannel')->willReturn(null);

        $this->assertFalse($this->channel->verify(1, 'badtoken'));
    }

    public function testVerifyTokenAndResolveUserReturnsUserId(): void
    {
        $token = 'magictoken123';
        $hashedToken = hash('sha256', $token);

        $this->otpGenerator->method('hash')->willReturn($hashedToken);

        $verification = (object) [
            'id'         => 1,
            'user_id'    => 42,
            'channel_id' => 'magic_link',
            'code'       => $hashedToken,
            'expires_at' => gmdate('Y-m-d H:i:s', time() + 600),
            'used_at'    => null,
        ];

        $this->verificationRepo->method('findByCodeAndChannel')->willReturn($verification);
        $this->verificationRepo->method('markUsed')->willReturn(true);

        $this->assertSame(42, $this->channel->verifyTokenAndResolveUser($token));
    }

    public function testVerifyTokenAndResolveUserReturnsNullForExpired(): void
    {
        $this->otpGenerator->method('hash')->willReturn('somehash');

        $verification = (object) [
            'id'         => 1,
            'user_id'    => 1,
            'channel_id' => 'magic_link',
            'code'       => 'somehash',
            'expires_at' => gmdate('Y-m-d H:i:s', time() - 10),
            'used_at'    => null,
        ];

        $this->verificationRepo->method('findByCodeAndChannel')->willReturn($verification);

        $this->assertNull($this->channel->verifyTokenAndResolveUser('badtoken'));
    }

    public function testSendChallengeSendsEmailViaDispatcher(): void
    {
        $this->overrideGetUserdata(1, 'user@example.com');

        $factorRow = $this->makeFactorRow(ChannelStatus::Active);

        $wpdb = $this->getMockBuilder(\stdClass::class)
            ->addMethods(['get_row', 'prepare', 'insert', 'update', 'query', 'get_var'])
            ->getMock();
        $wpdb->prefix = 'wp_';
        $wpdb->insert_id = 1;
        $wpdb->rows_affected = 1;
        $wpdb->method('prepare')->willReturnCallback(fn(string $q) => $q);
        $wpdb->method('get_row')->willReturnOnConsecutiveCalls($factorRow, null);
        $wpdb->method('insert')->willReturn(1);
        $wpdb->method('update')->willReturn(1);
        $wpdb->method('query')->willReturn(1);
        $wpdb->method('get_var')->willReturn(0);
        $GLOBALS['wpdb'] = $wpdb;

        $this->otpGenerator->method('generateToken')->willReturn('abc123token');
        $this->otpGenerator->method('hash')->willReturn('hashed');

        $dispatcher = $this->createMock(MessageDispatcher::class);
        $dispatcher->expects($this->once())
            ->method('sendImmediate')
            ->with($this->callback(fn($msg) => $msg instanceof EmailMessage
                && $msg->getRecipient() === 'user@example.com'
            ))
            ->willReturn(DeliveryResult::sent());

        $verificationRepo = $this->createMock(VerificationRepository::class);
        $otpSvc = $this->createMock(OtpService::class);
        $otpSvc->method('createToken')->willReturn('abc123token');
        $channel = new MagicLinkChannel($this->otpGenerator, $this->auditLogger, $dispatcher, $verificationRepo, $otpSvc);
        $channel->sendChallenge(1);
    }

    public function testSendChallengeFailsWhenNotEnrolled(): void
    {
        $this->setupWpdbMock(null);

        $result = $this->channel->sendChallenge(1);

        $this->assertFalse($result->success);
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
            'channel_id' => 'magic_link',
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
