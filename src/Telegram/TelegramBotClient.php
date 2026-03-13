<?php

namespace WSms\Telegram;

defined('ABSPATH') || exit;

class TelegramBotClient
{
    private const API_BASE = 'https://api.telegram.org/bot';
    private const TIMEOUT = 10;

    public function __construct(
        private string $botToken,
    ) {
    }

    /**
     * Send a text message to a Telegram chat.
     */
    public function sendMessage(int $chatId, string $text): bool
    {
        $response = $this->post('sendMessage', [
            'chat_id'    => $chatId,
            'text'       => $text,
            'parse_mode' => 'HTML',
        ]);

        return $response !== null && ($response['ok'] ?? false);
    }

    /**
     * Set the webhook URL for the bot.
     */
    public function setWebhook(string $url, string $secretToken): bool
    {
        $response = $this->post('setWebhook', [
            'url'          => $url,
            'secret_token' => $secretToken,
        ]);

        return $response !== null && ($response['ok'] ?? false);
    }

    /**
     * Delete the current webhook.
     */
    public function deleteWebhook(): bool
    {
        $response = $this->post('deleteWebhook');

        return $response !== null && ($response['ok'] ?? false);
    }

    /**
     * Get basic bot info (username, id, etc.).
     */
    public function getMe(): ?array
    {
        $response = $this->post('getMe');

        if ($response === null || !($response['ok'] ?? false)) {
            return null;
        }

        return $response['result'] ?? null;
    }

    private function post(string $method, array $body = []): ?array
    {
        $url = self::API_BASE . $this->botToken . '/' . $method;

        $response = wp_remote_post($url, [
            'body'    => $body ?: null,
            'timeout' => self::TIMEOUT,
        ]);

        if (is_wp_error($response)) {
            return null;
        }

        return json_decode(wp_remote_retrieve_body($response), true);
    }
}
