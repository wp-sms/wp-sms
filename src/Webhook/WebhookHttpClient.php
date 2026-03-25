<?php

namespace WSms\Webhook;

defined('ABSPATH') || exit;

class WebhookHttpClient
{
    /**
     * Send a signed webhook payload via HTTP POST.
     *
     * @return array{response: array|\WP_Error, body: string, signature: string}
     */
    public static function send(
        string $url,
        string $body,
        string $secret,
        string $event,
        string $webhookId,
    ): array {
        $signature = hash_hmac('sha256', $body, $secret);

        $response = wp_remote_post($url, [
            'timeout' => 15,
            'headers' => [
                'Content-Type'      => 'application/json',
                'User-Agent'        => 'WSMS-Webhook/1.0',
                'X-WSMS-Signature'  => $signature,
                'X-WSMS-Event'      => $event,
                'X-WSMS-Webhook-Id' => $webhookId,
            ],
            'body' => $body,
        ]);

        return [
            'response'  => $response,
            'body'      => $body,
            'signature' => $signature,
        ];
    }

    /**
     * Check if the response indicates success (2xx status).
     */
    public static function isSuccess(array|\WP_Error $response): bool
    {
        if (is_wp_error($response)) {
            return false;
        }

        $code = wp_remote_retrieve_response_code($response);

        return $code >= 200 && $code < 300;
    }
}
