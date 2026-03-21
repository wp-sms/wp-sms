<?php

namespace WSms\Tests\Unit\Integration\ContactForm7;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use WSms\Integration\ContactForm7\EditorPanel;
use WSms\Messaging\Gateway\GatewayRegistry;

class EditorPanelTest extends TestCase
{
    private MockObject&GatewayRegistry $gatewayRegistry;
    private EditorPanel $panel;

    protected function setUp(): void
    {
        $this->gatewayRegistry = $this->createMock(GatewayRegistry::class);
        $this->panel = new EditorPanel($this->gatewayRegistry);
        $_POST = [];
    }

    protected function tearDown(): void
    {
        $_POST = [];
    }

    public function testRegisterPropertiesSetsDefaultsWhenNotPresent(): void
    {
        $properties = [];
        $result = $this->panel->registerProperties($properties);

        $expected = [
            'active'        => false,
            'channel'       => 'sms',
            'to'            => '',
            'subject'       => '',
            'body'          => '',
            'exclude_blank' => false,
        ];

        $this->assertSame($expected, $result['wsms_notification']);
        $this->assertSame($expected, $result['wsms_notification_2']);
    }

    public function testRegisterPropertiesPreservesExistingValues(): void
    {
        $existing = [
            'active'        => true,
            'channel'       => 'email',
            'to'            => 'admin@example.com',
            'subject'       => 'Test',
            'body'          => 'Hello',
            'exclude_blank' => true,
        ];

        $properties = ['wsms_notification' => $existing];
        $result = $this->panel->registerProperties($properties);

        $this->assertSame($existing, $result['wsms_notification']);
    }

    public function testSanitizeChannelAgainstWhitelist(): void
    {
        $_POST['wsms_notification'] = [
            'active'  => '1',
            'channel' => 'sms',
            'to'      => '+1234567890',
            'body'    => 'Hello',
        ];

        $result = $this->panel->sanitizeNotificationConfig('wsms_notification');

        $this->assertSame('sms', $result['channel']);
    }

    public function testSanitizeUnknownChannelFallsBackToSms(): void
    {
        $_POST['wsms_notification'] = [
            'active'  => '1',
            'channel' => 'carrier_pigeon',
            'to'      => '+1234567890',
            'body'    => 'Hello',
        ];

        $result = $this->panel->sanitizeNotificationConfig('wsms_notification');

        $this->assertSame('sms', $result['channel']);
    }

    public function testSanitizeToWithSanitizeTextField(): void
    {
        $_POST['wsms_notification'] = [
            'active'  => '1',
            'channel' => 'sms',
            'to'      => '  <script>alert(1)</script>+1234567890  ',
            'body'    => 'Hello',
        ];

        $result = $this->panel->sanitizeNotificationConfig('wsms_notification');

        $this->assertSame('alert(1)+1234567890', $result['to']);
    }

    public function testSanitizeSubjectWithSanitizeTextField(): void
    {
        $_POST['wsms_notification'] = [
            'active'  => '1',
            'channel' => 'email',
            'to'      => 'test@example.com',
            'subject' => '  <b>Subject</b>  ',
            'body'    => 'Hello',
        ];

        $result = $this->panel->sanitizeNotificationConfig('wsms_notification');

        $this->assertSame('Subject', $result['subject']);
    }

    public function testSanitizeBodyWithSanitizeTextareaField(): void
    {
        $_POST['wsms_notification'] = [
            'active'  => '1',
            'channel' => 'sms',
            'to'      => '+1234567890',
            'body'    => '  <script>alert(1)</script>Hello World  ',
        ];

        $result = $this->panel->sanitizeNotificationConfig('wsms_notification');

        $this->assertSame('alert(1)Hello World', $result['body']);
    }

    public function testSanitizeCastsActiveToBool(): void
    {
        $_POST['wsms_notification'] = [
            'active'  => '1',
            'channel' => 'sms',
            'to'      => '+1234567890',
            'body'    => 'Hello',
        ];

        $result = $this->panel->sanitizeNotificationConfig('wsms_notification');

        $this->assertTrue($result['active']);

        $_POST['wsms_notification_2'] = [
            'channel' => 'sms',
            'to'      => '+1234567890',
            'body'    => 'Hello',
        ];

        $result = $this->panel->sanitizeNotificationConfig('wsms_notification_2');

        $this->assertFalse($result['active']);
    }

    public function testSanitizeCastsExcludeBlankToBool(): void
    {
        $_POST['wsms_notification'] = [
            'active'        => '1',
            'channel'       => 'sms',
            'to'            => '+1234567890',
            'body'          => 'Hello',
            'exclude_blank' => '1',
        ];

        $result = $this->panel->sanitizeNotificationConfig('wsms_notification');

        $this->assertTrue($result['exclude_blank']);

        $_POST['wsms_notification_2'] = [
            'active'  => '1',
            'channel' => 'sms',
            'to'      => '+1234567890',
            'body'    => 'Hello',
        ];

        $result = $this->panel->sanitizeNotificationConfig('wsms_notification_2');

        $this->assertFalse($result['exclude_blank']);
    }

    public function testSanitizeReturnsDefaultsForNonArrayInput(): void
    {
        $_POST['wsms_notification'] = 'not an array';

        $result = $this->panel->sanitizeNotificationConfig('wsms_notification');

        $this->assertFalse($result['active']);
        $this->assertSame('sms', $result['channel']);
        $this->assertSame('', $result['to']);
    }

    public function testSanitizeReturnsDefaultsWhenKeyMissing(): void
    {
        $result = $this->panel->sanitizeNotificationConfig('wsms_notification');

        $this->assertFalse($result['active']);
        $this->assertSame('sms', $result['channel']);
    }

    /**
     * @dataProvider allowedChannelsProvider
     */
    public function testSanitizeAcceptsAllAllowedChannels(string $channel): void
    {
        $_POST['wsms_notification'] = [
            'active'  => '1',
            'channel' => $channel,
            'to'      => 'recipient',
            'body'    => 'Hello',
        ];

        $result = $this->panel->sanitizeNotificationConfig('wsms_notification');

        $this->assertSame($channel, $result['channel']);
    }

    public static function allowedChannelsProvider(): array
    {
        return [
            'sms'      => ['sms'],
            'email'    => ['email'],
            'whatsapp' => ['whatsapp'],
            'telegram' => ['telegram'],
        ];
    }
}
