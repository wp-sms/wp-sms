<?php

namespace WSms\Messaging\Gateway\Telegram;

use WSms\Auth\SettingsRepository;
use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\GatewayInterface;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\TestConnectionResult;
use WSms\Messaging\Gateway\GatewayApiClient;
use WSms\Telegram\TelegramBotClient;

defined('ABSPATH') || exit;

class TelegramGateway implements GatewayInterface
{
    private ?GatewayApiClient $apiClient = null;

    public function __construct(
        private readonly TelegramBotClient $telegramClient,
    ) {
    }

    public function setApiClient(GatewayApiClient $apiClient): void
    {
        $this->apiClient = $apiClient;
    }

    public function getId(): string
    {
        return 'telegram';
    }

    public function getName(): string
    {
        return $this->apiClient?->get($this->getId())['name'] ?? __('Telegram Bot', 'wp-sms');
    }

    public function getSupportedChannels(): array
    {
        return ['telegram'];
    }

    public function send(MessageInterface $message): DeliveryResult
    {
        $chatId = (int) $message->getRecipient();

        if ($chatId === 0) {
            return DeliveryResult::failed(__('Invalid Telegram chat ID', 'wp-sms'));
        }

        $sent = $this->telegramClient->sendMessage($chatId, $message->getBody());

        if (!$sent) {
            return DeliveryResult::failed(__('Telegram message send failed', 'wp-sms'));
        }

        return DeliveryResult::sent();
    }

    public function getConfigSchema(): array
    {
        return [
            'shared' => [
                'bot_token' => [
                    'type'     => 'string',
                    'label'    => __('Bot Token', 'wp-sms'),
                    'required' => true,
                ],
            ],
            'channels' => [],
        ];
    }

    public function validateConfig(array $config): bool
    {
        return !empty($config['shared']['bot_token'] ?? $config['bot_token'] ?? null);
    }

    public function isConfigured(): bool
    {
        $settings = get_option(SettingsRepository::OPTION_KEY, []);
        return !empty($settings['telegram']['bot_token']);
    }

    public function isConfiguredForChannel(string $channel): bool
    {
        return $channel === 'telegram' && $this->isConfigured();
    }

    public function getMetadata(): array
    {
        $api = $this->apiClient?->get($this->getId());

        if (!$api) {
            return ['description' => __('Send messages via Telegram Bot API', 'wp-sms')];
        }

        return [
            'description' => $api['description'] ?? '',
            'website'     => $api['website'] ?? '',
            'icon'        => $api['branding']['logo_square'] ?? '',
            'regions'     => $api['coverage']['regions'] ?? [],
            'setup_url'   => $api['setup']['dashboard'] ?? '',
            'setup_notes' => $api['setup']['notes'] ?? [],
            'status'      => $api['status'] ?? 'active',
            'tier'        => $api['tier'] ?? 'free',
            'recommended' => $api['recommended'] ?? false,
            'branding'    => $api['branding'] ?? [],
            'coverage'    => $api['coverage'] ?? [],
        ];
    }

    public function getFeatures(): array
    {
        $base = ['unicode' => true, 'test_connection' => false];

        $api = $this->apiClient?->get($this->getId());

        if ($api) {
            if (isset($api['features'])) {
                $base = array_merge($base, $api['features']);
            }
            if (isset($api['test_connection'])) {
                $base['test_connection'] = $api['test_connection'];
            }
        }

        return $base;
    }

    public function getCredit(): ?string
    {
        return null;
    }

    public function testConnection(): TestConnectionResult
    {
        return TestConnectionResult::error(__('Connection testing is not supported for this gateway', 'wp-sms'));
    }
}
