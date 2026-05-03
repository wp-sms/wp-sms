<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Gateway\AbstractProvider;

defined('ABSPATH') || exit;

/**
 * BareedSMS — Bahraini SMS gateway with self-service web portal at bareedsms.com.
 *
 * API contract (verbatim from the v1 wp-sms-pro implementation; no public API
 * reference is reachable — bareedsms.com/RemoteAPI/ is auth-walled):
 *   Send: GET https://bareedsms.com/RemoteAPI/SendSMS.aspx
 *         ?encoding=url&username=…&password=…&type=…&receiver=…&source=…&messagedata=…
 *   type: 0 = plain SMS, 1 = flash, 2 = unicode, 6 = unicode flash
 *   Response: plain text. Failures contain the literal substring "Error";
 *             success returns the message identifier / OK string.
 *
 * Out of scope (provider does not document any of these): balance lookup
 * (the v1 implementation's CreditCheck endpoint is commented out as broken),
 * test-connection probe, DLR webhook, inbound MO, templates, opt-out detection,
 * dynamic sender lookup, or any non-SMS channel. The AbstractProvider defaults
 * (getCredit() => null, testConnection() => "not supported") are correct.
 */
final class BareedSmsProvider extends AbstractProvider
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const SEND_ENDPOINT = 'https://bareedsms.com/RemoteAPI/SendSMS.aspx';

    public function getId(): string
    {
        return 'bareedsms';
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
                    'label'       => __('API Username', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Your BareedSMS panel username (the same one you use to log in at bareedsms.com).', 'wp-sms'),
                ],
                'password' => [
                    'type'        => 'secret',
                    'label'       => __('API Password', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Your BareedSMS panel password.', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'from' => [
                        'type'        => 'string',
                        'label'       => __('Sender ID', 'wp-sms'),
                        'required'    => true,
                        'placeholder' => 'COMPANY',
                        'description' => __('Pre-approved sender ID registered in your BareedSMS panel under Sender Names.', 'wp-sms'),
                    ],
                ],
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        $username = (string) $this->getSharedConfig('username', '');
        $password = (string) $this->getSharedConfig('password', '');
        $from     = (string) $this->getChannelConfig('sms', 'from', '');

        if ($username === '' || $password === '' || $from === '') {
            return DeliveryResult::failed(__('BareedSMS credentials not configured', 'wp-sms'));
        }

        $url = add_query_arg([
            'encoding'    => 'url',
            'username'    => $username,
            'password'    => $password,
            'type'        => $this->resolveType($message),
            'receiver'    => ltrim($message->getRecipient(), '+'),
            'source'      => $from,
            'messagedata' => $message->getBody(),
        ], self::SEND_ENDPOINT);

        $result = $this->httpGet($url);
        if ($result instanceof DeliveryResult) {
            return $result;
        }

        $body = trim((string) $result['body']);
        $code = (int) $result['code'];

        if ($code >= 200 && $code < 300 && $body !== '' && stripos($body, 'error') === false) {
            return DeliveryResult::sent(providerId: $body);
        }

        $error = $body !== ''
            ? $body
            : sprintf(__('BareedSMS send failed (HTTP %d)', 'wp-sms'), $code);

        return DeliveryResult::failed($error);
    }

    /**
     * Map per-message flash/unicode flags onto the provider's `type` integer.
     * Falls back to auto-detecting unicode from the body when no explicit flag
     * is set — mirrors v1's behaviour without reading a global plugin option.
     */
    private function resolveType(MessageInterface $message): int
    {
        $meta      = $message->getMeta();
        $isFlash   = !empty($meta['flash']);
        $isUnicode = array_key_exists('unicode', $meta)
            ? !empty($meta['unicode'])
            : (bool) preg_match('/[^\x00-\x7F]/', $message->getBody());

        return match (true) {
            $isFlash && $isUnicode => 6,
            $isUnicode             => 2,
            $isFlash               => 1,
            default                => 0,
        };
    }
}
