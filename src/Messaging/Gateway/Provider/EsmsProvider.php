<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\SupportsDynamicOptions;
use WSms\Messaging\Contracts\TestConnectionResult;
use WSms\Messaging\Gateway\AbstractProvider;

defined('ABSPATH') || exit;

/**
 * eSMS Vietnam — modern JSON REST gateway covering SMS Brandname and Viber OTT.
 *
 * Endpoint: https://rest.esms.vn/MainService.svc/json/. Auth = ApiKey + SecretKey
 * passed in the body (SMS / Viber send) or in the URL path (GetBalance / GetListBrandname).
 * Successful sends return CodeResult: "100" with an SMSID; balance / brandname GETs use
 * CodeResponse: "00" for success.
 *
 * TODO(zalo): eSMS exposes Zalo ZNS via /MultiChannelMessage/ and /SendZaloMessage/;
 *   defer until WSMS adds a 'zalo' channel (not yet a first-class WSMS channel).
 * TODO(voice): eSMS exposes eVoice OTP; defer until WSMS lands first-class voice
 *   channel — pattern matches TelnyxProvider / SevenProvider / UnifonicProvider TODOs.
 * TODO(status-callback): per-send CallBackUrl is supported but no documented HMAC
 *   signature scheme; can be added later with a configurable URL-token reject-by-default
 *   scheme similar to AlphaSmsProvider.
 */
class EsmsProvider extends AbstractProvider implements SupportsDynamicOptions
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const API_BASE = 'https://rest.esms.vn/MainService.svc/json';

    public function getId(): string
    {
        return 'esms';
    }

    public function getSupportedChannels(): array
    {
        return ['sms', 'viber'];
    }

    public function getConfigSchema(): array
    {
        return [
            'shared' => [
                'api_key' => [
                    'type'        => 'string',
                    'label'       => __('API Key', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Find your API Key in the eSMS dashboard at esms.vn under account settings.', 'wp-sms'),
                ],
                'secret_key' => [
                    'type'        => 'secret',
                    'label'       => __('Secret Key', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Find your Secret Key in the eSMS dashboard at esms.vn alongside the API Key.', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'brandname' => [
                        'type'        => 'string',
                        'label'       => __('Brandname', 'wp-sms'),
                        'required'    => true,
                        'description' => __('Pre-approved brandname registered with Vietnamese carriers via eSMS.', 'wp-sms'),
                        'dynamic'     => true,
                    ],
                    'sms_type' => [
                        'type'     => 'select',
                        'label'    => __('SMS Type', 'wp-sms'),
                        'required' => true,
                        'default'  => '2',
                        'options'  => [
                            ['value' => '2', 'label' => __('Brandname (customer care)', 'wp-sms')],
                            ['value' => '8', 'label' => __('Fixed 10-digit prefix', 'wp-sms')],
                        ],
                    ],
                    'is_unicode' => [
                        'type'        => 'boolean',
                        'label'       => __('Unicode', 'wp-sms'),
                        'default'     => false,
                        'description' => __('Enable for Vietnamese diacritics / non-ASCII content.', 'wp-sms'),
                    ],
                    'sandbox' => [
                        'type'        => 'boolean',
                        'label'       => __('Sandbox', 'wp-sms'),
                        'default'     => false,
                        'description' => __('Test mode — does not deliver or consume credit.', 'wp-sms'),
                    ],
                ],
                'viber' => [
                    'brandname' => [
                        'type'        => 'string',
                        'label'       => __('Brandname', 'wp-sms'),
                        'required'    => true,
                        'description' => __('Pre-approved Viber Business brandname; eSMS uses one Brandname catalog across both channels.', 'wp-sms'),
                        'dynamic'     => true,
                    ],
                    'sandbox' => [
                        'type'        => 'boolean',
                        'label'       => __('Sandbox', 'wp-sms'),
                        'default'     => false,
                        'description' => __('Test mode — does not deliver or consume credit.', 'wp-sms'),
                    ],
                ],
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        $apiKey = $this->getSharedConfig('api_key');
        $secretKey = $this->getSharedConfig('secret_key');

        if (!$apiKey || !$secretKey) {
            return DeliveryResult::failed(__('eSMS credentials not configured', 'wp-sms'));
        }

        $channel = $message->getChannel();
        $brandname = $this->getChannelConfig($channel, 'brandname');
        if (!$brandname) {
            return DeliveryResult::failed(__('eSMS Brandname not configured for this channel', 'wp-sms'));
        }

        return match ($channel) {
            'sms'   => $this->sendSms($message, $apiKey, $secretKey, (string) $brandname),
            'viber' => $this->sendViber($message, $apiKey, $secretKey, (string) $brandname),
            default => DeliveryResult::failed(sprintf(__('eSMS does not support channel %s', 'wp-sms'), $channel)),
        };
    }

    private function sendSms(MessageInterface $message, string $apiKey, string $secretKey, string $brandname): DeliveryResult
    {
        $meta = $message->getMeta();
        $body = [
            'ApiKey'     => $apiKey,
            'SecretKey'  => $secretKey,
            'Phone'      => $message->getRecipient(),
            'Content'    => $message->getBody(),
            'IsUnicode'  => $this->getChannelConfig('sms', 'is_unicode') ? 1 : 0,
            'Brandname'  => $brandname,
            'SmsType'    => (string) ($this->getChannelConfig('sms', 'sms_type') ?? '2'),
            'Sandbox'    => $this->getChannelConfig('sms', 'sandbox') ? 1 : 0,
        ];

        if (!empty($meta['request_id'])) {
            $body['RequestId'] = (string) $meta['request_id'];
        }

        return $this->postAndParse(self::API_BASE . '/SendMultipleMessage_V4_post_json/', $body);
    }

    private function sendViber(MessageInterface $message, string $apiKey, string $secretKey, string $brandname): DeliveryResult
    {
        $meta = $message->getMeta();
        $body = [
            'ApiKey'    => $apiKey,
            'SecretKey' => $secretKey,
            'Brandname' => $brandname,
            'SmsType'   => 23,
            'Phones'    => [$message->getRecipient()],
            'Content'   => $message->getBody(),
            'Sandbox'   => $this->getChannelConfig('viber', 'sandbox') ? 1 : 0,
        ];

        if (!empty($meta['image_url'])) {
            $body['OttImgUrl'] = (string) $meta['image_url'];
        }
        if (!empty($meta['button_url'])) {
            $body['OttUrl'] = (string) $meta['button_url'];
        }
        if (!empty($meta['button_label'])) {
            $body['OTTLabel'] = (string) $meta['button_label'];
        }
        if (!empty($meta['request_id'])) {
            $body['RequestId'] = (string) $meta['request_id'];
        }

        return $this->postAndParse(self::API_BASE . '/Send_Multiple_Sms_OTT/', $body);
    }

    private function postAndParse(string $url, array $body): DeliveryResult
    {
        $result = $this->httpPost($url, [
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ],
            'body' => wp_json_encode($body),
        ]);

        if ($result instanceof DeliveryResult) {
            return $result;
        }

        $data = json_decode($result['body'], true);

        if ($result['code'] === 401 || $result['code'] === 403) {
            return DeliveryResult::failed(__('Invalid eSMS credentials', 'wp-sms'));
        }

        if (!is_array($data)) {
            return DeliveryResult::failed(sprintf(__('Invalid response from eSMS (HTTP %d)', 'wp-sms'), $result['code']));
        }

        $code = (string) ($data['CodeResult'] ?? '');
        if ($code === '100') {
            $smsId = isset($data['SMSID']) ? (string) $data['SMSID'] : null;
            return DeliveryResult::queued($smsId);
        }

        $error = (string) ($data['ErrorMessage'] ?? '');
        if ($error === '') {
            $error = $code !== ''
                ? sprintf(__('eSMS error code %s', 'wp-sms'), $code)
                : sprintf(__('eSMS request failed (HTTP %d)', 'wp-sms'), $result['code']);
        }

        return DeliveryResult::failed($error, array_filter([
            'esms_code' => $code !== '' ? $code : null,
        ]));
    }

    public function getCredit(): ?string
    {
        $apiKey = $this->getSharedConfig('api_key');
        $secretKey = $this->getSharedConfig('secret_key');
        if (!$apiKey || !$secretKey) {
            return null;
        }

        $result = $this->httpGet($this->balanceUrl($apiKey, $secretKey));
        if ($result instanceof DeliveryResult) {
            return null;
        }

        $data = json_decode($result['body'], true);
        if (!is_array($data) || ($data['CodeResponse'] ?? null) !== '00') {
            return null;
        }

        $balance = $data['Balance'] ?? null;
        if ($balance === null) {
            return null;
        }

        return sprintf('%s VND', $balance);
    }

    public function testConnection(): TestConnectionResult
    {
        $apiKey = $this->getSharedConfig('api_key');
        $secretKey = $this->getSharedConfig('secret_key');

        if (!$apiKey || !$secretKey) {
            return TestConnectionResult::error(__('API Key and Secret Key are required', 'wp-sms'));
        }

        $result = $this->httpGet($this->balanceUrl($apiKey, $secretKey));

        $data = $this->validateTestResponse($result, 'eSMS');
        if ($data instanceof TestConnectionResult) {
            return $data;
        }

        $codeResponse = (string) ($data['CodeResponse'] ?? '');
        if ($codeResponse !== '00') {
            // 01 / 02 are eSMS's auth-failure codes per their error reference table.
            if (in_array($codeResponse, ['01', '02'], true)) {
                return TestConnectionResult::error(__('Invalid eSMS API Key or Secret Key', 'wp-sms'));
            }
            return TestConnectionResult::error(sprintf(__('eSMS error code %s', 'wp-sms'), $codeResponse));
        }

        $balance = $data['Balance'] ?? null;
        if ($balance === null) {
            return TestConnectionResult::ok(__('Connected to eSMS', 'wp-sms'));
        }

        return TestConnectionResult::ok(
            sprintf(__('Connected — Balance: %s VND', 'wp-sms'), $balance),
            ['balance' => (string) $balance],
        );
    }

    public function getConfigOptions(string $fieldKey, string $section, array $config, array $context = []): array
    {
        if ($fieldKey !== 'brandname' || !in_array($section, ['sms', 'viber'], true)) {
            return [];
        }

        return $this->withConfig($config, function () {
            $apiKey = (string) $this->getSharedConfig('api_key', '');
            $secretKey = (string) $this->getSharedConfig('secret_key', '');
            if ($apiKey === '' || $secretKey === '') {
                return [];
            }

            try {
                $data = $this->fetchJsonOrFail(self::API_BASE . "/GetListBrandname/{$apiKey}/{$secretKey}");
            } catch (\RuntimeException) {
                return [];
            }

            if (($data['CodeResponse'] ?? null) !== '00') {
                return [];
            }

            $options = [];
            foreach ($data['ListBrandName'] ?? [] as $entry) {
                $name = is_array($entry) ? ($entry['Brandname'] ?? null) : null;
                if (!is_string($name) || $name === '') {
                    continue;
                }
                $options[] = ['value' => $name, 'label' => $name];
            }

            return $options;
        });
    }

    private function balanceUrl(string $apiKey, string $secretKey): string
    {
        return self::API_BASE . "/GetBalance/{$apiKey}/{$secretKey}";
    }
}
