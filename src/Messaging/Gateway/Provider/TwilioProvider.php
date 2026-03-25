<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Catalog\ProviderTemplate;
use WSms\Messaging\Catalog\TemplateCatalogException;
use WSms\Messaging\Catalog\TemplateCatalogManager;
use WSms\Messaging\Catalog\TemplateMapping;
use WSms\Messaging\Catalog\TemplateStatus;
use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\InboundMessage;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\StatusUpdate;
use WSms\Messaging\Contracts\SupportsInboundMessage;
use WSms\Messaging\Contracts\SupportsDynamicOptions;
use WSms\Messaging\Contracts\SupportsOptOutDetection;
use WSms\Messaging\Contracts\SupportsStatusCallback;
use WSms\Messaging\Contracts\SupportsTemplateCatalog;
use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Contracts\TestConnectionResult;

defined('ABSPATH') || exit;

class TwilioProvider extends AbstractProvider implements SupportsStatusCallback, SupportsInboundMessage, SupportsOptOutDetection, SupportsTemplateCatalog, SupportsDynamicOptions
{
    private const API_BASE = 'https://api.twilio.com/2010-04-01';
    private const CONTENT_API_BASE = 'https://content.twilio.com/v1';

    private ?TemplateCatalogManager $catalogManager = null;

    public function setCatalogManager(TemplateCatalogManager $manager): void
    {
        $this->catalogManager = $manager;
    }

    public function getId(): string
    {
        return 'twilio';
    }

    public function getName(): string
    {
        return 'Twilio';
    }

    public function getSupportedChannels(): array
    {
        return ['sms', 'whatsapp'];
    }

    public function getConfigSchema(): array
    {
        return [
            'shared' => [
                'account_sid' => [
                    'type'        => 'string',
                    'label'       => __('Account SID', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Your Account SID from the Twilio Console dashboard, starts with "AC"', 'wp-sms'),
                    'placeholder' => 'ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
                ],
                'auth_token' => [
                    'type'        => 'secret',
                    'label'       => __('Auth Token', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Found below your Account SID on the Twilio Console dashboard', 'wp-sms'),
                    'placeholder' => '32-character hex string',
                ],
            ],
            'channels' => [
                'sms' => [
                    'from_number' => [
                        'type'        => 'string',
                        'label'       => __('From Number', 'wp-sms'),
                        'required'    => true,
                        'description' => __('Your Twilio phone number in E.164 format (e.g., +15551234567)', 'wp-sms'),
                        'placeholder' => '+15551234567',
                        'dynamic'     => true,
                    ],
                ],
                'whatsapp' => [
                    'from_number' => [
                        'type'        => 'string',
                        'label'       => __('WhatsApp Number', 'wp-sms'),
                        'required'    => true,
                        'description' => __('Your Twilio WhatsApp-enabled number. For sandbox testing, use +14155238886', 'wp-sms'),
                        'placeholder' => '+14155238886',
                        'dynamic'     => true,
                    ],
                    'otp_content_sid' => [
                        'type'        => 'string',
                        'label'       => __('OTP Template SID', 'wp-sms'),
                        'required'    => false,
                        'description' => __('Content Template SID (HX...) for OTP messages. Create an Authentication template in Twilio Console. Leave empty for sandbox mode.', 'wp-sms'),
                        'placeholder' => 'HXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX',
                    ],
                ],
            ],
        ];
    }

    public function getMetadata(): array
    {
        return [
            'description' => __('Cloud communications platform for SMS, WhatsApp, and more', 'wp-sms'),
            'website'     => 'https://www.twilio.com',
            'icon'        => '',
            'regions'     => ['global'],
            'setup_url'   => 'https://console.twilio.com/',
            'setup_notes' => [
                __('Find your Account SID and Auth Token on the Twilio Console dashboard.', 'wp-sms'),
                __('For SMS, purchase a phone number at Phone Numbers > Manage > Buy a Number.', 'wp-sms'),
                __('For WhatsApp, enable the sandbox at Messaging > Try it out > Send a WhatsApp message.', 'wp-sms'),
            ],
        ];
    }

    public function getFeatures(): array
    {
        return array_merge(parent::getFeatures(), [
            'mms'              => true,
            'delivery_receipt' => true,
            'incoming'         => true,
            'test_connection'  => true,
        ]);
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        $accountSid = $this->getSharedConfig('account_sid');
        $authToken = $this->getSharedConfig('auth_token');
        $channel = $message->getChannel();
        $from = $this->getChannelConfig($channel, 'from_number');

        if (!$accountSid || !$authToken || !$from) {
            return DeliveryResult::failed(__('Twilio credentials not configured', 'wp-sms'));
        }

        $to = $message->getRecipient();
        // WhatsApp requires the whatsapp: prefix
        if ($channel === 'whatsapp') {
            $from = 'whatsapp:' . $from;
            $to = 'whatsapp:' . $to;
        }

        $url = self::API_BASE . "/Accounts/{$accountSid}/Messages.json";

        $meta = $message->getMeta();

        $body = [
            'From'           => $from,
            'To'             => $to,
            'StatusCallback' => $this->getStatusCallbackUrl(),
        ];

        $contentPayload = null;

        if ($channel === 'whatsapp') {
            // Catalog-based template resolution (new path)
            $templateType = $meta['template_type'] ?? null;
            if ($templateType && $this->catalogManager) {
                $mapping = $this->catalogManager->resolveMapping($templateType, $this->getId());
                if ($mapping) {
                    $resolved = $mapping->resolveVariables($meta['template_variables'] ?? []);
                    $contentPayload = $this->buildTemplatePayload($mapping, $resolved);
                }
            }

            // Legacy fallback: hardcoded otp_content_sid config
            if (!$contentPayload && ($meta['purpose'] ?? null) === 'otp') {
                $contentSid = $this->getChannelConfig('whatsapp', 'otp_content_sid');
                if ($contentSid && isset($meta['otp_code'])) {
                    $contentPayload = [
                        'ContentSid' => $contentSid,
                        'ContentVariables' => wp_json_encode(['1' => $meta['otp_code']]),
                    ];
                }
            }
        }

        if ($contentPayload) {
            $body = array_merge($body, $contentPayload);
        } else {
            $body['Body'] = $message->getBody();
        }

        $mediaUrls = $meta['media_urls'] ?? [];
        foreach ($mediaUrls as $mediaUrl) {
            $body['MediaUrl'][] = $mediaUrl;
        }

        $result = $this->httpPost($url, [
            'headers' => $this->authHeaders(),
            'body' => $body,
        ]);

        if ($result instanceof DeliveryResult) {
            return $result;
        }

        $data = json_decode($result['body'], true);

        if ($result['code'] >= 200 && $result['code'] < 300) {
            $status = $data['status'] ?? 'queued';
            $providerId = $data['sid'] ?? null;
            $cost = isset($data['price']) ? abs((float) $data['price']) : null;

            if (in_array($status, ['sent', 'delivered'], true)) {
                return DeliveryResult::sent($providerId, $cost);
            }

            return DeliveryResult::queued($providerId);
        }

        return DeliveryResult::failed(
            $data['message'] ?? "HTTP {$result['code']}",
            meta: array_filter([
                'twilio_code' => $data['code'] ?? null,
                'more_info'   => $data['more_info'] ?? null,
            ]),
        );
    }

    public function validateStatusCallback(\WP_REST_Request $request): bool
    {
        return $this->verifyTwilioSignature($request, $this->getStatusCallbackUrl());
    }

    /** @return StatusUpdate[] */
    public function parseStatusCallback(\WP_REST_Request $request): array
    {
        $messageSid = $request->get_param('MessageSid');
        $messageStatus = $request->get_param('MessageStatus');

        if (empty($messageSid) || empty($messageStatus)) {
            return [];
        }

        $status = match ($messageStatus) {
            'queued', 'accepted' => 'queued',
            'sending', 'sent'   => 'sent',
            'delivered'         => 'delivered',
            'undelivered', 'failed' => 'failed',
            default             => $messageStatus,
        };

        $errorCode = $request->get_param('ErrorCode');

        return [new StatusUpdate(
            providerId: $messageSid,
            status: $status,
            errorCode: $errorCode,
            errorMessage: $request->get_param('ErrorMessage'),
            permanent: $this->isPermanentTwilioError($errorCode),
            complaint: $errorCode === '21610',
        )];
    }

    public function getStatusCallbackUrl(): string
    {
        return rest_url('wsms/v1/callbacks/twilio/status');
    }

    public function getCredit(): ?string
    {
        $accountSid = $this->getSharedConfig('account_sid');
        $authToken = $this->getSharedConfig('auth_token');

        if (!$accountSid || !$authToken) {
            return null;
        }

        $url = self::API_BASE . "/Accounts/{$accountSid}/Balance.json";

        $result = $this->httpGet($url, [
            'headers' => $this->authHeaders(),
        ]);

        if ($result instanceof DeliveryResult) {
            return null;
        }

        $data = json_decode($result['body'], true);

        $balance = $data['balance'] ?? null;
        if ($balance === null) {
            return null;
        }
        $currency = $data['currency'] ?? 'USD';
        return "{$balance} {$currency}";
    }

    public function testConnection(): TestConnectionResult
    {
        $accountSid = $this->getSharedConfig('account_sid');
        $authToken = $this->getSharedConfig('auth_token');

        if (!$accountSid || !$authToken) {
            return TestConnectionResult::error(__('Account SID and Auth Token are required', 'wp-sms'));
        }

        $url = self::API_BASE . "/Accounts/{$accountSid}/Balance.json";

        $result = $this->httpGet($url, [
            'headers' => $this->authHeaders(),
        ]);

        // Provider-specific error codes before common validation
        if (!$result instanceof DeliveryResult) {
            if ($result['code'] === 401) {
                return TestConnectionResult::error(__('Invalid Account SID or Auth Token', 'wp-sms'));
            }
            if ($result['code'] === 404) {
                return TestConnectionResult::error(__('Account not found — check your Account SID', 'wp-sms'));
            }
        }

        $data = $this->validateTestResponse($result, 'Twilio');
        if ($data instanceof TestConnectionResult) {
            return $data;
        }

        $balance = $data['balance'] ?? 'N/A';
        $currency = $data['currency'] ?? 'USD';

        return TestConnectionResult::ok(
            sprintf(__('Connected — Balance: %s %s', 'wp-sms'), $balance, $currency),
            ['balance' => $balance, 'currency' => $currency],
        );
    }

    // --- SupportsInboundMessage ---

    public function validateInboundCallback(\WP_REST_Request $request): bool
    {
        return $this->verifyTwilioSignature($request, $this->getInboundCallbackUrl());
    }

    /** @return InboundMessage[] */
    public function parseInboundCallback(\WP_REST_Request $request): array
    {
        $from = $request->get_param('From');
        $to = $request->get_param('To');
        $body = $request->get_param('Body') ?? '';
        $messageSid = $request->get_param('MessageSid');
        $optOutType = $request->get_param('OptOutType');

        if (empty($from)) {
            return [];
        }

        return [new InboundMessage(
            from: $from,
            to: $to ?? '',
            body: $body,
            providerId: $messageSid,
            optOutType: $optOutType,
            meta: array_filter([
                'num_media' => $request->get_param('NumMedia'),
                'account_sid' => $request->get_param('AccountSid'),
            ]),
        )];
    }

    public function getInboundCallbackUrl(): string
    {
        return rest_url('wsms/v1/callbacks/twilio/inbound');
    }

    // --- SupportsOptOutDetection ---

    public function isOptOutError(DeliveryResult $result): bool
    {
        // Twilio error 21610: "Attempt to send to unsubscribed recipient"
        return ($result->meta['twilio_code'] ?? null) == 21610;
    }

    // --- SupportsTemplateCatalog ---

    /** @return ProviderTemplate[] */
    public function fetchTemplates(): array
    {
        $accountSid = $this->getSharedConfig('account_sid');
        $authToken = $this->getSharedConfig('auth_token');

        if (!$accountSid || !$authToken) {
            throw new TemplateCatalogException(__('Twilio credentials not configured', 'wp-sms'));
        }

        $url = self::CONTENT_API_BASE . '/Content';

        $result = $this->httpGet($url, [
            'headers' => $this->authHeaders(),
        ]);

        if ($result instanceof DeliveryResult) {
            throw new TemplateCatalogException(
                $result->error ?? __('Failed to fetch templates from Twilio', 'wp-sms'),
            );
        }

        $data = json_decode($result['body'], true);

        if ($result['code'] < 200 || $result['code'] >= 300) {
            throw new TemplateCatalogException(
                $data['message'] ?? "HTTP {$result['code']}",
            );
        }

        $templates = [];
        foreach ($data['contents'] ?? [] as $content) {
            $template = $this->parseTwilioContent($content);
            if ($template && $template->isUsable()) {
                $templates[] = $template;
            }
        }

        return $templates;
    }

    public function requiresTemplateForChannel(string $channel): bool
    {
        if ($channel !== 'whatsapp') {
            return false;
        }

        // Sandbox number doesn't require templates
        $from = $this->getChannelConfig('whatsapp', 'from_number');
        return $from !== '+14155238886';
    }

    public function buildTemplatePayload(TemplateMapping $mapping, array $resolvedVariables): array
    {
        $payload = [
            'ContentSid' => $mapping->providerTemplateId,
        ];

        if (!empty($resolvedVariables)) {
            $payload['ContentVariables'] = wp_json_encode($resolvedVariables);
        }

        return $payload;
    }

    // --- SupportsDynamicOptions ---

    public function getConfigOptions(string $fieldKey, string $section, array $config, array $context = []): array
    {
        if ($fieldKey !== 'from_number') {
            return [];
        }

        return $this->withConfig($config, function () use ($section) {
            $accountSid = $this->getSharedConfig('account_sid');
            $url = self::API_BASE . "/Accounts/{$accountSid}/IncomingPhoneNumbers.json?PageSize=100";
            $data = $this->fetchJsonOrFail($url, ['headers' => $this->authHeaders()]);

            $options = [];
            foreach ($data['incoming_phone_numbers'] ?? [] as $number) {
                $phoneNumber = $number['phone_number'] ?? '';
                if (!$phoneNumber) {
                    continue;
                }

                // Filter by channel capability
                $capabilities = $number['capabilities'] ?? [];
                if ($section === 'sms' && empty($capabilities['sms'])) {
                    continue;
                }

                $friendlyName = $number['friendly_name'] ?? '';
                $label = $friendlyName && $friendlyName !== $phoneNumber
                    ? "{$phoneNumber} ({$friendlyName})"
                    : $phoneNumber;

                $options[] = ['value' => $phoneNumber, 'label' => $label];
            }

            return $options;
        });
    }

    // --- Internal ---

    private function authHeaders(): array
    {
        $accountSid = $this->getSharedConfig('account_sid');
        $authToken = $this->getSharedConfig('auth_token');

        return [
            'Authorization' => 'Basic ' . base64_encode("{$accountSid}:{$authToken}"),
        ];
    }

    private function verifyTwilioSignature(\WP_REST_Request $request, string $callbackUrl): bool
    {
        $authToken = $this->getSharedConfig('auth_token');
        if (!$authToken) {
            return false;
        }

        $signature = $request->get_header('x-twilio-signature');
        if (empty($signature)) {
            return false;
        }

        $params = $request->get_params();
        unset($params['gateway_id']);

        ksort($params);
        $data = $callbackUrl;
        foreach ($params as $key => $value) {
            $data .= $key . $value;
        }

        $expected = base64_encode(hash_hmac('sha1', $data, $authToken, true));

        return hash_equals($expected, $signature);
    }

    private function isPermanentTwilioError(?string $code): bool
    {
        if ($code === null) {
            return false;
        }

        return in_array($code, [
            '30003', // Unreachable destination handset
            '30005', // Unknown destination handset
            '30006', // Landline or unreachable carrier
            '21610', // Unsubscribed recipient
            '21611', // Sender not authorized
            '21614', // Invalid mobile number
        ], true);
    }

    private function parseTwilioContent(array $content): ?ProviderTemplate
    {
        $types = $content['types'] ?? [];

        // Only include templates that have a WhatsApp type
        $whatsappType = $types['twilio/whatsapp'] ?? null;
        if (!$whatsappType) {
            return null;
        }

        $bodyText = $whatsappType['body'] ?? '';
        $variableCount = 0;

        // Count positional variables {{1}}, {{2}}, etc.
        if (preg_match_all('/\{\{(\d+)\}\}/', $bodyText, $matches)) {
            $variableCount = count(array_unique($matches[1]));
        }

        // Normalize approval status
        $approvalRequests = $content['approval_requests'] ?? [];
        $status = 'approved'; // Default for content templates in Twilio
        foreach ($approvalRequests as $provider => $request) {
            if (str_contains($provider, 'whatsapp')) {
                $status = $request['status'] ?? 'approved';
                break;
            }
        }

        // Determine category from Twilio's content type
        $category = 'utility';
        foreach ($types as $typeName => $typeData) {
            if (str_contains($typeName, 'authentication')) {
                $category = 'authentication';
                break;
            }
        }

        return new ProviderTemplate(
            id: $content['sid'],
            name: $content['friendly_name'] ?? $content['sid'],
            language: $content['language'] ?? 'en',
            category: $category,
            status: TemplateStatus::fromProviderStatus($status),
            bodyText: $bodyText,
            variableCount: $variableCount,
            providerMeta: [
                'types'             => array_keys($types),
                'date_created'      => $content['date_created'] ?? null,
                'date_updated'      => $content['date_updated'] ?? null,
                'approval_requests' => $approvalRequests,
            ],
        );
    }
}
