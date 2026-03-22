<?php

namespace WSms\Flow\Action;

use WSms\Campaign\AudienceResolver;
use WSms\Contact\Contracts\ListRepositoryInterface;
use WSms\Contact\Contracts\TagRepositoryInterface;
use WSms\Flow\Contracts\AbstractAction;
use WSms\Flow\Contracts\ActionResult;
use WSms\Messaging\Contracts\TemplateEngineInterface;
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
        private readonly TagRepositoryInterface $tagRepository,
        private readonly ListRepositoryInterface $listRepository,
        private readonly AudienceResolver $audienceResolver,
        private readonly TemplateEngineInterface $templateEngine,
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

    public function getDescription(): string
    {
        return __('Send an SMS, email, or webhook message', 'wp-sms');
    }

    public function getGroup(): string
    {
        return 'WSMS';
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
                'default' => self::DEFAULT_GATEWAY,
                'example' => 'twilio',
            ],
            'recipient_mode' => [
                'type'       => 'string',
                'label'      => __('Send To', 'wp-sms'),
                'enum'       => ['custom', 'admin', 'tag', 'list'],
                'enumLabels' => [
                    'custom' => __('Specific recipient', 'wp-sms'),
                    'admin'  => __('Admin number', 'wp-sms'),
                    'tag'    => __('Tag group', 'wp-sms'),
                    'list'   => __('Contact list', 'wp-sms'),
                ],
                'default'  => 'custom',
                'required' => true,
            ],
            'tag_id' => [
                'type'           => 'string',
                'label'          => __('Tag', 'wp-sms'),
                'dynamic'        => true,
                'dependsOn'      => ['recipient_mode'],
                'displayOptions' => ['show' => ['recipient_mode' => ['tag']]],
            ],
            'list_id' => [
                'type'           => 'string',
                'label'          => __('List', 'wp-sms'),
                'dynamic'        => true,
                'dependsOn'      => ['recipient_mode'],
                'displayOptions' => ['show' => ['recipient_mode' => ['list']]],
            ],
            'to' => [
                'type' => 'string',
                'label' => __('Recipient', 'wp-sms'),
                'description' => __('Recipient phone, email, or URL.', 'wp-sms'),
                'hint' => __('Use {{customer.phone}} to send to the trigger contact.', 'wp-sms'),
                'template' => true,
                'required' => true,
                'example' => '{{user.phone}}',
                'displayOptions' => ['show' => ['recipient_mode' => ['custom']]],
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

        if ($fieldKey === 'tag_id') {
            return array_map(fn(array $tag) => [
                'value' => $tag['id'],
                'label' => $tag['name'],
            ], $this->tagRepository->findAll());
        }

        if ($fieldKey === 'list_id') {
            return array_map(fn(array $list) => [
                'value' => $list['id'],
                'label' => $list['name'],
            ], $this->listRepository->findAll());
        }

        return [];
    }

    public function getOutputSchema(): array
    {
        return [
            'status'      => ['type' => 'string', 'title' => 'Status'],
            'provider_id' => ['type' => 'string', 'title' => 'Provider ID'],
            'sent_count'  => ['type' => 'integer', 'title' => 'Sent Count'],
        ];
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
        $mode = $config['recipient_mode'] ?? 'custom';
        $channel = $config['channel'] ?? 'sms';
        $gatewayId = $config['gateway'] ?? self::DEFAULT_GATEWAY;

        return match ($mode) {
            'admin' => $this->executeAdmin($payload, $config, $channel, $gatewayId),
            'tag'   => $this->executeTag($payload, $config, $channel, $gatewayId),
            'list'  => $this->executeList($payload, $config, $channel, $gatewayId),
            default => $this->executeCustom($payload, $config, $channel, $gatewayId),
        };
    }

    private function executeCustom(array $payload, array $config, string $channel, string $gatewayId): ActionResult
    {
        return $this->sendSingle($config['to'] ?? '', $channel, $config, $payload, $gatewayId);
    }

    private function executeAdmin(array $payload, array $config, string $channel, string $gatewayId): ActionResult
    {
        $to = match (true) {
            in_array($channel, ['sms', 'whatsapp', 'telegram']) => (get_option('wsms_auth_settings', []))['site_phone'] ?? '',
            $channel === 'email' => get_option('admin_email', ''),
            default => '',
        };

        if ($to === '') {
            return ActionResult::failure("Admin recipient not configured for {$channel} channel");
        }

        return $this->sendSingle($to, $channel, $config, $payload, $gatewayId);
    }

    private function sendSingle(string $to, string $channel, array $config, array $payload, string $gatewayId): ActionResult
    {
        $body = $config['body'] ?? '';
        $executionId = $payload['_execution_id'] ?? null;

        $message = $this->buildMessage($channel, $to, $body, $config, $executionId);

        $result = $this->messageDispatcher->sendImmediate(
            $message,
            $this->resolveGatewayId($gatewayId),
        );

        if ($result->success) {
            return ActionResult::success([
                'status' => $result->status,
                'provider_id' => $result->providerId,
            ]);
        }

        return ActionResult::failure($result->error ?? 'Send failed');
    }

    private function executeTag(array $payload, array $config, string $channel, string $gatewayId): ActionResult
    {
        $tagId = $config['tag_id'] ?? '';
        if (empty($tagId)) {
            return ActionResult::failure('Tag ID is required');
        }

        $tag = $this->tagRepository->find($tagId);
        if (!$tag) {
            return ActionResult::failure('Tag not found');
        }

        $audience = [
            'sources' => [['type' => 'tags', 'tag_ids' => [$tagId]]],
        ];

        return $this->sendToAudience($audience, $channel, $config, $payload, $gatewayId);
    }

    private function executeList(array $payload, array $config, string $channel, string $gatewayId): ActionResult
    {
        $listId = $config['list_id'] ?? '';
        if (empty($listId)) {
            return ActionResult::failure('List ID is required');
        }

        $list = $this->listRepository->find($listId);
        if (!$list) {
            return ActionResult::failure('List not found');
        }

        $type = $list['type'] ?? 'static';

        if ($type === 'dynamic' && !empty($list['conditions'])) {
            $conditions = is_string($list['conditions']) ? json_decode($list['conditions'], true) : $list['conditions'];
            $audience = [
                'sources' => [['type' => 'segment', 'conditions' => $conditions]],
            ];
        } else {
            $tagId = $list['tag_id'] ?? '';
            if (empty($tagId)) {
                return ActionResult::failure('List has no associated tag');
            }
            $audience = [
                'sources' => [['type' => 'tags', 'tag_ids' => [$tagId]]],
            ];
        }

        return $this->sendToAudience($audience, $channel, $config, $payload, $gatewayId);
    }

    private function sendToAudience(array $audience, string $channel, array $config, array $payload, string $gatewayId): ActionResult
    {
        $body = $config['body'] ?? '';
        $executionId = $payload['_execution_id'] ?? null;
        $resolvedGatewayId = $this->resolveGatewayId($gatewayId);
        $isPhoneChannel = in_array($channel, ['sms', 'whatsapp', 'telegram'], true);

        $sentCount = 0;
        $cursor = null;

        do {
            $batch = $this->audienceResolver->resolve($audience, $channel, 500, $cursor);

            foreach ($batch->recipients as $recipient) {
                $personalizedBody = $this->templateEngine->render($body, [
                    'first_name' => $recipient['first_name'] ?? '',
                    'last_name'  => $recipient['last_name'] ?? '',
                    'email'      => $isPhoneChannel ? '' : $recipient['recipient'],
                    'phone'      => $isPhoneChannel ? $recipient['recipient'] : '',
                    'custom'     => $recipient['custom_fields'] ?? [],
                ]);

                $message = $this->buildMessage($channel, $recipient['recipient'], $personalizedBody, $config, $executionId);
                $this->messageDispatcher->sendQueued($message, $resolvedGatewayId);
                $sentCount++;
            }

            $cursor = $batch->lastId;
        } while ($batch->hasMore);

        return ActionResult::success(['sent_count' => $sentCount]);
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

    private function resolveGatewayId(string $gatewayId): ?string
    {
        return $gatewayId !== self::DEFAULT_GATEWAY ? $gatewayId : null;
    }
}
