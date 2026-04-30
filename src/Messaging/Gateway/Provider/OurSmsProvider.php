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

class OurSmsProvider extends AbstractProvider implements
    SupportsStatusCallback,
    SupportsDynamicOptions
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const API_BASE = 'https://api.oursms.com';

    // TODO(verify): OurSMS has no Verify-as-a-Service endpoint in the documented
    // OpenAPI spec. The legacy Laravel SDK targets a different host
    // (oursms.app/api/v1/SMS/Add/SendOtpSms) that is not part of the canonical
    // spec; OTPs flow through plain /msgs/sms with rendered text. Route through
    // SupportsVerify when WSMS lands the interface.

    public function getId(): string
    {
        return 'oursms';
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
                    'description' => __('Bearer token from your OurSMS dashboard under API Keys.', 'wp-sms'),
                    'placeholder' => 'Pasted Bearer token',
                ],
                'callback_token' => [
                    'type'        => 'secret',
                    'label'       => __('Webhook Token', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Random string appended to the DLR webhook URL as ?token= for verification. OurSMS does not sign webhooks; without this token the plugin rejects status posts.', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'sender_id' => [
                        'type'        => 'string',
                        'label'       => __('Sender Name', 'wp-sms'),
                        'required'    => true,
                        'dynamic'     => true,
                        'description' => __('CITC-approved sender ID registered with OurSMS. Only approved senders appear in the dropdown.', 'wp-sms'),
                        'placeholder' => 'OurSms',
                    ],
                ],
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        $apiKey = $this->getSharedConfig('api_key');
        if (!$apiKey) {
            return DeliveryResult::failed(__('OurSMS API key not configured', 'wp-sms'));
        }

        $sender = $this->getChannelConfig('sms', 'sender_id');
        if (!$sender) {
            return DeliveryResult::failed(__('OurSMS sender ID not configured', 'wp-sms'));
        }

        $body = [
            'src'      => (string) $sender,
            'dests'    => [$message->getRecipient()],
            'body'     => $message->getBody(),
            'msgClass' => 'transactional',
            'dlr'      => (bool) $this->getSharedConfig('callback_token'),
            'prevDups' => 0,
        ];

        $result = $this->httpPost(self::API_BASE . '/msgs/sms', [
            'headers' => array_merge($this->authHeaders($apiKey), [
                'Content-Type' => 'application/json',
            ]),
            'body'    => wp_json_encode($body),
        ]);

        if ($result instanceof DeliveryResult) {
            return $result;
        }

        if ($result['code'] === 401 || $result['code'] === 403) {
            return DeliveryResult::failed(__('Invalid OurSMS API key', 'wp-sms'));
        }

        $data = json_decode($result['body'], true);

        if ($result['code'] < 200 || $result['code'] >= 300) {
            return DeliveryResult::failed($this->extractErrorMessage($data, $result['code']));
        }

        // OpenAPI spec doesn't pin the success body shape — try the common keys
        // and the status-array shape used by /msgs/status. Verify against a live
        // send when flipping TESTED to true.
        $providerId = null;
        if (is_array($data)) {
            foreach (['msgId', 'id', 'messageId'] as $key) {
                if (!empty($data[$key])) {
                    $providerId = (string) $data[$key];
                    break;
                }
            }
            if ($providerId === null && !empty($data['statuses'][0]['msgId'])) {
                $providerId = (string) $data['statuses'][0]['msgId'];
            }
        }

        return DeliveryResult::sent(providerId: $providerId);
    }

    public function getCredit(): ?string
    {
        $apiKey = $this->getSharedConfig('api_key');
        if (!$apiKey) {
            return null;
        }

        $result = $this->httpGet(self::API_BASE . '/billing/credits', [
            'headers' => $this->authHeaders($apiKey),
        ]);

        if ($result instanceof DeliveryResult) {
            return null;
        }

        $data = json_decode($result['body'], true);
        if (!is_array($data) || !isset($data['credits'])) {
            return null;
        }

        return (string) $data['credits'];
    }

    public function testConnection(): TestConnectionResult
    {
        $apiKey = $this->getSharedConfig('api_key');
        if (!$apiKey) {
            return TestConnectionResult::error(__('API Key is required', 'wp-sms'));
        }

        $result = $this->httpGet(self::API_BASE . '/users/me', [
            'headers' => $this->authHeaders($apiKey),
        ]);

        if (!$result instanceof DeliveryResult) {
            if ($result['code'] === 401 || $result['code'] === 403) {
                return TestConnectionResult::error(__('Invalid OurSMS API key', 'wp-sms'));
            }
        }

        $data = $this->validateTestResponse($result, 'OurSMS');
        if ($data instanceof TestConnectionResult) {
            return $data;
        }

        $username = (string) ($data['username'] ?? '');
        $email = (string) ($data['email'] ?? '');

        return TestConnectionResult::ok(
            $username !== ''
                ? sprintf(__('Connected as %s', 'wp-sms'), $username)
                : __('Connection successful', 'wp-sms'),
            array_filter(['username' => $username, 'email' => $email]),
        );
    }

    // --- SupportsStatusCallback ---

    public function getStatusCallbackUrl(): string
    {
        $token = $this->getSharedConfig('callback_token');
        $args = $token ? ['token' => $token] : [];
        return RestRoute::url('callbacks/' . $this->getId() . '/status', $args);
    }

    public function validateStatusCallback(\WP_REST_Request $request): bool
    {
        $expected = (string) $this->getSharedConfig('callback_token', '');
        if ($expected === '') {
            // OurSMS does not sign webhooks. Without a token configured the
            // endpoint is unauthenticated; refuse to process rather than trust
            // arbitrary inbound posts.
            return false;
        }

        $provided = (string) ($request->get_param('token') ?? '');
        if ($provided === '') {
            return false;
        }

        return hash_equals($expected, $provided);
    }

    /** @return StatusUpdate[] */
    public function parseStatusCallback(\WP_REST_Request $request): array
    {
        $payload = $request->get_json_params();
        $statuses = $payload['statuses'] ?? [];
        if (!is_array($statuses)) {
            return [];
        }

        $updates = [];
        foreach ($statuses as $row) {
            if (!is_array($row)) {
                continue;
            }
            $providerId = (string) ($row['msgId'] ?? '');
            $rawStatus = strtolower((string) ($row['status'] ?? ''));
            if ($providerId === '' || $rawStatus === '') {
                continue;
            }

            $status = match ($rawStatus) {
                'delivered'   => 'delivered',
                'undelivered',
                'failed'      => 'failed',
                'sent'        => 'sent',
                'deleted'     => 'failed',
                default       => $rawStatus,
            };

            $updates[] = new StatusUpdate(
                providerId:   $providerId,
                status:       $status,
                errorMessage: $status === 'failed' ? sprintf('OurSMS: %s', $rawStatus) : null,
                permanent:    $status === 'failed',
            );
        }

        return $updates;
    }

    // --- SupportsDynamicOptions ---

    public function getConfigOptions(string $fieldKey, string $section, array $config, array $context = []): array
    {
        if ($fieldKey !== 'sender_id' || $section !== 'sms') {
            return [];
        }

        return $this->withConfig($config, function () {
            $apiKey = $this->getSharedConfig('api_key');
            if (!$apiKey) {
                return [];
            }

            try {
                $data = $this->fetchJsonOrFail(
                    add_query_arg(['count' => 200], self::API_BASE . '/addresses/srcs'),
                    ['headers' => $this->authHeaders($apiKey)],
                );
            } catch (\RuntimeException) {
                return [];
            }

            $rows = $data['srcs'] ?? $data['addresses'] ?? $data['data'] ?? [];
            if (!is_array($rows)) {
                return [];
            }

            $options = [];
            foreach ($rows as $row) {
                $value = is_array($row)
                    ? (string) ($row['src'] ?? $row['address'] ?? $row['name'] ?? '')
                    : (string) $row;
                if ($value === '') {
                    continue;
                }
                $options[] = ['value' => $value, 'label' => $value];
            }

            return $options;
        });
    }

    // --- Internal ---

    private function authHeaders(string $apiKey): array
    {
        return [
            'Authorization' => 'Bearer ' . $apiKey,
            'Accept'        => 'application/json',
        ];
    }

    private function extractErrorMessage(mixed $data, int $statusCode): string
    {
        if (is_array($data)) {
            if (!empty($data['message'])) {
                return (string) $data['message'];
            }
            if (!empty($data['errorCode'])) {
                return sprintf('OurSMS error %s', (string) $data['errorCode']);
            }
            if (!empty($data['error'])) {
                return (string) $data['error'];
            }
        }
        return sprintf('HTTP %d', $statusCode);
    }
}
