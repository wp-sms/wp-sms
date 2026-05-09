<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Gateway\AbstractProvider;

defined('ABSPATH') || exit;

/**
 * SSL Wireless — Bangladesh A2P SMS aggregator on the ISMSPlus product.
 *
 * Auth: a single API Hash Token, generated from the operator's profile at
 * ismsplus.sslwireless.com and POSTed in the JSON body alongside the SID.
 *
 * Send: POST /api/v3/send-sms with body {api_token, sid, msisdn, sms, csms_id};
 * success is signalled by a top-level `status_code === 200`. The API is
 * fire-and-forget — there is no DLR webhook, no inbound MO, no balance
 * endpoint, no Verify lifecycle and no template registration in the v3
 * surface, so the provider only implements doSend and inherits everything
 * else from AbstractProvider (getCredit returning null and testConnection
 * defaulting to "not supported" are exactly right).
 */
final class SslWirelessProvider extends AbstractProvider
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const SEND_URL = 'https://smsplus.sslwireless.com/api/v3/send-sms';

    public function getId(): string
    {
        return 'sslwireless';
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
                    'description' => __('Your SSL Wireless ISMSPlus API token. Generate it from your profile at ismsplus.sslwireless.com (note: this is the modern ISMSPlus product, not the legacy iSMS panel).', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'sid' => [
                        'type'        => 'string',
                        'label'       => __('Sender ID (SID)', 'wp-sms'),
                        'required'    => true,
                        'placeholder' => 'YOUR_SID',
                        'description' => __('BTRC-approved Sender ID assigned by SSL Wireless when you onboard to ISMSPlus.', 'wp-sms'),
                    ],
                ],
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        $apiToken = (string) $this->getSharedConfig('api_token', '');
        $sid      = (string) $this->getChannelConfig('sms', 'sid', '');

        if ($apiToken === '') {
            return DeliveryResult::failed(__('SSL Wireless API token is required', 'wp-sms'));
        }
        if ($sid === '') {
            return DeliveryResult::failed(__('SSL Wireless Sender ID (SID) not configured', 'wp-sms'));
        }

        $csmsId = uniqid('wsms_', true);

        $result = $this->httpPost(self::SEND_URL, [
            'headers' => [
                'Accept'       => 'application/json',
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode([
                'api_token' => $apiToken,
                'sid'       => $sid,
                'msisdn'    => $message->getRecipient(),
                'sms'       => $message->getBody(),
                'csms_id'   => $csmsId,
            ]),
        ]);

        if ($result instanceof DeliveryResult) {
            return $result;
        }

        $json = json_decode((string) $result['body'], true);
        $statusCode = is_array($json) ? ($json['status_code'] ?? null) : null;

        if ($statusCode !== 200) {
            $errorMessage = is_array($json) ? ($json['error_message'] ?? null) : null;
            return DeliveryResult::failed(
                $errorMessage !== null && $errorMessage !== ''
                    ? sprintf(__('SSL Wireless send failed: %s', 'wp-sms'), (string) $errorMessage)
                    : sprintf(__('SSL Wireless send failed (HTTP %d)', 'wp-sms'), $result['code']),
            );
        }

        // TODO(verify): ISMSPlus v3 response message-ID field name is not publicly documented.
        // The official sslw/ismsplus_api client and the official SSL Wireless WordPress plugin
        // both check only `status_code === 200` and surface `error_message`. Until we capture
        // a real success response, treat the client-generated `csms_id` as the provider
        // correlation token; once verified, switch to `reference_id`/`tran_id` if present.
        return DeliveryResult::sent($csmsId, null, ['csms_id' => $csmsId]);
    }
}
