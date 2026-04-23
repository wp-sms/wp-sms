<?php

namespace WSms\Messaging\Email;

use WSms\Rest\RestRoute;
use WSms\Support\SigningKey;

defined('ABSPATH') || exit;

class UnsubscribeTokenService
{
    private const TOKEN_VERSION = 'wsms_email_unsub_v1';
    private const EXPIRY_SECONDS = 90 * 86400; // 90 days

    private string $key;

    public function __construct()
    {
        $this->key = hash_hmac('sha256', self::TOKEN_VERSION, SigningKey::get());
    }

    public function generate(string $email, ?string $campaignId = null): string
    {
        $payload = wp_json_encode([
            'e' => strtolower($email),
            'c' => $campaignId,
            'x' => time() + self::EXPIRY_SECONDS,
        ]);

        $encodedPayload = $this->base64UrlEncode($payload);
        $signature = $this->base64UrlEncode(
            substr(hash_hmac('sha256', $encodedPayload, $this->key, true), 0, 16)
        );

        return $encodedPayload . '.' . $signature;
    }

    public function validate(string $token): ?UnsubscribePayload
    {
        $parts = explode('.', $token, 2);
        if (count($parts) !== 2) {
            return null;
        }

        [$encodedPayload, $encodedSignature] = $parts;

        $expectedSignature = $this->base64UrlEncode(
            substr(hash_hmac('sha256', $encodedPayload, $this->key, true), 0, 16)
        );

        if (!hash_equals($expectedSignature, $encodedSignature)) {
            return null;
        }

        $json = $this->base64UrlDecode($encodedPayload);
        if ($json === false) {
            return null;
        }

        $data = json_decode($json, true);
        if (!is_array($data) || empty($data['e']) || !isset($data['x'])) {
            return null;
        }

        if (time() > (int) $data['x']) {
            return null;
        }

        return new UnsubscribePayload(
            email: $data['e'],
            campaignId: $data['c'] ?? null,
        );
    }

    public function getUnsubscribeUrl(string $email, ?string $campaignId = null): string
    {
        $token = $this->generate($email, $campaignId);

        return RestRoute::url('email/unsubscribe', ['token' => $token]);
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $data): string|false
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}
