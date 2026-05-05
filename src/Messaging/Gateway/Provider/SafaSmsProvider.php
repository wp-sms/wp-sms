<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\SupportsDynamicOptions;
use WSms\Messaging\Contracts\TestConnectionResult;
use WSms\Messaging\Gateway\AbstractProvider;

defined('ABSPATH') || exit;

/**
 * SafaSMS — Saudi Arabian bulk-SMS gateway (صفا رسائل الجوال) widely used
 * by KSA schools, clinics, and SMBs.
 *
 * API contract (verbatim from the official PDF at
 * https://www.prowebsms.com/assets/bs3/img/blog/opencart/doc/safa-sms/safa-api.pdf
 * cross-checked against https://safa-sms.com/pages/49/):
 *   Send:      GET https://www.safa-sms.com/api/sendsms.php
 *              ?username=…&password=…&message=…&numbers=…&sender=…
 *   Balance:   GET https://www.safa-sms.com/api/getbalance.php
 *              ?username=…&password=…
 *   Senders:   GET https://www.safa-sms.com/specialapi/GetAllSenders.php
 *              ?username=…&password=…&return=xml
 *
 * Response: plain text. Send returns the message ID on success or an "Error"-
 * prefixed line on failure. GetAllSenders.php returns XML when return=xml.
 *
 * Out of scope (not documented): DLR webhook, inbound MO, templates,
 * opt-out detection, MMS, flash SMS, any non-SMS channel.
 */
final class SafaSmsProvider extends AbstractProvider implements SupportsDynamicOptions
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const API_BASE     = 'https://www.safa-sms.com/api';
    private const SPECIAL_BASE = 'https://www.safa-sms.com/specialapi';

    public function getId(): string
    {
        return 'safasms';
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
                    'description' => __('Your SafaSMS account username (the same one you use to log in at safa-sms.com).', 'wp-sms'),
                ],
                'password' => [
                    'type'        => 'secret',
                    'label'       => __('API Password', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Your SafaSMS account password.', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'sender' => [
                        'type'        => 'string',
                        'label'       => __('Sender ID', 'wp-sms'),
                        'required'    => true,
                        'dynamic'     => true,
                        'placeholder' => 'COMPANY',
                        'description' => __('A sender ID that you have registered and activated in your SafaSMS dashboard. KSA recipients require CITC approval before delivery.', 'wp-sms'),
                    ],
                ],
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        $username = (string) $this->getSharedConfig('username', '');
        $password = (string) $this->getSharedConfig('password', '');
        $sender   = (string) $this->getChannelConfig('sms', 'sender', '');

        if ($username === '' || $password === '' || $sender === '') {
            return DeliveryResult::failed(__('SafaSMS credentials not configured', 'wp-sms'));
        }

        $url = self::API_BASE . '/sendsms.php?' . http_build_query([
            'username' => $username,
            'password' => $password,
            'message'  => $message->getBody(),
            'numbers'  => ltrim($message->getRecipient(), '+'),
            'sender'   => $sender,
        ]);

        $result = $this->httpGet($url);
        if ($result instanceof DeliveryResult) {
            return $result;
        }

        $body = trim((string) $result['body']);

        if ($body === '' || stripos($body, 'error') !== false || stripos($body, 'fail') !== false) {
            return DeliveryResult::failed($body !== '' ? $body : __('SafaSMS returned an empty response', 'wp-sms'));
        }

        // SafaSMS returns the message ID on success.
        return DeliveryResult::sent(providerId: $body);
    }

    public function getCredit(): ?string
    {
        $body = $this->fetchBalance();
        if ($body === null) {
            return null;
        }

        return is_numeric($body) ? (string) (int) $body : null;
    }

    public function testConnection(): TestConnectionResult
    {
        $username = (string) $this->getSharedConfig('username', '');
        $password = (string) $this->getSharedConfig('password', '');

        if ($username === '' || $password === '') {
            return TestConnectionResult::error(__('SafaSMS credentials not configured', 'wp-sms'));
        }

        $body = $this->fetchBalance();
        if ($body === null) {
            return TestConnectionResult::error(
                __('Could not reach the SafaSMS API. Check your server\'s internet connection.', 'wp-sms')
            );
        }

        if (!is_numeric($body)) {
            return TestConnectionResult::error(
                stripos($body, 'error') !== false || stripos($body, 'fail') !== false
                    ? $body
                    : __('Invalid SafaSMS credentials', 'wp-sms')
            );
        }

        return TestConnectionResult::ok(
            sprintf(__('Connected. Balance: %d', 'wp-sms'), (int) $body)
        );
    }

    // --- SupportsDynamicOptions ---

    public function getConfigOptions(string $fieldKey, string $section, array $config, array $context = []): array
    {
        if ($fieldKey !== 'sender' || $section !== 'sms') {
            return [];
        }

        return $this->withConfig($config, function () {
            $username = (string) $this->getSharedConfig('username', '');
            $password = (string) $this->getSharedConfig('password', '');

            if ($username === '' || $password === '') {
                throw new \RuntimeException(__('Enter API Username and API Password first', 'wp-sms'));
            }

            $url = self::SPECIAL_BASE . '/GetAllSenders.php?' . http_build_query([
                'username' => $username,
                'password' => $password,
                'return'   => 'xml',
            ]);

            $result = $this->httpGet($url);
            if ($result instanceof DeliveryResult) {
                throw new \RuntimeException(__('Could not reach the SafaSMS senders API', 'wp-sms'));
            }

            $body = trim((string) $result['body']);
            if ($body === '') {
                return [];
            }

            $previous = libxml_use_internal_errors(true);
            $xml = simplexml_load_string($body);
            libxml_use_internal_errors($previous);

            if ($xml === false) {
                throw new \RuntimeException(__('SafaSMS returned a malformed sender list', 'wp-sms'));
            }

            $options = [];
            foreach ($xml->children() as $node) {
                // SafaSMS XML wraps each sender in an element; the sender name
                // can appear as the element value, a "Sender"/"sender" child,
                // or a "name" attribute. Be permissive — we test all three.
                $candidate = trim((string) $node);
                if ($candidate === '') {
                    foreach (['Sender', 'sender', 'SenderName', 'Name', 'name'] as $key) {
                        if (isset($node->$key)) {
                            $candidate = trim((string) $node->$key);
                            if ($candidate !== '') break;
                        }
                    }
                }
                if ($candidate === '' && isset($node['name'])) {
                    $candidate = trim((string) $node['name']);
                }

                if ($candidate !== '') {
                    $options[] = ['value' => $candidate, 'label' => $candidate];
                }
            }

            return $options;
        });
    }

    /**
     * GET /api/getbalance.php and return the trimmed body, or null on a
     * network failure or unconfigured credentials.
     */
    private function fetchBalance(): ?string
    {
        $username = (string) $this->getSharedConfig('username', '');
        $password = (string) $this->getSharedConfig('password', '');

        if ($username === '' || $password === '') {
            return null;
        }

        $url = self::API_BASE . '/getbalance.php?' . http_build_query([
            'username' => $username,
            'password' => $password,
        ]);

        $result = $this->httpGet($url);
        if ($result instanceof DeliveryResult) {
            return null;
        }

        return trim((string) $result['body']);
    }
}
