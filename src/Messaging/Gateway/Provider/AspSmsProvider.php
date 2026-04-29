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

class AspSmsProvider extends AbstractProvider implements
    SupportsStatusCallback,
    SupportsInboundMessage
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = true;

    private const API_BASE = 'https://json.aspsms.com/';

    public function getId(): string
    {
        return 'aspsms';
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
                    'label'       => __('Userkey / Username', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Your ASPSMS Userkey from the customer area.', 'wp-sms'),
                ],
                'password' => [
                    'type'        => 'secret',
                    'label'       => __('Password', 'wp-sms'),
                    'required'    => true,
                    'description' => __('The ASPSMS password assigned to your Userkey.', 'wp-sms'),
                ],
                'affiliate_id' => [
                    'type'        => 'string',
                    'label'       => __('Affiliate ID', 'wp-sms'),
                    'required'    => false,
                    'description' => __('Optional affiliate identifier added to send requests.', 'wp-sms'),
                ],
                'callback_token' => [
                    'type'        => 'secret',
                    'label'       => __('Callback Token', 'wp-sms'),
                    'required'    => false,
                    'description' => __('Shared secret appended to webhook URLs. Required to enable delivery receipts and two-way SMS.', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'from_number' => [
                        'type'        => 'string',
                        'label'       => __('Originator', 'wp-sms'),
                        'required'    => true,
                        'description' => __('Sender ID — max 11 alphanumeric characters or a verified phone number.', 'wp-sms'),
                        'placeholder' => 'MyBrand',
                    ],
                    'flash_sms' => [
                        'type'    => 'boolean',
                        'label'   => __('Send as Flash SMS', 'wp-sms'),
                        'default' => false,
                    ],
                ],
            ],
        ];
    }

    public function getFeatures(): array
    {
        return array_merge(parent::getFeatures(), [
            'flash_sms'        => true,
            'delivery_receipt' => true,
            'incoming'         => true,
            'unicode'          => true,
            'test_connection'  => true,
        ]);
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        $username = $this->getSharedConfig('username');
        $password = $this->getSharedConfig('password');

        if (!$username || !$password) {
            return DeliveryResult::failed(__('ASPSMS credentials not configured', 'wp-sms'));
        }

        $originator = $this->getChannelConfig('sms', 'from_number');
        if (!$originator) {
            return DeliveryResult::failed(__('ASPSMS Originator not configured', 'wp-sms'));
        }

        $body = [
            'UserName'     => $username,
            'Password'     => $password,
            'Originator'   => $originator,
            'Recipients'   => [$message->getRecipient()],
            'MessageText'  => $message->getBody(),
            'ForceGSM7bit' => false,
        ];

        if ($this->getChannelConfig('sms', 'flash_sms')) {
            $body['FlashingSMS'] = true;
        }

        $affiliateId = $this->getSharedConfig('affiliate_id');
        if ($affiliateId) {
            $body['AffiliateId'] = $affiliateId;
        }

        $callbackToken = $this->getSharedConfig('callback_token');
        if ($callbackToken) {
            $base = $this->getStatusCallbackUrl();
            $body['URLDeliveryNotification']        = $this->buildCallbackUrl($base, $callbackToken, 'delivered');
            $body['URLNonDeliveryNotification']     = $this->buildCallbackUrl($base, $callbackToken, 'failed');
            $body['URLBufferedMessageNotification'] = $this->buildCallbackUrl($base, $callbackToken, 'buffered');
        }

        $result = $this->httpPost(self::API_BASE . 'SendTextSMS', [
            'headers' => [
                'Content-Type' => 'application/json; charset=UTF-8',
            ],
            'body' => wp_json_encode($body),
        ]);

        if ($result instanceof DeliveryResult) {
            return $result;
        }

        $data = json_decode($result['body'], true);

        if ($result['code'] < 200 || $result['code'] >= 300 || !is_array($data)) {
            return DeliveryResult::failed(sprintf('HTTP %d', $result['code']));
        }

        $statusCode = isset($data['StatusCode']) ? (string) $data['StatusCode'] : '';
        $statusInfo = $data['StatusInfo'] ?? '';

        if ($statusCode === '1' || $statusInfo === 'OK') {
            $providerId = $this->extractProviderId($data);
            return DeliveryResult::sent($providerId);
        }

        return DeliveryResult::failed(
            $this->describeStatusCode($statusCode, $statusInfo),
            meta: array_filter([
                'aspsms_status_code' => $statusCode ?: null,
                'aspsms_status_info' => $statusInfo ?: null,
            ]),
        );
    }

    public function getCredit(): ?string
    {
        $username = $this->getSharedConfig('username');
        $password = $this->getSharedConfig('password');

        if (!$username || !$password) {
            return null;
        }

        $result = $this->httpPost(self::API_BASE . 'CheckCredits', [
            'headers' => ['Content-Type' => 'application/json; charset=UTF-8'],
            'body'    => wp_json_encode(['UserName' => $username, 'Password' => $password]),
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
        $username = $this->getSharedConfig('username');
        $password = $this->getSharedConfig('password');

        if (!$username || !$password) {
            return TestConnectionResult::error(__('Userkey and Password are required', 'wp-sms'));
        }

        $result = $this->httpPost(self::API_BASE . 'CheckCredits', [
            'headers' => ['Content-Type' => 'application/json; charset=UTF-8'],
            'body'    => wp_json_encode(['UserName' => $username, 'Password' => $password]),
        ]);

        $data = $this->validateTestResponse($result, 'ASPSMS');
        if ($data instanceof TestConnectionResult) {
            return $data;
        }

        $statusCode = isset($data['StatusCode']) ? (string) $data['StatusCode'] : '';
        if ($statusCode === '3') {
            return TestConnectionResult::error(__('Invalid Userkey or Password', 'wp-sms'));
        }

        if ($statusCode !== '1' && ($data['StatusInfo'] ?? '') !== 'OK') {
            return TestConnectionResult::error(
                sprintf(__('ASPSMS rejected the request: %s', 'wp-sms'), $data['StatusInfo'] ?? "code {$statusCode}")
            );
        }

        $credits = $data['Credits'] ?? 'N/A';

        return TestConnectionResult::ok(
            sprintf(__('Connected — Credits: %s', 'wp-sms'), $credits),
            ['balance' => (string) $credits],
        );
    }

    // --- SupportsStatusCallback ---

    public function getStatusCallbackUrl(): string
    {
        return RestRoute::url('callbacks/' . $this->getId() . '/status');
    }

    public function validateStatusCallback(\WP_REST_Request $request): bool
    {
        return $this->verifyToken($request);
    }

    /** @return StatusUpdate[] */
    public function parseStatusCallback(\WP_REST_Request $request): array
    {
        $tx = $request->get_param('tx');
        $kind = $request->get_param('kind');

        if (empty($tx) || empty($kind)) {
            return [];
        }

        $status = match ($kind) {
            'delivered' => 'delivered',
            'buffered'  => 'queued',
            'failed'    => 'failed',
            default     => 'failed',
        };

        return [new StatusUpdate(
            providerId:   (string) $tx,
            status:       $status,
            errorMessage: $status === 'failed' ? __('ASPSMS reported non-delivery', 'wp-sms') : null,
        )];
    }

    // --- SupportsInboundMessage ---

    public function getInboundCallbackUrl(): string
    {
        return RestRoute::url('callbacks/' . $this->getId() . '/inbound');
    }

    public function validateInboundCallback(\WP_REST_Request $request): bool
    {
        return $this->verifyToken($request);
    }

    /** @return InboundMessage[] */
    public function parseInboundCallback(\WP_REST_Request $request): array
    {
        $from = $request->get_param('Originator');
        $to   = $request->get_param('Recipient');
        $body = $request->get_param('MessageData');

        if (empty($from) || empty($to) || $body === null) {
            return [];
        }

        return [new InboundMessage(
            from: (string) $from,
            to:   (string) $to,
            body: (string) $body,
            meta: array_filter([
                'date_received' => $request->get_param('DateReceived'),
            ]),
        )];
    }

    // --- Internal ---

    private function verifyToken(\WP_REST_Request $request): bool
    {
        $expected = (string) $this->getSharedConfig('callback_token', '');
        if ($expected === '') {
            return false;
        }
        $given = (string) ($request->get_param('token') ?? '');
        if ($given === '') {
            return false;
        }
        return hash_equals($expected, $given);
    }

    private function buildCallbackUrl(string $base, string $token, string $kind): string
    {
        // ASPSMS server-substitutes <TransactionReferenceNumber> in URLs we register
        // for delivery / non-delivery / buffered notifications.
        $sep = (str_contains($base, '?') ? '&' : '?');
        return $base
            . $sep . 'token=' . rawurlencode($token)
            . '&tx=<TransactionReferenceNumber>'
            . '&kind=' . $kind;
    }

    private function extractProviderId(array $data): ?string
    {
        $messageNumbers = $data['MessageNumbers'] ?? null;
        if (is_array($messageNumbers) && !empty($messageNumbers)) {
            $first = $messageNumbers[0];
            if (is_array($first)) {
                return isset($first['TransactionReferenceNumber'])
                    ? (string) $first['TransactionReferenceNumber']
                    : null;
            }
            return (string) $first;
        }

        return isset($data['MessageNumber']) ? (string) $data['MessageNumber'] : null;
    }

    private function describeStatusCode(string $code, string $info): string
    {
        $message = match ($code) {
            '3'  => __('Invalid Userkey or Password', 'wp-sms'),
            '5'  => __('Not enough credits', 'wp-sms'),
            '10' => __('Invalid Originator (max 11 alphanumeric characters)', 'wp-sms'),
            '20' => __('Missing recipient', 'wp-sms'),
            default => $info ?: sprintf(__('ASPSMS error (code %s)', 'wp-sms'), $code ?: '?'),
        };

        return $message;
    }
}
