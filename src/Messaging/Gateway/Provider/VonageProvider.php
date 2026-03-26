<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\StatusUpdate;
use WSms\Messaging\Contracts\SupportsDynamicOptions;
use WSms\Messaging\Contracts\SupportsStatusCallback;
use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Contracts\TestConnectionResult;

defined('ABSPATH') || exit;

class VonageProvider extends AbstractProvider implements SupportsDynamicOptions, SupportsStatusCallback
{
    private const API_URL = 'https://rest.nexmo.com/sms/json';
    private const BALANCE_URL = 'https://rest.nexmo.com/account/get-balance';
    private const NUMBERS_URL = 'https://rest.nexmo.com/account/numbers';

    public function getId(): string
    {
        return 'vonage';
    }

    public function getName(): string
    {
        return 'Vonage (Nexmo)';
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
                    'type'        => 'string',
                    'label'       => __('API Key', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Found on the Vonage API Dashboard under Settings', 'wp-sms'),
                    'placeholder' => 'a1b2c3d4',
                ],
                'api_secret' => [
                    'type'        => 'secret',
                    'label'       => __('API Secret', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Found on the Vonage API Dashboard under Settings, next to your API Key', 'wp-sms'),
                    'placeholder' => 'Your API secret',
                ],
            ],
            'channels' => [
                'sms' => [
                    'from' => [
                        'type'        => 'string',
                        'label'       => __('Sender ID', 'wp-sms'),
                        'required'    => true,
                        'description' => __('A Vonage virtual number or an alphanumeric sender ID (max 11 chars)', 'wp-sms'),
                        'placeholder' => '+15551234567',
                        'dynamic'     => true,
                    ],
                ],
            ],
        ];
    }

    public function getMetadata(): array
    {
        return [
            'description' => __('Global cloud communications provider (formerly Nexmo)', 'wp-sms'),
            'website'     => 'https://www.vonage.com',
            'icon'        => '',
            'regions'     => ['global'],
            'setup_url'   => 'https://dashboard.nexmo.com/settings',
            'setup_notes' => [
                __('Find your API Key and API Secret on the Vonage API Dashboard under Settings.', 'wp-sms'),
                __('Purchase a virtual number at Numbers > Buy numbers to use as the Sender ID.', 'wp-sms'),
                __('Alphanumeric sender IDs (e.g., "MyApp") are available in supported countries.', 'wp-sms'),
            ],
        ];
    }

    public function getFeatures(): array
    {
        return array_merge(parent::getFeatures(), [
            'delivery_receipt' => true,
            'incoming'         => true,
            'test_connection'  => true,
        ]);
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        $apiKey = $this->getSharedConfig('api_key');
        $apiSecret = $this->getSharedConfig('api_secret');
        $from = $this->getChannelConfig('sms', 'from');

        if (!$apiKey || !$apiSecret || !$from) {
            return DeliveryResult::failed(__('Vonage credentials not configured', 'wp-sms'));
        }

        $result = $this->httpPost(self::API_URL, [
            'body' => wp_json_encode([
                'api_key'    => $apiKey,
                'api_secret' => $apiSecret,
                'from'       => $from,
                'to'         => $message->getRecipient(),
                'text'       => $message->getBody(),
                'type'       => 'unicode',
                'callback'   => $this->getStatusCallbackUrl(),
            ]),
            'headers' => ['Content-Type' => 'application/json'],
        ]);

        if ($result instanceof DeliveryResult) {
            return $result;
        }

        $data = json_decode($result['body'], true);
        $msg = $data['messages'][0] ?? [];

        if (($msg['status'] ?? '1') === '0') {
            return DeliveryResult::sent(
                providerId: $msg['message-id'] ?? null,
                cost: isset($msg['message-price']) ? (float) $msg['message-price'] : null,
            );
        }

        return DeliveryResult::failed($msg['error-text'] ?? __('Vonage send failed', 'wp-sms'));
    }

    public function getCredit(): ?string
    {
        $apiKey = $this->getSharedConfig('api_key');
        $apiSecret = $this->getSharedConfig('api_secret');

        if (!$apiKey || !$apiSecret) {
            return null;
        }

        $result = $this->httpGet(self::BALANCE_URL . "?api_key={$apiKey}&api_secret={$apiSecret}");

        if ($result instanceof DeliveryResult) {
            return null;
        }

        $data = json_decode($result['body'], true);
        return isset($data['value']) ? number_format((float) $data['value'], 2) : null;
    }

    // --- SupportsStatusCallback ---

    public function getStatusCallbackUrl(): string
    {
        return rest_url('wsms/v1/callbacks/' . $this->getId() . '/status') . '?token=' . $this->callbackToken();
    }

    public function validateStatusCallback(\WP_REST_Request $request): bool
    {
        if (!$this->getSharedConfig('api_secret')) {
            return false;
        }

        return hash_equals($this->callbackToken(), $request->get_param('token') ?? '');
    }

    /** @return StatusUpdate[] */
    public function parseStatusCallback(\WP_REST_Request $request): array
    {
        $messageId = $request->get_param('messageId');
        $status = $request->get_param('status');

        if (empty($messageId) || empty($status)) {
            return [];
        }

        $normalizedStatus = match ($status) {
            'delivered'                        => 'delivered',
            'accepted', 'buffered', 'unknown'  => 'sent',
            'expired', 'failed', 'rejected'    => 'failed',
            default                            => $status,
        };

        $errCode = $request->get_param('err-code');

        return [new StatusUpdate(
            providerId: $messageId,
            status: $normalizedStatus,
            errorCode: $errCode && $errCode !== '0' ? $errCode : null,
            errorMessage: $normalizedStatus === 'failed'
                ? sprintf('Vonage DLR: %s (err-code: %s)', $status, $errCode ?? '0')
                : null,
            permanent: $normalizedStatus === 'failed',
        )];
    }

    private function callbackToken(): string
    {
        return hash_hmac('sha256', 'vonage-dlr', $this->getSharedConfig('api_secret'));
    }

    // --- SupportsDynamicOptions ---

    public function getConfigOptions(string $fieldKey, string $section, array $config, array $context = []): array
    {
        if ($fieldKey !== 'from') {
            return [];
        }

        return $this->withConfig($config, function () {
            $apiKey = $this->getSharedConfig('api_key');
            $apiSecret = $this->getSharedConfig('api_secret');
            $url = self::NUMBERS_URL . "?api_key={$apiKey}&api_secret={$apiSecret}&size=100";
            $data = $this->fetchJsonOrFail($url);

            if (isset($data['error-text'])) {
                throw new \RuntimeException($data['error-text']);
            }

            $options = [];
            foreach ($data['numbers'] ?? [] as $number) {
                $msisdn = $number['msisdn'] ?? '';
                if (!$msisdn) {
                    continue;
                }

                $country = $number['country'] ?? '';
                $features = implode(', ', $number['features'] ?? []);
                $parts = ["+{$msisdn}"];
                if ($country || $features) {
                    $detail = array_filter([$country, $features]);
                    $parts[] = '(' . implode(' — ', $detail) . ')';
                }

                $options[] = ['value' => "+{$msisdn}", 'label' => implode(' ', $parts)];
            }

            return $options;
        });
    }

    public function testConnection(): TestConnectionResult
    {
        $apiKey = $this->getSharedConfig('api_key');
        $apiSecret = $this->getSharedConfig('api_secret');

        if (!$apiKey || !$apiSecret) {
            return TestConnectionResult::error(__('API Key and API Secret are required', 'wp-sms'));
        }

        $result = $this->httpGet(self::BALANCE_URL . "?api_key={$apiKey}&api_secret={$apiSecret}");

        if (!$result instanceof DeliveryResult && $result['code'] === 401) {
            return TestConnectionResult::error(__('Invalid API Key or Secret', 'wp-sms'));
        }

        $data = $this->validateTestResponse($result, 'Vonage');
        if ($data instanceof TestConnectionResult) {
            return $data;
        }

        if (isset($data['error-text'])) {
            return TestConnectionResult::error($data['error-text']);
        }

        $balance = isset($data['value']) ? number_format((float) $data['value'], 2) : 'N/A';

        return TestConnectionResult::ok(
            sprintf(__('Connected — Balance: %s EUR', 'wp-sms'), $balance),
            ['balance' => $balance],
        );
    }
}
