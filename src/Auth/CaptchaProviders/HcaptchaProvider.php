<?php

namespace WSms\Auth\CaptchaProviders;

defined('ABSPATH') || exit;

class HcaptchaProvider implements ProviderInterface
{
    private const VERIFY_URL = 'https://api.hcaptcha.com/siteverify';

    public function verify(string $token, string $secretKey, string $ip): bool
    {
        $response = wp_remote_post(self::VERIFY_URL, [
            'body'    => [
                'secret'   => $secretKey,
                'response' => $token,
                'sitekey'  => '', // Optional — hCaptcha validates without it.
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
        return 'https://js.hcaptcha.com/1/api.js?render=explicit';
    }
}
