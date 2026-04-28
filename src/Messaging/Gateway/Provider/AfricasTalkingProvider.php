<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\InboundMessage;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\StatusUpdate;
use WSms\Messaging\Contracts\SupportsInboundMessage;
use WSms\Messaging\Contracts\SupportsOptOutDetection;
use WSms\Messaging\Contracts\SupportsStatusCallback;
use WSms\Messaging\Contracts\TestConnectionResult;
use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Rest\RestRoute;

defined('ABSPATH') || exit;

class AfricasTalkingProvider extends AbstractProvider implements
    SupportsStatusCallback,
    SupportsInboundMessage,
    SupportsOptOutDetection
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const LIVE_BASE        = 'https://api.africastalking.com/version1';
    private const SANDBOX_BASE     = 'https://api.sandbox.africastalking.com/version1';
    private const SANDBOX_USERNAME = 'sandbox';

    public function getId(): string
    {
        return 'africastalking';
    }

    public function getSupportedChannels(): array
    {
        return ['sms'];
    }

    public function getConfigSchema(): array
    {
        return [
            'shared' => [
                'username' => [
                    'type'        => 'string',
                    'label'       => __('Username', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Your Africa\'s Talking username from account.africastalking.com. Use "sandbox" for the free test environment.', 'wp-sms'),
                ],
                'api_key' => [
                    'type'        => 'secret',
                    'label'       => __('API Key', 'wp-sms'),
                    'required'    => true,
                    'description' => __('From your Africa\'s Talking dashboard under Settings > API Key.', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'from' => [
                        'type'        => 'string',
                        'label'       => __('Sender ID', 'wp-sms'),
                        'required'    => false,
                        'description' => __('Optional alphanumeric sender ID or shortcode pre-approved on your account. Leave blank for sandbox.', 'wp-sms'),
                        'placeholder' => 'AFTKNG',
                    ],
                ],
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        $apiKey = $this->getSharedConfig('api_key');
        $username = $this->getSharedConfig('username');

        if (!$apiKey || !$username) {
            return DeliveryResult::failed(__('Africa\'s Talking credentials not configured', 'wp-sms'));
        }

        $body = [
            'username' => $username,
            'to'       => $message->getRecipient(),
            'message'  => $message->getBody(),
        ];

        $from = $this->getChannelConfig('sms', 'from');
        if (!empty($from)) {
            $body['from'] = $from;
        }

        $result = $this->httpPost($this->baseUrl() . '/messaging', [
            'headers' => $this->authHeaders(),
            'body'    => $body,
        ]);

        if ($result instanceof DeliveryResult) {
            return $result;
        }

        $data = json_decode($result['body'], true);
        $recipient = $data['SMSMessageData']['Recipients'][0] ?? null;

        if ($result['code'] === 401) {
            return DeliveryResult::failed(__('Invalid Africa\'s Talking credentials', 'wp-sms'));
        }

        if ($recipient) {
            $code = (int) ($recipient['statusCode'] ?? 0);
            $status = (string) ($recipient['status'] ?? '');

            if ($this->isAcceptedStatus($code, $status)) {
                return DeliveryResult::sent(
                    providerId: $recipient['messageId'] ?? null,
                    cost: $this->parseCost($recipient['cost'] ?? null),
                );
            }

            return DeliveryResult::failed(
                $status !== '' ? $status : __('Africa\'s Talking send failed', 'wp-sms'),
                meta: array_filter(['at_status_code' => $code ?: null]),
            );
        }

        $error = $data['SMSMessageData']['Message']
            ?? $data['errorMessage']
            ?? sprintf('HTTP %d', $result['code']);

        return DeliveryResult::failed($error);
    }

    public function getCredit(): ?string
    {
        $username = $this->getSharedConfig('username');
        $apiKey = $this->getSharedConfig('api_key');

        if (!$apiKey || !$username) {
            return null;
        }

        $result = $this->httpGet($this->userUrl($username), [
            'headers' => $this->authHeaders(),
        ]);

        if ($result instanceof DeliveryResult) {
            return null;
        }

        $data = json_decode($result['body'], true);
        return $data['UserData']['balance'] ?? null;
    }

    public function testConnection(): TestConnectionResult
    {
        $username = $this->getSharedConfig('username');
        $apiKey = $this->getSharedConfig('api_key');

        if (!$apiKey || !$username) {
            return TestConnectionResult::error(__('Username and API Key are required', 'wp-sms'));
        }

        $result = $this->httpGet($this->userUrl($username), [
            'headers' => $this->authHeaders(),
        ]);

        if (!$result instanceof DeliveryResult) {
            if ($result['code'] === 401 || $result['code'] === 403) {
                return TestConnectionResult::error(__('Invalid Username or API Key', 'wp-sms'));
            }
        }

        $data = $this->validateTestResponse($result, "Africa's Talking");
        if ($data instanceof TestConnectionResult) {
            return $data;
        }

        $balance = $data['UserData']['balance'] ?? 'N/A';

        return TestConnectionResult::ok(
            sprintf(__('Connected — Balance: %s', 'wp-sms'), $balance),
            ['balance' => $balance],
        );
    }

    // --- SupportsStatusCallback ---

    public function getStatusCallbackUrl(): string
    {
        return RestRoute::url(
            'callbacks/' . $this->getId() . '/status',
            ['token' => $this->callbackToken()],
        );
    }

    public function validateStatusCallback(\WP_REST_Request $request): bool
    {
        if (!$this->getSharedConfig('api_key')) {
            return false;
        }
        return hash_equals($this->callbackToken(), (string) ($request->get_param('token') ?? ''));
    }

    /** @return StatusUpdate[] */
    public function parseStatusCallback(\WP_REST_Request $request): array
    {
        $id = $request->get_param('id');
        $rawStatus = $request->get_param('status');

        if (empty($id) || empty($rawStatus)) {
            return [];
        }

        $normalized = match ($rawStatus) {
            'Sent', 'Submitted'              => 'sent',
            'Buffered', 'Queued'             => 'queued',
            'Success', 'Delivered'           => 'delivered',
            'Rejected', 'Failed', 'Expired'  => 'failed',
            default                          => $rawStatus,
        };

        $failureReason = $request->get_param('failureReason');

        return [new StatusUpdate(
            providerId:   (string) $id,
            status:       $normalized,
            errorCode:    $failureReason,
            errorMessage: $normalized === 'failed'
                ? sprintf('Africa\'s Talking DLR: %s%s', $rawStatus, $failureReason ? " ({$failureReason})" : '')
                : null,
            permanent:    in_array($rawStatus, ['Rejected', 'Expired'], true),
        )];
    }

    // --- SupportsInboundMessage ---

    public function getInboundCallbackUrl(): string
    {
        return RestRoute::url(
            'callbacks/' . $this->getId() . '/inbound',
            ['token' => $this->callbackToken()],
        );
    }

    public function validateInboundCallback(\WP_REST_Request $request): bool
    {
        if (!$this->getSharedConfig('api_key')) {
            return false;
        }
        return hash_equals($this->callbackToken(), (string) ($request->get_param('token') ?? ''));
    }

    /** @return InboundMessage[] */
    public function parseInboundCallback(\WP_REST_Request $request): array
    {
        $from = (string) ($request->get_param('from') ?? '');
        if ($from === '') {
            return [];
        }

        return [new InboundMessage(
            from:       $from,
            to:         (string) ($request->get_param('to') ?? ''),
            body:       (string) ($request->get_param('text') ?? ''),
            providerId: $request->get_param('id'),
            meta:       array_filter([
                'date'         => $request->get_param('date'),
                'link_id'      => $request->get_param('linkId'),
                'network_code' => $request->get_param('networkCode'),
            ]),
        )];
    }

    // --- SupportsOptOutDetection ---

    public function isOptOutError(DeliveryResult $result): bool
    {
        $code = (int) ($result->meta['at_status_code'] ?? 0);
        // 406 = UserInBlackList, 409 = DoNotDisturbRejection
        return $code === 406 || $code === 409;
    }

    // --- Internal ---

    private function isAcceptedStatus(int $code, string $status): bool
    {
        // Per AT docs: 100 Processed, 101 Sent, 102 Queued — anything else is a failure mode.
        if (in_array($code, [100, 101, 102], true)) {
            return true;
        }
        return in_array($status, ['Success', 'Sent', 'Queued', 'Submitted'], true);
    }

    private function parseCost(?string $cost): ?float
    {
        if (empty($cost)) {
            return null;
        }
        if (preg_match('/[\d.]+/', $cost, $m)) {
            return (float) $m[0];
        }
        return null;
    }

    private function baseUrl(): string
    {
        return $this->getSharedConfig('username') === self::SANDBOX_USERNAME
            ? self::SANDBOX_BASE
            : self::LIVE_BASE;
    }

    private function userUrl(string $username): string
    {
        return $this->baseUrl() . '/user?username=' . rawurlencode($username);
    }

    private function authHeaders(): array
    {
        return [
            'apiKey' => (string) $this->getSharedConfig('api_key'),
            'Accept' => 'application/json',
        ];
    }

    private function callbackToken(): string
    {
        return hash_hmac('sha256', 'africastalking-callback', (string) $this->getSharedConfig('api_key'));
    }
}
