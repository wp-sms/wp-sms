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

class SpotHitProvider extends AbstractProvider implements SupportsStatusCallback
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const API_BASE = 'https://www.spot-hit.fr';

    // TODO(inbound): Spot-Hit markets "réponses et STOPS" but doc.spot-hit.fr/api/reponses.html
    // is Cloudflare-blocked and no SDK implements MO. Defer SupportsInboundMessage until the
    // webhook field shape is verified against an authoritative source.

    // TODO(opt-out): erreurs.html lists integer error codes but no public mapping is reachable.
    // statut=4/5 don't distinguish opt-out either. Defer SupportsOptOutDetection until the codes
    // are documented somewhere we can quote.

    public function getId(): string
    {
        return 'spothit';
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
                    'label'       => __('API Key', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Your Spot-Hit API Key, generated in the account dashboard.', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'from' => [
                        'type'        => 'string',
                        'label'       => __('Sender ID', 'wp-sms'),
                        'required'    => true,
                        'description' => __('3–11 alphanumeric characters (a–zA–Z). The default fallback on the Spot-Hit network is a 5-digit shortcode.', 'wp-sms'),
                        'placeholder' => 'WSMS',
                    ],
                    'type' => [
                        'type'        => 'select',
                        'label'       => __('SMS Type', 'wp-sms'),
                        'required'    => false,
                        'default'     => 'lowcost',
                        'description' => __('Low-cost is suitable for transactional traffic. Premium uses higher-quality routes for marketing, but messages must include a "STOP au XXXXX" clause per French regulation. Defaults to low-cost.', 'wp-sms'),
                        'options'     => [
                            ['value' => 'lowcost', 'label' => __('Low cost', 'wp-sms')],
                            ['value' => 'premium', 'label' => __('Premium', 'wp-sms')],
                        ],
                    ],
                    'campaign_name' => [
                        'type'        => 'string',
                        'label'       => __('Campaign Name', 'wp-sms'),
                        'required'    => false,
                        'description' => __('Optional dashboard-only label for grouping sends. Not visible to recipients.', 'wp-sms'),
                    ],
                ],
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        $apiKey = $this->getSharedConfig('api_key');
        if (!$apiKey) {
            return DeliveryResult::failed(__('Spot-Hit credentials not configured', 'wp-sms'));
        }

        $sender = $this->getChannelConfig('sms', 'from');
        if (!$sender) {
            return DeliveryResult::failed(__('Spot-Hit Sender ID not configured', 'wp-sms'));
        }

        $body = [
            'key'           => $apiKey,
            'destinataires' => $message->getRecipient(),
            'type'          => $this->getChannelConfig('sms', 'type', 'lowcost'),
            'message'       => $message->getBody(),
            'expediteur'    => $sender,
            'smslong'       => 1,
            'url'           => $this->getStatusCallbackUrl(),
        ];

        $campaignName = $this->getChannelConfig('sms', 'campaign_name');
        if ($campaignName) {
            $body['nom'] = $campaignName;
        }

        $result = $this->httpPost(self::API_BASE . '/api/envoyer/sms', [
            'body' => $body,
        ]);

        if ($result instanceof DeliveryResult) {
            return $result;
        }

        $data = json_decode($result['body'], true);

        if ($result['code'] === 401 || $result['code'] === 403) {
            return DeliveryResult::failed(__('Invalid Spot-Hit API Key', 'wp-sms'));
        }

        if (!is_array($data)) {
            return DeliveryResult::failed(
                sprintf(__('Spot-Hit returned an unparseable response (HTTP %d)', 'wp-sms'), $result['code']),
            );
        }

        if (($data['resultat'] ?? null) == 1) {
            $providerId = isset($data['id']) ? (string) $data['id'] : null;
            return DeliveryResult::sent(providerId: $providerId);
        }

        $errors = $data['erreurs'] ?? [];
        $errorList = is_array($errors) ? implode(',', $errors) : (string) $errors;

        return DeliveryResult::failed(
            sprintf(__('Spot-Hit rejected the request (erreurs: %s)', 'wp-sms'), $errorList ?: 'unknown'),
            meta: array_filter([
                'spothit_errors' => is_array($errors) ? $errors : ($errors !== '' ? [$errors] : null),
                'http_code'      => $result['code'],
            ], fn($v) => $v !== null),
        );
    }

    public function getCredit(): ?string
    {
        $apiKey = $this->getSharedConfig('api_key');
        if (!$apiKey) {
            return null;
        }

        $result = $this->httpPost(self::API_BASE . '/api/credits', [
            'body' => ['key' => $apiKey],
        ]);

        if ($result instanceof DeliveryResult) {
            return null;
        }

        $data = json_decode($result['body'], true);
        if (!is_array($data) || ($data['resultat'] ?? null) != 1) {
            return null;
        }

        $credits = $data['credits'] ?? null;
        if ($credits === null) {
            return null;
        }

        return ((string) $credits) . ' €';
    }

    public function testConnection(): TestConnectionResult
    {
        $apiKey = $this->getSharedConfig('api_key');
        if (!$apiKey) {
            return TestConnectionResult::error(__('API Key is required', 'wp-sms'));
        }

        $result = $this->httpPost(self::API_BASE . '/api/credits', [
            'body' => ['key' => $apiKey],
        ]);

        if (!$result instanceof DeliveryResult) {
            if ($result['code'] === 401 || $result['code'] === 403) {
                return TestConnectionResult::error(__('Invalid Spot-Hit API Key', 'wp-sms'));
            }
        }

        $data = $this->validateTestResponse($result, 'Spot-Hit');
        if ($data instanceof TestConnectionResult) {
            return $data;
        }

        if (($data['resultat'] ?? null) != 1) {
            return TestConnectionResult::error(__('Invalid Spot-Hit API Key', 'wp-sms'));
        }

        $credits = $data['credits'] ?? 'N/A';

        return TestConnectionResult::ok(
            sprintf(__('Connected — Balance: %s €', 'wp-sms'), $credits),
            ['balance' => (string) $credits],
        );
    }

    // --- SupportsStatusCallback ---

    public function getStatusCallbackUrl(): string
    {
        return RestRoute::url('callbacks/' . $this->getId() . '/status', ['token' => $this->callbackToken()]);
    }

    public function validateStatusCallback(\WP_REST_Request $request): bool
    {
        $expected = $this->callbackToken();
        if ($expected === '') {
            return false;
        }
        $given = (string) ($request->get_param('token') ?? '');
        if ($given === '') {
            return false;
        }
        return hash_equals($expected, $given);
    }

    /** @return StatusUpdate[] */
    public function parseStatusCallback(\WP_REST_Request $request): array
    {
        $statut    = $request->get_param('statut');
        $messageId = $request->get_param('id_message');

        if ($statut === null || $statut === '' || empty($messageId)) {
            return [];
        }

        $rawStatut = (string) $statut;

        // Status enum from youlead-bow Symfony webhook bridge:
        // 0=En attente (pending), 1=Livré (delivered), 2=Envoyé (sent),
        // 3=En cours (in progress), 4=Echec (failed), 5=Expiré (expired)
        $status = match ($rawStatut) {
            '0'           => 'queued',
            '1'           => 'delivered',
            '2', '3'      => 'sent',
            '4', '5'      => 'failed',
            default       => $rawStatut,
        };

        $permanent = in_array($rawStatut, ['4', '5'], true);

        return [new StatusUpdate(
            providerId:   (string) $messageId,
            status:       $status,
            errorCode:    $status === 'failed' ? $rawStatut : null,
            errorMessage: $status === 'failed'
                ? sprintf(__('Spot-Hit DLR statut=%s', 'wp-sms'), $rawStatut)
                : null,
            permanent:    $permanent,
        )];
    }

    // --- Internal ---

    private function callbackToken(): string
    {
        $apiKey = (string) $this->getSharedConfig('api_key', '');
        if ($apiKey === '') {
            return '';
        }
        return hash_hmac('sha256', 'spothit-callback', $apiKey);
    }
}
