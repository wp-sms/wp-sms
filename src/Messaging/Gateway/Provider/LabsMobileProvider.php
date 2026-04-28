<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\StatusUpdate;
use WSms\Messaging\Contracts\SupportsStatusCallback;
use WSms\Messaging\Contracts\TestConnectionResult;
use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Rest\RestRoute;

defined('ABSPATH') || exit;

/**
 * LabsMobile — Spanish SMS provider with a documented simulated-send mode.
 *
 * Auth: HTTP Basic, base64(username:api_token).
 * Send: POST /json/send returning { code: 0, message, subid }.
 * Balance: GET /json/balance returning { code, credits }.
 * DLR: GET request to ackurl with query params (subid, msisdn, status, desc).
 *
 * Inbound MO is not documented as a public webhook; LabsMobile is primarily a
 * one-way bulk-send platform, so SupportsInboundMessage is intentionally absent.
 */
class LabsMobileProvider extends AbstractProvider implements SupportsStatusCallback
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = true;

    private const API_BASE = 'https://api.labsmobile.com/json';

    public function getId(): string
    {
        return 'labsmobile';
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
                    'description' => __('Your LabsMobile registration email.', 'wp-sms'),
                    'placeholder' => 'you@example.com',
                ],
                'api_token' => [
                    'type'        => 'secret',
                    'label'       => __('API Token', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Generated under API Settings in your LabsMobile account.', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'tpoa' => [
                        'type'        => 'string',
                        'label'       => __('Sender ID', 'wp-sms'),
                        'required'    => false,
                        'description' => __('Optional alphanumeric sender (max 11 chars) or numeric sender (max 16 digits). Leave blank to use the LabsMobile default.', 'wp-sms'),
                    ],
                ],
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        $username = $this->getSharedConfig('username');
        $apiToken = $this->getSharedConfig('api_token');

        if (!$username || !$apiToken) {
            return DeliveryResult::failed(__('LabsMobile credentials not configured', 'wp-sms'));
        }

        $body = [
            'message'   => $message->getBody(),
            'recipient' => [['msisdn' => $message->getRecipient()]],
            'ackurl'    => $this->getStatusCallbackUrl(),
        ];

        $tpoa = $this->getChannelConfig('sms', 'tpoa');
        if (!empty($tpoa)) {
            $body['tpoa'] = $tpoa;
        }

        // Auto-toggle Unicode when message contains non-GSM characters.
        if (preg_match('/[^\x00-\x7F]/', $message->getBody())) {
            $body['ucs2'] = 1;
        }

        // Auto-enable concatenated SMS for messages > 160 chars.
        if (mb_strlen($message->getBody()) > 160) {
            $body['long'] = 1;
        }

        $result = $this->httpPost(self::API_BASE . '/send', [
            'headers' => $this->authHeaders($username, $apiToken),
            'body'    => wp_json_encode($body),
        ]);

        if ($result instanceof DeliveryResult) {
            return $result;
        }

        if ($result['code'] === 401 || $result['code'] === 403) {
            return DeliveryResult::failed(__('Invalid LabsMobile credentials', 'wp-sms'));
        }

        $data = json_decode($result['body'], true);
        $apiCode = isset($data['code']) ? (int) $data['code'] : null;

        if ($apiCode === 0) {
            return DeliveryResult::sent(
                providerId: $data['subid'] ?? null,
            );
        }

        return DeliveryResult::failed(
            $data['message'] ?? sprintf(__('LabsMobile send failed (code %s)', 'wp-sms'), $apiCode ?? 'n/a'),
            meta: array_filter(['labsmobile_code' => $apiCode]),
        );
    }

    public function getCredit(): ?string
    {
        $username = $this->getSharedConfig('username');
        $apiToken = $this->getSharedConfig('api_token');

        if (!$username || !$apiToken) {
            return null;
        }

        $result = $this->httpGet(self::API_BASE . '/balance', [
            'headers' => $this->authHeaders($username, $apiToken),
        ]);

        if ($result instanceof DeliveryResult) {
            return null;
        }

        $data = json_decode($result['body'], true);
        return $data['credits'] ?? null;
    }

    public function testConnection(): TestConnectionResult
    {
        $username = $this->getSharedConfig('username');
        $apiToken = $this->getSharedConfig('api_token');

        if (!$username || !$apiToken) {
            return TestConnectionResult::error(__('Username and API Token are required', 'wp-sms'));
        }

        $result = $this->httpGet(self::API_BASE . '/balance', [
            'headers' => $this->authHeaders($username, $apiToken),
        ]);

        if (!$result instanceof DeliveryResult) {
            if ($result['code'] === 401 || $result['code'] === 403) {
                return TestConnectionResult::error(__('Invalid Username or API Token', 'wp-sms'));
            }
        }

        $data = $this->validateTestResponse($result, 'LabsMobile');
        if ($data instanceof TestConnectionResult) {
            return $data;
        }

        $credits = $data['credits'] ?? 'N/A';

        return TestConnectionResult::ok(
            sprintf(__('Connected — Balance: %s credits', 'wp-sms'), $credits),
            ['balance' => $credits],
        );
    }

    // --- SupportsStatusCallback ---

    public function getStatusCallbackUrl(): string
    {
        return RestRoute::url(
            'callbacks/' . $this->getId() . '/status',
            ['token' => $this->callbackToken()],
        );
    }

    public function validateStatusCallback(\WP_REST_Request $request): bool
    {
        if (!$this->getSharedConfig('api_token')) {
            return false;
        }
        return hash_equals($this->callbackToken(), (string) ($request->get_param('token') ?? ''));
    }

    /** @return StatusUpdate[] */
    public function parseStatusCallback(\WP_REST_Request $request): array
    {
        $subid = $request->get_param('subid');
        $desc = $request->get_param('desc');
        $status = $request->get_param('status');

        if (empty($subid)) {
            return [];
        }

        $normalized = $this->normalizeStatus((string) $desc, (string) $status);

        return [new StatusUpdate(
            providerId:   (string) $subid,
            status:       $normalized,
            errorCode:    $desc ? (string) $desc : null,
            errorMessage: $normalized === 'failed'
                ? sprintf('LabsMobile DLR: %s', $desc ?: 'unknown')
                : null,
            permanent:    in_array($desc, ['REJECTD', 'UNDELIV', 'EXPIRED'], true),
        )];
    }

    // --- Internal ---

    private function normalizeStatus(string $desc, string $status): string
    {
        $byDesc = match ($desc) {
            'DELIVRD'                          => 'delivered',
            'ENROUTE', 'ACCEPTD'               => 'sent',
            'REJECTD', 'UNDELIV', 'EXPIRED'    => 'failed',
            ''                                 => null,
            default                            => null,
        };
        if ($byDesc !== null) {
            return $byDesc;
        }
        return $status === 'ok' ? 'delivered' : 'failed';
    }

    private function authHeaders(string $username, string $apiToken): array
    {
        return [
            'Authorization' => 'Basic ' . base64_encode("{$username}:{$apiToken}"),
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
        ];
    }

    private function callbackToken(): string
    {
        return hash_hmac('sha256', 'labsmobile-callback', (string) $this->getSharedConfig('api_token'));
    }
}
