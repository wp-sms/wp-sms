<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\SupportsDynamicOptions;
use WSms\Messaging\Contracts\TestConnectionResult;
use WSms\Messaging\Gateway\AbstractProvider;

defined('ABSPATH') || exit;

/**
 * 4jawaly — Saudi-licensed (CITC/CST) bulk SMS gateway used widely by KSA SMBs.
 *
 * API contract reverse-engineered from the official Laravel SDKs
 * (4jawalycom/sms-gateway-4jawaly, 4jawaly/smsgateway) — there is no public
 * REST reference indexed:
 *   Send:    POST  https://api-sms.4jawaly.com/api/v1/account/area/sms/send
 *   Balance: GET   https://api-sms.4jawaly.com/api/v1/account/area/me/packages
 *   Senders: GET   https://api-sms.4jawaly.com/api/v1/account/area/senders
 *
 * Auth is HTTP Basic with `api_key:api_secret`. Send returns
 * `{success:true, job_id:"…"}` or `{success:false, errors:{error_type:[…]}}`.
 *
 * Out of scope (no documented endpoints): DLR webhook, inbound MO,
 * opt-out detection, templates, MMS/flash SMS, voice.
 */
// TODO(verify): 4jawaly's marketing mentions OTP routing and WhatsApp but
// exposes no /verify start+check endpoints in the public API. Defer until
// WSMS adds SupportsVerify.
final class _4jawalyProvider extends AbstractProvider implements SupportsDynamicOptions
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const API_BASE = 'https://api-sms.4jawaly.com/api/v1';

    public function getId(): string
    {
        return '4jawaly';
    }

    public function getSupportedChannels(): array
    {
        return ['sms'];
    }

    public function getConfigSchema(): array
    {
        return [
            'shared' => [
                'api_key' => [
                    'type'        => 'secret',
                    'label'       => __('API Key', 'wp-sms'),
                    'required'    => true,
                    'placeholder' => __('Your 4jawaly API Key', 'wp-sms'),
                    'description' => __('Find this in the 4jawaly dashboard under Personal Information → API Token.', 'wp-sms'),
                ],
                'api_secret' => [
                    'type'        => 'secret',
                    'label'       => __('API Secret', 'wp-sms'),
                    'required'    => true,
                    'placeholder' => __('Your 4jawaly API Secret', 'wp-sms'),
                    'description' => __('Pair with the API Key from the same dashboard section.', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'sender_name' => [
                        'type'        => 'string',
                        'label'       => __('Sender Name', 'wp-sms'),
                        'required'    => true,
                        'dynamic'     => true,
                        'placeholder' => __('Pre-approved sender ID', 'wp-sms'),
                        'description' => __('Sender names must be approved by 4jawaly under CITC/CST rules before they can deliver.', 'wp-sms'),
                    ],
                ],
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        $apiKey    = (string) $this->getSharedConfig('api_key', '');
        $apiSecret = (string) $this->getSharedConfig('api_secret', '');
        $sender    = (string) $this->getChannelConfig('sms', 'sender_name', '');

        if ($apiKey === '' || $apiSecret === '' || $sender === '') {
            return DeliveryResult::failed(__('4jawaly credentials not configured', 'wp-sms'));
        }

        $payload = [
            'messages' => [[
                'text'    => $message->getBody(),
                'numbers' => [$message->getRecipient()],
                'sender'  => $sender,
            ]],
        ];

        $result = $this->httpPost(self::API_BASE . '/account/area/sms/send', [
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode($apiKey . ':' . $apiSecret),
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ],
            'body' => json_encode($payload),
        ]);

        if ($result instanceof DeliveryResult) {
            return $result;
        }

        $data = json_decode($result['body'], true);
        if (!is_array($data)) {
            return DeliveryResult::failed(__('Invalid response from 4jawaly', 'wp-sms'));
        }

        if (!empty($data['success'])) {
            return DeliveryResult::sent(providerId: (string) ($data['job_id'] ?? ''));
        }

        $errorType = $data['errors']['error_type'] ?? [];
        $errorMessage = is_array($errorType) ? implode(', ', $errorType) : (string) $errorType;
        if ($errorMessage === '') {
            $errorMessage = __('4jawaly send failed', 'wp-sms');
        }

        return DeliveryResult::failed($errorMessage);
    }

    public function getCredit(): ?string
    {
        $result = $this->fetchBalance();
        if (!is_array($result)) {
            return null;
        }

        if (!isset($result['total_balance'])) {
            return null;
        }

        return (string) $result['total_balance'];
    }

    public function testConnection(): TestConnectionResult
    {
        $apiKey    = (string) $this->getSharedConfig('api_key', '');
        $apiSecret = (string) $this->getSharedConfig('api_secret', '');

        if ($apiKey === '' || $apiSecret === '') {
            return TestConnectionResult::error(__('4jawaly credentials not configured', 'wp-sms'));
        }

        $result = $this->httpGet(self::balanceUrl(), [
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode($apiKey . ':' . $apiSecret),
                'Accept'        => 'application/json',
            ],
        ]);

        if (!($result instanceof DeliveryResult) && ($result['code'] === 401 || $result['code'] === 403)) {
            return TestConnectionResult::error(__('Invalid 4jawaly credentials', 'wp-sms'));
        }

        $data = $this->validateTestResponse($result, '4jawaly');
        if ($data instanceof TestConnectionResult) {
            return $data;
        }

        $balance = $data['total_balance'] ?? null;
        $message = $balance !== null
            ? sprintf(__('Connected. Balance: %s', 'wp-sms'), (string) $balance)
            : __('Connected', 'wp-sms');

        return TestConnectionResult::ok($message, $balance !== null ? ['balance' => $balance] : []);
    }

    // --- SupportsDynamicOptions ---

    public function getConfigOptions(string $fieldKey, string $section, array $config, array $context = []): array
    {
        if ($fieldKey !== 'sender_name' || $section !== 'sms') {
            return [];
        }

        return $this->withConfig($config, function () {
            $apiKey    = (string) $this->getSharedConfig('api_key', '');
            $apiSecret = (string) $this->getSharedConfig('api_secret', '');

            if ($apiKey === '' || $apiSecret === '') {
                return [];
            }

            $url = self::API_BASE . '/account/area/senders?' . http_build_query([
                'status'            => 1,
                'return_collection' => 1,
                'page_size'         => 50,
                'page'              => 1,
            ]);

            $result = $this->httpGet($url, [
                'headers' => [
                    'Authorization' => 'Basic ' . base64_encode($apiKey . ':' . $apiSecret),
                    'Accept'        => 'application/json',
                ],
            ]);

            if ($result instanceof DeliveryResult) {
                return [];
            }

            if ($result['code'] < 200 || $result['code'] >= 300) {
                return [];
            }

            $data = json_decode($result['body'], true);
            if (!is_array($data) || empty($data['items']) || !is_array($data['items'])) {
                return [];
            }

            $options = [];
            foreach ($data['items'] as $item) {
                $name = trim((string) ($item['sender_name'] ?? ''));
                if ($name !== '') {
                    $options[] = ['value' => $name, 'label' => $name];
                }
            }

            return $options;
        });
    }

    /**
     * GET /account/area/me/packages and return the decoded body, or null on failure.
     */
    private function fetchBalance(): ?array
    {
        $apiKey    = (string) $this->getSharedConfig('api_key', '');
        $apiSecret = (string) $this->getSharedConfig('api_secret', '');

        if ($apiKey === '' || $apiSecret === '') {
            return null;
        }

        $result = $this->httpGet(self::balanceUrl(), [
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode($apiKey . ':' . $apiSecret),
                'Accept'        => 'application/json',
            ],
        ]);

        if ($result instanceof DeliveryResult) {
            return null;
        }

        if ($result['code'] < 200 || $result['code'] >= 300) {
            return null;
        }

        $data = json_decode($result['body'], true);
        return is_array($data) ? $data : null;
    }

    private static function balanceUrl(): string
    {
        return self::API_BASE . '/account/area/me/packages?' . http_build_query([
            'is_active'         => 1,
            'order_by'          => 'id',
            'order_by_type'     => 'desc',
            'page'              => 1,
            'page_size'         => 10,
            'return_collection' => 1,
        ]);
    }
}
