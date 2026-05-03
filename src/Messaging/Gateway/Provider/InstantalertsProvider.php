<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\TestConnectionResult;
use WSms\Messaging\Gateway\AbstractProvider;

defined('ABSPATH') || exit;

/**
 * Instantalerts (Spring Edge) — DLT-compliant Indian SMS gateway with
 * international routing.
 *
 * API contract verified against the official Spring Edge SDKs (PHP, Node.js,
 * Python, Java) and a live probe of `instantalerts.co`:
 *   Send:   GET https://instantalerts.co/api/web/send/
 *           ?apikey=…&sender=…&to=…&message=…&format=json
 *   Credit: GET https://instantalerts.co/api/status/credit?apikey=…&format=json
 *
 * Success body: {groupID, MessageIDs, status: "AWAITED-DLR"}.
 * Error body:   {status: false, error: "Invalid API Key "} (HTTP 403 on bad keys).
 *
 * Out of scope (not surfaced by any official SDK): MMS/media, flash SMS,
 * delivery receipts, inbound MO, opt-out detection, list-senders, templates,
 * regulatory IDs (DLT compliance is enforced out-of-band on the dashboard,
 * not via API parameters), and the multi-channel WhatsApp/Voice/RCS endpoints
 * advertised on the marketing site.
 */
class InstantalertsProvider extends AbstractProvider
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const SEND_ENDPOINT   = 'https://instantalerts.co/api/web/send/';
    private const CREDIT_ENDPOINT = 'https://instantalerts.co/api/status/credit';

    public function getId(): string
    {
        return 'instantalerts';
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
                    'description' => __('Generate from the Developers section of your Spring Edge / Instantalerts account.', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'from' => [
                        'type'        => 'string',
                        'required'    => true,
                        'label'       => __('Sender ID', 'wp-sms'),
                        'placeholder' => 'SEDEMO',
                        'description' => __('Approved DLT header (6 alphanumeric characters for Indian routes). Set up under Sender Names in the Spring Edge dashboard.', 'wp-sms'),
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
            return DeliveryResult::failed(__('Instantalerts credentials not configured', 'wp-sms'));
        }

        $url = add_query_arg([
            'apikey'  => $apiKey,
            'sender'  => $from,
            'to'      => ltrim($message->getRecipient(), '+'),
            'message' => $message->getBody(),
            'format'  => 'json',
        ], self::SEND_ENDPOINT);

        $result = $this->httpGet($url);
        if ($result instanceof DeliveryResult) {
            return $result;
        }

        $data = json_decode($result['body'], true);
        $code = $result['code'];

        if ($code === 401 || $code === 403) {
            $error = is_array($data) && !empty($data['error'])
                ? trim((string) $data['error'])
                : __('Invalid Instantalerts API key', 'wp-sms');
            return DeliveryResult::failed($error);
        }

        if ($code >= 200 && $code < 300 && is_array($data) && !empty($data['MessageIDs'])) {
            return DeliveryResult::sent(providerId: (string) $data['MessageIDs']);
        }

        $error = is_array($data) && !empty($data['error'])
            ? trim((string) $data['error'])
            : sprintf(__('Instantalerts send failed (HTTP %d)', 'wp-sms'), $code);

        return DeliveryResult::failed($error);
    }

    public function getCredit(): ?string
    {
        $apiKey = (string) $this->getSharedConfig('api_key', '');
        if ($apiKey === '') {
            return null;
        }

        $result = $this->httpGet(add_query_arg(
            ['apikey' => $apiKey, 'format' => 'json'],
            self::CREDIT_ENDPOINT,
        ));
        if ($result instanceof DeliveryResult) {
            return null;
        }

        $data = json_decode($result['body'], true);
        if (!is_array($data) || isset($data['error'])) {
            return null;
        }

        $credits = $data['credits'] ?? $data['balance'] ?? null;
        return $credits !== null ? (string) $credits : null;
    }

    public function testConnection(): TestConnectionResult
    {
        $apiKey = (string) $this->getSharedConfig('api_key', '');
        if ($apiKey === '') {
            return TestConnectionResult::error(__('API Key is required', 'wp-sms'));
        }

        $result = $this->httpGet(add_query_arg(
            ['apikey' => $apiKey, 'format' => 'json'],
            self::CREDIT_ENDPOINT,
        ));

        // Live API returns HTTP 403 with a JSON body on bad keys; surface the
        // precise error before falling through to the generic non-2xx handler.
        if (!$result instanceof DeliveryResult) {
            if ($result['code'] === 401 || $result['code'] === 403) {
                $data = json_decode($result['body'], true);
                $message = is_array($data) && !empty($data['error'])
                    ? trim((string) $data['error'])
                    : __('Invalid Instantalerts API key', 'wp-sms');
                return TestConnectionResult::error($message);
            }
            $data = json_decode($result['body'], true);
            if (is_array($data) && ($data['status'] ?? null) === false) {
                return TestConnectionResult::error(
                    !empty($data['error']) ? trim((string) $data['error']) : __('Invalid Instantalerts API key', 'wp-sms'),
                );
            }
        }

        $data = $this->validateTestResponse($result, 'Instantalerts');
        if ($data instanceof TestConnectionResult) {
            return $data;
        }

        $credits = $data['credits'] ?? $data['balance'] ?? 'N/A';
        return TestConnectionResult::ok(
            sprintf(__('Connected — Credits: %s', 'wp-sms'), $credits),
            ['balance' => (string) $credits],
        );
    }
}
