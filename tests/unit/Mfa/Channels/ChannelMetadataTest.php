<?php

namespace WSms\Tests\Unit\Mfa\Channels;

use PHPUnit\Framework\TestCase;
use WSms\Audit\AuditLogger;
use WSms\Messaging\MessageDispatcher;
use WSms\Messaging\Template\TemplateManager;
use WSms\Mfa\Channels\BackupCodesChannel;
use WSms\Mfa\Channels\EmailChannel;
use WSms\Mfa\Channels\LineChannel;
use WSms\Mfa\Channels\MagicLinkChannel;
use WSms\Mfa\Channels\PasskeyChannel;
use WSms\Mfa\Channels\PhoneChannel;
use WSms\Mfa\Channels\TelegramChannel;
use WSms\Mfa\Channels\TotpChannel;
use WSms\Mfa\Contracts\ChannelInterface;
use WSms\Mfa\SecretEncryptor;
use WSms\Verification\OtpGenerator;
use WSms\Verification\VerificationRepository;

/**
 * Verifies that all built-in channels implement getIconSvg(), getDescription(), and getConfigSchema().
 */
class ChannelMetadataTest extends TestCase
{
    /** @return ChannelInterface[] */
    private function buildAllChannels(): array
    {
        $otpGenerator = $this->createStub(OtpGenerator::class);
        $auditLogger = $this->createStub(AuditLogger::class);
        $dispatcher = $this->createStub(MessageDispatcher::class);
        $verificationRepo = $this->createStub(VerificationRepository::class);
        $templateManager = $this->createStub(TemplateManager::class);
        $encryptor = $this->createStub(SecretEncryptor::class);

        $magicLink = new MagicLinkChannel($otpGenerator, $auditLogger, $dispatcher, $verificationRepo, $templateManager);

        return [
            'phone'        => new PhoneChannel($otpGenerator, $auditLogger, $dispatcher, $magicLink, $verificationRepo, $templateManager),
            'email'        => new EmailChannel($otpGenerator, $auditLogger, $dispatcher, $magicLink, $verificationRepo, $templateManager),
            'backup_codes' => new BackupCodesChannel($otpGenerator, $auditLogger),
            'telegram'     => new TelegramChannel($otpGenerator, $auditLogger, $dispatcher, $verificationRepo, $templateManager),
            'line'         => new LineChannel($otpGenerator, $auditLogger, $dispatcher, $verificationRepo, $templateManager),
            'totp'         => new TotpChannel($auditLogger, $encryptor),
            'passkey'      => new PasskeyChannel($auditLogger, $encryptor),
            'magic_link'   => $magicLink,
        ];
    }

    public function testAllChannelsReturnValidIconSvg(): void
    {
        foreach ($this->buildAllChannels() as $id => $channel) {
            $svg = $channel->getIconSvg();
            $this->assertNotEmpty($svg, "$id should return a non-empty SVG");
            $this->assertStringContainsString('<svg', $svg, "$id should return valid SVG markup");
        }
    }

    public function testAllChannelsReturnNonEmptyDescription(): void
    {
        foreach ($this->buildAllChannels() as $id => $channel) {
            $this->assertNotEmpty($channel->getDescription(), "$id should return a non-empty description");
        }
    }

    public function testAllBuiltinChannelsReturnEmptyConfigSchema(): void
    {
        foreach ($this->buildAllChannels() as $id => $channel) {
            $this->assertSame([], $channel->getConfigSchema(), "$id (built-in) should return empty config schema");
        }
    }
}
