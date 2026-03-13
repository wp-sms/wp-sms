<?php

namespace WSms\Auth\CaptchaProviders;

defined('ABSPATH') || exit;

class RecaptchaProvider implements ProviderInterface
{
    private const VERIFY_URL = 'https://www.google.com/recaptcha/api/siteverify';

    private float $minScore;

    public function __construct(float $minScore = 0.5)
    {
        $this->minScore = $minScore;
    }

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

        if (empty($body['success'])) {
            return false;
        }

        // reCAPTCHA v3 returns a score — reject if below threshold.
        if (isset($body['score']) && (float) $body['score'] < $this->minScore) {
            return false;
        }

        return true;
    }

    public function getScriptUrl(): string
    {
        return 'https://www.google.com/recaptcha/api.js?render=explicit';
    }
}
