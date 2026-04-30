<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Catalog\ProviderTemplate;
use WSms\Messaging\Catalog\TemplateCatalogException;
use WSms\Messaging\Catalog\TemplateCatalogManager;
use WSms\Messaging\Catalog\TemplateMapping;
use WSms\Messaging\Catalog\TemplateStatus;
use WSms\Messaging\Catalog\VariableStyle;
use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\InboundMessage;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\StatusUpdate;
use WSms\Messaging\Contracts\SupportsDynamicOptions;
use WSms\Messaging\Contracts\SupportsInboundMessage;
use WSms\Messaging\Contracts\SupportsOptOutDetection;
use WSms\Messaging\Contracts\SupportsStatusCallback;
use WSms\Messaging\Contracts\SupportsTemplateFetch;
use WSms\Messaging\Contracts\SupportsTemplates;
use WSms\Messaging\Contracts\TestConnectionResult;
use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Rest\RestRoute;

defined('ABSPATH') || exit;

// TODO(verify): provider exposes /OTPApi/send (generate+verify); defer until WSMS adds SupportsVerify
// TODO(voice): provider has /OBD/Voice API; defer until WSMS adds a Voice channel
// TODO(rcs-rich): RCS singlecard/carousel templates documented but WSMS has no rich-card schema; only RCS text mode is wired

class SmsGatewayCenterProvider extends AbstractProvider implements
    SupportsStatusCallback,
    SupportsInboundMessage,
    SupportsOptOutDetection,
    SupportsDynamicOptions,
    SupportsTemplateFetch,
    SupportsTemplates
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const SMS_SEND_URL    = 'https://www.smsgateway.center/SMSApi/rest/send';
    private const RCS_SEND_URL    = 'https://www.smsgateway.center/RCSApi/send';
    private const BALANCE_URL     = 'https://www.smsgateway.center/SMSApi/rest/balanceValidityCheck';
    private const PROFILE_URL     = 'https://www.smsgateway.center/library/api/self/ViewProfile/';
    private const SENDER_LIST_URL = 'https://www.smsgateway.center/library/api/self/SenderName/';
    private const TEMPLATE_LIST_URL = 'https://www.smsgateway.center/library/api/self/Templates/';

    private ?TemplateCatalogManager $catalogManager = null;

    public function setCatalogManager(TemplateCatalogManager $manager): void
    {
        $this->catalogManager = $manager;
    }

    public function getId(): string
    {
        return 'smsgatewaycenter';
    }

    public function getSupportedChannels(): array
    {
        return ['sms', 'rcs'];
    }

    public function getConfigSchema(): array
    {
        return [
            'shared' => [
                'auth_method' => [
                    'type'    => 'select',
                    'label'   => __('Authentication Method', 'wp-sms'),
                    'required' => true,
                    'default' => 'api_key',
                    'options' => [
                        ['value' => 'api_key',     'label' => __('API Key (recommended)', 'wp-sms')],
                        ['value' => 'credentials', 'label' => __('User ID + Password', 'wp-sms')],
                    ],
                    'description' => __('Choose how WSMS authenticates with SMSGatewayCenter. API Key is preferred — generate one from the dashboard.', 'wp-sms'),
                ],
                'api_key' => [
                    'type'        => 'secret',
                    'label'       => __('API Key', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Generate from the dashboard under Account → Generate API Key.', 'wp-sms'),
                    'show_if'     => ['field' => 'auth_method', 'equals' => 'api_key'],
                ],
                'user_id' => [
                    'type'        => 'string',
                    'label'       => __('User ID', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Your registered SMSGatewayCenter username.', 'wp-sms'),
                    'show_if'     => ['field' => 'auth_method', 'equals' => 'credentials'],
                ],
                'password' => [
                    'type'        => 'secret',
                    'label'       => __('Password', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Your SMSGatewayCenter account password.', 'wp-sms'),
                    'show_if'     => ['field' => 'auth_method', 'equals' => 'credentials'],
                ],
                'webhook_token' => [
                    'type'        => 'secret',
                    'label'       => __('Webhook Token', 'wp-sms'),
                    'required'    => false,
                    'description' => __('Append ?token=<value> to the DLR and Inbound webhook URLs you register in the SMSGatewayCenter dashboard. Required because neither webhook is signed.', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'sender_id' => [
                        'type'        => 'string',
                        'label'       => __('Sender ID (Header)', 'wp-sms'),
                        'required'    => true,
                        'dynamic'     => true,
                        'description' => __('DLT-approved 6-character alphabetic Header (e.g. SMSGAT). Demo accounts use SMSGAT.', 'wp-sms'),
                        'placeholder' => 'SMSGAT',
                    ],
                    'duplicate_check' => [
                        'type'        => 'boolean',
                        'label'       => __('Reject Duplicate Messages', 'wp-sms'),
                        'default'     => true,
                        'description' => __('Server-side filter that drops identical messages sent within a short window. Recommended.', 'wp-sms'),
                    ],
                ],
                'rcs' => [
                    'sender_id' => [
                        'type'        => 'string',
                        'label'       => __('RCS Sender ID', 'wp-sms'),
                        'required'    => true,
                        'dynamic'     => true,
                        'description' => __('Approved RCS bot/header registered with SMSGatewayCenter.', 'wp-sms'),
                    ],
                ],
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        $authError = $this->validateAuthConfigured();
        if ($authError !== null) {
            return $authError;
        }

        $channel = $message->getChannel();

        return match ($channel) {
            'sms' => $this->sendSms($message),
            'rcs' => $this->sendRcs($message),
            default => DeliveryResult::failed(sprintf(__('Unsupported channel: %s', 'wp-sms'), $channel)),
        };
    }

    private function sendSms(MessageInterface $message): DeliveryResult
    {
        $senderId = $this->getChannelConfig('sms', 'sender_id');
        if (!$senderId) {
            return DeliveryResult::failed(__('SMSGatewayCenter Sender ID not configured for SMS', 'wp-sms'));
        }

        $meta = $message->getMeta();
        $body = $this->resolveSmsBody($message, $meta);
        if ($body === null) {
            return DeliveryResult::failed(__('Cannot send SMS without a body or resolved template', 'wp-sms'));
        }

        $params = [
            'sendMethod'    => 'simpleMsg',
            'mobile'        => $this->normalizeMobile($message->getRecipient()),
            'senderId'      => $senderId,
            'msgType'       => $this->detectMsgType($body),
            'msg'           => $body,
            'format'        => 'json',
            'duplicateCheck' => $this->getChannelConfig('sms', 'duplicate_check', true) ? 'true' : 'false',
        ];

        if (!empty($meta['flash_sms'])) {
            $params['flashMsg'] = 'true';
        }

        return $this->postFormSend(self::SMS_SEND_URL, $params);
    }

    private function sendRcs(MessageInterface $message): DeliveryResult
    {
        $senderId = $this->getChannelConfig('rcs', 'sender_id');
        if (!$senderId) {
            return DeliveryResult::failed(__('SMSGatewayCenter Sender ID not configured for RCS', 'wp-sms'));
        }

        $params = [
            'sendMethod' => 'quick',
            'mobile'     => $this->normalizeMobile($message->getRecipient()),
            'senderId'   => $senderId,
            'msgType'    => 'text',
            'msg'        => $message->getBody(),
            'format'     => 'json',
        ];

        return $this->postFormSend(self::RCS_SEND_URL, $params);
    }

    private function resolveSmsBody(MessageInterface $message, array $meta): ?string
    {
        // Direct template mode: the catalog (or flow) handed us a resolved provider template id.
        // SMSGatewayCenter has no template_id field on the send endpoint — DLT enforcement is
        // server-side via senderId+content match — so we pull the cached provider template body
        // and interpolate variables ourselves.
        if (!empty($meta['template_mode']) && !empty($meta['provider_template_id']) && $this->catalogManager) {
            try {
                $templates = $this->catalogManager->getTemplates($this->getId());
            } catch (TemplateCatalogException $e) {
                $templates = [];
            }

            foreach ($templates as $tpl) {
                if ($tpl->id === (string) $meta['provider_template_id']) {
                    return $this->interpolate($tpl->bodyText, $meta['template_variables'] ?? []);
                }
            }
        }

        // Catalog-resolved: well-known template type → registered DLT body.
        $templateType = $meta['template_type'] ?? null;
        if ($templateType && $this->catalogManager) {
            $mapping = $this->catalogManager->resolveMapping($templateType, $this->getId());
            if ($mapping && $mapping->providerTemplateBody !== '') {
                $resolved = $mapping->resolveVariables($meta['template_variables'] ?? []);
                return $this->interpolate($mapping->providerTemplateBody, $resolved);
            }
        }

        $body = $message->getBody();
        return $body !== '' ? $body : null;
    }

    private function postFormSend(string $url, array $params): DeliveryResult
    {
        $args = [
            'headers' => array_merge($this->buildAuthHeaders(), [
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Accept'       => 'application/json',
            ]),
            'body' => http_build_query(array_merge($params, $this->buildAuthBody())),
        ];

        $result = $this->httpPost($url, $args);
        if ($result instanceof DeliveryResult) {
            return $result;
        }

        $data = json_decode($result['body'], true);
        if (!is_array($data)) {
            return DeliveryResult::failed(sprintf(__('Invalid response from SMSGatewayCenter (HTTP %d)', 'wp-sms'), $result['code']));
        }

        if (($data['status'] ?? '') === 'success') {
            return DeliveryResult::sent(
                providerId: isset($data['transactionId']) ? (string) $data['transactionId'] : null,
                meta: array_filter([
                    'sgc_status_code' => isset($data['statusCode']) ? (string) $data['statusCode'] : null,
                ]),
            );
        }

        $errorCode = isset($data['statusCode']) ? (string) $data['statusCode'] : null;

        return DeliveryResult::failed(
            $data['reason'] ?? sprintf(__('SMSGatewayCenter send failed (HTTP %d)', 'wp-sms'), $result['code']),
            meta: array_filter([
                'sgc_error_code' => $errorCode,
                'sgc_http_code'  => $result['code'] ?: null,
            ]),
        );
    }

    public function getCredit(): ?string
    {
        if ($this->validateAuthConfigured() !== null) {
            return null;
        }

        $result = $this->httpPost(self::BALANCE_URL, [
            'headers' => array_merge($this->buildAuthHeaders(), [
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Accept'       => 'application/json',
            ]),
            'body' => http_build_query(array_merge(['format' => 'json'], $this->buildAuthBody())),
        ]);

        if ($result instanceof DeliveryResult) {
            return null;
        }

        $data = json_decode($result['body'], true);
        if (!is_array($data) || !isset($data['smsBalance'])) {
            return null;
        }

        return (string) $data['smsBalance'];
    }

    public function testConnection(): TestConnectionResult
    {
        $authError = $this->validateAuthConfigured();
        if ($authError !== null) {
            return TestConnectionResult::error($authError->error ?? __('Credentials not configured', 'wp-sms'));
        }

        $result = $this->httpPost(self::PROFILE_URL, [
            'headers' => array_merge($this->buildAuthHeaders(), [
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Accept'       => 'application/json',
            ]),
            'body' => http_build_query(array_merge(['format' => 'json'], $this->buildAuthBody())),
        ]);

        if (!$result instanceof DeliveryResult) {
            if ($result['code'] === 401 || $result['code'] === 403) {
                return TestConnectionResult::error(__('Invalid SMSGatewayCenter credentials', 'wp-sms'));
            }
        }

        $data = $this->validateTestResponse($result, 'SMSGatewayCenter');
        if ($data instanceof TestConnectionResult) {
            return $data;
        }

        if (($data['status'] ?? '') === 'error') {
            return TestConnectionResult::error($data['reason'] ?? __('Authentication failed', 'wp-sms'));
        }

        return TestConnectionResult::ok(__('Connected to SMSGatewayCenter', 'wp-sms'));
    }

    // --- SupportsStatusCallback ---

    public function getStatusCallbackUrl(): string
    {
        $token = $this->getSharedConfig('webhook_token');
        return RestRoute::url('callbacks/' . $this->getId() . '/status', $token ? ['token' => $token] : []);
    }

    public function validateStatusCallback(\WP_REST_Request $request): bool
    {
        return $this->validateWebhookToken($request);
    }

    /** @return StatusUpdate[] */
    public function parseStatusCallback(\WP_REST_Request $request): array
    {
        $msgId = $request->get_param('msgId');
        $transId = $request->get_param('transId');
        $providerId = (string) ($msgId ?: $transId);

        if ($providerId === '') {
            return [];
        }

        $errorCode = $request->get_param('errorCode');
        $errorCodeStr = $errorCode !== null ? (string) $errorCode : null;
        $status = $this->mapDlrStatus($errorCodeStr);

        return [new StatusUpdate(
            providerId:   $providerId,
            status:       $status,
            errorCode:    $errorCodeStr,
            errorMessage: $status === 'failed' && $errorCodeStr !== null
                ? sprintf('SMSGatewayCenter error code %s', $errorCodeStr)
                : null,
            permanent:    $errorCodeStr !== null && $this->isPermanentDlrCode($errorCodeStr),
            unsubscribe:  $errorCodeStr !== null && $this->isOptOutDlrCode($errorCodeStr),
        )];
    }

    // --- SupportsInboundMessage ---

    public function getInboundCallbackUrl(): string
    {
        $token = $this->getSharedConfig('webhook_token');
        return RestRoute::url('callbacks/' . $this->getId() . '/inbound', $token ? ['token' => $token] : []);
    }

    public function validateInboundCallback(\WP_REST_Request $request): bool
    {
        return $this->validateWebhookToken($request);
    }

    /** @return InboundMessage[] */
    public function parseInboundCallback(\WP_REST_Request $request): array
    {
        $from = $request->get_param('phoneno');
        if (empty($from)) {
            return [];
        }

        return [new InboundMessage(
            from:   (string) $from,
            to:     (string) ($request->get_param('phonecode') ?? ''),
            body:   (string) ($request->get_param('content') ?? ''),
            meta:   array_filter([
                'keyword' => $request->get_param('keyword'),
                'location' => $request->get_param('location'),
                'carrier' => $request->get_param('carrier'),
            ]),
        )];
    }

    // --- SupportsOptOutDetection ---

    public function isOptOutError(DeliveryResult $result): bool
    {
        $code = $result->meta['sgc_error_code'] ?? null;
        return $code !== null && $this->isOptOutDlrCode((string) $code);
    }

    // --- SupportsDynamicOptions ---

    public function getConfigOptions(string $fieldKey, string $section, array $config, array $context = []): array
    {
        if ($fieldKey !== 'sender_id') {
            return [];
        }

        return $this->withConfig($config, function () {
            $args = [
                'headers' => array_merge($this->buildAuthHeaders(), [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                    'Accept'       => 'application/json',
                ]),
                'body' => http_build_query(array_merge(
                    ['do' => 'list', 'format' => 'json'],
                    $this->buildAuthBody(),
                )),
            ];

            $result = $this->httpPost(self::SENDER_LIST_URL, $args);
            if ($result instanceof DeliveryResult) {
                throw new \RuntimeException(
                    sprintf(__('Could not reach the %s API. Check your server\'s internet connection.', 'wp-sms'), 'SMSGatewayCenter'),
                );
            }
            if ($result['code'] === 401 || $result['code'] === 403) {
                throw new \RuntimeException(__('Invalid credentials', 'wp-sms'));
            }

            $data = json_decode($result['body'], true);
            $records = $this->extractRecords($data);

            $options = [];
            foreach ($records as $record) {
                if (!is_array($record)) {
                    continue;
                }
                $value = $this->firstStringValue($record, ['senderName', 'sender', 'header', 'name', 'senderId']);
                if ($value === null) {
                    continue;
                }
                $options[] = ['value' => $value, 'label' => $value];
            }
            return $options;
        });
    }

    // --- SupportsTemplates ---

    public function requiresTemplateForChannel(string $channel): bool
    {
        return false;
    }

    public function getVariableStyle(): VariableStyle
    {
        return VariableStyle::Named;
    }

    public function buildTemplatePayload(TemplateMapping $mapping, array $resolvedVariables): array
    {
        // SMSGatewayCenter has no template_id parameter — the registered DLT body is what
        // gets sent, with WSMS interpolating variables before transmission. Surface the
        // resolved body so the dispatcher can route it as a regular send.
        return [
            'msg' => $this->interpolate($mapping->providerTemplateBody, $resolvedVariables),
        ];
    }

    // --- SupportsTemplateFetch ---

    /** @return ProviderTemplate[] */
    public function fetchTemplates(): array
    {
        if ($this->validateAuthConfigured() !== null) {
            throw new TemplateCatalogException(__('SMSGatewayCenter credentials not configured', 'wp-sms'));
        }

        $args = [
            'headers' => array_merge($this->buildAuthHeaders(), [
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Accept'       => 'application/json',
            ]),
            'body' => http_build_query(array_merge(
                ['do' => 'list', 'format' => 'json'],
                $this->buildAuthBody(),
            )),
        ];

        $result = $this->httpPost(self::TEMPLATE_LIST_URL, $args);
        if ($result instanceof DeliveryResult) {
            throw new TemplateCatalogException($result->error ?? __('Failed to fetch templates from SMSGatewayCenter', 'wp-sms'));
        }
        if ($result['code'] < 200 || $result['code'] >= 300) {
            throw new TemplateCatalogException(sprintf('HTTP %d', $result['code']));
        }

        $data = json_decode($result['body'], true);
        $records = $this->extractRecords($data);

        $templates = [];
        foreach ($records as $record) {
            if (!is_array($record)) {
                continue;
            }
            $id = $this->firstStringValue($record, ['templateId', 'id', 'template_id']);
            $name = $this->firstStringValue($record, ['templateName', 'name', 'title']);
            $body = $this->firstStringValue($record, ['template', 'message', 'body', 'content']) ?? '';
            $statusRaw = $this->firstStringValue($record, ['status', 'state', 'approvalStatus']) ?? 'approved';

            if ($id === null) {
                continue;
            }

            $templates[] = new ProviderTemplate(
                id: $id,
                name: $name ?? $id,
                language: 'en',
                category: $this->firstStringValue($record, ['type', 'category']) ?? 'transactional',
                status: TemplateStatus::fromProviderStatus($statusRaw),
                bodyText: $body,
                variableCount: substr_count($body, '{#var#}') + substr_count($body, '{{1}}'),
                providerMeta: array_filter([
                    'dlt_template_id' => $this->firstStringValue($record, ['dltTemplateId', 'dlt_template_id', 'peId']),
                ]),
            );
        }
        return $templates;
    }

    // --- Internal ---

    private function validateAuthConfigured(): ?DeliveryResult
    {
        if ($this->getSharedConfig('auth_method') === 'credentials') {
            if (!$this->getSharedConfig('user_id') || !$this->getSharedConfig('password')) {
                return DeliveryResult::failed(__('SMSGatewayCenter user_id and password are required', 'wp-sms'));
            }
            return null;
        }

        // Default: api_key. Treat unset auth_method as api_key so existing fixtures work.
        if (!$this->getSharedConfig('api_key')) {
            return DeliveryResult::failed(__('SMSGatewayCenter API key is required', 'wp-sms'));
        }
        return null;
    }

    private function buildAuthHeaders(): array
    {
        if ($this->getSharedConfig('auth_method') === 'credentials') {
            return [];
        }
        $apiKey = $this->getSharedConfig('api_key');
        return $apiKey ? ['apikey' => (string) $apiKey] : [];
    }

    private function buildAuthBody(): array
    {
        if ($this->getSharedConfig('auth_method') !== 'credentials') {
            return [];
        }
        return [
            'userId'   => (string) $this->getSharedConfig('user_id'),
            'password' => (string) $this->getSharedConfig('password'),
        ];
    }

    private function validateWebhookToken(\WP_REST_Request $request): bool
    {
        $expected = $this->getSharedConfig('webhook_token');
        if (!$expected) {
            return false;
        }
        $provided = $request->get_param('token');
        return is_string($provided) && hash_equals((string) $expected, $provided);
    }

    private function detectMsgType(string $body): string
    {
        return preg_match('/[^\x20-\x7E\r\n\t]/', $body) === 1 ? 'unicode' : 'text';
    }

    private function normalizeMobile(string $recipient): string
    {
        // SMSGatewayCenter expects MSISDN with country code, no plus, no spaces (e.g. 919999999999).
        return preg_replace('/\D+/', '', $recipient) ?? '';
    }

    private function interpolate(string $template, array $variables): string
    {
        if ($template === '' || empty($variables)) {
            return $template;
        }
        $replacements = [];
        foreach ($variables as $key => $value) {
            $replacements['{{' . $key . '}}'] = (string) $value;
            $replacements['{' . $key . '}']   = (string) $value;
        }
        return strtr($template, $replacements);
    }

    /**
     * SMSGatewayCenter library APIs return either a flat list under `data` / `records`
     * or a single record at the top level. Normalise to an array of records.
     */
    private function extractRecords(mixed $data): array
    {
        if (!is_array($data)) {
            return [];
        }
        foreach (['data', 'records', 'response', 'list', 'result'] as $key) {
            if (isset($data[$key]) && is_array($data[$key])) {
                $candidate = $data[$key];
                if ($this->isList($candidate)) {
                    return $candidate;
                }
                return [$candidate];
            }
        }
        return $this->isList($data) ? $data : [$data];
    }

    private function isList(array $array): bool
    {
        return $array === [] || array_keys($array) === range(0, count($array) - 1);
    }

    private function firstStringValue(array $record, array $candidateKeys): ?string
    {
        foreach ($candidateKeys as $key) {
            if (isset($record[$key]) && is_scalar($record[$key])) {
                $value = (string) $record[$key];
                if ($value !== '') {
                    return $value;
                }
            }
        }
        return null;
    }

    private function mapDlrStatus(?string $errorCode): string
    {
        if ($errorCode === null || $errorCode === '') {
            return 'sent';
        }
        return match ($errorCode) {
            '1'  => 'delivered',
            '0', '12' => 'sent',
            default => $this->isPermanentDlrCode($errorCode) || in_array($errorCode, ['15', '25', '28', '32', '35', '37', '41', '7'], true)
                ? 'failed'
                : 'sent',
        };
    }

    /**
     * Permanent failure DLR codes: terminal errors that should mark the message dead
     * and (where applicable) flip the contact to a Bounced/opt-out state.
     *
     * From smsgateway.center/docs/api/info/delivery-error-codes/.
     */
    private function isPermanentDlrCode(string $code): bool
    {
        // 7  = NCPR Fail (DND); 9  = Regulatory Fail; 17 = Invalid SenderId;
        // 22 = DLT Failed; 37 = Non Optin (recipient opted out);
        // 42 = MASK_NOT_ALLOWED; 50–67 = DLT entity/template/header registration failures.
        if (in_array($code, ['7', '9', '17', '22', '37', '42'], true)) {
            return true;
        }
        $numeric = (int) $code;
        return $numeric >= 50 && $numeric <= 67;
    }

    private function isOptOutDlrCode(string $code): bool
    {
        // 7 = DND list (NCPR), 37 = Non Optin (recipient unsubscribed).
        return in_array($code, ['7', '37'], true);
    }
}
