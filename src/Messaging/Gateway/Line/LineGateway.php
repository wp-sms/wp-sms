<?php

namespace WSms\Messaging\Gateway\Line;

use WSms\Line\LineBotClient;
use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\GatewayInterface;
use WSms\Messaging\Contracts\InboundMessage;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\SupportsInboundMessage;
use WSms\Messaging\Contracts\SupportsOptOutDetection;
use WSms\Messaging\Contracts\TestConnectionResult;
use WSms\Messaging\Gateway\UsesGatewayApi;
use WSms\Rest\RestRoute;
use WP_REST_Request;

defined('ABSPATH') || exit;

class LineGateway implements GatewayInterface, SupportsInboundMessage, SupportsOptOutDetection
{
    use UsesGatewayApi;

    public function __construct(
        private readonly LineBotClient $client,
    ) {
    }

    public function getId(): string
    {
        return 'line';
    }

    public function getSupportedChannels(): array
    {
        return ['line'];
    }

    public function send(MessageInterface $message): DeliveryResult
    {
        $recipient = $message->getRecipient();

        if (empty($recipient)) {
            return DeliveryResult::failed(__('Invalid LINE user ID', 'wp-sms'));
        }

        $meta = $message->getMeta();
        $lineMessages = $this->buildLineMessages($message);
        $retryKey = wp_generate_uuid4();

        // If reply token present, use free reply.
        if (!empty($meta['line_reply_token'])) {
            $result = $this->client->replyMessage($meta['line_reply_token'], $lineMessages);
        } else {
            $result = $this->client->pushMessage($recipient, $lineMessages, $retryKey);
        }

        if ($result === null) {
            return DeliveryResult::failed(__('LINE API request failed', 'wp-sms'), [], true);
        }

        // Note: Line returns 200 even for blocked users — message silently dropped.
        return DeliveryResult::sent();
    }

    public function getConfigSchema(): array
    {
        return [
            'shared' => [
                'channel_access_token' => [
                    'type'     => 'secret',
                    'label'    => __('Channel Access Token', 'wp-sms'),
                    'required' => true,
                ],
                'channel_secret' => [
                    'type'     => 'secret',
                    'label'    => __('Channel Secret', 'wp-sms'),
                    'required' => true,
                ],
            ],
            'channels' => [
                'line' => [
                    'bot_basic_id' => [
                        'type'        => 'string',
                        'label'       => __('Bot Basic ID', 'wp-sms'),
                        'required'    => false,
                        'description' => __('For friend-add URL. Found in LINE Developers Console.', 'wp-sms'),
                    ],
                ],
            ],
        ];
    }

    public function validateConfig(array $config): bool
    {
        return !empty($config['shared']['channel_access_token'] ?? $config['channel_access_token'] ?? null)
            && !empty($config['shared']['channel_secret'] ?? $config['channel_secret'] ?? null);
    }

    public function isConfigured(): bool
    {
        $configs = get_option('wsms_gateway_configs', []);

        return !empty($configs['line']['shared']['channel_access_token']);
    }

    public function isConfiguredForChannel(string $channel): bool
    {
        return $channel === 'line' && $this->isConfigured();
    }

    public function getCredit(): ?string
    {
        $quota = $this->client->getQuota();
        $consumption = $this->client->getQuotaConsumption();

        if (!$quota || !$consumption) {
            return null;
        }

        return sprintf(
            '%d / %d messages',
            $consumption['totalUsage'] ?? 0,
            $quota['value'] ?? 0,
        );
    }

    public function testConnection(): TestConnectionResult
    {
        $info = $this->client->getBotInfo();

        if ($info === null) {
            return TestConnectionResult::error(__('Failed to connect to LINE API', 'wp-sms'));
        }

        return TestConnectionResult::ok(
            sprintf(__('Connected as: %s', 'wp-sms'), $info['displayName'] ?? 'Unknown'),
        );
    }

    // SupportsInboundMessage

    public function validateInboundCallback(WP_REST_Request $request): bool
    {
        $signature = $request->get_header('x-line-signature');
        $body = $request->get_body();
        $secret = self::resolveChannelSecret();

        if (empty($signature) || empty($secret)) {
            return false;
        }

        return LineBotClient::verifySignature($body, $signature, $secret);
    }

    /**
     * Resolve channel secret from gateway configs, falling back to integration configs.
     */
    public static function resolveChannelSecret(): string
    {
        $gatewayConfigs = get_option('wsms_gateway_configs', []);

        if (!empty($gatewayConfigs['line']['shared']['channel_secret'])) {
            return $gatewayConfigs['line']['shared']['channel_secret'];
        }

        $integrationConfigs = get_option('wsms_integration_configs', []);

        return $integrationConfigs['line']['credentials']['channel_secret'] ?? '';
    }

    public function parseInboundCallback(WP_REST_Request $request): array
    {
        $data = json_decode($request->get_body(), true);
        $messages = [];

        foreach ($data['events'] ?? [] as $event) {
            if ($event['type'] !== 'message' || ($event['message']['type'] ?? '') !== 'text') {
                continue;
            }

            $messages[] = new InboundMessage(
                from: $event['source']['userId'] ?? '',
                to: '',
                body: $event['message']['text'] ?? '',
                providerId: $event['message']['id'] ?? null,
            );
        }

        return $messages;
    }

    public function getInboundCallbackUrl(): string
    {
        return RestRoute::url('line/webhook');
    }

    // SupportsOptOutDetection

    public function isOptOutError(DeliveryResult $result): bool
    {
        // Line returns 200 for blocked users, so this catches explicit API errors.
        $error = $result->error ?? '';

        return str_contains($error, 'not found') || str_contains($error, 'user doesn\'t exist');
    }

    /**
     * Build Line message objects from a plugin Message.
     */
    private function buildLineMessages(MessageInterface $message): array
    {
        $meta = $message->getMeta();
        $type = $meta['line_message_type'] ?? 'text';

        $messages = match ($type) {
            'flex' => [[
                'type'     => 'flex',
                'altText'  => $meta['line_alt_text'] ?? mb_substr($message->getBody(), 0, 400),
                'contents' => json_decode($meta['line_flex_json'] ?? '{}', true),
            ]],
            'image' => [[
                'type'               => 'image',
                'originalContentUrl' => $meta['media_urls'][0] ?? '',
                'previewImageUrl'    => $meta['media_preview_url'] ?? $meta['media_urls'][0] ?? '',
            ]],
            'template' => [[
                'type'     => 'template',
                'altText'  => $meta['line_alt_text'] ?? $message->getBody(),
                'template' => $meta['line_template'] ?? [],
            ]],
            'sticker' => [[
                'type'      => 'sticker',
                'packageId' => $meta['line_sticker']['packageId'] ?? '',
                'stickerId' => $meta['line_sticker']['stickerId'] ?? '',
            ]],
            default => [[
                'type' => 'text',
                'text' => $message->getBody(),
            ]],
        };

        // Append quick reply if present.
        if (!empty($meta['line_quick_reply']) && !empty($messages)) {
            $messages[0]['quickReply'] = $meta['line_quick_reply'];
        }

        return $messages;
    }
}
