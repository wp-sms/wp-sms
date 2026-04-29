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

/**
 * Cellsynt — Swedish SMS aggregator with worldwide delivery.
 *
 * Single endpoint POST /sms.php with username/password in the form body.
 * Success returns plain-text "OK: <tracking-id>"; anything else is an error
 * (often "Error: <reason>"). No balance, templates, or dynamic-options APIs.
 *
 * DLR + inbound MO are configured per-account in the Cellsynt portal and
 * carry no signature, so we authenticate them with separate URL tokens —
 * one for delivery reports, one for inbound — letting users rotate the
 * two independently when only one URL leaks.
 */
class CellsyntProvider extends AbstractProvider implements
    SupportsStatusCallback,
    SupportsInboundMessage
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const API_URL = 'https://se-1.cellsynt.net/sms.php';

    public function getId(): string
    {
        return 'cellsynt';
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
                    'description' => __('Your Cellsynt account username from the Cellsynt customer portal.', 'wp-sms'),
                ],
                'password' => [
                    'type'        => 'secret',
                    'label'       => __('Password', 'wp-sms'),
                    'required'    => true,
                    'description' => __('The Cellsynt account password paired with the username above.', 'wp-sms'),
                ],
                'callback_token_dlr' => [
                    'type'        => 'secret',
                    'label'       => __('Status Callback Token', 'wp-sms'),
                    'required'    => false,
                    'description' => __('Random shared secret appended as ?token=<value> to the delivery-report URL you register in the Cellsynt portal. Required to enable delivery receipts — Cellsynt does not sign webhooks, so the token is what authenticates them.', 'wp-sms'),
                ],
                'callback_token_mo' => [
                    'type'        => 'secret',
                    'label'       => __('Inbound Callback Token', 'wp-sms'),
                    'required'    => false,
                    'description' => __('Random shared secret appended as ?token=<value> to the inbound URL registered against your Cellsynt virtual number. Use a different value than the Status Callback Token so the two channels can be rotated independently.', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'originator' => [
                        'type'        => 'string',
                        'label'       => __('Sender Number', 'wp-sms'),
                        'required'    => true,
                        'placeholder' => 'MyBrand',
                        'description' => __('The sender ID recipients see. Up to 11 alphanumeric characters, up to 16 digits for a numeric MSISDN, or a 5-digit shortcode.', 'wp-sms'),
                    ],
                    'originator_type' => [
                        'type'    => 'select',
                        'label'   => __('Originator Type', 'wp-sms'),
                        'default' => 'alpha',
                        'options' => [
                            ['value' => 'alpha',     'label' => __('Alphanumeric (max 11 chars)', 'wp-sms')],
                            ['value' => 'numeric',   'label' => __('Numeric MSISDN (max 16 digits)', 'wp-sms')],
                            ['value' => 'shortcode', 'label' => __('Shortcode (5 digits)', 'wp-sms')],
                        ],
                        'description' => __('Pick the format that matches the Sender Number above. Alphanumeric is supported in most countries but not all — check Cellsynt coverage if delivery fails.', 'wp-sms'),
                    ],
                    'flash_sms' => [
                        'type'        => 'boolean',
                        'label'       => __('Send as Flash SMS', 'wp-sms'),
                        'default'     => false,
                        'description' => __('Display the message directly on the recipient\'s screen without saving to the inbox. Carrier support varies.', 'wp-sms'),
                    ],
                    'allow_concat' => [
                        'type'        => 'number',
                        'label'       => __('Max Concatenated Parts', 'wp-sms'),
                        'default'     => 6,
                        'description' => __('How many 160-character segments a long message may be split into (1–6). Cellsynt bills one SMS per segment.', 'wp-sms'),
                    ],
                ],
            ],
        ];
    }

    public function getFeatures(): array
    {
        return array_merge(parent::getFeatures(), [
            'flash_sms'        => true,
            'unicode'          => true,
            'delivery_receipt' => true,
            'incoming'         => true,
            'test_connection'  => true,
        ]);
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        $username = $this->getSharedConfig('username');
        $password = $this->getSharedConfig('password');

        if (!$username || !$password) {
            return DeliveryResult::failed(__('Cellsynt credentials not configured', 'wp-sms'));
        }

        $originator = $this->getChannelConfig('sms', 'originator');
        if (!$originator) {
            return DeliveryResult::failed(__('Cellsynt Sender Number not configured', 'wp-sms'));
        }

        $originatorType = (string) $this->getChannelConfig('sms', 'originator_type', 'alpha');
        $flashChannel   = (bool) $this->getChannelConfig('sms', 'flash_sms', false);
        $allowConcat    = (int) $this->getChannelConfig('sms', 'allow_concat', 6);

        $meta  = $message->getMeta();
        $flash = isset($meta['flash']) ? (bool) $meta['flash'] : $flashChannel;
        if (isset($meta['allow_concat'])) {
            $allowConcat = (int) $meta['allow_concat'];
        }
        $allowConcat = max(1, min(6, $allowConcat));

        $body = $message->getBody();
        $type = $this->resolveType($body, $flash, $meta['type'] ?? null);

        $form = [
            'username'       => (string) $username,
            'password'       => (string) $password,
            'destination'    => $message->getRecipient(),
            'originator'     => (string) $originator,
            'originatortype' => $originatorType,
            'text'           => $body,
            'type'           => $type,
            'charset'        => 'UTF-8',
            'allowconcat'    => $allowConcat,
        ];

        $result = $this->httpPost(self::API_URL, [
            'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
            'body'    => $form,
        ]);

        if ($result instanceof DeliveryResult) {
            return $result;
        }

        if ($result['code'] === 401 || $result['code'] === 403) {
            return DeliveryResult::failed(__('Invalid Cellsynt username or password', 'wp-sms'));
        }

        $rawBody = trim((string) $result['body']);

        if ($rawBody === '') {
            return DeliveryResult::failed(__('Empty response from Cellsynt', 'wp-sms'));
        }

        if (str_starts_with($rawBody, 'OK:')) {
            $payload = trim(substr($rawBody, 3));
            // Comma-separated tracking IDs for batch sends; we send single-recipient so take the first.
            $providerId = trim(explode(',', $payload)[0]);
            return DeliveryResult::sent($providerId !== '' ? $providerId : null);
        }

        $cleaned = preg_replace('/^Error:\s*/i', '', $rawBody);
        return DeliveryResult::failed(
            $cleaned !== '' ? $cleaned : sprintf(__('Cellsynt error (HTTP %d)', 'wp-sms'), (int) $result['code']),
        );
    }

    /**
     * Cellsynt has no public balance / account-info endpoint — credit must be
     * checked via the customer portal. Return null so the admin UI hides the
     * balance widget for this gateway.
     */
    public function getCredit(): ?string
    {
        return null;
    }

    public function testConnection(): TestConnectionResult
    {
        $username = $this->getSharedConfig('username');
        $password = $this->getSharedConfig('password');

        if (!$username || !$password) {
            return TestConnectionResult::error(__('Username and Password are required', 'wp-sms'));
        }

        // No dedicated test/balance endpoint exists. Probe with empty `destination`:
        // the API responds with a parameter error if creds are valid, and an auth
        // error otherwise — neither costs a credit since no message is dispatched.
        $result = $this->httpPost(self::API_URL, [
            'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
            'body'    => [
                'username'       => (string) $username,
                'password'       => (string) $password,
                'destination'    => '',
                'originator'     => 'wsms',
                'originatortype' => 'alpha',
                'text'           => 'wsms test',
                'type'           => 'text',
                'charset'        => 'UTF-8',
            ],
        ]);

        if ($result instanceof DeliveryResult) {
            return TestConnectionResult::error(
                sprintf(__('Could not reach the %s API. Check your server\'s internet connection.', 'wp-sms'), 'Cellsynt'),
            );
        }

        if ($result['code'] === 401 || $result['code'] === 403) {
            return TestConnectionResult::error(__('Invalid Cellsynt username or password', 'wp-sms'));
        }

        $rawBody = trim((string) $result['body']);

        if (preg_match('/auth|password|username|credentials|denied/i', $rawBody)) {
            return TestConnectionResult::error(__('Invalid Cellsynt username or password', 'wp-sms'));
        }

        if (preg_match('/destination|recipient|number|msisdn/i', $rawBody)) {
            return TestConnectionResult::ok(__('Connected — credentials accepted', 'wp-sms'));
        }

        if (str_starts_with($rawBody, 'OK:')) {
            return TestConnectionResult::ok(__('Connected — credentials accepted', 'wp-sms'));
        }

        return TestConnectionResult::error(
            sprintf(__('Unexpected response from Cellsynt: %s', 'wp-sms'), mb_substr($rawBody, 0, 200)),
        );
    }

    // --- SupportsStatusCallback ---

    public function getStatusCallbackUrl(): string
    {
        return RestRoute::url('callbacks/' . $this->getId() . '/status');
    }

    public function validateStatusCallback(\WP_REST_Request $request): bool
    {
        return $this->verifyToken($request, 'callback_token_dlr');
    }

    /**
     * Parse a Cellsynt DLR. The portal lets users template the callback URL with
     * placeholders (no fixed body shape), so we read the documented core fields
     * (`trackingid` + `status`) from query/form params and accept either GET or
     * POST. Unknown statuses degrade to "sent" rather than dropping the update.
     *
     * TODO(verify): confirm trackingid/status field names against a live DLR
     * payload during manual testing; portal templates may use different keys.
     *
     * @return StatusUpdate[]
     */
    public function parseStatusCallback(\WP_REST_Request $request): array
    {
        $trackingId = (string) ($request->get_param('trackingid') ?? '');
        $rawStatus  = strtolower((string) ($request->get_param('status') ?? ''));

        if ($trackingId === '' || $rawStatus === '') {
            return [];
        }

        $normalized = match (true) {
            in_array($rawStatus, ['delivered', 'success', 'ok'], true)            => 'delivered',
            in_array($rawStatus, ['failed', 'undelivered', 'expired'], true)      => 'failed',
            default                                                                => 'sent',
        };

        return [new StatusUpdate(
            providerId:   $trackingId,
            status:       $normalized,
            errorCode:    $normalized === 'failed' ? $rawStatus : null,
            errorMessage: $normalized === 'failed' ? sprintf(__('Cellsynt: %s', 'wp-sms'), $rawStatus) : null,
            permanent:    $normalized === 'failed',
        )];
    }

    // --- SupportsInboundMessage ---

    public function getInboundCallbackUrl(): string
    {
        return RestRoute::url('callbacks/' . $this->getId() . '/inbound');
    }

    public function validateInboundCallback(\WP_REST_Request $request): bool
    {
        return $this->verifyToken($request, 'callback_token_mo');
    }

    /**
     * Parse a Cellsynt inbound MO. Portal templates can name the fields
     * differently — try the canonical names then fall back to common aliases.
     *
     * TODO(verify): confirm originator/destination/text field names against a
     * live MO payload during manual testing.
     *
     * @return InboundMessage[]
     */
    public function parseInboundCallback(\WP_REST_Request $request): array
    {
        $from = (string) ($request->get_param('originator') ?? $request->get_param('from') ?? '');
        if ($from === '') {
            return [];
        }

        $to   = (string) ($request->get_param('destination') ?? $request->get_param('to') ?? '');
        $text = (string) ($request->get_param('text') ?? $request->get_param('message') ?? '');

        return [new InboundMessage(
            from: $from,
            to:   $to,
            body: $text,
        )];
    }

    // --- Internal ---

    private function verifyToken(\WP_REST_Request $request, string $configKey): bool
    {
        $expected = (string) $this->getSharedConfig($configKey, '');
        if ($expected === '') {
            return false;
        }
        $given = (string) ($request->get_param('token') ?? '');
        if ($given === '') {
            return false;
        }
        return hash_equals($expected, $given);
    }

    private function resolveType(string $body, bool $flash, mixed $explicitType): string
    {
        if (is_string($explicitType) && $explicitType !== '') {
            return $explicitType;
        }
        if ($flash) {
            return 'flash';
        }
        // Anything outside 7-bit ASCII triggers UCS-2 unicode mode. This is slightly
        // over-eager for the GSM-7 extension set (Swedish å/ä/ö are technically
        // GSM-7) but garbling unicode is worse than burning a couple extra parts.
        if (preg_match('/[^\x00-\x7F]/u', $body) === 1) {
            return 'unicode';
        }
        return 'text';
    }
}
