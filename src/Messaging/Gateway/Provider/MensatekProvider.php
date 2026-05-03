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
 * Mensatek — Spanish multi-channel provider (SMS, WhatsApp, RCS).
 *
 * Auth: HTTP Basic with `UsuarioAPI:APIToken` (Tus Datos → Configurar Cuenta).
 * Wire format: POST + application/x-www-form-urlencoded; success envelope is
 * `{Res: <int>, Msgid: <int>, Cred: <float>, ...}` where `Res > 0` = queued
 * and `Res ∈ {-1,-2,-3}` map to auth/credit/parameter errors respectively.
 *
 * Webhook auth: Mensatek's panel does not sign callbacks; we generate a
 * `?token=<webhook_token>` URL the admin pastes into the panel and reject
 * mismatching tokens. When no token is configured, callbacks are accepted
 * (the registry setup notes flag this as an opt-in hardening step).
 *
 * TODO(verify): Mensatek exposes a separate /apiOTP endpoint for verification;
 * defer wiring until WSMS lands a SupportsVerify interface.
 */
class MensatekProvider extends AbstractProvider implements
    SupportsStatusCallback,
    SupportsInboundMessage
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const API_BASE = 'https://api.mensatek.com/v7';

    public function getId(): string
    {
        return 'mensatek';
    }

    public function getSupportedChannels(): array
    {
        return ['sms', 'whatsapp', 'rcs'];
    }

    public function getConfigSchema(): array
    {
        return [
            'shared' => [
                'api_user' => [
                    'type'        => 'string',
                    'label'       => __('API User', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Find it in the Mensatek panel under Tus Datos → Configurar Cuenta.', 'wp-sms'),
                ],
                'api_token' => [
                    'type'        => 'secret',
                    'label'       => __('API Token', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Listed alongside the API User in Tus Datos → Configurar Cuenta.', 'wp-sms'),
                ],
                'webhook_token' => [
                    'type'        => 'secret',
                    'label'       => __('Webhook Token', 'wp-sms'),
                    'required'    => false,
                    'description' => __('Optional. When set, callbacks must include this value as a `?token=` query arg — Mensatek does not sign webhooks, so this is the only way to authenticate inbound + status callbacks.', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'from' => [
                        'type'        => 'string',
                        'label'       => __('Sender ID / Number', 'wp-sms'),
                        'required'    => true,
                        'placeholder' => 'MIEMPRESA',
                        'description' => __('Alphanumeric sender ID (max 11 chars) or a validated number.', 'wp-sms'),
                    ],
                ],
                'whatsapp' => [
                    'from' => [
                        'type'        => 'string',
                        'label'       => __('WhatsApp Sender', 'wp-sms'),
                        'required'    => false,
                        'description' => __('Number provisioned in your Mensatek WhatsApp panel. Leave blank if your account has only one sender.', 'wp-sms'),
                    ],
                ],
                'rcs' => [
                    'agent_id' => [
                        'type'        => 'string',
                        'label'       => __('RCS Agent ID', 'wp-sms'),
                        'required'    => false,
                        'description' => __('Leave empty if your account has only one registered agent.', 'wp-sms'),
                    ],
                    'default_template_id' => [
                        'type'        => 'string',
                        'label'       => __('Default RCS Template ID', 'wp-sms'),
                        'required'    => false,
                        'description' => __('Used when no template_id is provided in the message meta.', 'wp-sms'),
                    ],
                ],
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        if (!$this->getSharedConfig('api_user') || !$this->getSharedConfig('api_token')) {
            return DeliveryResult::failed(__('Mensatek credentials not configured', 'wp-sms'));
        }

        return match ($message->getChannel()) {
            'sms'      => $this->sendSms($message),
            'whatsapp' => $this->sendWhatsapp($message),
            'rcs'      => $this->sendRcs($message),
            default    => DeliveryResult::failed(sprintf(__('Mensatek does not support channel %s', 'wp-sms'), $message->getChannel())),
        };
    }

    private function sendSms(MessageInterface $message): DeliveryResult
    {
        $from = (string) $this->getChannelConfig('sms', 'from', '');
        if ($from === '') {
            return DeliveryResult::failed(__('Mensatek SMS sender not configured', 'wp-sms'));
        }

        $payload = [
            'Resp'          => 'JSON',
            'Remitente'     => $from,
            'Mensaje'       => $message->getBody(),
            'Destinatarios' => wp_json_encode([['Movil' => $message->getRecipient()]]),
            'Fixutf8'       => '1',
        ];

        return $this->dispatch('/EnviarSMS', $payload);
    }

    private function sendWhatsapp(MessageInterface $message): DeliveryResult
    {
        $meta = $message->getMeta();

        $payload = [
            'Resp'          => 'JSON',
            'Destinatarios' => wp_json_encode([['Telefono' => $message->getRecipient()]]),
        ];

        if (!empty($meta['template_name'])) {
            $payload['Tipomensaje']  = 'PLANTILLA';
            $payload['DatosMensaje'] = wp_json_encode(['Plantilla' => (string) $meta['template_name']]);
        } elseif (!empty($meta['media_urls'][0])) {
            $url = (string) $meta['media_urls'][0];
            $payload['Tipomensaje']  = $this->detectWhatsappMediaType($url);
            $payload['DatosMensaje'] = wp_json_encode(array_filter([
                'URL'   => $url,
                'Texto' => $message->getBody() !== '' ? $message->getBody() : null,
            ]));
        } else {
            $payload['Tipomensaje']  = 'TEXTO';
            $payload['DatosMensaje'] = wp_json_encode(['Texto' => $message->getBody()]);
        }

        return $this->dispatch('/EnviarWHATSAPP', $payload);
    }

    private function sendRcs(MessageInterface $message): DeliveryResult
    {
        $meta = $message->getMeta();
        $templateId = (string) ($meta['template_id'] ?? $this->getChannelConfig('rcs', 'default_template_id', ''));
        if ($templateId === '') {
            return DeliveryResult::failed(__('Mensatek RCS requires a template_id (provide in message meta or set a default in the channel config)', 'wp-sms'));
        }

        $payload = [
            'Resp'          => 'JSON',
            'Plantilla'     => $templateId,
            'Destinatarios' => wp_json_encode([['Telefono' => $message->getRecipient()]]),
        ];

        $agente = (string) $this->getChannelConfig('rcs', 'agent_id', '');
        if ($agente !== '') {
            $payload['Agente'] = $agente;
        }

        return $this->dispatch('/EnviarRCS', $payload);
    }

    private function dispatch(string $path, array $payload): DeliveryResult
    {
        $result = $this->httpPost(self::API_BASE . $path, [
            'headers' => $this->authHeaders() + ['Content-Type' => 'application/x-www-form-urlencoded'],
            'body'    => http_build_query($payload),
        ]);

        if ($result instanceof DeliveryResult) {
            return $result;
        }

        if ($result['code'] === 401 || $result['code'] === 403) {
            return DeliveryResult::failed(__('Invalid Mensatek credentials', 'wp-sms'));
        }

        $data = json_decode($result['body'], true);

        if ($result['code'] < 200 || $result['code'] >= 300) {
            $error = is_array($data) ? ($data['Error'] ?? $data['message'] ?? null) : null;
            return DeliveryResult::failed($error ?: sprintf('HTTP %d', $result['code']));
        }

        if (!is_array($data) || !array_key_exists('Res', $data)) {
            return DeliveryResult::failed(__('Invalid response from Mensatek', 'wp-sms'));
        }

        return $this->interpretSendResponse($data);
    }

    private function interpretSendResponse(array $data): DeliveryResult
    {
        $res = (int) $data['Res'];

        if ($res > 0) {
            $msgid = isset($data['Msgid']) ? (string) $data['Msgid'] : null;
            return DeliveryResult::queued($msgid);
        }

        return match ($res) {
            -1      => DeliveryResult::failed(__('Invalid Mensatek credentials', 'wp-sms')),
            -2      => DeliveryResult::failed(__('Insufficient Mensatek credit', 'wp-sms')),
            -3      => DeliveryResult::failed(sprintf(__('Mensatek parameter error: %s', 'wp-sms'), (string) ($data['Error'] ?? __('unknown', 'wp-sms')))),
            default => DeliveryResult::failed(__('Mensatek did not accept the message', 'wp-sms')),
        };
    }

    public function getCredit(): ?string
    {
        if (!$this->getSharedConfig('api_user') || !$this->getSharedConfig('api_token')) {
            return null;
        }

        $result = $this->httpPost(self::API_BASE . '/GetCreditos', [
            'headers' => $this->authHeaders() + ['Content-Type' => 'application/x-www-form-urlencoded'],
            'body'    => http_build_query(['Resp' => 'JSON']),
        ]);

        if ($result instanceof DeliveryResult) {
            return null;
        }

        if ($result['code'] < 200 || $result['code'] >= 300) {
            return null;
        }

        $data = json_decode($result['body'], true);
        if (!is_array($data) || !isset($data['Cred'])) {
            return null;
        }

        return number_format((float) $data['Cred'], 2);
    }

    public function testConnection(): TestConnectionResult
    {
        if (!$this->getSharedConfig('api_user') || !$this->getSharedConfig('api_token')) {
            return TestConnectionResult::error(__('API User and API Token are required', 'wp-sms'));
        }

        $result = $this->httpPost(self::API_BASE . '/GetCreditos', [
            'headers' => $this->authHeaders() + ['Content-Type' => 'application/x-www-form-urlencoded'],
            'body'    => http_build_query(['Resp' => 'JSON']),
        ]);

        if (!$result instanceof DeliveryResult) {
            if ($result['code'] === 401 || $result['code'] === 403) {
                return TestConnectionResult::error(__('Invalid Mensatek credentials', 'wp-sms'));
            }
        }

        $data = $this->validateTestResponse($result, 'Mensatek');
        if ($data instanceof TestConnectionResult) {
            return $data;
        }

        if (!isset($data['Cred'])) {
            return TestConnectionResult::ok(__('Connected to Mensatek', 'wp-sms'));
        }

        $balance = number_format((float) $data['Cred'], 2);
        return TestConnectionResult::ok(
            sprintf(__('Connected to Mensatek — Credit: %s', 'wp-sms'), $balance),
            ['balance' => $balance],
        );
    }

    // --- SupportsStatusCallback ---

    public function getStatusCallbackUrl(): string
    {
        return $this->callbackUrl('callbacks/' . $this->getId() . '/status');
    }

    public function validateStatusCallback(\WP_REST_Request $request): bool
    {
        return $this->verifyToken($request);
    }

    /** @return StatusUpdate[] */
    public function parseStatusCallback(\WP_REST_Request $request): array
    {
        $payload = $this->callbackPayload($request);
        $servicio = (string) ($payload['Servicio'] ?? '');

        if ($servicio === 'SMSRECIBIDO') {
            return [];
        }

        $providerId = isset($payload['idMensaje']) ? (string) $payload['idMensaje'] : '';
        if ($providerId === '' || !isset($payload['Resultado'])) {
            return [];
        }

        $code = (int) $payload['Resultado'];
        [$status, $permanent] = $this->mapStatusCode($code);

        return [new StatusUpdate(
            providerId:   $providerId,
            status:       $status,
            errorCode:    (string) $code,
            errorMessage: $status === 'failed' ? sprintf('Mensatek code %d', $code) : null,
            permanent:    $permanent,
        )];
    }

    // --- SupportsInboundMessage ---

    public function getInboundCallbackUrl(): string
    {
        return $this->callbackUrl('callbacks/' . $this->getId() . '/inbound');
    }

    public function validateInboundCallback(\WP_REST_Request $request): bool
    {
        return $this->verifyToken($request);
    }

    /** @return InboundMessage[] */
    public function parseInboundCallback(\WP_REST_Request $request): array
    {
        $payload = $this->callbackPayload($request);
        if (((string) ($payload['Servicio'] ?? '')) !== 'SMSRECIBIDO') {
            return [];
        }

        $from = (string) ($payload['Remitente'] ?? '');
        if ($from === '') {
            return [];
        }

        return [new InboundMessage(
            from:       $from,
            to:         (string) ($payload['Movil'] ?? ''),
            body:       (string) ($payload['Mensaje'] ?? ''),
            providerId: isset($payload['idR']) ? (string) $payload['idR'] : null,
            meta:       array_filter([
                'timestamp'           => $payload['timestamp'] ?? null,
                'fecha'               => $payload['Fecha'] ?? null,
                'es_cert'             => isset($payload['EsCert']) ? (bool) $payload['EsCert'] : null,
                'reference'           => $payload['Referencia'] ?? null,
                'reply_to_provider_id' => isset($payload['idM']) ? (string) $payload['idM'] : null,
            ], fn($v) => $v !== null && $v !== ''),
        )];
    }

    // --- Internal ---

    private function authHeaders(): array
    {
        return [
            'Authorization' => 'Basic ' . base64_encode(
                ((string) $this->getSharedConfig('api_user', '')) . ':' . ((string) $this->getSharedConfig('api_token', ''))
            ),
            'Accept' => 'application/json',
        ];
    }

    private function callbackUrl(string $path): string
    {
        $token = (string) $this->getSharedConfig('webhook_token', '');
        return $token !== ''
            ? RestRoute::url($path, ['token' => $token])
            : RestRoute::url($path);
    }

    /**
     * Mensatek does not sign webhooks. When `webhook_token` is set, we require
     * the matching `?token=` query arg on every callback. When it's unset, we
     * accept callbacks (registry setup notes flag this as an opt-in hardening).
     */
    private function verifyToken(\WP_REST_Request $request): bool
    {
        $expected = (string) $this->getSharedConfig('webhook_token', '');
        if ($expected === '') {
            return true;
        }

        $supplied = (string) ($request->get_param('token') ?? '');
        if ($supplied === '') {
            return false;
        }

        return hash_equals($expected, $supplied);
    }

    /** @return array<string,mixed> */
    private function callbackPayload(\WP_REST_Request $request): array
    {
        $json = $request->get_json_params();
        if (is_array($json) && $json !== []) {
            return $json;
        }
        $params = $request->get_params();
        return is_array($params) ? $params : [];
    }

    private function detectWhatsappMediaType(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH) ?? '';
        $ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($ext) {
            'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv' => 'DOCUMENTO',
            default                                                          => 'IMAGEN',
        };
    }

    /**
     * Map a Mensatek status code into [normalized status, is permanent failure].
     *
     * Codes verified from mensatek.com/api-recepcion-confirmaciones.html.
     *
     * @return array{0: string, 1: bool}
     */
    private function mapStatusCode(int $code): array
    {
        return match (true) {
            in_array($code, [10, 11], true)                                                       => ['queued', false],
            in_array($code, [13, 14, 70, 106], true)                                              => ['delivered', false],
            in_array($code, [50, 51, 53, 54, 55, 101, 102, 103, 104, 110, 111, 112, 113], true) => ['failed', true],
            in_array($code, [52, 120, 121], true)                                                 => ['failed', false],
            default                                                                               => ['failed', false],
        };
    }
}
