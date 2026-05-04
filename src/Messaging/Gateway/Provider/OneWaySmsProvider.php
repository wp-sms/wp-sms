<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\TestConnectionResult;
use WSms\Messaging\Gateway\AbstractProvider;

defined('ABSPATH') || exit;

/**
 * OneWaySMS — Asia-Pacific / Australasia / Europe SMS aggregator with regional
 * gateway endpoints assigned per customer (Malaysia, Australia, UK, etc.).
 *
 * API contract (verbatim from the v1 wp-sms-pro implementation; the public
 * onewaysms.com docs page is out of reach without an account):
 *   Send:   GET {region}/api.aspx
 *           ?apiusername=…&apipassword=…&senderid=…&mobileno=…&message=…&languagetype={1|2}
 *   Balance: GET {region}/bulkcredit.aspx?apiusername=…&apipassword=…
 *   languagetype: 1 = ASCII / GSM, 2 = Unicode (UCS-2)
 *
 * Send response is a plain-text numeric string. A non-negative number is the
 * MTID (carrier reference); a negative number is a provider error code.
 * Balance response is a plain-text decimal credit amount; negative values
 * indicate an error (e.g. invalid auth).
 *
 * Out of scope (provider does not document any of these in a way the v1 class
 * exercised): inbound MO, DLR webhook (status is poll-based via bulktrx.aspx —
 * deferred), templates, opt-out detection, dynamic sender lookup, MMS, flash,
 * Verify, or any non-SMS channel.
 */
final class OneWaySmsProvider extends AbstractProvider
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const REGION_ENDPOINTS = [
        'my' => [
            'send'    => 'http://gateway.onewaysms.com.my:10001/api.aspx',
            'balance' => 'http://gateway.onewaysms.com.my:10001/bulkcredit.aspx',
        ],
        'au' => [
            'send'    => 'http://gateway.onewaysms.com.au:10001/api.aspx',
            'balance' => 'http://gateway.onewaysms.com.au:10001/bulkcredit.aspx',
        ],
    ];

    public function getId(): string
    {
        return 'onewaysms';
    }

    public function getSupportedChannels(): array
    {
        return ['sms'];
    }

    public function getConfigSchema(): array
    {
        return [
            'shared' => [
                'region' => [
                    'type'        => 'select',
                    'label'       => __('Region', 'wp-sms'),
                    'required'    => true,
                    'default'     => 'my',
                    'options'     => [
                        ['value' => 'my',     'label' => __('Malaysia (gateway.onewaysms.com.my:10001)', 'wp-sms')],
                        ['value' => 'au',     'label' => __('Australia (gateway.onewaysms.com.au:10001)', 'wp-sms')],
                        ['value' => 'custom', 'label' => __('Custom URL (specify below)', 'wp-sms')],
                    ],
                    'description' => __('OneWaySMS assigns a region-specific gateway URL when you sign up. Pick the region that matches your account, or choose Custom URL to paste a host and port we have not pre-registered.', 'wp-sms'),
                ],
                'custom_send_url' => [
                    'type'        => 'string',
                    'label'       => __('Custom Send URL', 'wp-sms'),
                    'required'    => false,
                    'placeholder' => 'http://your-gateway.example.com:10001/api.aspx',
                    'description' => __('Required only when Region is set to Custom URL. Paste the full api.aspx endpoint your OneWaySMS account was given.', 'wp-sms'),
                ],
                'custom_balance_url' => [
                    'type'        => 'string',
                    'label'       => __('Custom Balance URL', 'wp-sms'),
                    'required'    => false,
                    'placeholder' => 'http://your-gateway.example.com:10001/bulkcredit.aspx',
                    'description' => __('Required only when Region is set to Custom URL. Paste the full bulkcredit.aspx endpoint your OneWaySMS account was given.', 'wp-sms'),
                ],
                'apiusername' => [
                    'type'        => 'string',
                    'label'       => __('API Username', 'wp-sms'),
                    'required'    => true,
                    'description' => __('The API username issued by OneWaySMS for your account.', 'wp-sms'),
                ],
                'apipassword' => [
                    'type'        => 'secret',
                    'label'       => __('API Password', 'wp-sms'),
                    'required'    => true,
                    'description' => __('The API password issued by OneWaySMS for your account.', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'from' => [
                        'type'        => 'string',
                        'label'       => __('Sender ID', 'wp-sms'),
                        'required'    => true,
                        'placeholder' => 'COMPANY',
                        'description' => __('Pre-approved sender ID registered with OneWaySMS (alphanumeric, up to 11 characters).', 'wp-sms'),
                    ],
                ],
            ],
        ];
    }

    public function validateConfig(array $config): bool
    {
        if (!parent::validateConfig($config)) {
            return false;
        }

        $shared = $config['shared'] ?? [];
        if (($shared['region'] ?? '') === 'custom') {
            if (empty($shared['custom_send_url']) || empty($shared['custom_balance_url'])) {
                return false;
            }
        }

        return true;
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        $username = (string) $this->getSharedConfig('apiusername', '');
        $password = (string) $this->getSharedConfig('apipassword', '');
        $from     = (string) $this->getChannelConfig('sms', 'from', '');
        $endpoint = $this->resolveEndpoint('send');

        if ($username === '' || $password === '' || $from === '' || $endpoint === null) {
            return DeliveryResult::failed(__('OneWaySMS credentials not configured', 'wp-sms'));
        }

        $url = add_query_arg([
            'apiusername'  => $username,
            'apipassword'  => $password,
            'senderid'     => $from,
            'mobileno'     => ltrim($message->getRecipient(), '+'),
            'message'      => $message->getBody(),
            'languagetype' => $this->resolveLanguageType($message),
        ], $endpoint);

        $result = $this->httpGet($url);
        if ($result instanceof DeliveryResult) {
            return $result;
        }

        $body = trim((string) $result['body']);
        $code = (int) $result['code'];

        if ($code < 200 || $code >= 300) {
            return DeliveryResult::failed(
                sprintf(__('OneWaySMS send failed (HTTP %d)', 'wp-sms'), $code),
            );
        }

        if ($body !== '' && is_numeric($body) && (float) $body >= 0) {
            return DeliveryResult::sent(providerId: $body);
        }

        $error = $body !== '' ? $body : __('OneWaySMS returned an empty response', 'wp-sms');

        return DeliveryResult::failed(sprintf(__('OneWaySMS error: %s', 'wp-sms'), $error));
    }

    public function getCredit(): ?string
    {
        $username = (string) $this->getSharedConfig('apiusername', '');
        $password = (string) $this->getSharedConfig('apipassword', '');
        $endpoint = $this->resolveEndpoint('balance');

        if ($username === '' || $password === '' || $endpoint === null) {
            return null;
        }

        $url = add_query_arg([
            'apiusername' => $username,
            'apipassword' => $password,
        ], $endpoint);

        $result = $this->httpGet($url);
        if ($result instanceof DeliveryResult) {
            return null;
        }

        $code = (int) $result['code'];
        $body = trim((string) $result['body']);

        if ($code < 200 || $code >= 300) {
            return null;
        }

        if ($body !== '' && is_numeric($body) && (float) $body >= 0) {
            return $body;
        }

        return null;
    }

    public function testConnection(): TestConnectionResult
    {
        $username = (string) $this->getSharedConfig('apiusername', '');
        $password = (string) $this->getSharedConfig('apipassword', '');
        $endpoint = $this->resolveEndpoint('balance');

        if ($username === '' || $password === '' || $endpoint === null) {
            return TestConnectionResult::error(__('OneWaySMS credentials not configured', 'wp-sms'));
        }

        $url = add_query_arg([
            'apiusername' => $username,
            'apipassword' => $password,
        ], $endpoint);

        $result = $this->httpGet($url);
        if ($result instanceof DeliveryResult) {
            return TestConnectionResult::error(
                __('Could not reach the OneWaySMS API. Check your server\'s internet connection.', 'wp-sms'),
            );
        }

        $code = (int) $result['code'];
        $body = trim((string) $result['body']);

        if ($code < 200 || $code >= 300) {
            return TestConnectionResult::error(
                sprintf(__('Unexpected response from OneWaySMS (HTTP %d)', 'wp-sms'), $code),
            );
        }

        if ($body !== '' && is_numeric($body) && (float) $body >= 0) {
            return TestConnectionResult::ok(
                sprintf(__('Credit balance: %s', 'wp-sms'), $body),
            );
        }

        $error = $body !== '' ? $body : __('OneWaySMS returned an empty response', 'wp-sms');

        return TestConnectionResult::error(sprintf(__('OneWaySMS error: %s', 'wp-sms'), $error));
    }

    private function resolveEndpoint(string $kind): ?string
    {
        $region = (string) $this->getSharedConfig('region', 'my');

        if ($region === 'custom') {
            $key   = $kind === 'send' ? 'custom_send_url' : 'custom_balance_url';
            $value = (string) $this->getSharedConfig($key, '');
            return $value !== '' ? $value : null;
        }

        return self::REGION_ENDPOINTS[$region][$kind] ?? null;
    }

    /**
     * Map message body / meta onto OneWaySMS's `languagetype` integer:
     * 1 = ASCII (GSM), 2 = Unicode (UCS-2). meta['unicode'] takes precedence
     * over the auto-detect fallback (mirrors v1's send_unicode flag semantics).
     */
    private function resolveLanguageType(MessageInterface $message): int
    {
        $meta = $message->getMeta();
        $isUnicode = array_key_exists('unicode', $meta)
            ? !empty($meta['unicode'])
            : (bool) preg_match('/[^\x00-\x7F]/', $message->getBody());

        return $isUnicode ? 2 : 1;
    }
}
