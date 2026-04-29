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

class MtargetProvider extends AbstractProvider implements
    SupportsStatusCallback,
    SupportsInboundMessage
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const API_BASE     = 'https://api-public-2.mtarget.fr';
    private const SEND_PATH    = '/messages';
    private const BALANCE_PATH = '/balance';

    public function getId(): string
    {
        return 'mtarget';
    }

    public function getSupportedChannels(): array
    {
        // mtarget's marketing pages document WhatsApp, RCS, and Voice (VMS),
        // but no public REST endpoint, body shape, or signature scheme is
        // currently published. Re-evaluate once developers.mtarget.fr exposes
        // a multi-channel API reference or an official SDK lands on GitHub.
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
                    'description' => __('Provided by mtarget after account creation (email support@mtarget.fr).', 'wp-sms'),
                ],
                'password' => [
                    'type'     => 'secret',
                    'label'    => __('API Password', 'wp-sms'),
                    'required' => true,
                ],
                'callback_token' => [
                    'type'        => 'secret',
                    'label'       => __('Webhook Token', 'wp-sms'),
                    'required'    => false,
                    'description' => __('Append ?token=<this-value> to the callback URL when registering it in the mtarget dashboard. Required to accept delivery receipts and inbound replies.', 'wp-sms'),
                ],
                'send_unicode' => [
                    'type'        => 'boolean',
                    'label'       => __('Allow Unicode', 'wp-sms'),
                    'default'     => false,
                    'description' => __('Send messages with non-GSM-7 characters (UCS-2). Halves the per-segment character limit.', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'from' => [
                        'type'        => 'string',
                        'label'       => __('Sender ID', 'wp-sms'),
                        'required'    => true,
                        'description' => __('Up to 11 alphanumeric characters. Must be pre-approved by mtarget for your destination country.', 'wp-sms'),
                        'placeholder' => 'MyBrand',
                    ],
                ],
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        $username = $this->getSharedConfig('username');
        $password = $this->getSharedConfig('password');

        if (!$username || !$password) {
            return DeliveryResult::failed(__('mtarget credentials not configured', 'wp-sms'));
        }

        $from = $this->getChannelConfig('sms', 'from');
        if (!$from) {
            return DeliveryResult::failed(__('mtarget Sender ID not configured', 'wp-sms'));
        }

        $body = [
            'username'     => $username,
            'password'     => $password,
            'sender'       => $from,
            'msisdn'       => $message->getRecipient(),
            'msg'          => $message->getBody(),
            'allowunicode' => $this->getSharedConfig('send_unicode') ? 'true' : 'false',
        ];

        $result = $this->httpPost(self::API_BASE . self::SEND_PATH, [
            'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
            'body'    => $body,
        ]);

        if ($result instanceof DeliveryResult) {
            return $result;
        }

        $data = json_decode($result['body'], true);

        if (!is_array($data) || !isset($data['results'][0])) {
            return DeliveryResult::failed(
                sprintf(__('Unexpected mtarget response (HTTP %d)', 'wp-sms'), $result['code']),
            );
        }

        $entry = $data['results'][0];
        $code  = isset($entry['code']) ? (int) $entry['code'] : null;

        if ($code === 0) {
            $ticket = isset($entry['ticket']) ? (string) $entry['ticket'] : null;
            return DeliveryResult::queued($ticket);
        }

        return DeliveryResult::failed(
            $this->describeSendCode($code, $entry),
            meta: array_filter([
                'mtarget_code'   => $code !== null ? (string) $code : null,
                'mtarget_reason' => isset($entry['reason']) ? (string) $entry['reason'] : null,
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

        $result = $this->httpPost(self::API_BASE . self::BALANCE_PATH, [
            'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
            'body'    => ['username' => $username, 'password' => $password],
        ]);

        if ($result instanceof DeliveryResult) {
            return null;
        }

        $data = json_decode($result['body'], true);
        if (!is_array($data) || !isset($data['amount'])) {
            return null;
        }

        return number_format((float) $data['amount'], 2);
    }

    public function testConnection(): TestConnectionResult
    {
        $username = $this->getSharedConfig('username');
        $password = $this->getSharedConfig('password');

        if (!$username || !$password) {
            return TestConnectionResult::error(__('API Username and API Password are required', 'wp-sms'));
        }

        $result = $this->httpPost(self::API_BASE . self::BALANCE_PATH, [
            'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
            'body'    => ['username' => $username, 'password' => $password],
        ]);

        $data = $this->validateTestResponse($result, 'mtarget');
        if ($data instanceof TestConnectionResult) {
            return $data;
        }

        if (isset($data['amount'])) {
            return TestConnectionResult::ok(
                sprintf(__('Connected — Balance: %s', 'wp-sms'), number_format((float) $data['amount'], 2)),
                ['balance' => (string) $data['amount']],
            );
        }

        // mtarget returns HTTP 200 even for credential failures; the error
        // text lives in the body (-1 = authentication failed per the docs).
        $error = $data['error'] ?? ($data['results'][0]['reason'] ?? null);
        if ($error !== null) {
            return TestConnectionResult::error(
                sprintf(__('mtarget rejected the request: %s', 'wp-sms'), (string) $error),
            );
        }

        return TestConnectionResult::error(__('Invalid mtarget response', 'wp-sms'));
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
        $statusRaw = $request->get_param('Status');
        if ($statusRaw === null || $statusRaw === '') {
            return [];
        }

        $status = (int) $statusRaw;

        // Status=5 means inbound MO (mobile-originated). Handled by the
        // inbound route, not the status route.
        if ($status === 5) {
            return [];
        }

        $msgId = (string) ($request->get_param('MsgId') ?? '');
        if ($msgId === '') {
            return [];
        }

        [$normalized, $permanent] = $this->mapDlrStatus($status);

        $reason = $request->get_param('Reason');
        $rsn    = $request->get_param('RSN');

        return [new StatusUpdate(
            providerId:   $msgId,
            status:       $normalized,
            errorCode:    $rsn !== null && $rsn !== '' ? (string) $rsn : null,
            errorMessage: $normalized === 'failed'
                ? sprintf('mtarget: %s', (string) ($reason ?: $request->get_param('StatusText') ?: $status))
                : null,
            permanent:    $permanent,
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
        if ((int) $request->get_param('Status') !== 5) {
            return [];
        }

        $from = (string) ($request->get_param('OriginatedAddress') ?? '');
        if ($from === '') {
            return [];
        }

        // mtarget docs misspell DestinationAdress (single 'd') in the DLR/MO
        // webhook field list — preserve as-is.
        $to = (string) ($request->get_param('DestinationAdress') ?? '');

        // The MO message body field is not explicitly named in the public
        // docs; sample payloads suggest it may arrive in 'Msg' (mirroring
        // the outbound parameter) or 'Reason'. Try both; verify against a
        // real MO payload before flipping TESTED to true.
        $body = (string) ($request->get_param('Msg') ?? $request->get_param('Reason') ?? '');

        $msgId = $request->get_param('MsgId');

        return [new InboundMessage(
            from:       $from,
            to:         $to,
            body:       $body,
            providerId: $msgId !== null && $msgId !== '' ? (string) $msgId : null,
        )];
    }

    // --- Internal ---

    private function verifyToken(\WP_REST_Request $request): bool
    {
        $expected = (string) ($this->getSharedConfig('callback_token') ?? '');
        if ($expected === '') {
            return false;
        }
        $provided = (string) ($request->get_param('token') ?? '');
        return $provided !== '' && hash_equals($expected, $provided);
    }

    /**
     * Map mtarget DLR Status codes to WSMS status names + permanence.
     *
     * @return array{0: string, 1: bool}
     */
    private function mapDlrStatus(int $status): array
    {
        return match ($status) {
            0, 1, 2 => ['sent', false],     // 0=waiting, 1=in progress, 2=sent to operator
            3       => ['delivered', true],
            4, 6    => ['failed', true],    // 4=refused by operator, 6=not delivered
            default => ['failed', false],
        };
    }

    private function describeSendCode(?int $code, array $entry): string
    {
        if (!empty($entry['reason'])) {
            return sprintf('mtarget: %s', (string) $entry['reason']);
        }

        return match ($code) {
            -1      => __('Authentication failed', 'wp-sms'),
            -2      => __('Invalid recipient address', 'wp-sms'),
            -4      => __('No route to destination', 'wp-sms'),
            -10     => __('Message too long', 'wp-sms'),
            -11     => __('Insufficient credit', 'wp-sms'),
            -12     => __('Invalid parameter', 'wp-sms'),
            null    => __('mtarget did not return a result code', 'wp-sms'),
            default => sprintf(__('mtarget error %d', 'wp-sms'), $code),
        };
    }
}
