<?php

namespace WSms\Auth\CaptchaProviders;

defined('ABSPATH') || exit;

interface ProviderInterface
{
    /**
     * Verify a CAPTCHA token with the provider's API.
     */
    public function verify(string $token, string $secretKey, string $ip): bool;

    /**
     * Get the URL of the provider's client-side script.
     */
    public function getScriptUrl(): string;
}
