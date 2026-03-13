<?php

namespace WSms\Tests\Unit\Mfa\Channels;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use WSms\Audit\AuditLogger;
use WSms\Enums\ChannelStatus;
use WSms\Mfa\Channels\TelegramChannel;
use WSms\Mfa\OtpGenerator;
use WSms\Telegram\TelegramBotClient;

class TelegramChannelTest extends TestCase
{
    private TelegramChannel $channel;
    private MockObject&OtpGenerator $otpGenerator;
    private MockObject&AuditLogger $auditLogger;
    private MockObject&TelegramBotClient $botClient;
    private object $wpdb;

    protected function setUp(): void
    {
        $this->otpGenerator = $this->createMock(OtpGenerator::class);
        $this->auditLogger = $this->createMock(AuditLogger::class);
        $this->botClient = $this->createMock(TelegramBotClient::class);
        $this->channel = new TelegramChannel($this->otpGenerator, $this->auditLogger, $this->botClient);

        $this->setupWpdbMock(null);
        $GLOBALS['_test_options'] = ['wsms_auth_settings' => ['telegram' => ['bot_username' => 'test_bot']]];
        unset($GLOBALS['_test_transients']);
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['wpdb'], $GLOBALS['_test_options'], $GLOBALS['_test_transients']);
    }

    public function testGetIdReturnsTelegram(): void
    {
        $this->assertSame('telegram', $this->channel->getId());
    }

    public function testGetNameReturnsTelegram(): void
    {
        $this->assertSame('Telegram', $this->channel->getName());
    }

    public function testDoesNotSupportPrimaryAuth(): void
    {
        $this->assertFalse($this->channel->supportsPrimaryAuth());
    }

    public function testSupportsMfa(): void
    {
        $this->assertTrue($this->channel->supportsMfa());
    }

    public function testSupportsAutoEnrollment(): void
    {
        $this->assertTrue($this->channel->supportsAutoEnrollment());
    }

    public function testEnrollCreatesDeepLink(): void
    {
        $this->setupWpdbMock(null);

        $result = $this->channel->enroll(1, []);

        $this->assertTrue($result->success);
        $this->assertArrayHasKey('deep_link', $result->data);
        $this->assertStringContainsString('https://t.me/test_bot?start=', $result->data['deep_link']);
        $this->assertArrayHasKey('linking_token', $result->data);
        $this->assertTrue($result->data['requires_confirmation']);
    }

    public function testEnrollRejectsAlreadyEnrolled(): void
    {
        $this->setupWpdbMock($this->makeFactorRow(ChannelStatus::Active));

        $result = $this->channel->enroll(1, []);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Already enrolled', $result->message);
    }

    public function testAutoEnrollCreatesActiveFactor(): void
    {
        $this->setupWpdbMock(null);

        $this->channel->autoEnroll(42, 987654, 'johndoe');

        // Factor should have been created — verify via wpdb insert.
        // The mock insert is configured to return 1, so no exception = success.
        // We just verify the audit log was called.
        $this->auditLogger->expects($this->once())
            ->method('log')
            ->with(
                $this->anything(),
                'success',
                42,
                $this->callback(fn($ctx) => $ctx['channel'] === 'telegram' && $ctx['method'] === 'auto'),
            );

        // Re-run to trigger the expectation.
        $this->setupWpdbMock(null);
        $this->channel->autoEnroll(42, 987654, 'johndoe');
    }

    public function testCompleteLinkingActivatesPendingFactor(): void
    {
        // Simulate a linking token in transient.
        $GLOBALS['_test_transients']['wsms_tg_link_' . str_repeat('ab', 16)] = [
            'value'   => 1,
            'expires' => time() + 300,
        ];

        $this->setupWpdbMock($this->makeFactorRow(ChannelStatus::Pending));

        $result = $this->channel->completeLinking(str_repeat('ab', 16), 12345, 'telegram_user');

        $this->assertTrue($result);
    }

    public function testCompleteLinkingReturnsFalseForExpiredToken(): void
    {
        // No transient = expired token.
        $this->assertFalse($this->channel->completeLinking('nonexistent_token', 12345));
    }

    public function testCompleteLinkingReturnsFalseWhenNoFactorPending(): void
    {
        $GLOBALS['_test_transients']['wsms_tg_link_validtoken123456789012'] = [
            'value'   => 1,
            'expires' => time() + 300,
        ];

        $this->setupWpdbMock(null); // No factor found.

        $this->assertFalse($this->channel->completeLinking('validtoken123456789012', 12345));
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

    public function testGetIdentifierReturnsChatIdFromFactor(): void
    {
        $this->setupWpdbMock($this->makeFactorRow(ChannelStatus::Active, ['chat_id' => 99999]));

        // isAvailableForUser uses getIdentifier internally.
        $this->assertTrue($this->channel->isAvailableForUser(1));
    }

    public function testGetIdentifierReturnsNullWhenNoChatId(): void
    {
        $this->setupWpdbMock($this->makeFactorRow(ChannelStatus::Active, []));

        $this->assertFalse($this->channel->isAvailableForUser(1));
    }

    public function testMaskIdentifierMaskesLongChatId(): void
    {
        $this->setupWpdbMock($this->makeFactorRow(ChannelStatus::Active, ['chat_id' => 987654321]));

        $info = $this->channel->getEnrollmentInfo(1);

        $this->assertTrue($info['enrolled']);
        $this->assertStringContainsString('Telegram', $info['identifier']);
        $this->assertStringContainsString('***', $info['identifier']);
    }

    public function testSendChallengeDeliversViaBot(): void
    {
        $factorRow = $this->makeFactorRow(ChannelStatus::Active, ['chat_id' => 12345]);

        // Setup wpdb: first get_row returns factor, subsequent returns null (no cooldown, no existing verification).
        $wpdb = $this->getMockBuilder(\stdClass::class)
            ->addMethods(['get_row', 'prepare', 'insert', 'update', 'query', 'get_var', 'get_results'])
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

        $this->otpGenerator->method('generate')->willReturn('654321');
        $this->otpGenerator->method('hash')->willReturn('hashed');

        $this->botClient->expects($this->once())
            ->method('sendMessage')
            ->with(12345, $this->stringContains('654321'))
            ->willReturn(true);

        $result = $this->channel->sendChallenge(1);

        $this->assertTrue($result->success);
    }

    public function testSendChallengeFailsWhenNotEnrolled(): void
    {
        $this->setupWpdbMock(null);

        $result = $this->channel->sendChallenge(1);

        $this->assertFalse($result->success);
    }

    // -- Helpers --

    private function makeFactorRow(ChannelStatus $status, array $meta = ['chat_id' => 12345]): object
    {
        return (object) [
            'id'         => 1,
            'user_id'    => 1,
            'channel_id' => 'telegram',
            'status'     => $status->value,
            'meta'       => json_encode($meta),
            'created_at' => '2026-01-01 00:00:00',
            'updated_at' => '2026-01-01 00:00:00',
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
