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
        return $this->isOk($this->post('sendMessage', [
            'chat_id'    => $chatId,
            'text'       => $text,
            'parse_mode' => 'HTML',
        ]));
    }

    /**
     * Set the webhook URL for the bot.
     */
    public function setWebhook(string $url, string $secretToken): bool
    {
        return $this->isOk($this->post('setWebhook', [
            'url'          => $url,
            'secret_token' => $secretToken,
        ]));
    }

    /**
     * Delete the current webhook.
     */
    public function deleteWebhook(): bool
    {
        return $this->isOk($this->post('deleteWebhook'));
    }

    /**
     * Get basic bot info (username, id, etc.).
     */
    public function getMe(): ?array
    {
        return $this->extractResult($this->post('getMe'));
    }

    /**
     * Send a text message with full options (parse mode, reply markup, reply-to).
     */
    public function sendMessageAdvanced(int $chatId, string $text, array $options = []): ?array
    {
        $body = [
            'chat_id'    => $chatId,
            'text'       => $text,
            'parse_mode' => $options['parse_mode'] ?? 'HTML',
        ];

        if (!empty($options['reply_to_message_id'])) {
            $body['reply_to_message_id'] = (int) $options['reply_to_message_id'];
        }

        if (!empty($options['reply_markup'])) {
            $body['reply_markup'] = $this->encodeReplyMarkup($options['reply_markup']);
        }

        return $this->extractResult($this->post('sendMessage', $body));
    }

    /**
     * Send a photo by URL with optional caption and reply markup.
     */
    public function sendPhoto(int $chatId, string $photoUrl, array $options = []): ?array
    {
        $body = [
            'chat_id' => $chatId,
            'photo'   => $photoUrl,
        ];

        if (!empty($options['caption'])) {
            $body['caption'] = $options['caption'];
        }
        if (!empty($options['parse_mode'])) {
            $body['parse_mode'] = $options['parse_mode'];
        }
        if (!empty($options['reply_markup'])) {
            $body['reply_markup'] = $this->encodeReplyMarkup($options['reply_markup']);
        }

        return $this->extractResult($this->post('sendPhoto', $body));
    }

    /**
     * Send a document by URL with optional caption.
     */
    public function sendDocument(int $chatId, string $documentUrl, array $options = []): ?array
    {
        $body = [
            'chat_id'  => $chatId,
            'document' => $documentUrl,
        ];

        if (!empty($options['caption'])) {
            $body['caption'] = $options['caption'];
        }
        if (!empty($options['parse_mode'])) {
            $body['parse_mode'] = $options['parse_mode'];
        }

        return $this->extractResult($this->post('sendDocument', $body));
    }

    /**
     * Edit an existing message's text and/or reply markup.
     */
    public function editMessageText(int $chatId, int $messageId, string $text, array $options = []): ?array
    {
        $body = [
            'chat_id'    => $chatId,
            'message_id' => $messageId,
            'text'       => $text,
            'parse_mode' => $options['parse_mode'] ?? 'HTML',
        ];

        if (!empty($options['reply_markup'])) {
            $body['reply_markup'] = $this->encodeReplyMarkup($options['reply_markup']);
        }

        return $this->extractResult($this->post('editMessageText', $body));
    }

    /**
     * Answer a callback query (stop button loading spinner).
     */
    public function answerCallbackQuery(string $callbackQueryId, ?string $text = null, bool $showAlert = false): bool
    {
        $body = [
            'callback_query_id' => $callbackQueryId,
            'show_alert'        => $showAlert,
        ];

        if ($text !== null) {
            $body['text'] = $text;
        }

        return $this->isOk($this->post('answerCallbackQuery', $body));
    }

    private function isOk(?array $response): bool
    {
        return $response !== null && ($response['ok'] ?? false);
    }

    private function extractResult(?array $response): ?array
    {
        if (!$this->isOk($response)) {
            return null;
        }

        return $response['result'] ?? null;
    }

    private function encodeReplyMarkup(string|array $markup): string
    {
        return is_string($markup) ? $markup : (json_encode($markup) ?: '[]');
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
