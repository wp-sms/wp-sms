<?php

namespace WSms\Line;

defined('ABSPATH') || exit;

class LineBotClient
{
    private const API_BASE = 'https://api.line.me/v2/bot/';
    private const DATA_BASE = 'https://api-data.line.me/v2/bot/';
    private const TIMEOUT = 10;

    public function __construct(
        private string $channelAccessToken,
    ) {
    }

    /**
     * Push a message to a user (consumes quota).
     *
     * @param string      $to       Line userId
     * @param array       $messages Array of Line message objects (max 5)
     * @param string|null $retryKey UUID for idempotency (24h window)
     */
    public function pushMessage(string $to, array $messages, ?string $retryKey = null): ?array
    {
        return $this->post('message/push', [
            'to'       => $to,
            'messages' => array_slice($messages, 0, 5),
        ], $retryKey);
    }

    /**
     * Reply to a webhook event (free, but token expires in 60s and is single-use).
     */
    public function replyMessage(string $replyToken, array $messages): ?array
    {
        return $this->post('message/reply', [
            'replyToken' => $replyToken,
            'messages'   => array_slice($messages, 0, 5),
        ]);
    }

    /**
     * Send messages to multiple users at once (max 500 userIds per request).
     */
    public function multicast(array $to, array $messages, ?string $retryKey = null): ?array
    {
        return $this->post('message/multicast', [
            'to'       => array_slice($to, 0, 500),
            'messages' => array_slice($messages, 0, 5),
        ], $retryKey);
    }

    /**
     * Broadcast messages to all friends.
     */
    public function broadcast(array $messages, ?string $retryKey = null): ?array
    {
        return $this->post('message/broadcast', [
            'messages' => array_slice($messages, 0, 5),
        ], $retryKey);
    }

    /**
     * Get a user's profile (displayName, pictureUrl, statusMessage, language).
     */
    public function getProfile(string $userId): ?array
    {
        return $this->get('profile/' . $userId);
    }

    /**
     * Get bot info (displayName, userId, basicId, pictureUrl, chatMode, markAsReadMode).
     */
    public function getBotInfo(): ?array
    {
        return $this->get('info');
    }

    /**
     * Get the monthly message quota for this channel.
     */
    public function getQuota(): ?array
    {
        return $this->get('message/quota');
    }

    /**
     * Get the number of messages sent this month.
     */
    public function getQuotaConsumption(): ?array
    {
        return $this->get('message/quota/consumption');
    }

    /**
     * Set a rich menu for a specific user.
     */
    public function setRichMenuForUser(string $userId, string $richMenuId): bool
    {
        $result = $this->post("user/{$userId}/richmenu/{$richMenuId}");

        return $result !== null;
    }

    /**
     * Verify webhook signature using HMAC-SHA256.
     * Must be called on the raw request body BEFORE JSON parsing.
     */
    public static function verifySignature(string $body, string $signature, string $channelSecret): bool
    {
        $hash = base64_encode(hash_hmac('sha256', $body, $channelSecret, true));

        return hash_equals($hash, $signature);
    }

    private function post(string $endpoint, array $body = [], ?string $retryKey = null): ?array
    {
        if (empty($this->channelAccessToken)) {
            return null;
        }

        $headers = [
            'Authorization' => 'Bearer ' . $this->channelAccessToken,
            'Content-Type'  => 'application/json',
        ];

        if ($retryKey !== null) {
            $headers['X-Line-Retry-Key'] = $retryKey;
        }

        $response = wp_remote_post(self::API_BASE . $endpoint, [
            'headers' => $headers,
            'body'    => !empty($body) ? wp_json_encode($body) : null,
            'timeout' => self::TIMEOUT,
        ]);

        if (is_wp_error($response)) {
            $this->logError('POST', $endpoint, 0, $response->get_error_message());

            return null;
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $decoded = json_decode(wp_remote_retrieve_body($response), true);

        if ($code >= 400) {
            $this->logError('POST', $endpoint, $code, $decoded['message'] ?? '');

            return null;
        }

        return $decoded ?? [];
    }

    private function get(string $endpoint): ?array
    {
        if (empty($this->channelAccessToken)) {
            return null;
        }

        $response = wp_remote_get(self::API_BASE . $endpoint, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->channelAccessToken,
            ],
            'timeout' => self::TIMEOUT,
        ]);

        if (is_wp_error($response)) {
            $this->logError('GET', $endpoint, 0, $response->get_error_message());

            return null;
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $decoded = json_decode(wp_remote_retrieve_body($response), true);

        if ($code >= 400) {
            $this->logError('GET', $endpoint, $code, $decoded['message'] ?? '');

            return null;
        }

        return $decoded;
    }

    private function logError(string $method, string $endpoint, int $code, string $message): void
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf('[LINE API] %s %s failed (%d): %s', $method, $endpoint, $code, $message));
        }
    }
}
