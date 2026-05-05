<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\TestConnectionResult;
use WSms\Messaging\Gateway\AbstractProvider;

defined('ABSPATH') || exit;

/**
 * Zain (zain.im) — Saudi bulk-SMS reseller.
 *
 * The only authoritative API artifact is the integration PDF linked from
 * https://www.zain.im/index.php/page/16. The body is encoded but one embedded
 * example URL leaks the canonical request shape:
 *   GET https://www.zain.im/index.php/api/sendsms/
 *       ?user=…&pass=…&to=…&message=…&sender=…&date=YYYY-MM-DD&time=HH:MM:SS
 *
 * Security: plaintext credentials travel in the URL (and therefore web-server
 * access logs). That is the documented auth scheme; WSMS cannot change it —
 * the setup notes warn the operator to keep the WP host on HTTPS and avoid
 * sharing access logs.
 *
 * Out of scope (no public docs surfaced): balance lookup, DLR webhook, inbound
 * MO, templates, opt-out detection, regulatory IDs, dynamic options. The
 * AbstractProvider defaults (getCredit() => null, etc.) are correct.
 */
final class ZainProvider extends AbstractProvider
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const SEND_ENDPOINT = 'https://www.zain.im/index.php/api/sendsms/';

    public function getId(): string
    {
        return 'zain';
    }

    public function getName(): string
    {
        return 'Zain SMS';
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
                    'label'       => __('Username', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Your zain.im account username.', 'wp-sms'),
                ],
                'password' => [
                    'type'        => 'secret',
                    'label'       => __('Password', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Your zain.im account password (sent in the URL query string — keep WSMS on HTTPS).', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'sender' => [
                        'type'        => 'string',
                        'label'       => __('Sender ID', 'wp-sms'),
                        'required'    => true,
                        'description' => __('Registered alphanumeric sender ID (max 11 chars).', 'wp-sms'),
                    ],
                ],
            ],
        ];
    }

    public function getMetadata(): array
    {
        return [
            'description' => __('Saudi Arabia bulk SMS reseller — minimal HTTP-API SMS-only integration.', 'wp-sms'),
            'website'     => 'https://www.zain.im/',
            'regions'     => ['middle-east', 'gcc'],
            'setup_url'   => 'https://www.zain.im/',
            'setup_notes' => [
                __('Sign up at zain.im — the dashboard is Arabic-only; pricing is SAR-based credit packs (no free trial).', 'wp-sms'),
                __('Request the API integration PDF from Zain.im support — public docs only describe the GET sendsms URL pattern.', 'wp-sms'),
                __('Set Username and Password to your zain.im account credentials. They are sent in the URL query string, so make sure WSMS is on HTTPS and that web-server access logs are not shared with untrusted parties.', 'wp-sms'),
                __('Set Sender to your registered alphanumeric Sender ID (max 11 chars).', 'wp-sms'),
            ],
        ];
    }

    public function getFeatures(): array
    {
        return array_merge(parent::getFeatures(), [
            'delivery_receipt' => false,
            'test_connection'  => true,
        ]);
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        $username = (string) $this->getSharedConfig('username', '');
        $password = (string) $this->getSharedConfig('password', '');
        $sender   = (string) $this->getChannelConfig('sms', 'sender', '');

        if ($username === '' || $password === '' || $sender === '') {
            return DeliveryResult::failed(__('Zain credentials not configured', 'wp-sms'));
        }

        $now = current_datetime();

        // add_query_arg handles URL-encoding so password/message containing
        // & or = won't break the request.
        $url = add_query_arg([
            'user'    => $username,
            'pass'    => $password,
            'to'      => ltrim($message->getRecipient(), '+'),
            'message' => $message->getBody(),
            'sender'  => $sender,
            'date'    => $now->format('Y-m-d'),
            'time'    => $now->format('H:i:s'),
        ], self::SEND_ENDPOINT);

        $result = $this->httpGet($url);
        if ($result instanceof DeliveryResult) {
            return $result;
        }

        $code = (int) $result['code'];
        $body = trim((string) $result['body']);

        // Response shape was not in the leaked docs — adjust parsing once a
        // real send is captured during manual verification (success token,
        // error format, message-id field).
        if ($code >= 200 && $code < 300) {
            return DeliveryResult::sent(providerId: $body !== '' ? $body : null);
        }

        return DeliveryResult::failed(
            $body !== '' ? $body : sprintf(__('Zain send failed (HTTP %d)', 'wp-sms'), $code),
        );
    }

    public function testConnection(): TestConnectionResult
    {
        if (!$this->getSharedConfig('username') || !$this->getSharedConfig('password')) {
            return TestConnectionResult::error(__('Username and Password are required', 'wp-sms'));
        }

        $result = $this->httpGet(self::SEND_ENDPOINT);
        if ($result instanceof DeliveryResult) {
            return TestConnectionResult::error(
                __('Could not reach the Zain API. Check your server\'s internet connection.', 'wp-sms'),
            );
        }

        $code = (int) $result['code'];
        if ($code >= 200 && $code < 300) {
            // Zain has no documented dry-run / verify endpoint; reaching the
            // sendsms URL with empty params just confirms the host responds.
            return TestConnectionResult::ok(__('Zain API reachable. Credentials cannot be verified without sending a real SMS.', 'wp-sms'));
        }

        return TestConnectionResult::error(
            sprintf(__('Unexpected response from Zain (HTTP %d)', 'wp-sms'), $code),
        );
    }
}
