<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Gateway\AbstractProvider;

defined('ABSPATH') || exit;

/**
 * SMSpoint — German EU-focused SMS gateway (smspoint.de).
 *
 * Auth: X-Auth-Token header carrying a UUID generated/regenerated in the
 * SMSpoint panel.
 * Send: POST https://app.smspoint.de/public/api/v1/sms/send
 *       body { senderName, body, phone } — phone without leading "+",
 *       constrained to the API regex ^[+|0]{0,2}[0-9]{9,11}$ (max 11 digits).
 *       Live API responses are RFC 7807 problem-details: HTTP 2xx on
 *       success (legacy README also shows {"success": true}); HTTP 400
 *       CommonProblem with detail=MISSING_API_TOKEN / UNKNOWN_API_TOKEN
 *       on auth failures; HTTP 422 ViolationProblem with violations[]
 *       on field-validation failures. No message ID is returned on
 *       success.
 *
 * Capabilities the provider does NOT publicly document and which are therefore
 * intentionally absent: balance lookup, test-connection endpoint, DLR webhook
 * fields/signing, inbound MO, opt-out error codes, list-senders, templates,
 * and any non-SMS channel. The AbstractProvider defaults (getCredit() => null,
 * testConnection() => "not supported") are correct for this provider.
 */
class SmspointProvider extends AbstractProvider
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const ENDPOINT = 'https://app.smspoint.de/public/api/v1/sms/send';

    public function getId(): string
    {
        return 'smspoint';
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
                    'label'       => __('API Token', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Generated or regenerated in the SMSpoint panel — sent as the X-Auth-Token header.', 'wp-sms'),
                    'placeholder' => '00000000-0000-0000-0000-000000000000',
                ],
            ],
            'channels' => [
                'sms' => [
                    'from' => [
                        'type'        => 'string',
                        'label'       => __('Sender Name', 'wp-sms'),
                        'required'    => true,
                        'description' => __('Up to 13 ASCII characters — alphanumeric or numeric.', 'wp-sms'),
                        'placeholder' => 'Company ABC',
                    ],
                ],
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        $apiToken = $this->getSharedConfig('api_token');
        $from = $this->getChannelConfig('sms', 'from');

        if (!$apiToken || !$from) {
            return DeliveryResult::failed(__('SMSpoint credentials not configured', 'wp-sms'));
        }

        $body = [
            'senderName' => $from,
            'body'       => $message->getBody(),
            'phone'      => ltrim($message->getRecipient(), '+'),
        ];

        $result = $this->httpPost(self::ENDPOINT, [
            'headers' => [
                'X-Auth-Token' => $apiToken,
                'Content-Type' => 'application/json;charset=UTF-8',
                'Accept'       => 'application/json',
            ],
            'body'    => wp_json_encode($body),
        ]);

        if ($result instanceof DeliveryResult) {
            return $result;
        }

        $data = json_decode($result['body'], true);
        $code = $result['code'];

        if ($code >= 200 && $code < 300 && (!is_array($data) || ($data['success'] ?? true) !== false)) {
            return DeliveryResult::sent();
        }

        return DeliveryResult::failed($this->formatError($data, $code));
    }

    /**
     * Format an API error from either the legacy {success,errorMessage} shape
     * or the live RFC 7807 problem-details shape (ViolationProblem for 422
     * field validation, CommonProblem for 400 with a `detail` slug like
     * UNKNOWN_API_TOKEN).
     */
    private function formatError(mixed $data, int $code): string
    {
        if (!is_array($data)) {
            return sprintf(__('SMSpoint send failed (HTTP %d)', 'wp-sms'), $code);
        }

        if (!empty($data['violations']) && is_array($data['violations'])) {
            $parts = [];
            foreach ($data['violations'] as $v) {
                $field = $v['field'] ?? '';
                $msg = $v['message'] ?? '';
                $parts[] = $field !== '' ? sprintf('%s: %s', $field, $msg) : $msg;
            }
            return sprintf(__('SMSpoint validation error — %s', 'wp-sms'), implode('; ', array_filter($parts)));
        }

        if (!empty($data['detail'])) {
            return sprintf(__('SMSpoint error — %s', 'wp-sms'), $data['detail']);
        }

        if (!empty($data['errorMessage'])) {
            return (string) $data['errorMessage'];
        }

        return sprintf(__('SMSpoint send failed (HTTP %d)', 'wp-sms'), $code);
    }
}
