<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\SupportsDynamicOptions;
use WSms\Messaging\Contracts\TestConnectionResult;
use WSms\Messaging\Gateway\AbstractProvider;

defined('ABSPATH') || exit;

/**
 * Oxemis (OxiSMS) — French SMS provider with worldwide premium routing.
 *
 * Auth: HTTP Basic, base64(API_LOGIN:API_PASSWORD).
 * Send: POST /send returning { SendingId, Filtered? } on 2xx, { Code, Message } on 4xx/406.
 * Balance: GET /user returning the User schema { CompanyName, Credits, CreditsValidBefore, Rates }.
 * Senders: GET /senders returning { OK: string[], PENDING: string[], BLOCKED: string[] }.
 *
 * Options.Strategy is forced to "notification" so OTP/transactional traffic is not
 * postponed by Oxemis's commercial-quiet-hours rule (no sends 9pm–8am or Sundays).
 *
 * Delivery receipts and inbound replies are poll-only (GET /status, GET /replies); Oxemis
 * does not expose push webhooks, so SupportsStatusCallback / SupportsInboundMessage are
 * intentionally absent. Verify-as-a-Service is not offered (no /verify endpoint).
 */
class OxemisProvider extends AbstractProvider implements SupportsDynamicOptions
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const API_BASE = 'https://api.oxisms.com';

    public function getId(): string
    {
        return 'oxemis';
    }

    public function getSupportedChannels(): array
    {
        return ['sms'];
    }

    public function getConfigSchema(): array
    {
        return [
            'shared' => [
                'api_login' => [
                    'type'        => 'string',
                    'label'       => __('API Login', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Your OxiSMS API Login from account.oxemis.com (Settings → API).', 'wp-sms'),
                ],
                'api_password' => [
                    'type'        => 'secret',
                    'label'       => __('API Password', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Your OxiSMS API Password from account.oxemis.com (Settings → API).', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'sender_id' => [
                        'type'        => 'string',
                        'label'       => __('Sender ID', 'wp-sms'),
                        'required'    => false,
                        'dynamic'     => true,
                        'description' => __('Approved alphanumeric sender (max 11 chars, no spaces). Must be registered and approved (Status = OK) under Senders in your Oxemis dashboard. Leave blank to use the Oxemis short code (e.g. 36111).', 'wp-sms'),
                    ],
                ],
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        $login    = (string) $this->getSharedConfig('api_login', '');
        $password = (string) $this->getSharedConfig('api_password', '');

        if ($login === '' || $password === '') {
            return DeliveryResult::failed(__('Oxemis credentials not configured', 'wp-sms'));
        }

        $messageBlock = ['Text' => $message->getBody()];

        $sender = (string) $this->getChannelConfig('sms', 'sender_id', '');
        if ($sender !== '') {
            $messageBlock['Sender'] = $sender;
        }

        $body = [
            'Options'    => ['Strategy' => 'notification'],
            'Message'    => $messageBlock,
            'Recipients' => [['PhoneNumber' => $message->getRecipient()]],
        ];

        $result = $this->httpPost(self::API_BASE . '/send', [
            'headers' => $this->authHeaders($login, $password),
            'body'    => wp_json_encode($body),
        ]);

        if ($result instanceof DeliveryResult) {
            return $result;
        }

        if ($result['code'] === 401 || $result['code'] === 403) {
            return DeliveryResult::failed(__('Invalid Oxemis API Login or API Password', 'wp-sms'));
        }

        $data = json_decode($result['body'], true);

        // Errors come back as { Code: int, Message: string } (typically with HTTP 406).
        if (is_array($data) && isset($data['Code'])) {
            return DeliveryResult::failed(
                $data['Message'] ?? sprintf('Oxemis error %s', $data['Code']),
                meta: array_filter(['oxemis_code' => $data['Code']]),
            );
        }

        if ($result['code'] < 200 || $result['code'] >= 300) {
            return DeliveryResult::failed(sprintf('Oxemis: HTTP %d', $result['code']));
        }

        if (!is_array($data)) {
            return DeliveryResult::failed(__('Invalid response from Oxemis', 'wp-sms'));
        }

        return DeliveryResult::sent(
            providerId: isset($data['SendingId']) ? (string) $data['SendingId'] : null,
        );
    }

    public function getCredit(): ?string
    {
        $login    = (string) $this->getSharedConfig('api_login', '');
        $password = (string) $this->getSharedConfig('api_password', '');

        if ($login === '' || $password === '') {
            return null;
        }

        $result = $this->httpGet(self::API_BASE . '/user', [
            'headers' => $this->authHeaders($login, $password),
        ]);

        if ($result instanceof DeliveryResult) {
            return null;
        }

        $data = json_decode($result['body'], true);
        if (!is_array($data) || !isset($data['Credits'])) {
            return null;
        }

        return (string) $data['Credits'];
    }

    public function testConnection(): TestConnectionResult
    {
        $login    = (string) $this->getSharedConfig('api_login', '');
        $password = (string) $this->getSharedConfig('api_password', '');

        if ($login === '' || $password === '') {
            return TestConnectionResult::error(__('API Login and API Password are required', 'wp-sms'));
        }

        $result = $this->httpGet(self::API_BASE . '/user', [
            'headers' => $this->authHeaders($login, $password),
        ]);

        if (!$result instanceof DeliveryResult) {
            if ($result['code'] === 401 || $result['code'] === 403) {
                return TestConnectionResult::error(__('Invalid Oxemis API Login or API Password', 'wp-sms'));
            }
        }

        $data = $this->validateTestResponse($result, 'Oxemis');
        if ($data instanceof TestConnectionResult) {
            return $data;
        }

        $credits = $data['Credits'] ?? 'N/A';

        return TestConnectionResult::ok(
            sprintf(__('Connected — Balance: %s credits', 'wp-sms'), (string) $credits),
            ['balance' => (string) $credits],
        );
    }

    // --- SupportsDynamicOptions ---

    public function getConfigOptions(string $fieldKey, string $section, array $config, array $context = []): array
    {
        if ($fieldKey !== 'sender_id' || $section !== 'sms') {
            return [];
        }

        return $this->withConfig($config, function () {
            $login    = (string) $this->getSharedConfig('api_login', '');
            $password = (string) $this->getSharedConfig('api_password', '');

            if ($login === '' || $password === '') {
                return [];
            }

            $result = $this->httpGet(self::API_BASE . '/senders', [
                'headers' => $this->authHeaders($login, $password),
            ]);

            if ($result instanceof DeliveryResult) {
                throw new \RuntimeException(__('Could not reach Oxemis', 'wp-sms'));
            }

            if ($result['code'] === 401 || $result['code'] === 403) {
                throw new \RuntimeException(__('Invalid Oxemis API Login or API Password', 'wp-sms'));
            }

            // 204 No Content = no senders registered yet.
            if ($result['code'] === 204) {
                return [];
            }

            $data = json_decode($result['body'], true);
            if (!is_array($data)) {
                return [];
            }

            // /senders returns { OK: [...], PENDING: [...], BLOCKED: [...] } — we only
            // surface approved senders; PENDING ones can't be used and BLOCKED ones
            // are dead.
            $approved = $data['OK'] ?? [];
            if (!is_array($approved)) {
                return [];
            }

            $options = [];
            foreach ($approved as $sender) {
                if (is_string($sender) && $sender !== '') {
                    $options[] = ['value' => $sender, 'label' => $sender];
                }
            }
            return $options;
        });
    }

    // --- Internal ---

    private function authHeaders(string $login, string $password): array
    {
        return [
            'Authorization' => 'Basic ' . base64_encode("{$login}:{$password}"),
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
        ];
    }
}
