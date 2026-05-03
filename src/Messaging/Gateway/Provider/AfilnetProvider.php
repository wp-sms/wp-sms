<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Catalog\TemplateCatalogManager;
use WSms\Messaging\Catalog\TemplateMapping;
use WSms\Messaging\Catalog\VariableStyle;
use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\SupportsTemplates;
use WSms\Messaging\Contracts\TestConnectionResult;
use WSms\Messaging\Gateway\AbstractProvider;

defined('ABSPATH') || exit;

/**
 * Afilnet — Spanish multi-channel cloud-messaging provider.
 *
 * One unified HTTP API at https://www.afilnet.com/api/http/, dispatched by
 * `class` (sms|email|voice|whatsapp|user) and `method`. Auth: every request
 * carries `user` + `password` form fields. Success envelope:
 *   {status: "SUCCESS", result: <id|count|balance>, ...}
 * Failure envelope:
 *   {status: "ERROR", error: "INCORRECT_USER_PASSWORD"|"NO_CREDITS"|...}
 *
 * All four channels accept `idtemplate` + `params=k1:v1,k2:v2` via the
 * `sendXfromtemplate` method variant for catalog-resolved templates.
 *
 * TODO(verify): Afilnet has an Authentication API for OTP send/verify;
 * defer until SupportsVerify lands.
 */
class AfilnetProvider extends AbstractProvider implements SupportsTemplates
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const API_BASE = 'https://www.afilnet.com/api/http/';

    private ?TemplateCatalogManager $catalogManager = null;

    public function setCatalogManager(TemplateCatalogManager $manager): void
    {
        $this->catalogManager = $manager;
    }

    public function getId(): string
    {
        return 'afilnet';
    }

    public function getSupportedChannels(): array
    {
        return ['sms', 'email', 'voice', 'whatsapp'];
    }

    public function getConfigSchema(): array
    {
        return [
            'shared' => [
                'username' => [
                    'type'        => 'string',
                    'label'       => __('Username', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Your Afilnet account email.', 'wp-sms'),
                ],
                'password' => [
                    'type'        => 'secret',
                    'label'       => __('Password', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Your Afilnet account password.', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'from' => [
                        'type'        => 'string',
                        'label'       => __('Sender ID', 'wp-sms'),
                        'required'    => true,
                        'placeholder' => 'MyBrand',
                        'description' => __('Alphanumeric sender ID (max 11 characters). Configure under Sender in your Afilnet dashboard.', 'wp-sms'),
                    ],
                ],
                'email' => [
                    'subject_prefix' => [
                        'type'        => 'string',
                        'label'       => __('Default Email Subject', 'wp-sms'),
                        'required'    => false,
                        'description' => __('Used when the message meta does not provide its own subject.', 'wp-sms'),
                    ],
                ],
                'voice' => [
                    'language' => [
                        'type'        => 'select',
                        'label'       => __('TTS Language', 'wp-sms'),
                        'required'    => false,
                        'default'     => 'EN',
                        'options'     => [
                            ['value' => 'EN', 'label' => __('English', 'wp-sms')],
                            ['value' => 'ES', 'label' => __('Spanish', 'wp-sms')],
                            ['value' => 'FR', 'label' => __('French', 'wp-sms')],
                            ['value' => 'DE', 'label' => __('German', 'wp-sms')],
                            ['value' => 'IT', 'label' => __('Italian', 'wp-sms')],
                            ['value' => 'PT', 'label' => __('Portuguese', 'wp-sms')],
                        ],
                        'description' => __('Language used by the text-to-speech engine for outgoing voice calls.', 'wp-sms'),
                    ],
                ],
                'whatsapp' => [
                    'platform_id' => [
                        'type'        => 'string',
                        'label'       => __('WhatsApp Platform ID', 'wp-sms'),
                        'required'    => true,
                        'description' => __('Platform ID assigned by Afilnet support after onboarding your WhatsApp Business Account.', 'wp-sms'),
                    ],
                ],
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        if (!$this->getSharedConfig('username') || !$this->getSharedConfig('password')) {
            return DeliveryResult::failed(__('Afilnet credentials not configured', 'wp-sms'));
        }

        return match ($message->getChannel()) {
            'sms'      => $this->sendSms($message),
            'email'    => $this->sendEmail($message),
            'voice'    => $this->sendVoice($message),
            'whatsapp' => $this->sendWhatsapp($message),
            default    => DeliveryResult::failed(sprintf(__('Afilnet does not support channel %s', 'wp-sms'), $message->getChannel())),
        };
    }

    private function sendSms(MessageInterface $message): DeliveryResult
    {
        $from = (string) $this->getChannelConfig('sms', 'from', '');
        if ($from === '') {
            return DeliveryResult::failed(__('Afilnet SMS sender not configured', 'wp-sms'));
        }

        $template = $this->resolveTemplatePayload($message->getMeta());
        if ($template !== null) {
            return $this->postForm([
                'class'  => 'sms',
                'method' => 'sendsmsfromtemplate',
                'from'   => $from,
                'to'     => $message->getRecipient(),
            ] + $template);
        }

        return $this->postForm([
            'class'  => 'sms',
            'method' => 'sendsms',
            'from'   => $from,
            'to'     => $message->getRecipient(),
            'sms'    => $message->getBody(),
        ]);
    }

    private function sendEmail(MessageInterface $message): DeliveryResult
    {
        $meta = $message->getMeta();
        $subject = (string) ($meta['subject'] ?? $this->getChannelConfig('email', 'subject_prefix', ''));

        $template = $this->resolveTemplatePayload($meta);
        if ($template !== null) {
            return $this->postForm(array_filter([
                'class'   => 'email',
                'method'  => 'sendemailfromtemplate',
                'to'      => $message->getRecipient(),
                'subject' => $subject !== '' ? $subject : null,
            ]) + $template);
        }

        return $this->postForm([
            'class'   => 'email',
            'method'  => 'sendemail',
            'to'      => $message->getRecipient(),
            'subject' => $subject,
            'email'   => $message->getBody(),
        ]);
    }

    private function sendVoice(MessageInterface $message): DeliveryResult
    {
        $language = (string) $this->getChannelConfig('voice', 'language', 'EN');

        $template = $this->resolveTemplatePayload($message->getMeta());
        if ($template !== null) {
            return $this->postForm([
                'class'    => 'voice',
                'method'   => 'sendvoicefromtemplate',
                'to'       => $message->getRecipient(),
                'language' => $language,
            ] + $template);
        }

        return $this->postForm([
            'class'    => 'voice',
            'method'   => 'sendvoice',
            'to'       => $message->getRecipient(),
            'message'  => $message->getBody(),
            'language' => $language,
        ]);
    }

    private function sendWhatsapp(MessageInterface $message): DeliveryResult
    {
        $platformId = (string) $this->getChannelConfig('whatsapp', 'platform_id', '');
        if ($platformId === '') {
            return DeliveryResult::failed(__('Afilnet WhatsApp Platform ID not configured', 'wp-sms'));
        }

        $meta = $message->getMeta();

        $template = $this->resolveTemplatePayload($meta);
        if ($template !== null) {
            return $this->postForm([
                'class'       => 'whatsapp',
                'method'      => 'sendmessagefromtemplate',
                'platformid'  => $platformId,
                'destination' => $message->getRecipient(),
            ] + $template);
        }

        $mediaUrl = (string) ($meta['media_urls'][0] ?? '');
        if ($mediaUrl !== '') {
            return $this->postForm(array_filter([
                'class'       => 'whatsapp',
                'method'      => 'sendfile',
                'platformid'  => $platformId,
                'destination' => $message->getRecipient(),
                'type'        => $this->detectWhatsappMediaType($mediaUrl),
                'fileurl'     => $mediaUrl,
                'message'     => $message->getBody() !== '' ? $message->getBody() : null,
            ]));
        }

        return $this->postForm([
            'class'       => 'whatsapp',
            'method'      => 'sendmessage',
            'platformid'  => $platformId,
            'destination' => $message->getRecipient(),
            'message'     => $message->getBody(),
        ]);
    }

    public function getCredit(): ?string
    {
        if (!$this->getSharedConfig('username') || !$this->getSharedConfig('password')) {
            return null;
        }

        $data = $this->fetchBalance();
        if (!is_array($data) || ($data['status'] ?? '') !== 'SUCCESS' || !isset($data['result'])) {
            return null;
        }

        return (string) $data['result'];
    }

    public function testConnection(): TestConnectionResult
    {
        if (!$this->getSharedConfig('username') || !$this->getSharedConfig('password')) {
            return TestConnectionResult::error(__('Username and Password are required', 'wp-sms'));
        }

        $data = $this->fetchBalance();
        if ($data instanceof TestConnectionResult) {
            return $data;
        }

        if (($data['status'] ?? '') !== 'SUCCESS') {
            $error = (string) ($data['error'] ?? '');
            if ($error === 'INCORRECT_USER_PASSWORD') {
                return TestConnectionResult::error(__('Invalid Afilnet credentials', 'wp-sms'));
            }
            return TestConnectionResult::error($error !== '' ? sprintf('Afilnet: %s', $error) : __('Afilnet rejected the connection', 'wp-sms'));
        }

        $balance = (string) ($data['result'] ?? 'N/A');
        return TestConnectionResult::ok(
            sprintf(__('Connected to Afilnet — Balance: %s', 'wp-sms'), $balance),
            ['balance' => $balance],
        );
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
        $pairs = [];
        foreach ($resolvedVariables as $name => $value) {
            $pairs[] = ((string) $name) . ':' . ((string) $value);
        }

        return [
            'idtemplate' => (string) $mapping->providerTemplateId,
            'params'     => implode(',', $pairs),
        ];
    }

    // --- Internal ---

    private function resolveTemplatePayload(array $meta): ?array
    {
        if (!empty($meta['template_mode']) && !empty($meta['provider_template_id'])) {
            $mapping = new TemplateMapping(
                templateType: '',
                providerTemplateId: (string) $meta['provider_template_id'],
                gatewayId: $this->getId(),
                language: (string) ($meta['template_language'] ?? ''),
                variableMap: [],
            );
            return $this->buildTemplatePayload($mapping, $meta['template_variables'] ?? []);
        }

        $templateType = $meta['template_type'] ?? null;
        if ($templateType && $this->catalogManager) {
            $mapping = $this->catalogManager->resolveMapping($templateType, $this->getId());
            if ($mapping) {
                $resolved = $mapping->resolveVariables($meta['template_variables'] ?? []);
                return $this->buildTemplatePayload($mapping, $resolved);
            }
        }

        return null;
    }

    private function fetchBalance(): array|TestConnectionResult
    {
        $result = $this->httpPost(self::API_BASE, [
            'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
            'body'    => http_build_query($this->withCredentials([
                'class'  => 'user',
                'method' => 'getbalance',
            ])),
        ]);

        $data = $this->validateTestResponse($result, 'Afilnet');
        if ($data instanceof TestConnectionResult) {
            return $data;
        }

        return $data;
    }

    /**
     * Issue a form-encoded POST and normalize Afilnet's envelope into a DeliveryResult.
     */
    private function postForm(array $payload): DeliveryResult
    {
        $result = $this->httpPost(self::API_BASE, [
            'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
            'body'    => http_build_query($this->withCredentials($payload)),
        ]);

        if ($result instanceof DeliveryResult) {
            return $result;
        }

        $data = json_decode($result['body'], true);

        if ($result['code'] < 200 || $result['code'] >= 300) {
            $error = is_array($data) ? ($data['error'] ?? null) : null;
            return DeliveryResult::failed($error ? sprintf('Afilnet: %s', $error) : sprintf('HTTP %d', $result['code']));
        }

        if (!is_array($data) || !isset($data['status'])) {
            return DeliveryResult::failed(__('Invalid response from Afilnet', 'wp-sms'));
        }

        if ($data['status'] !== 'SUCCESS') {
            $error = (string) ($data['error'] ?? '');
            return DeliveryResult::failed(
                $error !== '' ? sprintf('Afilnet: %s', $error) : __('Afilnet did not accept the message', 'wp-sms'),
                array_filter(['afilnet_error' => $error !== '' ? $error : null]),
            );
        }

        $messageId = isset($data['result']) ? (string) $data['result'] : null;
        return DeliveryResult::sent($messageId);
    }

    private function withCredentials(array $payload): array
    {
        return [
            'user'     => (string) $this->getSharedConfig('username', ''),
            'password' => (string) $this->getSharedConfig('password', ''),
        ] + $payload;
    }

    private function detectWhatsappMediaType(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH) ?? '';
        $ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($ext) {
            'mp4', 'mov', '3gp', 'webm'                                          => 'video',
            'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv'    => 'document',
            default                                                              => 'image',
        };
    }
}
