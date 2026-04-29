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

/**
 * SMSC — multi-channel via /sys/send.php with channel-toggle flags.
 *
 * SMS, Viber, WhatsApp, and Telegram all share one POST endpoint with toggles
 * (viber=1, bot=wa:<num>, tg=1 / bot=@name_bot). WhatsApp/Telegram routing goes
 * through SMSC's managed bots — there is no Meta WABA onboarding the customer
 * needs to do themselves. Auth is login + psw query/body params; responses use
 * fmt=3 (JSON) with {id, cnt} on success or {error, error_code} on failure.
 *
 * The same login+psw set works against any of the regional fronts (smsc.ua /
 * smsc.ru / smsc.kz / smsc.tj / smscentre.com), which all expose the same API.
 *
 * Webhook delivery reports and inbound MO messages are configured in the SMSC
 * dashboard under Settings → Notifications. SMSC does not sign webhooks, so we
 * require a shared-secret query token on the URL the user pastes there.
 */
class SmscProvider extends AbstractProvider implements
    SupportsStatusCallback,
    SupportsInboundMessage,
    SupportsOptOutDetection,
    SupportsDynamicOptions,
    SupportsTemplates,
    SupportsTemplateFetch
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const HOSTS = ['smsc.ua', 'smsc.ru', 'smsc.kz', 'smsc.tj', 'smscentre.com'];
    private const DEFAULT_HOST = 'smsc.ua';

    /** SMSC partner code identifying the integration source (matches the awd-studio SDK convention). */
    private const PARTNER_CODE = '343371';

    private ?TemplateCatalogManager $catalogManager = null;

    public function setCatalogManager(TemplateCatalogManager $manager): void
    {
        $this->catalogManager = $manager;
    }

    public function getId(): string
    {
        return 'smsc';
    }

    public function getSupportedChannels(): array
    {
        return ['sms', 'viber', 'whatsapp', 'telegram'];
    }

    public function getConfigSchema(): array
    {
        return [
            'shared' => [
                'host' => [
                    'type'        => 'select',
                    'label'       => __('Region', 'wp-sms'),
                    'default'     => self::DEFAULT_HOST,
                    'options'     => array_map(
                        fn(string $h) => ['value' => $h, 'label' => $h],
                        self::HOSTS,
                    ),
                    'description' => __('Which SMSC regional front your account is registered on. The API is identical across all five — pick the host that matches the dashboard you sign into.', 'wp-sms'),
                ],
                'login' => [
                    'type'        => 'string',
                    'label'       => __('Login', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Your SMSC account login from the dashboard (Settings → API).', 'wp-sms'),
                ],
                'password' => [
                    'type'        => 'secret',
                    'label'       => __('Password', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Your SMSC account password or API password (recommended). Set up an API-only password under Settings → Security for least-privilege access.', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'from' => [
                        'type'        => 'string',
                        'label'       => __('SMS Sender', 'wp-sms'),
                        'required'    => false,
                        'dynamic'     => true,
                        'description' => __('Approved alphanumeric sender or numeric MSISDN registered in your SMSC dashboard. Leave blank to use the account default.', 'wp-sms'),
                    ],
                ],
                'viber' => [
                    'from' => [
                        'type'        => 'string',
                        'label'       => __('Viber Sender', 'wp-sms'),
                        'required'    => true,
                        'dynamic'     => true,
                        'description' => __('Registered Viber Business sender approved by SMSC. Must be moderated before first send.', 'wp-sms'),
                    ],
                ],
                'whatsapp' => [
                    'bot_number' => [
                        'type'        => 'string',
                        'label'       => __('WhatsApp Bot Number', 'wp-sms'),
                        'required'    => true,
                        'placeholder' => '79991234567',
                        'description' => __('SMSC-issued WhatsApp bot number in international format without "+" (e.g. 79991234567). Request one from SMSC support — no Meta WABA onboarding required on your side.', 'wp-sms'),
                    ],
                ],
                'telegram' => [
                    'bot_handle' => [
                        'type'        => 'string',
                        'label'       => __('Telegram Bot', 'wp-sms'),
                        'required'    => false,
                        'placeholder' => '@your_bot_name',
                        'description' => __('Leave blank to route through SMSC\'s shared Telegram bot. To use your own bot, enter its @username after registering it in your SMSC dashboard.', 'wp-sms'),
                    ],
                ],
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        $login    = (string) $this->getSharedConfig('login', '');
        $password = (string) $this->getSharedConfig('password', '');

        if ($login === '' || $password === '') {
            return DeliveryResult::failed(__('SMSC credentials not configured', 'wp-sms'));
        }

        $channel = $message->getChannel();
        $meta    = $message->getMeta();

        $params = $this->baseQuery();
        $params['phones'] = $this->normalizePhones($message->getRecipient());
        $params['mes']    = $message->getBody();

        // Channel routing — SMSC uses flags on a single send.php endpoint.
        // TODO(voice): SMSC supports send.php?call=1&voice=m|w (IVR delivery);
        //   defer until WSMS adds 'voice' to recognized channels.
        // TODO(email): SMSC supports send.php?mail=1&subj=... (email delivery
        //   from the SMS account), but WSMS routes email through
        //   WpMailGateway/MailtrapGateway, not gateway provider classes.
        $channelResult = $this->applyChannelToggle($params, $channel);
        if ($channelResult instanceof DeliveryResult) {
            return $channelResult;
        }

        // Catalog-mode template dispatch — render locally to avoid speculating
        // on send.php's parametrized-template syntax (the docs cover the
        // templates.php CRUD endpoints, not server-side substitution shape).
        $rendered = $this->renderTemplateIfPresent($meta);
        if ($rendered !== null) {
            $params['mes'] = $rendered;
        }

        // MMS / media — SMSC accepts a single fileurl per send.
        $mediaUrls = $meta['media_urls'] ?? [];
        if (!empty($mediaUrls)) {
            $params['fileurl'] = (string) reset($mediaUrls);
        }

        // Flash SMS — exposed via meta flag on the SMS channel.
        if (!empty($meta['flash']) && $channel === 'sms') {
            $params['flash'] = 1;
        }

        $result = $this->httpPost($this->endpoint('send'), [
            'body' => $params,
        ]);

        if ($result instanceof DeliveryResult) {
            return $result;
        }

        if ($result['code'] === 401 || $result['code'] === 403) {
            return DeliveryResult::failed(__('Invalid SMSC login or password', 'wp-sms'));
        }

        $data = json_decode($result['body'], true);

        if (!is_array($data)) {
            return DeliveryResult::failed(sprintf('SMSC: HTTP %d', $result['code']));
        }

        if (isset($data['error_code'])) {
            $code = (string) $data['error_code'];
            return DeliveryResult::failed(
                $data['error'] ?? $this->describeSendError($code),
                meta: array_filter([
                    'smsc_error_code' => $code,
                    'smsc_http_code'  => $result['code'] ?: null,
                ]),
                retryable: in_array($code, ['4', '9'], true),
            );
        }

        $providerId = isset($data['id']) ? (string) $data['id'] : null;
        $cnt        = isset($data['cnt']) ? (int) $data['cnt'] : null;

        return DeliveryResult::sent(
            providerId: $providerId,
            meta: array_filter(['smsc_cnt' => $cnt]),
        );
    }

    public function getCredit(): ?string
    {
        $login    = (string) $this->getSharedConfig('login', '');
        $password = (string) $this->getSharedConfig('password', '');

        if ($login === '' || $password === '') {
            return null;
        }

        $result = $this->httpPost($this->endpoint('balance'), [
            'body' => $this->baseQuery() + ['cur' => 1],
        ]);

        if ($result instanceof DeliveryResult) {
            return null;
        }

        $data = json_decode($result['body'], true);
        if (!is_array($data) || isset($data['error_code'])) {
            return null;
        }

        $balance  = $data['balance'] ?? null;
        $currency = $data['currency'] ?? '';
        if ($balance === null) {
            return null;
        }

        return trim(number_format((float) $balance, 2) . ' ' . $currency);
    }

    public function testConnection(): TestConnectionResult
    {
        $login    = (string) $this->getSharedConfig('login', '');
        $password = (string) $this->getSharedConfig('password', '');

        if ($login === '' || $password === '') {
            return TestConnectionResult::error(__('Login and Password are required', 'wp-sms'));
        }

        $result = $this->httpPost($this->endpoint('balance'), [
            'body' => $this->baseQuery() + ['cur' => 1],
        ]);

        if (!$result instanceof DeliveryResult) {
            if ($result['code'] === 401 || $result['code'] === 403) {
                return TestConnectionResult::error(__('Invalid SMSC login or password', 'wp-sms'));
            }
        }

        $data = $this->validateTestResponse($result, 'SMSC');
        if ($data instanceof TestConnectionResult) {
            return $data;
        }

        if (isset($data['error_code'])) {
            $code = (string) $data['error_code'];
            // 2 = Invalid login or password
            if ($code === '2') {
                return TestConnectionResult::error(__('Invalid SMSC login or password', 'wp-sms'));
            }
            // 4 = IP temporarily blocked due to repeated errors
            if ($code === '4') {
                return TestConnectionResult::error(__('SMSC blocked your IP temporarily — wait a few minutes and retry', 'wp-sms'));
            }
            return TestConnectionResult::error(
                $data['error'] ?? sprintf(__('SMSC error %s', 'wp-sms'), $code),
            );
        }

        $balance  = $data['balance'] ?? 'N/A';
        $currency = $data['currency'] ?? '';

        return TestConnectionResult::ok(
            sprintf(__('Connected — Balance: %s %s', 'wp-sms'), $balance, $currency),
            ['balance' => (string) $balance, 'currency' => (string) $currency],
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
        if ((string) $this->getSharedConfig('password', '') === '') {
            return false;
        }
        return hash_equals($this->callbackToken(), (string) ($request->get_param('token') ?? ''));
    }

    /** @return StatusUpdate[] */
    public function parseStatusCallback(\WP_REST_Request $request): array
    {
        $id        = $request->get_param('id');
        $rawStatus = $request->get_param('status');

        if ($id === null || $rawStatus === null || (string) $id === '' || (string) $rawStatus === '') {
            return [];
        }

        $status = (string) $rawStatus;
        $err    = $request->get_param('err');
        $err    = ($err !== null && (string) $err !== '' && (string) $err !== '0') ? (string) $err : null;

        // SMSC status enum (per /sys/status.php docs):
        //   -1 = not delivered (queued/buffered)   →  sent
        //    0 = passed to SMSC                    →  sent
        //    1 = delivered                         →  delivered
        //    2 = read (Viber/WhatsApp)             →  delivered
        //    3 = expired                           →  failed
        //   20 = cannot deliver                    →  failed (permanent)
        //   22 = invalid number                    →  failed (permanent)
        //   23 = forbidden by recipient            →  failed (permanent, opt-out)
        //   24 = insufficient funds                →  failed
        //   25 = unreachable subscriber            →  failed (permanent)
        $normalized = match ($status) {
            '-1', '0'             => 'sent',
            '1', '2'              => 'delivered',
            '3', '20', '22', '23', '24', '25' => 'failed',
            default               => $status,
        };

        return [new StatusUpdate(
            providerId:   (string) $id,
            status:       $normalized,
            errorCode:    $err,
            errorMessage: $normalized === 'failed' ? sprintf('SMSC: status %s%s', $status, $err ? " (err {$err})" : '') : null,
            permanent:    in_array($status, ['20', '22', '23', '25'], true),
            unsubscribe:  $status === '23',
        )];
    }

    // --- SupportsInboundMessage ---

    public function getInboundCallbackUrl(): string
    {
        return RestRoute::url(
            'callbacks/' . $this->getId() . '/inbound',
            ['token' => $this->callbackToken()],
        );
    }

    public function validateInboundCallback(\WP_REST_Request $request): bool
    {
        if ((string) $this->getSharedConfig('password', '') === '') {
            return false;
        }
        return hash_equals($this->callbackToken(), (string) ($request->get_param('token') ?? ''));
    }

    /** @return InboundMessage[] */
    public function parseInboundCallback(\WP_REST_Request $request): array
    {
        $from = (string) ($request->get_param('phone') ?? '');
        if ($from === '') {
            return [];
        }

        return [new InboundMessage(
            from:       $from,
            to:         (string) ($request->get_param('to') ?? $this->getChannelConfig('sms', 'from', '')),
            body:       (string) ($request->get_param('mes') ?? ''),
            providerId: $request->get_param('id') !== null ? (string) $request->get_param('id') : null,
            meta:       array_filter([
                'date' => $request->get_param('get_timestamp') ?? $request->get_param('date'),
            ]),
        )];
    }

    // --- SupportsDynamicOptions ---

    public function getConfigOptions(string $fieldKey, string $section, array $config, array $context = []): array
    {
        if ($fieldKey !== 'from' || !in_array($section, ['sms', 'viber'], true)) {
            return [];
        }

        return $this->withConfig($config, function () {
            $login    = $this->getSharedConfig('login');
            $password = $this->getSharedConfig('password');
            if (!$login || !$password) {
                return [];
            }

            $result = $this->httpPost($this->endpoint('senders'), [
                'body' => $this->baseQuery() + ['get' => 1],
            ]);

            if ($result instanceof DeliveryResult) {
                throw new \RuntimeException(__('Could not reach SMSC', 'wp-sms'));
            }

            $data = json_decode($result['body'], true);
            if (is_array($data) && isset($data['error_code'])) {
                $code = (string) $data['error_code'];
                if ($code === '2') {
                    throw new \RuntimeException(__('Invalid SMSC login or password', 'wp-sms'));
                }
                throw new \RuntimeException($data['error'] ?? sprintf(__('SMSC error %s', 'wp-sms'), $code));
            }

            $options = [];
            // /sys/senders.php returns a top-level array of {sender, ...} entries on success.
            $entries = is_array($data) && array_is_list($data) ? $data : ($data['senders'] ?? []);
            foreach ($entries as $entry) {
                $sender = is_array($entry) ? ($entry['sender'] ?? null) : null;
                if ($sender) {
                    $options[] = ['value' => (string) $sender, 'label' => (string) $sender];
                }
            }
            return $options;
        });
    }

    // --- SupportsOptOutDetection ---

    public function isOptOutError(DeliveryResult $result): bool
    {
        // Send-time error 8 is documented as "Сообщение на указанный номер не
        // может быть доставлено" — recipient blacklist / opt-out is the only
        // bucket that maps cleanly to this code on the send path. DLR status 23
        // (forbidden by recipient) handles the post-send opt-out via
        // unsubscribe=true on the StatusUpdate.
        return ($result->meta['smsc_error_code'] ?? null) === '8';
    }

    // --- SupportsTemplates ---

    public function requiresTemplateForChannel(string $channel): bool
    {
        // SMSC accepts free-form text on every channel — templates are an
        // operator-side convenience, not a precondition.
        return false;
    }

    public function getVariableStyle(): VariableStyle
    {
        return VariableStyle::Positional;
    }

    public function buildTemplatePayload(TemplateMapping $mapping, array $resolvedVariables): array
    {
        // SMSC stores templates with %1, %2, ... placeholders; substitute them
        // client-side and send the rendered text via the standard `mes` param.
        // (We don't claim server-side template-by-id substitution because the
        // public docs cover templates.php CRUD but not the send.php param.)
        $body = $mapping->providerTemplateBody;

        ksort($resolvedVariables, SORT_NATURAL);
        foreach ($resolvedVariables as $position => $value) {
            $body = str_replace('%' . $position, (string) $value, $body);
        }

        return ['mes' => $body];
    }

    /** @return ProviderTemplate[] */
    public function fetchTemplates(): array
    {
        $login    = (string) $this->getSharedConfig('login', '');
        $password = (string) $this->getSharedConfig('password', '');

        if ($login === '' || $password === '') {
            throw new TemplateCatalogException(__('SMSC credentials not configured', 'wp-sms'));
        }

        $result = $this->httpPost($this->endpoint('templates'), [
            'body' => $this->baseQuery() + ['get' => 1],
        ]);

        if ($result instanceof DeliveryResult) {
            throw new TemplateCatalogException($result->error ?? __('Failed to fetch SMSC templates', 'wp-sms'));
        }

        $data = json_decode($result['body'], true);

        if ($result['code'] < 200 || $result['code'] >= 300) {
            throw new TemplateCatalogException(
                is_array($data) ? ($data['error'] ?? "HTTP {$result['code']}") : "HTTP {$result['code']}",
            );
        }

        if (is_array($data) && isset($data['error_code'])) {
            throw new TemplateCatalogException(
                $data['error'] ?? sprintf(__('SMSC error %s', 'wp-sms'), $data['error_code']),
            );
        }

        if (!is_array($data)) {
            return [];
        }

        $entries = array_is_list($data) ? $data : ($data['templates'] ?? []);

        $templates = [];
        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $id   = $entry['id'] ?? null;
            $name = $entry['name'] ?? null;
            $body = $entry['msg'] ?? '';
            if ($id === null || $name === null) {
                continue;
            }

            $templates[] = new ProviderTemplate(
                id:            (string) $id,
                name:          (string) $name,
                language:      (string) ($entry['format'] ?? ''),
                category:      'utility',
                status:        TemplateStatus::Approved,
                bodyText:      (string) $body,
                variableCount: $this->countTemplateVariables((string) $body),
            );
        }

        return $templates;
    }

    // --- Internal ---

    /**
     * Apply the per-channel flag SMSC's send.php uses to route the message.
     *
     * @param array<string, mixed> $params
     * @return null|DeliveryResult
     */
    private function applyChannelToggle(array &$params, string $channel): ?DeliveryResult
    {
        switch ($channel) {
            case 'sms':
                $sender = $this->getChannelConfig('sms', 'from');
                if ($sender) {
                    $params['sender'] = (string) $sender;
                }
                return null;

            case 'viber':
                $sender = $this->getChannelConfig('viber', 'from');
                if (!$sender) {
                    return DeliveryResult::failed(__('SMSC Viber sender is not configured', 'wp-sms'));
                }
                $params['viber']  = 1;
                $params['sender'] = (string) $sender;
                return null;

            case 'whatsapp':
                $bot = (string) $this->getChannelConfig('whatsapp', 'bot_number', '');
                if ($bot === '') {
                    return DeliveryResult::failed(__('SMSC WhatsApp bot number is not configured', 'wp-sms'));
                }
                $params['bot'] = 'wa:' . ltrim($bot, '+');
                return null;

            case 'telegram':
                $handle = (string) $this->getChannelConfig('telegram', 'bot_handle', '');
                if ($handle !== '') {
                    $params['bot'] = str_starts_with($handle, '@') ? $handle : '@' . $handle;
                } else {
                    $params['tg'] = 1;
                }
                return null;
        }

        return DeliveryResult::failed(sprintf(
            /* translators: %s: channel slug supplied by the caller */
            __('SMSC does not support channel %s', 'wp-sms'),
            $channel,
        ));
    }

    private function renderTemplateIfPresent(array $meta): ?string
    {
        // Direct mode — flow builder already chose the template + variables.
        if (!empty($meta['template_mode']) && !empty($meta['provider_template_id'])) {
            $mapping = new TemplateMapping(
                templateType:         '',
                providerTemplateId:   (string) $meta['provider_template_id'],
                gatewayId:            $this->getId(),
                language:             (string) ($meta['template_language'] ?? ''),
                variableMap:          [],
                providerTemplateBody: (string) ($meta['provider_template_body'] ?? ''),
            );
            $payload = $this->buildTemplatePayload($mapping, $meta['template_variables'] ?? []);
            return $payload['mes'] ?? null;
        }

        // Catalog-resolved (system OTP / well-known template type).
        $templateType = $meta['template_type'] ?? null;
        if ($templateType && $this->catalogManager) {
            $mapping = $this->catalogManager->resolveMapping($templateType, $this->getId());
            if ($mapping && $mapping->providerTemplateBody !== '') {
                $resolved = $mapping->resolveVariables($meta['template_variables'] ?? []);
                $payload  = $this->buildTemplatePayload($mapping, $resolved);
                return $payload['mes'] ?? null;
            }
        }

        return null;
    }

    private function endpoint(string $method): string
    {
        $host = $this->resolveHost();
        return 'https://' . $host . '/sys/' . $method . '.php';
    }

    private function resolveHost(): string
    {
        $host = (string) $this->getSharedConfig('host', self::DEFAULT_HOST);
        return in_array($host, self::HOSTS, true) ? $host : self::DEFAULT_HOST;
    }

    /**
     * Base query bag every SMSC call needs: credentials + JSON response format.
     *
     * @return array<string, mixed>
     */
    private function baseQuery(): array
    {
        return [
            'login'   => (string) $this->getSharedConfig('login', ''),
            'psw'     => (string) $this->getSharedConfig('password', ''),
            'fmt'     => 3,
            'charset' => 'utf-8',
            'pp'      => self::PARTNER_CODE,
        ];
    }

    /**
     * SMSC accepts comma-separated bare-digit MSISDNs. WSMS recipients are
     * usually E.164-formatted (with leading "+"), so strip the prefix.
     */
    private function normalizePhones(string $recipient): string
    {
        $parts = array_map(
            static fn(string $p) => ltrim(trim($p), '+'),
            explode(',', $recipient),
        );
        return implode(',', array_filter($parts));
    }

    private function callbackToken(): string
    {
        return hash_hmac('sha256', 'smsc-callback', (string) $this->getSharedConfig('password', ''));
    }

    private function describeSendError(string $code): string
    {
        return match ($code) {
            '1' => __('SMSC: invalid request parameters', 'wp-sms'),
            '2' => __('SMSC: invalid login or password', 'wp-sms'),
            '3' => __('SMSC: insufficient account balance', 'wp-sms'),
            '4' => __('SMSC: IP blocked temporarily, retry shortly', 'wp-sms'),
            '5' => __('SMSC: invalid date format', 'wp-sms'),
            '6' => __('SMSC: message blocked (text or sender forbidden)', 'wp-sms'),
            '7' => __('SMSC: invalid phone number format', 'wp-sms'),
            '8' => __('SMSC: message cannot be delivered to this number', 'wp-sms'),
            '9' => __('SMSC: rate limited (duplicate request)', 'wp-sms'),
            default => sprintf(__('SMSC error %s', 'wp-sms'), $code),
        };
    }

    private function countTemplateVariables(string $body): int
    {
        return preg_match_all('/%\d+/', $body) ?: 0;
    }
}
