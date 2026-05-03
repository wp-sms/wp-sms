<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\SupportsDynamicOptions;
use WSms\Messaging\Contracts\TestConnectionResult;
use WSms\Messaging\Gateway\AbstractProvider;

defined('ABSPATH') || exit;

class BulutfonProvider extends AbstractProvider implements SupportsDynamicOptions
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const API_BASE = 'https://api.bulutfon.com';

    public function getId(): string
    {
        return 'bulutfon';
    }

    public function getSupportedChannels(): array
    {
        return ['sms'];
    }

    public function getConfigSchema(): array
    {
        return [
            'shared' => [
                'access_token' => [
                    'type'        => 'secret',
                    'label'       => __('Access Token', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Personal API token from your Bulutfon panel → Settings → API Users (Ayarlar → API Kullanıcıları).', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'title' => [
                        'type'        => 'string',
                        'label'       => __('Title (Sender Header)', 'wp-sms'),
                        'required'    => true,
                        'dynamic'     => true,
                        'description' => __('Pre-approved sender header (Başlık) from your Bulutfon panel. Must be in CONFIRMED state. Turkish carrier rule (BTK).', 'wp-sms'),
                    ],
                ],
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        $accessToken = $this->getSharedConfig('access_token');
        $title       = $this->getChannelConfig('sms', 'title');

        if (!$accessToken) {
            return DeliveryResult::failed(__('Bulutfon access token not configured', 'wp-sms'));
        }
        if (!$title) {
            return DeliveryResult::failed(__('Bulutfon Title (Sender Header) not configured', 'wp-sms'));
        }

        $url = self::API_BASE . '/messages.json?' . http_build_query(['access_token' => $accessToken]);

        $result = $this->httpPost($url, [
            'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
            'body'    => http_build_query([
                'title'     => $title,
                'receivers' => $message->getRecipient(),
                'content'   => $message->getBody(),
            ]),
        ]);

        if ($result instanceof DeliveryResult) {
            return $result;
        }

        if ($result['code'] === 401 || $result['code'] === 403) {
            return DeliveryResult::failed(__('Invalid Bulutfon access token', 'wp-sms'));
        }

        if ($result['code'] >= 200 && $result['code'] < 300) {
            $data = json_decode($result['body'], true);
            $providerId = isset($data['message']['id']) ? (string) $data['message']['id'] : null;
            return DeliveryResult::sent(providerId: $providerId);
        }

        $body = trim($result['body']);
        return DeliveryResult::failed(
            $body !== '' ? $body : sprintf(__('Bulutfon returned HTTP %d', 'wp-sms'), $result['code']),
        );
    }

    public function getCredit(): ?string
    {
        $accessToken = $this->getSharedConfig('access_token');
        if (!$accessToken) {
            return null;
        }

        $result = $this->httpGet(self::API_BASE . '/me.json?' . http_build_query(['access_token' => $accessToken]));

        if ($result instanceof DeliveryResult) {
            return null;
        }

        if ($result['code'] < 200 || $result['code'] >= 300) {
            return null;
        }

        $data = json_decode($result['body'], true);
        if (!is_array($data) || !isset($data['credit']['sms_credit'])) {
            return null;
        }

        return (string) $data['credit']['sms_credit'];
    }

    public function testConnection(): TestConnectionResult
    {
        $accessToken = $this->getSharedConfig('access_token');
        if (!$accessToken) {
            return TestConnectionResult::error(__('Access Token is required', 'wp-sms'));
        }

        $result = $this->httpGet(self::API_BASE . '/message-titles.json?' . http_build_query(['access_token' => $accessToken]));

        if (!$result instanceof DeliveryResult && ($result['code'] === 401 || $result['code'] === 403)) {
            return TestConnectionResult::error(__('Invalid access token', 'wp-sms'));
        }

        $data = $this->validateTestResponse($result, 'Bulutfon');
        if ($data instanceof TestConnectionResult) {
            return $data;
        }

        $count = is_array($data['message_titles'] ?? null) ? count($data['message_titles']) : 0;

        return TestConnectionResult::ok(
            sprintf(__('Connected — %d approved title(s)', 'wp-sms'), $count),
            ['titles' => $count],
        );
    }

    public function getConfigOptions(string $fieldKey, string $section, array $config, array $context = []): array
    {
        if ($fieldKey !== 'title' || $section !== 'sms') {
            return [];
        }

        return $this->withConfig($config, function () {
            $accessToken = $this->getSharedConfig('access_token');
            if (!$accessToken) {
                return [];
            }

            $result = $this->httpGet(self::API_BASE . '/message-titles.json?' . http_build_query([
                'access_token' => $accessToken,
            ]));

            if ($result instanceof DeliveryResult) {
                return [];
            }

            if ($result['code'] === 401 || $result['code'] === 403) {
                throw new \RuntimeException(__('Invalid Bulutfon access token', 'wp-sms'));
            }

            if ($result['code'] < 200 || $result['code'] >= 300) {
                return [];
            }

            $data = json_decode($result['body'], true);
            $titles = is_array($data['message_titles'] ?? null) ? $data['message_titles'] : [];

            $options = [];
            foreach ($titles as $title) {
                if (!is_array($title) || ($title['state'] ?? null) !== 'CONFIRMED') {
                    continue;
                }
                $name = $title['name'] ?? null;
                if (!is_string($name) || $name === '') {
                    continue;
                }
                $options[] = ['value' => $name, 'label' => $name];
            }
            return $options;
        });
    }
}
