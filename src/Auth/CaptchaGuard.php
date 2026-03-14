<?php

namespace WSms\Auth;

use WP_REST_Request;
use WP_REST_Response;
use WSms\Auth\CaptchaProviders\ProviderInterface;
use WSms\Support\IpResolver;

defined('ABSPATH') || exit;

class CaptchaGuard
{
    private const HEADER = 'X-Captcha-Response';

    /** @var array<string, ProviderInterface> */
    private array $providers;

    /**
     * @param array<string, ProviderInterface> $providers  Keyed by provider id ('turnstile', 'recaptcha', 'hcaptcha').
     */
    public function __construct(array $providers, private SettingsRepository $settingsRepo)
    {
        $this->providers = $providers;
    }

    /**
     * Verify CAPTCHA for a given request and action.
     *
     * @return bool|null  null = not required/disabled (pass), true = verified (pass), false = failed (block).
     */
    public function verify(WP_REST_Request $request, string $action): ?bool
    {
        $settings = $this->getSettings();

        if (empty($settings['enabled'])) {
            return null;
        }

        $protectedActions = $settings['protected_actions'] ?? ['login', 'register', 'forgot_password'];

        if (!in_array($action, $protectedActions, true)) {
            return null;
        }

        $provider = $settings['provider'] ?? 'turnstile';
        $secretKey = $settings['secret_key'] ?? '';

        if (empty($secretKey) || !isset($this->providers[$provider])) {
            return $this->failOpen($settings);
        }

        $token = $request->get_header(self::HEADER);

        if (empty($token)) {
            return false;
        }

        $ip = IpResolver::resolve() ?: '0.0.0.0';

        try {
            $result = $this->providers[$provider]->verify($token, $secretKey, $ip);
        } catch (\Throwable $e) {
            return $this->failOpen($settings);
        }

        return $result;
    }

    /**
     * Get the public captcha config for the /auth/config endpoint (no secrets).
     *
     * @return array|null  null if disabled.
     */
    public function getPublicConfig(): ?array
    {
        $settings = $this->getSettings();

        if (empty($settings['enabled'])) {
            return null;
        }

        $provider = $settings['provider'] ?? 'turnstile';

        return [
            'enabled'           => true,
            'provider'          => $provider,
            'site_key'          => $settings['site_key'] ?? '',
            'protected_actions' => $settings['protected_actions'] ?? ['login', 'register', 'forgot_password'],
        ];
    }

    /**
     * Build a standard 403 response for failed CAPTCHA verification.
     */
    public static function failedResponse(): WP_REST_Response
    {
        return new WP_REST_Response([
            'success' => false,
            'error'   => 'captcha_failed',
            'message' => 'CAPTCHA verification failed.',
        ], 403);
    }

    /**
     * Get the client-side script URL for the active provider, or null if disabled.
     */
    public function getScriptUrl(): ?string
    {
        $settings = $this->getSettings();

        if (empty($settings['enabled'])) {
            return null;
        }

        $provider = $settings['provider'] ?? 'turnstile';

        return isset($this->providers[$provider])
            ? $this->providers[$provider]->getScriptUrl()
            : null;
    }

    /**
     * Apply fail_open setting — return null (pass) if fail_open is true, false (block) otherwise.
     */
    private function failOpen(array $settings): ?bool
    {
        return !empty($settings['fail_open']) ? null : false;
    }

    /**
     * @return array<string, mixed>
     */
    private function getSettings(): array
    {
        return $this->settingsRepo->channel('captcha');
    }
}
