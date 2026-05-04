<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Catalog\TemplateCatalogManager;
use WSms\Messaging\Catalog\TemplateMapping;
use WSms\Messaging\Catalog\VariableStyle;
use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\SupportsTemplates;
use WSms\Messaging\Gateway\AbstractProvider;

defined('ABSPATH') || exit;

/**
 * Espay (Indonesia) — SMS + WhatsApp gateway.
 *
 * Single endpoint POST https://api.espay.id/btext/send/outgoing handles both
 * channels — SMS vs WhatsApp is selected by the body's message_type field
 * (`SMS` vs `WA`). Auth is via a SHA-256 signature computed from sender_id +
 * rq_uuid + message_type + phone + signature_key. The WhatsApp endpoint
 * additionally requires HTTP Basic auth and a pre-approved template_id.
 *
 * Sales-onboarded only: Espay issues sender IDs, signature keys, and (for
 * WhatsApp) Basic-auth credentials and template IDs out-of-band, and the
 * caller's outbound IP must be whitelisted with Espay support — sends fail
 * with response code 0601 until they do.
 */
class EspayProvider extends AbstractProvider implements SupportsTemplates
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const ENDPOINT = 'https://api.espay.id/btext/send/outgoing';

    private ?TemplateCatalogManager $catalogManager = null;

    public function setCatalogManager(TemplateCatalogManager $manager): void
    {
        $this->catalogManager = $manager;
    }

    public function getId(): string
    {
        return 'espay';
    }

    public function getSupportedChannels(): array
    {
        return ['sms', 'whatsapp'];
    }

    public function getConfigSchema(): array
    {
        return [
            'shared' => [
                'sender_id' => [
                    'type'        => 'string',
                    'required'    => true,
                    'label'       => __('Sender ID', 'wp-sms'),
                    'placeholder' => 'SGOPLUS',
                    'description' => __('Alphanumeric sender mask issued by Espay during onboarding (used as the sender for SMS and the API identifier for WhatsApp).', 'wp-sms'),
                ],
                'signature_key' => [
                    'type'        => 'secret',
                    'required'    => true,
                    'label'       => __('Signature Key', 'wp-sms'),
                    'description' => __('Signature key provided by Espay support; used to sign every SMS and WhatsApp request.', 'wp-sms'),
                ],
            ],
            'channels' => [
                'whatsapp' => [
                    'basic_auth_username' => [
                        'type'        => 'string',
                        'required'    => true,
                        'label'       => __('WhatsApp Basic Auth Username', 'wp-sms'),
                        'description' => __('HTTP Basic auth username issued by Espay for the WhatsApp endpoint (separate from your Sender ID).', 'wp-sms'),
                    ],
                    'basic_auth_password' => [
                        'type'        => 'secret',
                        'required'    => true,
                        'label'       => __('WhatsApp Basic Auth Password', 'wp-sms'),
                        'description' => __('HTTP Basic auth password issued by Espay for the WhatsApp endpoint.', 'wp-sms'),
                    ],
                ],
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        $senderId     = (string) $this->getSharedConfig('sender_id', '');
        $signatureKey = (string) $this->getSharedConfig('signature_key', '');

        if ($senderId === '' || $signatureKey === '') {
            return DeliveryResult::failed(__('Espay credentials are not configured.', 'wp-sms'));
        }

        $channel = $message->getChannel();
        $messageType = $channel === 'whatsapp' ? 'WA' : 'SMS';
        $phone = ltrim($message->getRecipient(), '+');
        $rqUuid = $this->generateRqUuid();

        $body = [
            'rq_uuid'      => $rqUuid,
            'sender_id'    => $senderId,
            'message_type' => $messageType,
            'phone_number' => $phone,
            'message'      => $message->getBody(),
        ];

        $headers = [];

        if ($channel === 'whatsapp') {
            $username = (string) $this->getChannelConfig('whatsapp', 'basic_auth_username', '');
            $password = (string) $this->getChannelConfig('whatsapp', 'basic_auth_password', '');

            if ($username === '' || $password === '') {
                return DeliveryResult::failed(__('Espay WhatsApp Basic auth credentials are not configured.', 'wp-sms'));
            }

            $template = $this->resolveWhatsappTemplate($message->getMeta(), $message->getBody());
            if ($template === null) {
                return DeliveryResult::failed(__('Espay WhatsApp requires a template; configure a template mapping or pass template_mode with a provider_template_id in message meta.', 'wp-sms'));
            }

            $body['template_id'] = $template['template_id'];
            $body['message']     = $template['message'];

            $broadcast = $message->getMeta()['broadcast'] ?? null;
            if ($broadcast !== null && $broadcast !== '') {
                $body['broadcast'] = (string) $broadcast;
            }

            $headers['Authorization'] = 'Basic ' . base64_encode($username . ':' . $password);
        }

        $body['signature'] = $this->computeSignature($senderId, $rqUuid, $messageType, $phone, $signatureKey);

        $args = ['body' => $body];
        if (!empty($headers)) {
            $args['headers'] = $headers;
        }

        $result = $this->httpPost(self::ENDPOINT, $args);

        if ($result instanceof DeliveryResult) {
            return $result;
        }

        if ($result['code'] < 200 || $result['code'] >= 300) {
            return DeliveryResult::failed(
                sprintf(__('Espay HTTP error (%d).', 'wp-sms'), $result['code']),
                meta: array_filter(['espay_http_code' => $result['code'] ?: null]),
            );
        }

        $data = json_decode($result['body'], true);
        if (!is_array($data)) {
            return DeliveryResult::failed(__('Espay returned an unexpected response.', 'wp-sms'));
        }

        $errorCode = (string) ($data['error_code'] ?? '');

        if ($errorCode === '0000') {
            return DeliveryResult::queued($rqUuid);
        }

        return DeliveryResult::failed(
            $this->mapErrorMessage($errorCode, $data),
            meta: array_filter(['espay_error_code' => $errorCode !== '' ? $errorCode : null]),
        );
    }

    // --- SupportsTemplates ---

    public function requiresTemplateForChannel(string $channel): bool
    {
        return $channel === 'whatsapp';
    }

    public function getVariableStyle(): VariableStyle
    {
        return VariableStyle::Named;
    }

    public function buildTemplatePayload(TemplateMapping $mapping, array $resolvedVariables): array
    {
        return [
            'template_id'         => $mapping->providerTemplateId,
            'resolved_variables'  => $resolvedVariables,
        ];
    }

    /**
     * Resolve the WhatsApp template_id and rendered message body.
     *
     * Order of resolution:
     *   1. Direct mode: meta['template_mode'] truthy AND meta['provider_template_id'] set
     *      → use those verbatim, render body via meta['template_variables'].
     *   2. Catalog mode: meta['template_type'] set AND catalogManager wired up
     *      → resolve mapping, use its providerTemplateId, render body using its variableMap.
     *   3. Otherwise: null (caller fails the send).
     *
     * @return array{template_id: string, message: string}|null
     */
    private function resolveWhatsappTemplate(array $meta, string $body): ?array
    {
        if (!empty($meta['template_mode']) && !empty($meta['provider_template_id'])) {
            $variables = $meta['template_variables'] ?? [];
            return [
                'template_id' => (string) $meta['provider_template_id'],
                'message'     => $this->renderNamedTemplate($body, $variables),
            ];
        }

        $templateType = $meta['template_type'] ?? null;
        if ($templateType && $this->catalogManager) {
            $mapping = $this->catalogManager->resolveMapping((string) $templateType, $this->getId());
            if ($mapping) {
                $variables = $meta['template_variables'] ?? [];
                $renderingBody = $body !== '' ? $body : $mapping->providerTemplateBody;
                return [
                    'template_id' => $mapping->providerTemplateId,
                    'message'     => $this->renderNamedTemplate($renderingBody, $variables),
                ];
            }
        }

        return null;
    }

    /**
     * Substitute {{var}} placeholders in the body with values from the
     * variable map. Unreferenced placeholders pass through unchanged so a
     * mis-mapped variable surfaces in delivery rather than being silently
     * dropped.
     */
    private function renderNamedTemplate(string $body, array $variables): string
    {
        if ($body === '' || empty($variables)) {
            return $body;
        }

        $replacements = [];
        foreach ($variables as $name => $value) {
            $replacements['{{' . $name . '}}'] = (string) $value;
        }

        return strtr($body, $replacements);
    }

    private function computeSignature(
        string $senderId,
        string $rqUuid,
        string $messageType,
        string $phone,
        string $signatureKey
    ): string {
        $base = strtoupper(sprintf('#%s#%s#%s#%s#', $senderId, $rqUuid, $messageType, $phone));
        return hash('sha256', $base . $signatureKey . '#');
    }

    private function mapErrorMessage(string $code, array $data): string
    {
        $known = [
            '0001' => __('Malformed or missing parameter.', 'wp-sms'),
            '0011' => __('Invalid signature — check your Signature Key.', 'wp-sms'),
            '0015' => __('Espay internal error.', 'wp-sms'),
            '0041' => __('Invalid recipient phone number.', 'wp-sms'),
            '0050' => __('Empty required parameter.', 'wp-sms'),
            '0096' => __('Unsupported message type.', 'wp-sms'),
            '0401' => __('Request declined by Espay.', 'wp-sms'),
            '0601' => __('Server IP not whitelisted with Espay — provide your outbound IP to Espay support.', 'wp-sms'),
            '800'  => __('Insufficient balance on the Espay account.', 'wp-sms'),
        ];

        if (isset($known[$code])) {
            return $known[$code];
        }

        $providerMessage = $data['error_message'] ?? $data['error_desc'] ?? '';
        if (is_string($providerMessage) && $providerMessage !== '') {
            return $providerMessage;
        }

        return $code !== ''
            ? sprintf(__('Espay send failed (code %s).', 'wp-sms'), $code)
            : __('Espay send failed.', 'wp-sms');
    }

    /**
     * Generate a v4 UUID for rq_uuid. Extracted as a protected method so
     * tests can pin a deterministic value when verifying signature vectors.
     */
    protected function generateRqUuid(): string
    {
        if (function_exists('wp_generate_uuid4')) {
            return wp_generate_uuid4();
        }

        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            random_int(0, 0xffff),
            random_int(0, 0xffff),
            random_int(0, 0xffff),
            random_int(0, 0x0fff) | 0x4000,
            random_int(0, 0x3fff) | 0x8000,
            random_int(0, 0xffff),
            random_int(0, 0xffff),
            random_int(0, 0xffff),
        );
    }
}
