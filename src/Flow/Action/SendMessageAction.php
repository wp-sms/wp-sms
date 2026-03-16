<?php

namespace WSms\Flow\Action;

use WSms\Flow\Contracts\ActionInterface;
use WSms\Flow\Contracts\ActionResult;
use WSms\Messaging\MessageDispatcher;
use WSms\Messaging\Message\EmailMessage;
use WSms\Messaging\Message\SmsMessage;
use WSms\Messaging\Message\WebhookMessage;

defined('ABSPATH') || exit;

class SendMessageAction implements ActionInterface
{
    public function __construct(
        private readonly MessageDispatcher $messageDispatcher,
    ) {
    }

    public function getId(): string
    {
        return 'send_message';
    }

    public function getName(): string
    {
        return __('Send Message', 'wp-sms');
    }

    public function getGroup(): string
    {
        return 'Messaging';
    }

    public function getConfigSchema(): array
    {
        return [
            'gateway' => ['type' => 'string', 'label' => __('Gateway', 'wp-sms'), 'required' => true],
            'channel' => ['type' => 'string', 'label' => __('Channel', 'wp-sms'), 'required' => true, 'enum' => ['sms', 'email', 'webhook']],
            'to'      => ['type' => 'string', 'label' => __('Recipient', 'wp-sms'), 'template' => true, 'required' => true],
            'body'    => ['type' => 'text', 'label' => __('Message Body', 'wp-sms'), 'template' => true, 'required' => true],
            'subject' => ['type' => 'string', 'label' => __('Subject (email only)', 'wp-sms'), 'template' => true],
        ];
    }

    public function execute(array $payload, array $config): ActionResult
    {
        $channel = $config['channel'] ?? 'sms';
        $to = $config['to'] ?? '';
        $body = $config['body'] ?? '';

        $executionId = $payload['_execution_id'] ?? null;
        $message = $this->buildMessage($channel, $to, $body, $config, $executionId);

        $result = $this->messageDispatcher->sendImmediate($message);

        if ($result->success) {
            return ActionResult::success([
                'status'      => $result->status,
                'provider_id' => $result->providerId,
            ]);
        }

        return ActionResult::failure($result->error ?? 'Send failed');
    }

    private function buildMessage(string $channel, string $to, string $body, array $config, ?string $executionId): \WSms\Messaging\Contracts\MessageInterface
    {
        return match ($channel) {
            'email'   => new EmailMessage($to, $body, $config['subject'] ?? '', [], $executionId),
            'webhook' => new WebhookMessage($to, $body, $config['method'] ?? 'POST', $config['headers'] ?? [], $executionId),
            default   => new SmsMessage($to, $body, $executionId),
        };
    }
}
