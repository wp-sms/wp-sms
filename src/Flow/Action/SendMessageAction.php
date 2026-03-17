<?php

namespace WSms\Flow\Action;

use WSms\Flow\Contracts\AbstractAction;
use WSms\Flow\Contracts\ActionResult;
use WSms\Messaging\Gateway\GatewayRegistry;
use WSms\Messaging\MessageDispatcher;
use WSms\Messaging\Message\EmailMessage;
use WSms\Messaging\Message\Message;
use WSms\Messaging\Message\WebhookMessage;

defined('ABSPATH') || exit;

class SendMessageAction extends AbstractAction
{
    public const DEFAULT_GATEWAY = '__default__';

    public function __construct(
        private readonly MessageDispatcher $messageDispatcher,
        private readonly GatewayRegistry $gatewayRegistry,
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
            'channel' => [
                'type' => 'string',
                'label' => __('Channel', 'wp-sms'),
                'description' => __('The message channel type', 'wp-sms'),
                'required' => true,
                'dynamic' => true,
                'example' => 'sms',
            ],
            'gateway' => [
                'type' => 'string',
                'label' => __('Gateway', 'wp-sms'),
                'description' => __('The messaging gateway to send through', 'wp-sms'),
                'required' => true,
                'dynamic' => true,
                'dependsOn' => ['channel'],
                'example' => 'twilio',
            ],
            'to' => [
                'type' => 'string',
                'label' => __('Recipient', 'wp-sms'),
                'description' => __('Recipient phone, email, or URL.', 'wp-sms'),
                'hint' => __('Use {{customer.phone}} to send to the trigger contact.', 'wp-sms'),
                'template' => true,
                'required' => true,
                'example' => '{{user.phone}}',
            ],
            'body' => [
                'type' => 'text',
                'label' => __('Message Body', 'wp-sms'),
                'description' => __('The content of the message.', 'wp-sms'),
                'hint' => __('Use {{variables}} to personalize. Click {} to browse fields.', 'wp-sms'),
                'template' => true,
                'required' => true,
                'example' => 'Hello {{user.display_name}}, your order is confirmed.',
            ],
            'subject' => [
                'type' => 'string',
                'label' => __('Subject', 'wp-sms'),
                'description' => __('Email subject line', 'wp-sms'),
                'template' => true,
                'example' => 'Order Confirmation',
                'displayOptions' => [
                    'show' => ['channel' => ['email']],
                ],
            ],
            'media_url' => [
                'type'           => 'string',
                'label'          => __('Media URL', 'wp-sms'),
                'description'    => __('Publicly accessible image URL to attach (JPEG, PNG, GIF). Comma-separate for multiple.', 'wp-sms'),
                'template'       => true,
                'example'        => 'https://example.com/image.jpg',
                'displayOptions' => ['show' => ['channel' => ['sms', 'whatsapp']]],
            ],
        ];
    }

    public function getConfigOptions(string $fieldKey, array $context = []): array
    {
        if ($fieldKey === 'channel') {
            $channels = $this->gatewayRegistry->getConfiguredChannels();
            return array_map(fn(string $ch) => [
                'value' => $ch,
                'label' => ucfirst($ch),
            ], $channels);
        }

        if ($fieldKey === 'gateway') {
            $channel = $context['channel'] ?? '';
            if ($channel === '') {
                return [];
            }
            $options = [
                ['value' => self::DEFAULT_GATEWAY, 'label' => __('Channel default', 'wp-sms')],
            ];
            $gateways = $this->gatewayRegistry->getByChannel($channel);
            foreach ($gateways as $gateway) {
                if ($gateway->isConfiguredForChannel($channel)) {
                    $options[] = [
                        'value' => $gateway->getId(),
                        'label' => $gateway->getName(),
                    ];
                }
            }
            return $options;
        }

        return [];
    }

    public function getPlaceholders(string $triggerType): array
    {
        return match ($triggerType) {
            'woocommerce.order_created' => [
                'to' => '{{customer.phone}}',
                'body' => 'Hi {{customer.name}}, order #{{order_id}} (${{order.total}}) received!',
            ],
            'wordpress.user_register' => [
                'to' => '{{user.email}}',
                'body' => 'Welcome {{user.display_name}}! Your account is ready.',
            ],
            'wordpress.post_published' => [
                'body' => 'New post: "{{post_title}}" — {{post_url}}',
            ],
            default => [],
        };
    }

    public function execute(array $payload, array $config): ActionResult
    {
        $channel = $config['channel'] ?? 'sms';
        $to = $config['to'] ?? '';
        $body = $config['body'] ?? '';
        $gatewayId = $config['gateway'] ?? self::DEFAULT_GATEWAY;

        $executionId = $payload['_execution_id'] ?? null;
        $message = $this->buildMessage($channel, $to, $body, $config, $executionId);

        $result = $this->messageDispatcher->sendImmediate(
            $message,
            $gatewayId !== self::DEFAULT_GATEWAY ? $gatewayId : null,
        );

        if ($result->success) {
            return ActionResult::success([
                'status' => $result->status,
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
            default   => new Message($channel, $to, $body, $executionId, $this->buildMediaMeta($config)),
        };
    }

    private function buildMediaMeta(array $config): array
    {
        $mediaUrl = trim($config['media_url'] ?? '');
        if ($mediaUrl === '') {
            return [];
        }

        $urls = array_filter(array_map('trim', explode(',', $mediaUrl)));

        return !empty($urls) ? ['media_urls' => $urls] : [];
    }
}
