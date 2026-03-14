<?php

namespace WSms\Tests\Unit\Mfa\Channels;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use WSms\Audit\AuditLogger;
use WSms\Enums\ChannelStatus;
use WSms\Mfa\Channels\TotpChannel;

class TotpChannelTest extends TestCase
{
    private TotpChannel $channel;
    private MockObject&AuditLogger $auditLogger;

    protected function setUp(): void
    {
        $this->auditLogger = $this->createMock(AuditLogger::class);
        $this->channel = new TotpChannel($this->auditLogger);

        $this->setupWpdbMock(null);

        // Stub WordPress functions used by enroll().
        if (!function_exists('get_bloginfo')) {
            // Already defined in bootstrap.php.
        }
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['wpdb']);
    }

    public function testGetIdReturnsTotp(): void
    {
        $this->assertSame('totp', $this->channel->getId());
    }

    public function testGetNameReturnsAuthenticatorApp(): void
    {
        $this->assertSame('Authenticator App', $this->channel->getName());
    }

    public function testDoesNotSupportPrimaryAuth(): void
    {
        $this->assertFalse($this->channel->supportsPrimaryAuth());
    }

    public function testSupportsMfa(): void
    {
        $this->assertTrue($this->channel->supportsMfa());
    }

    public function testDoesNotSupportAutoEnrollment(): void
    {
        $this->assertFalse($this->channel->supportsAutoEnrollment());
    }

    public function testEnrollReturnsQrCodeAndSecret(): void
    {
        $this->setupWpdbMock(null);

        $result = $this->channel->enroll(1, []);

        $this->assertTrue($result->success);
        $this->assertTrue($result->data['requires_confirmation']);
        $this->assertStringStartsWith('data:image/svg+xml;base64,', $result->data['qr_code_uri']);
        $this->assertNotEmpty($result->data['secret']);
        $this->assertArrayHasKey('issuer', $result->data);
    }

    public function testEnrollRejectsWhenAlreadyEnrolled(): void
    {
        $this->setupWpdbMock($this->makeFactorRow(ChannelStatus::Active, ['secret' => 'JBSWY3DPEHPK3PXP']));

        $result = $this->channel->enroll(1, []);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Already enrolled', $result->message);
    }

    public function testEnrollAllowsReEnrollmentWhenPending(): void
    {
        $this->setupWpdbMock($this->makeFactorRow(ChannelStatus::Pending, ['secret' => 'OLDSECRET']));

        $result = $this->channel->enroll(1, []);

        $this->assertTrue($result->success);
        $this->assertTrue($result->data['requires_confirmation']);
    }

    public function testConfirmEnrollmentActivatesWithValidCode(): void
    {
        $secret = $this->generateTestSecret();
        $totp = \WSms\Dependencies\OTPHP\TOTP::createFromSecret($secret);
        $validCode = $totp->now();

        $this->setupWpdbMock($this->makeFactorRow(ChannelStatus::Pending, ['secret' => $secret]));

        $result = $this->channel->confirmEnrollment(1, $validCode);

        $this->assertTrue($result->success);
        $this->assertStringContainsString('enrolled successfully', $result->message);
    }

    public function testConfirmEnrollmentRejectsInvalidCode(): void
    {
        $secret = $this->generateTestSecret();
        $this->setupWpdbMock($this->makeFactorRow(ChannelStatus::Pending, ['secret' => $secret]));

        $result = $this->channel->confirmEnrollment(1, '000000');

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid', $result->message);
    }

    public function testConfirmEnrollmentFailsWithNoPendingFactor(): void
    {
        $this->setupWpdbMock(null);

        $result = $this->channel->confirmEnrollment(1, '123456');

        $this->assertFalse($result->success);
        $this->assertStringContainsString('No pending enrollment', $result->message);
    }

    public function testConfirmEnrollmentFailsWhenAlreadyActive(): void
    {
        $secret = $this->generateTestSecret();
        $this->setupWpdbMock($this->makeFactorRow(ChannelStatus::Active, ['secret' => $secret]));

        $result = $this->channel->confirmEnrollment(1, '123456');

        $this->assertFalse($result->success);
    }

    public function testSendChallengeReturnsNoDelivery(): void
    {
        $result = $this->channel->sendChallenge(1);

        $this->assertTrue($result->success);
        $this->assertFalse($result->meta['requires_delivery']);
        $this->assertStringContainsString('authenticator app', $result->message);
    }

    public function testVerifyAcceptsValidCode(): void
    {
        $secret = $this->generateTestSecret();
        $totp = \WSms\Dependencies\OTPHP\TOTP::createFromSecret($secret);
        $validCode = $totp->now();

        // Set last_used_timestamp to a past window to avoid anti-replay.
        $pastTimestamp = (int) floor(time() / $totp->getPeriod()) - 5;
        $factorRow = $this->makeFactorRow(ChannelStatus::Active, [
            'secret'              => $secret,
            'last_used_timestamp' => $pastTimestamp,
        ]);

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
        $GLOBALS['wpdb'] = $wpdb;

        $this->assertTrue($this->channel->verify(1, $validCode));
    }

    public function testVerifyRejectsInvalidCode(): void
    {
        $secret = $this->generateTestSecret();
        $pastTimestamp = (int) floor(time() / 30) - 5;

        $this->setupWpdbMock($this->makeFactorRow(ChannelStatus::Active, [
            'secret'              => $secret,
            'last_used_timestamp' => $pastTimestamp,
        ]));

        $this->assertFalse($this->channel->verify(1, '000000'));
    }

    public function testVerifyRejectsReplay(): void
    {
        $secret = $this->generateTestSecret();
        $totp = \WSms\Dependencies\OTPHP\TOTP::createFromSecret($secret);

        // Set last_used_timestamp to current window (simulating replay).
        $currentTimestamp = (int) floor(time() / $totp->getPeriod());

        $this->setupWpdbMock($this->makeFactorRow(ChannelStatus::Active, [
            'secret'              => $secret,
            'last_used_timestamp' => $currentTimestamp,
        ]));

        $validCode = $totp->now();

        $this->assertFalse($this->channel->verify(1, $validCode));
    }

    public function testVerifyFailsWhenNotEnrolled(): void
    {
        $this->setupWpdbMock(null);

        $this->assertFalse($this->channel->verify(1, '123456'));
    }

    public function testVerifyFailsWhenDisabled(): void
    {
        $this->setupWpdbMock($this->makeFactorRow(ChannelStatus::Disabled, ['secret' => 'JBSWY3DPEHPK3PXP']));

        $this->assertFalse($this->channel->verify(1, '123456'));
    }

    public function testUnenrollClearsSecret(): void
    {
        $wpdb = $this->getMockBuilder(\stdClass::class)
            ->addMethods(['get_row', 'prepare', 'insert', 'update', 'query', 'get_var'])
            ->getMock();
        $wpdb->prefix = 'wp_';
        $wpdb->insert_id = 1;
        $wpdb->rows_affected = 1;
        $wpdb->method('prepare')->willReturnCallback(fn(string $q) => $q);
        $wpdb->method('get_row')->willReturn(
            $this->makeFactorRow(ChannelStatus::Active, ['secret' => 'JBSWY3DPEHPK3PXP']),
        );

        // Capture the update call to verify secret is cleared.
        $wpdb->expects($this->once())
            ->method('update')
            ->with(
                'wp_wsms_user_factors',
                $this->callback(function (array $data) {
                    $meta = json_decode($data['meta'], true);

                    return $data['status'] === 'disabled' && empty($meta);
                }),
                $this->anything(),
            )
            ->willReturn(1);

        $GLOBALS['wpdb'] = $wpdb;

        $this->assertTrue($this->channel->unenroll(1));
    }

    public function testUnenrollFailsWhenNotEnrolled(): void
    {
        $this->setupWpdbMock(null);

        $this->assertFalse($this->channel->unenroll(1));
    }

    public function testIsEnrolledReturnsTrueForActiveFactor(): void
    {
        $this->setupWpdbMock($this->makeFactorRow(ChannelStatus::Active, ['secret' => 'JBSWY3DPEHPK3PXP']));

        $this->assertTrue($this->channel->isEnrolled(1));
    }

    public function testIsEnrolledReturnsFalseWhenNoFactor(): void
    {
        $this->setupWpdbMock(null);

        $this->assertFalse($this->channel->isEnrolled(1));
    }

    public function testIsAvailableForUserMatchesIsEnrolled(): void
    {
        $this->setupWpdbMock($this->makeFactorRow(ChannelStatus::Active, ['secret' => 'JBSWY3DPEHPK3PXP']));

        $this->assertTrue($this->channel->isAvailableForUser(1));
    }

    public function testGetEnrollmentInfoNeverExposesSecret(): void
    {
        $this->setupWpdbMock($this->makeFactorRow(ChannelStatus::Active, ['secret' => 'JBSWY3DPEHPK3PXP']));

        $info = $this->channel->getEnrollmentInfo(1);

        $this->assertTrue($info['enrolled']);
        $this->assertSame('totp', $info['channel']);
        $this->assertArrayNotHasKey('secret', $info);
    }

    public function testGetEnrollmentInfoWhenNotEnrolled(): void
    {
        $this->setupWpdbMock(null);

        $info = $this->channel->getEnrollmentInfo(1);

        $this->assertFalse($info['enrolled']);
    }

    // -- Helpers --

    private function generateTestSecret(): string
    {
        $totp = \WSms\Dependencies\OTPHP\TOTP::generate();

        return $totp->getSecret();
    }

    private function makeFactorRow(ChannelStatus $status, array $meta = []): object
    {
        return (object) [
            'id'         => 1,
            'user_id'    => 1,
            'channel_id' => 'totp',
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
