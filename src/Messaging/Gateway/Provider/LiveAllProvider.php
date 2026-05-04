<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\TestConnectionResult;
use WSms\Messaging\Gateway\AbstractProvider;

defined('ABSPATH') || exit;

/**
 * LiveAll — Greek SMS gateway with REST API for transactional and bulk
 * SMS delivery, focused on Greece and Cyprus.
 *
 * API contract verified against docs.liveall.eu/en/v20/:
 *   Send:    POST https://sms.liveall.eu/apiext/Sendout/SendJSMS
 *   Balance: POST https://sms.liveall.eu/apiext/Sendout/GetAccountBalance
 *   Auth:    apitoken field carried inside the JSON body (no Authorization header).
 *   Send body: { apitoken, senderid, messages: [{destination, message}] }
 *   Success:   { success: true, data: [{smsid, destination}] }
 *   Failure:   { success: false, OperationErrors: [{errorCode, errorMessage}] }
 *
 * TODO(viber): provider documents POST /Sendout/SendIM with im_type=IM_SMSFB
 * SMS fallback; defer until cross-channel scope.
 */
class LiveAllProvider extends AbstractProvider
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const SEND_ENDPOINT    = 'https://sms.liveall.eu/apiext/Sendout/SendJSMS';
    private const BALANCE_ENDPOINT = 'https://sms.liveall.eu/apiext/Sendout/GetAccountBalance';

    public function getId(): string
    {
        return 'liveall';
    }

    public function getSupportedChannels(): array
    {
        return ['sms'];
    }

    public function getConfigSchema(): array
    {
        return [
            'shared' => [
                'api_token' => [
                    'type'        => 'secret',
                    'required'    => true,
                    'label'       => __('API Token', 'wp-sms'),
                    'description' => __('Find your API Token in the LiveAll user dashboard at liveall.eu/user.', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'from' => [
                        'type'        => 'string',
                        'required'    => true,
                        'label'       => __('Sender ID', 'wp-sms'),
                        'placeholder' => 'MyBrand',
                        'description' => __('Pre-registered alphanumeric sender ID (max 11 characters).', 'wp-sms'),
                    ],
                ],
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        $apiToken = (string) $this->getSharedConfig('api_token', '');
        $from     = (string) $this->getChannelConfig('sms', 'from', '');

        if ($apiToken === '' || $from === '') {
            return DeliveryResult::failed(__('LiveAll API Token and Sender ID are required.', 'wp-sms'));
        }

        $body = [
            'apitoken' => $apiToken,
            'senderid' => $from,
            'messages' => [[
                'destination' => ltrim($message->getRecipient(), '+'),
                'message'     => $message->getBody(),
            ]],
        ];

        $result = $this->httpPost(self::SEND_ENDPOINT, [
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ],
            'body'    => wp_json_encode($body),
        ]);

        if ($result instanceof DeliveryResult) {
            return $result;
        }

        $code = (int) $result['code'];
        if ($code < 200 || $code >= 300) {
            return DeliveryResult::failed(
                sprintf(__('LiveAll HTTP error (%d).', 'wp-sms'), $code),
            );
        }

        $data = json_decode($result['body'], true);
        if (!is_array($data)) {
            return DeliveryResult::failed(__('LiveAll returned an unexpected response.', 'wp-sms'));
        }

        if (($data['success'] ?? null) !== true) {
            $error = $data['OperationErrors'][0]['errorMessage']
                ?? __('LiveAll rejected the request.', 'wp-sms');
            return DeliveryResult::failed((string) $error);
        }

        $smsId = $data['data'][0]['smsid'] ?? null;
        return DeliveryResult::sent($smsId !== null ? (string) $smsId : null);
    }

    public function getCredit(): ?string
    {
        $apiToken = (string) $this->getSharedConfig('api_token', '');
        if ($apiToken === '') {
            return null;
        }

        $result = $this->httpPost(self::BALANCE_ENDPOINT, [
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ],
            'body'    => wp_json_encode(['apitoken' => $apiToken]),
        ]);

        if ($result instanceof DeliveryResult) {
            return null;
        }

        $code = (int) $result['code'];
        if ($code < 200 || $code >= 300) {
            return null;
        }

        $data = json_decode($result['body'], true);
        if (!is_array($data) || ($data['success'] ?? null) !== true) {
            return null;
        }

        $balance = $this->extractBalance($data);
        if ($balance === null) {
            return null;
        }

        return sprintf('€%.2f', $balance);
    }

    public function testConnection(): TestConnectionResult
    {
        $apiToken = (string) $this->getSharedConfig('api_token', '');
        if ($apiToken === '') {
            return TestConnectionResult::error(__('API Token is required', 'wp-sms'));
        }

        $result = $this->httpPost(self::BALANCE_ENDPOINT, [
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ],
            'body'    => wp_json_encode(['apitoken' => $apiToken]),
        ]);

        $data = $this->validateTestResponse($result, 'LiveAll');
        if ($data instanceof TestConnectionResult) {
            return $data;
        }

        if (($data['success'] ?? null) !== true) {
            $error = $data['OperationErrors'][0]['errorMessage']
                ?? __('LiveAll rejected the credentials.', 'wp-sms');
            return TestConnectionResult::error((string) $error);
        }

        $balance  = $this->extractBalance($data);
        $balanceStr = $balance !== null ? sprintf('€%.2f', $balance) : __('N/A', 'wp-sms');

        return TestConnectionResult::ok(
            sprintf(__('Connected to LiveAll. Balance: %s', 'wp-sms'), $balanceStr),
            ['balance' => $balanceStr],
        );
    }

    private function extractBalance(array $data): ?float
    {
        foreach (['balance', 'Balance', 'euros', 'amount'] as $key) {
            if (isset($data[$key]) && is_numeric($data[$key])) {
                return (float) $data[$key];
            }
            if (isset($data['data'][$key]) && is_numeric($data['data'][$key])) {
                return (float) $data['data'][$key];
            }
        }
        return null;
    }
}
