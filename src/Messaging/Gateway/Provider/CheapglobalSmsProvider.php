<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\InboundMessage;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\StatusUpdate;
use WSms\Messaging\Contracts\SupportsInboundMessage;
use WSms\Messaging\Contracts\SupportsStatusCallback;
use WSms\Messaging\Contracts\TestConnectionResult;
use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Rest\RestRoute;

defined('ABSPATH') || exit;

/**
 * CheapglobalSMS — globally-routable bulk SMS gateway operated by
 * Tormuto Information Technologies (Nigeria), with a single REST endpoint
 * (https://cheapglobalsms.com/api_v1/) consumed via x-www-form-urlencoded.
 *
 * Auth is the user's sub-account name + password (or main-account email +
 * password). Webhook callbacks (DLR + inbound) are unsigned, so this provider
 * uses the shared-secret token-in-URL pattern: a configurable ?token=... is
 * appended to the registered DLR URL and the inbound URL the user pastes into
 * a CGSMS campaign config.
 */
class CheapglobalSmsProvider extends AbstractProvider implements
    SupportsStatusCallback,
    SupportsInboundMessage
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const ENDPOINT = 'https://cheapglobalsms.com/api_v1/';

    public function getId(): string
    {
        return 'cheapglobalsms';
    }

    public function getSupportedChannels(): array
    {
        return ['sms'];
    }

    public function getConfigSchema(): array
    {
        return [
            'shared' => [
                'sub_account' => [
                    'type'        => 'string',
                    'label'       => __('Sub-account / Email', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Sub-account name OR your CGSMS main-account email address.', 'wp-sms'),
                ],
                'sub_account_pass' => [
                    'type'        => 'secret',
                    'label'       => __('Sub-account Password', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Sub-account password OR your CGSMS main-account password.', 'wp-sms'),
                ],
                'callback_token' => [
                    'type'        => 'string',
                    'label'       => __('Callback Token', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Shared secret appended to the DLR/inbound URLs as ?token=… so the receiver can authenticate webhooks. CheapglobalSMS does not sign callbacks.', 'wp-sms'),
                ],
                'route' => [
                    'type'    => 'select',
                    'label'   => __('Route', 'wp-sms'),
                    'default' => '0',
                    'options' => [
                        ['value' => '0', 'label' => __('Optimal Standard', 'wp-sms')],
                        ['value' => '1', 'label' => __('Priority', 'wp-sms')],
                        ['value' => '2', 'label' => __('Best Pricing', 'wp-sms')],
                    ],
                    'description' => __('Routing strategy applied by CheapglobalSMS to outbound traffic.', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'sender_id' => [
                        'type'        => 'string',
                        'label'       => __('Sender ID', 'wp-sms'),
                        'required'    => false,
                        'description' => __('3–11 alphanumeric characters or 3–14 digits. Leave blank to use the account default.', 'wp-sms'),
                    ],
                    'flash' => [
                        'type'        => 'boolean',
                        'label'       => __('Send as Flash SMS', 'wp-sms'),
                        'default'     => false,
                        'description' => __('Send as Class 0 (flash) — displayed on the recipient screen without saving to the inbox. Carrier support varies.', 'wp-sms'),
                    ],
                    'unicode_mode' => [
                        'type'    => 'select',
                        'label'   => __('Unicode Mode', 'wp-sms'),
                        'default' => '2',
                        'options' => [
                            ['value' => '0', 'label' => __('GSM-7 (plain ASCII)', 'wp-sms')],
                            ['value' => '1', 'label' => __('Unicode (UCS-2)', 'wp-sms')],
                            ['value' => '2', 'label' => __('Auto-detect', 'wp-sms')],
                        ],
                    ],
                ],
            ],
        ];
    }

    public function getFeatures(): array
    {
        return array_merge(parent::getFeatures(), [
            'flash_sms'        => true,
            'unicode'          => true,
            'delivery_receipt' => true,
            'incoming'         => true,
            'test_connection'  => true,
        ]);
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        $subAccount = $this->getSharedConfig('sub_account');
        $subPass    = $this->getSharedConfig('sub_account_pass');
        if (!$subAccount || !$subPass) {
            return DeliveryResult::failed(__('CheapglobalSMS credentials not configured', 'wp-sms'));
        }

        $body = [
            'sub_account'      => (string) $subAccount,
            'sub_account_pass' => (string) $subPass,
            'action'           => 'send_sms',
            'recipients'       => ltrim($message->getRecipient(), '+'),
            'message'          => $message->getBody(),
            'unicode'          => (string) $this->getChannelConfig('sms', 'unicode_mode', '2'),
            'route'            => (string) $this->getSharedConfig('route', '0'),
        ];

        $senderId = $this->getChannelConfig('sms', 'sender_id');
        if ($senderId) {
            $body['sender_id'] = (string) $senderId;
        }

        if ($this->getChannelConfig('sms', 'flash', false)) {
            $body['type'] = '1';
        }

        $callbackUrl = $this->getStatusCallbackUrlWithToken();
        if ($callbackUrl !== null) {
            $body['callback_url'] = $callbackUrl;
        }

        $result = $this->httpPost(self::ENDPOINT, [
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Accept'       => 'application/json',
            ],
            'body' => http_build_query($body),
        ]);

        if ($result instanceof DeliveryResult) {
            return $result;
        }

        $data = json_decode($result['body'], true);

        if ($result['code'] === 401 || $result['code'] === 403) {
            return DeliveryResult::failed(__('Invalid CheapglobalSMS credentials', 'wp-sms'));
        }

        if (is_array($data) && isset($data['error'])) {
            return DeliveryResult::failed(
                (string) $data['error'],
                meta: array_filter(['cgsms_error_code' => isset($data['error_code']) ? (string) $data['error_code'] : null]),
            );
        }

        if ($result['code'] >= 200 && $result['code'] < 300 && is_array($data) && isset($data['batch_id'])) {
            // CGSMS returns a batch_id, not per-message ids — the per-message
            // sms_id arrives later via the DLR callback.
            return DeliveryResult::queued((string) $data['batch_id']);
        }

        return DeliveryResult::failed(
            sprintf(__('Unexpected response from CheapglobalSMS (HTTP %d)', 'wp-sms'), $result['code']),
        );
    }

    public function getCredit(): ?string
    {
        $subAccount = $this->getSharedConfig('sub_account');
        $subPass    = $this->getSharedConfig('sub_account_pass');
        if (!$subAccount || !$subPass) {
            return null;
        }

        $result = $this->httpPost(self::ENDPOINT, [
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Accept'       => 'application/json',
            ],
            'body' => http_build_query([
                'sub_account'      => (string) $subAccount,
                'sub_account_pass' => (string) $subPass,
                'action'           => 'account_info',
                'only_balance'     => '1',
            ]),
        ]);

        if ($result instanceof DeliveryResult) {
            return null;
        }

        $data = json_decode($result['body'], true);
        if (!is_array($data) || !isset($data['balance'])) {
            return null;
        }

        return sprintf('%s credits', (string) $data['balance']);
    }

    public function testConnection(): TestConnectionResult
    {
        $subAccount = $this->getSharedConfig('sub_account');
        $subPass    = $this->getSharedConfig('sub_account_pass');
        if (!$subAccount || !$subPass) {
            return TestConnectionResult::error(__('Sub-account credentials are required', 'wp-sms'));
        }

        $result = $this->httpPost(self::ENDPOINT, [
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Accept'       => 'application/json',
            ],
            'body' => http_build_query([
                'sub_account'      => (string) $subAccount,
                'sub_account_pass' => (string) $subPass,
                'action'           => 'account_info',
            ]),
        ]);

        if (!$result instanceof DeliveryResult) {
            if ($result['code'] === 401 || $result['code'] === 403) {
                return TestConnectionResult::error(__('Invalid sub-account credentials', 'wp-sms'));
            }
        }

        $data = $this->validateTestResponse($result, 'CheapglobalSMS');
        if ($data instanceof TestConnectionResult) {
            return $data;
        }

        // Errors after a 200 still appear as {error: ...}
        if (isset($data['error'])) {
            return TestConnectionResult::error((string) $data['error']);
        }

        $balance = $data['balance'] ?? null;
        $message = $balance !== null
            ? sprintf(__('Connected — Balance: %s credits', 'wp-sms'), (string) $balance)
            : __('Connected', 'wp-sms');

        return TestConnectionResult::ok($message, ['balance' => $balance]);
    }

    // --- SupportsStatusCallback ---

    public function getStatusCallbackUrl(): string
    {
        return RestRoute::url('callbacks/' . $this->getId() . '/status');
    }

    public function validateStatusCallback(\WP_REST_Request $request): bool
    {
        return $this->validateCallbackToken($request);
    }

    /** @return StatusUpdate[] */
    public function parseStatusCallback(\WP_REST_Request $request): array
    {
        $raw = $request->get_param('result');
        if (!is_string($raw) || $raw === '') {
            return [];
        }

        $rows = json_decode($raw, true);
        if (!is_array($rows)) {
            return [];
        }

        $updates = [];
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['sms_id'])) {
                continue;
            }

            $statusCode = isset($row['status']) ? (int) $row['status'] : 0;
            [$normalized, $permanent] = match ($statusCode) {
                2       => ['delivered', false],
                1       => ['sent',      false],
                0       => ['queued',    false],
                -2, -4  => ['failed',    true],
                -1, -3  => ['failed',    false],
                default => ['failed',    false],
            };

            $statusMsg = isset($row['status_msg']) ? (string) $row['status_msg'] : null;

            $updates[] = new StatusUpdate(
                providerId:   (string) $row['sms_id'],
                status:       $normalized,
                errorCode:    (string) $statusCode,
                errorMessage: $normalized === 'failed' ? ($statusMsg ?: sprintf('CGSMS status %d', $statusCode)) : null,
                permanent:    $permanent,
            );
        }

        return $updates;
    }

    // --- SupportsInboundMessage ---

    public function getInboundCallbackUrl(): string
    {
        return RestRoute::url('callbacks/' . $this->getId() . '/inbound');
    }

    public function validateInboundCallback(\WP_REST_Request $request): bool
    {
        return $this->validateCallbackToken($request);
    }

    /** @return InboundMessage[] */
    public function parseInboundCallback(\WP_REST_Request $request): array
    {
        $sender = (string) ($request->get_param('sender') ?? '');
        if ($sender === '') {
            return [];
        }

        $clean = $request->get_param('clean_message');
        $body  = is_string($clean) && $clean !== ''
            ? $clean
            : (string) ($request->get_param('message') ?? '');

        return [new InboundMessage(
            from: $sender,
            to:   (string) ($request->get_param('recipient') ?? ''),
            body: $body,
            meta: array_filter([
                'campaign_id' => $request->get_param('campaign_id'),
                'keyword'     => $request->get_param('keyword'),
            ]),
        )];
    }

    // --- Internal ---

    private function getStatusCallbackUrlWithToken(): ?string
    {
        $token = $this->getSharedConfig('callback_token');
        if (!$token) {
            return null;
        }
        $url = $this->getStatusCallbackUrl();
        $separator = str_contains($url, '?') ? '&' : '?';
        return $url . $separator . 'token=' . rawurlencode((string) $token);
    }

    private function validateCallbackToken(\WP_REST_Request $request): bool
    {
        $expected = $this->getSharedConfig('callback_token');
        if (!is_string($expected) || $expected === '') {
            return false;
        }
        $supplied = (string) ($request->get_param('token') ?? '');
        if ($supplied === '') {
            return false;
        }
        return hash_equals($expected, $supplied);
    }
}
