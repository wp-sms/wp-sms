<?php

namespace WSms\Auth\CaptchaProviders;

defined('ABSPATH') || exit;

class TurnstileProvider implements ProviderInterface
{
    private const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public function verify(string $token, string $secretKey, string $ip): bool
    {
        $response = wp_remote_post(self::VERIFY_URL, [
            'body'    => [
                'secret'   => $secretKey,
                'response' => $token,
                'remoteip' => $ip,
            ],
            'timeout' => 10,
        ]);

        if (is_wp_error($response)) {
            return false;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        return !empty($body['success']);
    }

    public function getScriptUrl(): string
    {
        return 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';
    }
}
