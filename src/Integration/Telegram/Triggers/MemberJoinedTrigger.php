<?php

namespace WSms\Integration\Telegram\Triggers;

use WSms\Flow\Contracts\AbstractTrigger;
use WSms\Integration\PayloadSchemas;

defined('ABSPATH') || exit;

class MemberJoinedTrigger extends AbstractTrigger
{
    public function getId(): string
    {
        return 'telegram.member_joined';
    }

    public function getName(): string
    {
        return __('Member Joined', 'wp-sms');
    }

    public function getDescription(): string
    {
        return __('Fires when a new member joins a group or supergroup', 'wp-sms');
    }

    public function getGroup(): string
    {
        return __('Telegram', 'wp-sms');
    }

    public function getPayloadSchema(): array
    {
        return [
            'chat_id' => [
                'type' => 'integer',
                'label' => __('Chat ID', 'wp-sms'),
                'example' => -1001234567890,
            ],
            'chat' => [
                'type' => 'object',
                'label' => __('Chat', 'wp-sms'),
                'properties' => PayloadSchemas::telegramChat(),
                'example' => ['id' => -1001234567890, 'type' => 'supergroup', 'title' => 'My Group'],
            ],
            'user' => [
                'type' => 'object',
                'label' => __('User', 'wp-sms'),
                'properties' => PayloadSchemas::telegramUser(),
                'example' => ['id' => 123456789, 'first_name' => 'John', 'last_name' => 'Doe', 'username' => 'johndoe'],
            ],
        ];
    }

    public function subscribe(callable $callback): void
    {
        add_action('wsms_telegram_message', function (array $message) use ($callback) {
            $newMembers = $message['new_chat_members'] ?? [];

            if (empty($newMembers) || !is_array($newMembers)) {
                return;
            }

            $chatId = (int) ($message['chat']['id'] ?? 0);
            $chat = PayloadSchemas::extractTelegramChat($message['chat'] ?? []);

            foreach ($newMembers as $member) {
                $callback([
                    'chat_id' => $chatId,
                    'chat'    => $chat,
                    'user'    => PayloadSchemas::extractTelegramUser($member),
                ]);
            }
        });
    }
}
