<?php

namespace WSms\Integration\ContactForm7;

use WSms\Messaging\Gateway\GatewayRegistry;
use WSms\Messaging\Message\EmailMessage;
use WSms\Messaging\Message\Message;
use WSms\Messaging\MessageDispatcher;

defined('ABSPATH') || exit;

class NotificationSender
{
    private const ALLOWED_STATUSES = ['mail_sent', 'mail_failed'];

    public function __construct(
        private readonly MessageDispatcher $messageDispatcher,
        private readonly GatewayRegistry $gatewayRegistry,
    ) {
    }

    public function registerHooks(): void
    {
        add_action('wpcf7_submit', [$this, 'onSubmit'], 10, 2);
    }

    /**
     * Handle CF7 form submission — send configured notifications.
     */
    public function onSubmit(\WPCF7_ContactForm $contactForm, array $result): void
    {
        if (!in_array($result['status'] ?? '', self::ALLOWED_STATUSES, true)) {
            return;
        }

        $this->maybeSend($contactForm->prop('wsms_notification'));
        $this->maybeSend($contactForm->prop('wsms_notification_2'));
    }

    /**
     * Send a single notification if active and valid.
     */
    private function maybeSend(?array $config): void
    {
        if (!is_array($config) || empty($config['active'])) {
            return;
        }

        $channel = $config['channel'] ?? 'sms';

        if ($this->gatewayRegistry->getDefault($channel) === null) {
            return;
        }

        $to = \wpcf7_mail_replace_tags($config['to'] ?? '');

        if (trim($to) === '') {
            return;
        }

        $options = [];
        if (!empty($config['exclude_blank'])) {
            $options['exclude_blank'] = true;
        }

        $body = \wpcf7_mail_replace_tags($config['body'] ?? '', $options);

        if ($channel === 'email') {
            $subject = \wpcf7_mail_replace_tags($config['subject'] ?? '');
            $message = new EmailMessage($to, $body, $subject);
        } else {
            $message = new Message($channel, $to, $body);
        }

        $this->messageDispatcher->sendImmediate($message);
    }
}
