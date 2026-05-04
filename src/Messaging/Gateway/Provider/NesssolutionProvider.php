<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\StatusUpdate;
use WSms\Messaging\Contracts\SupportsDynamicOptions;
use WSms\Messaging\Contracts\SupportsStatusCallback;
use WSms\Messaging\Contracts\TestConnectionResult;
use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Rest\RestRoute;

defined('ABSPATH') || exit;

// TODO(verify): provider has SMS-for-verification flow; defer until WSMS adds SupportsVerify.
class NesssolutionProvider extends AbstractProvider implements
    SupportsStatusCallback,
    SupportsDynamicOptions
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const API_BASE = 'https://traffic.sales.lv/API:0.16/';

    public function getId(): string
    {
        return 'nesssolution';
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
                    'description' => __('Generate an API Key in https://traffic.sales.lv/ → Settings → Users.', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'from' => [
                        'type'        => 'string',
                        'label'       => __('Sender ID', 'wp-sms'),
                        'required'    => true,
                        'description' => __('Alphanumeric 3-11 chars (A-Z, 0-9, underscore) or numeric in +E.164 format.', 'wp-sms'),
                        'placeholder' => 'MyBrand',
                        'dynamic'     => true,
                    ],
                ],
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        $apiKey = $this->getSharedConfig('api_key');
        if (!$apiKey) {
            return DeliveryResult::failed(__('Ness Solutions API Key not configured', 'wp-sms'));
        }

        $sender = $this->getChannelConfig('sms', 'from');
        if (!$sender) {
            return DeliveryResult::failed(__('Ness Solutions Sender ID not configured', 'wp-sms'));
        }

        $body = [
            'APIKey'     => $apiKey,
            'Command'    => 'Send',
            'Sender'     => $sender,
            'Recipients' => $message->getRecipient(),
            'Content'    => $message->getBody(),
        ];

        $result = $this->httpPost(self::API_BASE, [
            'headers' => ['Content-Type' => 'application/x-www-form-urlencoded; charset=UTF-8'],
            'body'    => http_build_query($body, '', '&', PHP_QUERY_RFC3986),
        ]);

        if ($result instanceof DeliveryResult) {
            return $result;
        }

        $data = json_decode($result['body'], true);

        if ($result['code'] < 200 || $result['code'] >= 300) {
            return DeliveryResult::failed(sprintf('HTTP %d', $result['code']));
        }

        if (is_array($data) && isset($data['Error'])) {
            return DeliveryResult::failed(
                $this->mapError((string) $data['Error']),
                meta: ['nesssolution_error' => (string) $data['Error']],
            );
        }

        if (!is_array($data) || empty($data[0]) || !isset($data[0]['ID'])) {
            return DeliveryResult::failed(__('Invalid response from Ness Solutions', 'wp-sms'));
        }

        return DeliveryResult::sent((string) $data[0]['ID']);
    }

    public function getCredit(): ?string
    {
        $apiKey = $this->getSharedConfig('api_key');
        if (!$apiKey) {
            return null;
        }

        $result = $this->httpPost(self::API_BASE, [
            'headers' => ['Content-Type' => 'application/x-www-form-urlencoded; charset=UTF-8'],
            'body'    => http_build_query(['APIKey' => $apiKey, 'Command' => 'GetQuota'], '', '&', PHP_QUERY_RFC3986),
        ]);

        if ($result instanceof DeliveryResult) {
            return null;
        }

        $data = json_decode($result['body'], true);
        if (!is_array($data) || isset($data['Error'])) {
            return null;
        }

        $quota = $data['Quota'] ?? $data[0]['Quota'] ?? $data[0] ?? null;
        if (is_array($quota)) {
            $quota = $quota['Quota'] ?? null;
        }

        return $quota === null ? null : (string) $quota;
    }

    public function testConnection(): TestConnectionResult
    {
        $apiKey = $this->getSharedConfig('api_key');
        if (!$apiKey) {
            return TestConnectionResult::error(__('API Key is required', 'wp-sms'));
        }

        $result = $this->httpPost(self::API_BASE, [
            'headers' => ['Content-Type' => 'application/x-www-form-urlencoded; charset=UTF-8'],
            'body'    => http_build_query(['APIKey' => $apiKey, 'Command' => 'GetQuota'], '', '&', PHP_QUERY_RFC3986),
        ]);

        $data = $this->validateTestResponse($result, 'Ness Solutions');
        if ($data instanceof TestConnectionResult) {
            return $data;
        }

        if (isset($data['Error'])) {
            return TestConnectionResult::error($this->mapError((string) $data['Error']));
        }

        $quota = $data['Quota'] ?? $data[0]['Quota'] ?? $data[0] ?? null;
        if (is_array($quota)) {
            $quota = $quota['Quota'] ?? null;
        }

        $balance = $quota !== null ? (string) $quota : 'N/A';

        return TestConnectionResult::ok(
            sprintf(__('Connected — Quota: %s', 'wp-sms'), $balance),
            ['balance' => $balance],
        );
    }

    // --- SupportsDynamicOptions ---

    public function getConfigOptions(string $fieldKey, string $section, array $config, array $context = []): array
    {
        if ($fieldKey !== 'from' || $section !== 'sms') {
            return [];
        }

        return $this->withConfig($config, function () {
            $apiKey = $this->getSharedConfig('api_key');
            if (!$apiKey) {
                throw new \RuntimeException(__('Enter the API Key first', 'wp-sms'));
            }

            $result = $this->httpPost(self::API_BASE, [
                'headers' => ['Content-Type' => 'application/x-www-form-urlencoded; charset=UTF-8'],
                'body'    => http_build_query(['APIKey' => $apiKey, 'Command' => 'GetSenders'], '', '&', PHP_QUERY_RFC3986),
            ]);

            if ($result instanceof DeliveryResult) {
                throw new \RuntimeException(
                    __('Could not reach the Ness Solutions API. Check your server\'s internet connection.', 'wp-sms'),
                );
            }

            if ($result['code'] < 200 || $result['code'] >= 300) {
                throw new \RuntimeException(sprintf(__('HTTP %d from Ness Solutions', 'wp-sms'), $result['code']));
            }

            $data = json_decode($result['body'], true);
            if (!is_array($data)) {
                throw new \RuntimeException(__('Invalid response from Ness Solutions', 'wp-sms'));
            }

            if (isset($data['Error'])) {
                throw new \RuntimeException($this->mapError((string) $data['Error']));
            }

            $options = [];
            foreach ($data as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $sender = $row['Sender'] ?? $row['Name'] ?? $row['ID'] ?? null;
                if ($sender === null || $sender === '') {
                    continue;
                }
                $options[] = ['value' => (string) $sender, 'label' => (string) $sender];
            }

            return $options;
        });
    }

    // --- SupportsStatusCallback ---

    public function getStatusCallbackUrl(): string
    {
        return RestRoute::url('callbacks/' . $this->getId() . '/status');
    }

    public function validateStatusCallback(\WP_REST_Request $request): bool
    {
        $apiKey = $this->getSharedConfig('api_key');
        if (!$apiKey) {
            return false;
        }

        $mssid    = (string) ($request->get_param('MSSID') ?? '');
        $dlr      = (string) ($request->get_param('DLR') ?? '');
        $received = (string) ($request->get_param('HMAC') ?? '');

        if ($mssid === '' || $dlr === '' || $received === '') {
            return false;
        }

        $inner    = hash('sha256', $apiKey . $mssid . $dlr);
        $expected = hash('sha256', $apiKey . $inner);

        return hash_equals($expected, $received);
    }

    /** @return StatusUpdate[] */
    public function parseStatusCallback(\WP_REST_Request $request): array
    {
        $mssid = $request->get_param('MSSID');
        $dlr   = $request->get_param('DLR');

        if (empty($mssid) || empty($dlr)) {
            return [];
        }

        $expired = (string) ($request->get_param('Expired') ?? '0') === '1';

        [$status, $permanent] = match ((string) $dlr) {
            'Delivered'   => ['delivered', false],
            'Sent'        => ['sent', false],
            'Buffered'    => ['queued', false],
            'Undelivered' => ['failed', $expired],
            'Error'       => ['failed', true],
            default       => ['unknown', false],
        };

        return [new StatusUpdate(
            providerId:   (string) $mssid,
            status:       $status,
            errorCode:    $status === 'failed' || $status === 'unknown' ? (string) $dlr : null,
            errorMessage: $status === 'failed' ? sprintf('Ness Solutions: %s', (string) $dlr) : null,
            permanent:    $permanent,
        )];
    }

    // --- Internal ---

    private function mapError(string $code): string
    {
        return match ($code) {
            'NoAPIKey'             => __('API Key is missing', 'wp-sms'),
            'InvalidAPIKey'        => __('Invalid API Key', 'wp-sms'),
            'NoCommand'            => __('Command is missing', 'wp-sms'),
            'InvalidCommand'       => __('Unknown API command', 'wp-sms'),
            'NoSender'             => __('Sender ID is missing', 'wp-sms'),
            'InvalidSender'        => __('Invalid Sender ID — must be 3-11 alphanumeric chars or numeric in +E.164 format', 'wp-sms'),
            'NoRecipients'         => __('Recipients are missing', 'wp-sms'),
            'InvalidRecipients'    => __('Invalid recipients', 'wp-sms'),
            'NoContent'            => __('Message content is missing', 'wp-sms'),
            'InvalidContent'       => __('Invalid message content', 'wp-sms'),
            'NotEnoughCredits',
            'NotEnoughQuota'       => __('Not enough credits', 'wp-sms'),
            'AccountSuspended'     => __('Account suspended', 'wp-sms'),
            'InternalError'        => __('Provider internal error — try again later', 'wp-sms'),
            default                => sprintf(__('Ness Solutions error: %s', 'wp-sms'), $code),
        };
    }
}
