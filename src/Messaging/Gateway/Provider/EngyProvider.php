<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Gateway\AbstractProvider;

defined('ABSPATH') || exit;

/**
 * eNgY Solutions — German SMS gateway with REST API.
 *
 * API contract verified against the canonical Swagger 2.0 spec at
 * https://api.engy.solutions/openapi.json:
 *   Send: POST https://api.engy.solutions/outbound/sms
 *   Auth: Authorization: <api-key>  (apiKey-style header, no Bearer prefix
 *         per OpenAPI securityDefinitions of type apiKey, in: header,
 *         name: Authorization)
 *   Body (JSON): { From, To, Text, Encoding?, Flash?, RefId?, Udh?,
 *                  ReceiveDeliveryStatus? }
 *   Response: HTTP 200 + { statusCode: 200, messageIds: ["..."] }
 *
 * Out of scope:
 * - getCredit: provider exposes no balance endpoint.
 * - testConnection: provider exposes no whoami / ping endpoint;
 *   AbstractProvider's default "not supported" stub is left in place.
 * - DLR / SupportsStatusCallback: POST /inbound/dlr is declared but the
 *   InboundDeliveryReportRequest schema is empty in the spec — defer.
 * - MO / SupportsInboundMessage: same — InboundMobileOriginatedRequest
 *   schema empty in the spec — defer.
 * - TODO(verify): /2fa/initiate + /2fa/validate is a Verify-as-a-Service
 *   endpoint; defer until SupportsVerify lands.
 * - WhatsApp / RCS / Voice: marketing copy claims these; NOT in the
 *   public OpenAPI surface as of 2026-05-04 — not implemented.
 *
 * Deviation from v7 legacy class (includes/gateways/class-wpsms-gateway-engy.php):
 * - v7 sent apiKey in the request body (lowercase). OpenAPI requires
 *   Authorization header. Header chosen.
 * - v7 used lowercase field names (from/to/text). OpenAPI uses
 *   Capitalized fields. Capitalized chosen.
 * - v7 hardcoded a 160-char message cap (test-mode artifact). API
 *   auto-splits long messages into multiple messageIds. Cap removed.
 * - v7 used http:// (cleartext). OpenAPI host is https://. HTTPS chosen.
 */
class EngyProvider extends AbstractProvider
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const SEND_ENDPOINT = 'https://api.engy.solutions/outbound/sms';

    public function getId(): string
    {
        return 'engy';
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
                    'required'    => true,
                    'label'       => __('API Key', 'wp-sms'),
                    'description' => __('eNgY API key from your account control panel. Sent as the Authorization header on every request.', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'from' => [
                        'type'        => 'string',
                        'required'    => true,
                        'label'       => __('Sender ID', 'wp-sms'),
                        'placeholder' => 'MyBrand',
                        'description' => __('Alphanumeric sender ID (max 11 chars), pre-registered with eNgY.', 'wp-sms'),
                    ],
                    'flash' => [
                        'type'        => 'boolean',
                        'required'    => false,
                        'label'       => __('Send as Flash SMS', 'wp-sms'),
                        'default'     => false,
                        'description' => __('Display the message directly on the recipient\'s screen without saving it to inbox.', 'wp-sms'),
                    ],
                ],
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        $apiKey = (string) $this->getSharedConfig('api_key', '');
        $from   = (string) $this->getChannelConfig('sms', 'from', '');

        if ($apiKey === '' || $from === '') {
            return DeliveryResult::failed(__('eNgY credentials are not configured.', 'wp-sms'));
        }

        $body = [
            'From' => $from,
            'To'   => ltrim($message->getRecipient(), '+'),
            'Text' => $message->getBody(),
        ];

        if ($this->getChannelConfig('sms', 'flash')) {
            $body['Flash'] = true;
        }

        $result = $this->httpPost(self::SEND_ENDPOINT, [
            'headers' => [
                'Authorization' => $apiKey,
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ],
            'body'    => wp_json_encode($body),
        ]);

        if ($result instanceof DeliveryResult) {
            return $result;
        }

        $code = (int) $result['code'];
        if ($code < 200 || $code >= 300) {
            return DeliveryResult::failed(
                sprintf(__('eNgY HTTP error (%d).', 'wp-sms'), $code),
            );
        }

        $data = json_decode($result['body'], true);
        if (!is_array($data) || (int) ($data['statusCode'] ?? 0) !== 200) {
            return DeliveryResult::failed(__('eNgY returned an unexpected response.', 'wp-sms'));
        }

        $messageIds = isset($data['messageIds']) && is_array($data['messageIds'])
            ? $data['messageIds']
            : [];

        return DeliveryResult::sent($messageIds[0] ?? null, null, ['message_ids' => $messageIds]);
    }
}
