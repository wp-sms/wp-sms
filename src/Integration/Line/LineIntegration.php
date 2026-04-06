<?php

namespace WSms\Integration\Line;

use WSms\Integration\Contracts\IntegrationInterface;
use WSms\Integration\Line\Actions\MulticastAction;
use WSms\Integration\Line\Actions\ReplyAction;
use WSms\Integration\Line\Actions\SendFlexAction;
use WSms\Integration\Line\Actions\SendImageAction;
use WSms\Integration\Line\Actions\SendMessageAction;
use WSms\Integration\Line\Actions\SendTemplateAction;
use WSms\Integration\Line\Actions\SetRichMenuAction;
use WSms\Integration\Line\Triggers\AccountLinkTrigger;
use WSms\Integration\Line\Triggers\BeaconTrigger;
use WSms\Integration\Line\Triggers\FollowTrigger;
use WSms\Integration\Line\Triggers\MemberJoinedTrigger;
use WSms\Integration\Line\Triggers\MemberLeftTrigger;
use WSms\Integration\Line\Triggers\MessageReceivedTrigger;
use WSms\Integration\Line\Triggers\PostbackTrigger;
use WSms\Integration\Line\Triggers\UnfollowTrigger;
use WSms\Line\LineBotClient;

defined('ABSPATH') || exit;

class LineIntegration implements IntegrationInterface
{
    public function __construct(
        private readonly LineBotClient $botClient,
    ) {
    }

    public function getId(): string
    {
        return 'line';
    }

    public function getName(): string
    {
        return __('LINE', 'wp-sms');
    }

    public function getDescription(): string
    {
        return __('Send messages, images, and rich Flex Messages via LINE Messaging API.', 'wp-sms');
    }

    public function getCategory(): string
    {
        return 'communication';
    }

    public function getIcon(): string
    {
        return 'message-circle';
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function getAuthType(): string
    {
        return 'gateway';
    }

    public function getAuthSchema(): array
    {
        return [];
    }

    public function connect(array $credentials): array
    {
        if (!$this->isConnected()) {
            throw new \RuntimeException(__('Configure the LINE gateway first. The integration uses the same API credentials.', 'wp-sms'));
        }

        $info = $this->botClient->getBotInfo();

        if (!$info) {
            throw new \RuntimeException(__('Invalid channel access token in LINE gateway configuration.', 'wp-sms'));
        }

        return [
            'bot_name'     => $info['displayName'] ?? '',
            'bot_user_id'  => $info['userId'] ?? '',
            'bot_basic_id' => $info['basicId'] ?? '',
        ];
    }

    public function disconnect(): void
    {
    }

    public function isConnected(): bool
    {
        $config = $this->getGatewayConfig();

        return !empty($config['shared']['channel_access_token'])
            && !empty($config['shared']['channel_secret']);
    }

    private function getGatewayConfig(): array
    {
        $configs = get_option('wsms_gateway_configs', []);

        return $configs['line'] ?? [];
    }

    public function getTriggers(): array
    {
        return [
            new MessageReceivedTrigger(),
            new FollowTrigger(),
            new UnfollowTrigger(),
            new PostbackTrigger(),
            new MemberJoinedTrigger(),
            new MemberLeftTrigger(),
            new AccountLinkTrigger(),
            new BeaconTrigger(),
        ];
    }

    public function getActions(): array
    {
        return [
            new SendMessageAction($this->botClient),
            new SendFlexAction($this->botClient),
            new SendTemplateAction($this->botClient),
            new ReplyAction($this->botClient),
            new SendImageAction($this->botClient),
            new MulticastAction($this->botClient),
            new SetRichMenuAction($this->botClient),
        ];
    }

    public function getCapabilities(): array
    {
        return [];
    }

    public function boot(): void
    {
    }
}
