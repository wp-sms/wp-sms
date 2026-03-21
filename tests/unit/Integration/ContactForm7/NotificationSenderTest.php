<?php

// Global namespace stub for CF7's wpcf7_mail_replace_tags().
namespace {
    if (!function_exists('wpcf7_mail_replace_tags')) {
        function wpcf7_mail_replace_tags(string $content, array $options = []): string
        {
            $map = $GLOBALS['_test_cf7_mail_tags'] ?? [];

            $result = preg_replace_callback('/\[([a-zA-Z0-9_-]+)\]/', function ($matches) use ($map) {
                return $map[$matches[1]] ?? '';
            }, $content);

            if (!empty($options['exclude_blank'])) {
                $lines = explode("\n", $result);
                $lines = array_filter($lines, fn($line) => trim($line) !== '');
                $result = implode("\n", $lines);
            }

            return $result;
        }
    }
}

namespace WSms\Tests\Unit\Integration\ContactForm7 {

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use WSms\Integration\ContactForm7\NotificationSender;
use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\GatewayInterface;
use WSms\Messaging\Gateway\GatewayRegistry;
use WSms\Messaging\Message\EmailMessage;
use WSms\Messaging\Message\Message;
use WSms\Messaging\MessageDispatcher;

class NotificationSenderTest extends TestCase
{
    private MockObject&MessageDispatcher $messageDispatcher;
    private MockObject&GatewayRegistry $gatewayRegistry;
    private NotificationSender $sender;

    protected function setUp(): void
    {
        $this->messageDispatcher = $this->createMock(MessageDispatcher::class);
        $this->gatewayRegistry = $this->createMock(GatewayRegistry::class);
        $this->sender = new NotificationSender($this->messageDispatcher, $this->gatewayRegistry);

        $GLOBALS['_test_cf7_mail_tags'] = [
            'your-name'  => 'John Doe',
            'your-email' => 'john@example.com',
            'your-phone' => '+12025551234',
            'your-message' => 'Hello there',
        ];
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['_test_cf7_mail_tags']);
    }

    private function makeContactForm(array $notification = [], array $notification2 = []): \WPCF7_ContactForm
    {
        $contactForm = $this->createMock(\WPCF7_ContactForm::class);
        $contactForm->method('prop')->willReturnCallback(function ($key) use ($notification, $notification2) {
            return match ($key) {
                'wsms_notification'   => $notification,
                'wsms_notification_2' => $notification2,
                default               => null,
            };
        });
        return $contactForm;
    }

    private function makeActiveConfig(string $channel = 'sms', string $to = '[your-phone]', string $body = 'Hello [your-name]'): array
    {
        return [
            'active'        => true,
            'channel'       => $channel,
            'to'            => $to,
            'subject'       => '',
            'body'          => $body,
            'exclude_blank' => false,
        ];
    }

    private function configureGateway(string $channel): void
    {
        $gateway = $this->createMock(GatewayInterface::class);
        $this->gatewayRegistry->method('getDefault')
            ->with($channel)
            ->willReturn($gateway);
    }

    public function testSendsNotificationOnMailSent(): void
    {
        $config = $this->makeActiveConfig();
        $contactForm = $this->makeContactForm($config);
        $this->configureGateway('sms');

        $this->messageDispatcher->expects($this->once())
            ->method('sendImmediate')
            ->with($this->callback(fn($msg) =>
                $msg instanceof Message
                && $msg->getChannel() === 'sms'
                && $msg->getRecipient() === '+12025551234'
                && $msg->getBody() === 'Hello John Doe'
            ))
            ->willReturn(DeliveryResult::sent());

        $this->sender->onSubmit($contactForm, ['status' => 'mail_sent']);
    }

    public function testSendsNotificationOnMailFailed(): void
    {
        $config = $this->makeActiveConfig();
        $contactForm = $this->makeContactForm($config);
        $this->configureGateway('sms');

        $this->messageDispatcher->expects($this->once())
            ->method('sendImmediate')
            ->willReturn(DeliveryResult::sent());

        $this->sender->onSubmit($contactForm, ['status' => 'mail_failed']);
    }

    /**
     * @dataProvider skippedStatusProvider
     */
    public function testSkipsNonAllowedStatuses(string $status): void
    {
        $config = $this->makeActiveConfig();
        $contactForm = $this->makeContactForm($config);

        $this->messageDispatcher->expects($this->never())->method('sendImmediate');

        $this->sender->onSubmit($contactForm, ['status' => $status]);
    }

    public static function skippedStatusProvider(): array
    {
        return [
            'validation_failed'  => ['validation_failed'],
            'spam'               => ['spam'],
            'aborted'            => ['aborted'],
            'acceptance_missing' => ['acceptance_missing'],
        ];
    }

    public function testSkipsInactiveNotification(): void
    {
        $config = $this->makeActiveConfig();
        $config['active'] = false;
        $contactForm = $this->makeContactForm($config);

        $this->messageDispatcher->expects($this->never())->method('sendImmediate');

        $this->sender->onSubmit($contactForm, ['status' => 'mail_sent']);
    }

    public function testSkipsWhenChannelHasNoConfiguredGateway(): void
    {
        $config = $this->makeActiveConfig();
        $contactForm = $this->makeContactForm($config);

        $this->gatewayRegistry->method('getDefault')->with('sms')->willReturn(null);

        $this->messageDispatcher->expects($this->never())->method('sendImmediate');

        $this->sender->onSubmit($contactForm, ['status' => 'mail_sent']);
    }

    public function testSkipsWhenToResolvesToEmptyAfterReplacement(): void
    {
        $config = $this->makeActiveConfig('sms', '[nonexistent-field]', 'Hello');
        $contactForm = $this->makeContactForm($config);
        $this->configureGateway('sms');

        $this->messageDispatcher->expects($this->never())->method('sendImmediate');

        $this->sender->onSubmit($contactForm, ['status' => 'mail_sent']);
    }

    public function testUsesEmailMessageWithSubjectForEmailChannel(): void
    {
        $config = $this->makeActiveConfig('email', '[your-email]', 'Thanks [your-name]');
        $config['subject'] = 'Form from [your-name]';
        $contactForm = $this->makeContactForm($config);
        $this->configureGateway('email');

        $this->messageDispatcher->expects($this->once())
            ->method('sendImmediate')
            ->with($this->callback(fn($msg) =>
                $msg instanceof EmailMessage
                && $msg->getRecipient() === 'john@example.com'
                && $msg->getBody() === 'Thanks John Doe'
                && $msg->getMeta()['subject'] === 'Form from John Doe'
            ))
            ->willReturn(DeliveryResult::sent());

        $this->sender->onSubmit($contactForm, ['status' => 'mail_sent']);
    }

    public function testUsesMessageForWhatsAppChannel(): void
    {
        $config = $this->makeActiveConfig('whatsapp', '[your-phone]', 'WA: [your-message]');
        $contactForm = $this->makeContactForm($config);
        $this->configureGateway('whatsapp');

        $this->messageDispatcher->expects($this->once())
            ->method('sendImmediate')
            ->with($this->callback(fn($msg) =>
                $msg instanceof Message
                && $msg->getChannel() === 'whatsapp'
                && $msg->getRecipient() === '+12025551234'
                && $msg->getBody() === 'WA: Hello there'
            ))
            ->willReturn(DeliveryResult::sent());

        $this->sender->onSubmit($contactForm, ['status' => 'mail_sent']);
    }

    public function testUsesMessageForTelegramChannel(): void
    {
        $config = $this->makeActiveConfig('telegram', '123456789', 'TG: [your-name]');
        $contactForm = $this->makeContactForm($config);
        $this->configureGateway('telegram');

        $this->messageDispatcher->expects($this->once())
            ->method('sendImmediate')
            ->with($this->callback(fn($msg) =>
                $msg instanceof Message
                && $msg->getChannel() === 'telegram'
                && $msg->getRecipient() === '123456789'
                && $msg->getBody() === 'TG: John Doe'
            ))
            ->willReturn(DeliveryResult::sent());

        $this->sender->onSubmit($contactForm, ['status' => 'mail_sent']);
    }

    public function testReplacesMailTagsInToAndBody(): void
    {
        $config = $this->makeActiveConfig('sms', '[your-phone]', 'From [your-name]: [your-message]');
        $contactForm = $this->makeContactForm($config);
        $this->configureGateway('sms');

        $this->messageDispatcher->expects($this->once())
            ->method('sendImmediate')
            ->with($this->callback(fn($msg) =>
                $msg->getRecipient() === '+12025551234'
                && $msg->getBody() === 'From John Doe: Hello there'
            ))
            ->willReturn(DeliveryResult::sent());

        $this->sender->onSubmit($contactForm, ['status' => 'mail_sent']);
    }

    public function testPassesExcludeBlankOption(): void
    {
        // Set up tags where one is blank
        $GLOBALS['_test_cf7_mail_tags'] = [
            'your-name'  => 'John',
            'your-phone' => '+12025551234',
            'your-company' => '',
        ];

        $config = $this->makeActiveConfig('sms', '[your-phone]', "Name: [your-name]\n[your-company]\nDone");
        $config['exclude_blank'] = true;
        $contactForm = $this->makeContactForm($config);
        $this->configureGateway('sms');

        $this->messageDispatcher->expects($this->once())
            ->method('sendImmediate')
            ->with($this->callback(fn($msg) =>
                $msg->getBody() === "Name: John\nDone"
            ))
            ->willReturn(DeliveryResult::sent());

        $this->sender->onSubmit($contactForm, ['status' => 'mail_sent']);
    }

    public function testSendsBothNotificationsWhenBothActive(): void
    {
        $config1 = $this->makeActiveConfig('sms', '+19995551111', 'Admin alert');
        $config2 = $this->makeActiveConfig('sms', '[your-phone]', 'Thanks!');
        $contactForm = $this->makeContactForm($config1, $config2);

        $this->configureGateway('sms');

        $this->messageDispatcher->expects($this->exactly(2))
            ->method('sendImmediate')
            ->willReturn(DeliveryResult::sent());

        $this->sender->onSubmit($contactForm, ['status' => 'mail_sent']);
    }

    public function testSkipsWhenNotificationConfigIsNull(): void
    {
        $contactForm = $this->createMock(\WPCF7_ContactForm::class);
        $contactForm->method('prop')->willReturn(null);

        $this->messageDispatcher->expects($this->never())->method('sendImmediate');

        $this->sender->onSubmit($contactForm, ['status' => 'mail_sent']);
    }
}

} // namespace WSms\Tests\Unit\Integration\ContactForm7
