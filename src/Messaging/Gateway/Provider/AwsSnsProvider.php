<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\TestConnectionResult;
use WSms\Messaging\Gateway\AbstractProvider;

defined('ABSPATH') || exit;

// TODO(cross-cutting): DLR ingestion — SNS only emits delivery status to CloudWatch Logs.
//   Direct webhooks require migrating users to AWS End User Messaging (Pinpoint SMS Voice v2).
// TODO(cross-cutting): two-way SMS — same migration path.
// TODO(cross-cutting): SupportsVerify — defer until SupportsVerify interface lands; promote
//   SigV4 to a shared helper at that point so the EUM provider can reuse it.
class AwsSnsProvider extends AbstractProvider
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const SERVICE     = 'sns';
    private const API_VERSION = '2010-03-31';

    private const REGIONS = [
        'us-east-1', 'us-west-2', 'eu-west-1', 'eu-central-1', 'eu-north-1',
        'ap-south-1', 'ap-southeast-1', 'ap-southeast-2', 'ap-northeast-1',
        'ca-central-1', 'sa-east-1',
    ];

    public function getId(): string
    {
        return 'awssns';
    }

    public function getSupportedChannels(): array
    {
        return ['sms'];
    }

    public function getConfigSchema(): array
    {
        return [
            'shared' => [
                'access_key_id' => [
                    'type'        => 'string',
                    'label'       => __('Access Key ID', 'wp-sms'),
                    'required'    => true,
                    'description' => __('IAM user access key with sns:Publish permission.', 'wp-sms'),
                    'placeholder' => 'AKIAIOSFODNN7EXAMPLE',
                ],
                'secret_access_key' => [
                    'type'        => 'secret',
                    'label'       => __('Secret Access Key', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Shown once when the access key is created in IAM.', 'wp-sms'),
                ],
                'region' => [
                    'type'        => 'select',
                    'label'       => __('AWS Region', 'wp-sms'),
                    'required'    => true,
                    'default'     => 'us-east-1',
                    'options'     => array_map(fn($r) => ['value' => $r, 'label' => $r], self::REGIONS),
                    'description' => __('Region used to send SMS. Pick the one closest to your destination audience.', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'sender_id' => [
                        'type'        => 'string',
                        'label'       => __('Sender ID', 'wp-sms'),
                        'required'    => false,
                        'description' => __('Alphanumeric sender shown to recipients. Ignored for US/CA destinations.', 'wp-sms'),
                    ],
                    'sms_type' => [
                        'type'        => 'select',
                        'label'       => __('SMS Type', 'wp-sms'),
                        'required'    => false,
                        'default'     => 'Transactional',
                        'options'     => [
                            ['value' => 'Transactional', 'label' => __('Transactional', 'wp-sms')],
                            ['value' => 'Promotional', 'label' => __('Promotional', 'wp-sms')],
                        ],
                        'description' => __('Transactional optimizes for reliability; Promotional optimizes for cost.', 'wp-sms'),
                    ],
                    'max_price_usd' => [
                        'type'        => 'string',
                        'label'       => __('Max Price (USD)', 'wp-sms'),
                        'required'    => false,
                        'placeholder' => '0.50',
                        'description' => __('Caps spend per outgoing SMS. AWS will not deliver if route price exceeds this.', 'wp-sms'),
                    ],
                ],
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        $accessKey = $this->getSharedConfig('access_key_id');
        $secretKey = $this->getSharedConfig('secret_access_key');
        $region    = $this->getSharedConfig('region') ?: 'us-east-1';

        if (!$accessKey || !$secretKey) {
            return DeliveryResult::failed(__('AWS SNS credentials not configured', 'wp-sms'));
        }

        $meta = $message->getMeta();
        $senderId   = $meta['sender_id']     ?? $this->getChannelConfig('sms', 'sender_id');
        $smsType    = $meta['sms_type']      ?? $this->getChannelConfig('sms', 'sms_type', 'Transactional');
        $maxPrice   = $meta['max_price_usd'] ?? $this->getChannelConfig('sms', 'max_price_usd');

        $form = [
            'Action'      => 'Publish',
            'Version'     => self::API_VERSION,
            'PhoneNumber' => $message->getRecipient(),
            'Message'     => $message->getBody(),
        ];

        $entryIndex = 1;
        if ($senderId) {
            $form = array_merge($form, $this->messageAttributeEntry($entryIndex++, 'AWS.SNS.SMS.SenderID', 'String', (string) $senderId));
        }
        if ($smsType) {
            $form = array_merge($form, $this->messageAttributeEntry($entryIndex++, 'AWS.SNS.SMS.SMSType', 'String', (string) $smsType));
        }
        if ($maxPrice !== null && $maxPrice !== '') {
            $form = array_merge($form, $this->messageAttributeEntry($entryIndex++, 'AWS.SNS.SMS.MaxPrice', 'Number', (string) $maxPrice));
        }

        $result = $this->postSigned($region, $accessKey, $secretKey, $form);
        if ($result instanceof DeliveryResult) {
            return $result;
        }

        $parsed = $this->parseSnsXml($result['body']);

        if ($result['code'] >= 200 && $result['code'] < 300) {
            if (!empty($parsed['MessageId'])) {
                return DeliveryResult::queued($parsed['MessageId']);
            }
            return DeliveryResult::failed(
                __('AWS SNS returned 2xx without a MessageId', 'wp-sms'),
                ['raw' => $result['body']],
            );
        }

        $code = $parsed['Code'] ?? null;
        $msg  = $parsed['Message'] ?? null;
        $error = ($code && $msg)
            ? "AWS SNS: {$code} — {$msg}"
            : sprintf('AWS SNS HTTP %d', $result['code']);

        return DeliveryResult::failed($error, array_filter([
            'aws_error_code' => $code,
            'aws_request_id' => $parsed['RequestId'] ?? null,
            'http_code'      => $result['code'] ?: null,
        ]));
    }

    public function getCredit(): ?string
    {
        // SNS is post-pay; no balance API exists.
        return null;
    }

    public function testConnection(): TestConnectionResult
    {
        $accessKey = $this->getSharedConfig('access_key_id');
        $secretKey = $this->getSharedConfig('secret_access_key');
        $region    = $this->getSharedConfig('region') ?: 'us-east-1';

        if (!$accessKey || !$secretKey) {
            return TestConnectionResult::error(__('Access Key ID and Secret Access Key are required', 'wp-sms'));
        }

        $result = $this->postSigned($region, $accessKey, $secretKey, [
            'Action'  => 'GetSMSAttributes',
            'Version' => self::API_VERSION,
        ]);

        if ($result instanceof DeliveryResult) {
            return TestConnectionResult::error(
                __('Could not reach the AWS SNS API. Check your server\'s internet connection.', 'wp-sms'),
            );
        }

        if ($result['code'] === 403) {
            return TestConnectionResult::error(__('Invalid AWS credentials', 'wp-sms'));
        }

        $parsed = $this->parseSnsXml($result['body']);

        if ($result['code'] < 200 || $result['code'] >= 300) {
            $code = $parsed['Code'] ?? null;
            $msg  = $parsed['Message'] ?? null;
            return TestConnectionResult::error(
                ($code && $msg)
                    ? sprintf(__('AWS SNS: %s — %s', 'wp-sms'), $code, $msg)
                    : __('Could not reach AWS SNS', 'wp-sms'),
            );
        }

        $limit = $parsed['MonthlySpendLimit'] ?? null;
        $message = $limit !== null
            ? sprintf(__('Connected — Monthly spend limit: $%s USD', 'wp-sms'), $limit)
            : __('Connected to AWS SNS', 'wp-sms');

        return TestConnectionResult::ok($message, array_filter([
            'monthly_spend_limit' => $limit,
        ]));
    }

    /**
     * @param array<string, string> $form
     * @return array{response: array, body: string, code: int}|DeliveryResult
     */
    private function postSigned(string $region, string $accessKey, string $secretKey, array $form): array|DeliveryResult
    {
        $host    = "sns.{$region}.amazonaws.com";
        $body    = http_build_query($form, '', '&', PHP_QUERY_RFC3986);
        $amzDate = $this->now();

        $auth = $this->signV4($accessKey, $secretKey, $region, $host, $amzDate, $body);

        return $this->httpPost("https://{$host}/", [
            'headers' => [
                'Content-Type'  => 'application/x-www-form-urlencoded',
                'Host'          => $host,
                'X-Amz-Date'    => $amzDate,
                'Authorization' => $auth,
            ],
            'body' => $body,
        ]);
    }

    private function signV4(string $accessKey, string $secretKey, string $region, string $host, string $amzDate, string $payload): string
    {
        $dateStamp        = substr($amzDate, 0, 8);
        $signedHeaders    = 'content-type;host;x-amz-date';
        $credentialScope  = "{$dateStamp}/{$region}/" . self::SERVICE . '/aws4_request';

        $canonicalRequest = "POST\n"
            . "/\n"
            . "\n"
            . "content-type:application/x-www-form-urlencoded\n"
            . "host:{$host}\n"
            . "x-amz-date:{$amzDate}\n"
            . "\n"
            . "{$signedHeaders}\n"
            . hash('sha256', $payload);

        $stringToSign = "AWS4-HMAC-SHA256\n"
            . "{$amzDate}\n"
            . "{$credentialScope}\n"
            . hash('sha256', $canonicalRequest);

        $signingKey = $this->deriveSigningKey($secretKey, $dateStamp, $region);
        $signature  = hash_hmac('sha256', $stringToSign, $signingKey);

        return "AWS4-HMAC-SHA256 Credential={$accessKey}/{$credentialScope}, "
            . "SignedHeaders={$signedHeaders}, Signature={$signature}";
    }

    /**
     * Derive the SigV4 signing key.
     *
     * Two gotchas baked into the algorithm:
     *   1. Step 1 prepends the literal string "AWS4" (NOT "AWS4_") to the secret.
     *   2. Steps 1-4 use raw binary HMAC (true flag); the final signing in signV4()
     *      uses hex (default). Mixing produces SignatureDoesNotMatch with no useful diagnostic.
     */
    private function deriveSigningKey(string $secretKey, string $dateStamp, string $region): string
    {
        $kDate    = hash_hmac('sha256', $dateStamp,     'AWS4' . $secretKey, true);
        $kRegion  = hash_hmac('sha256', $region,        $kDate,              true);
        $kService = hash_hmac('sha256', self::SERVICE,  $kRegion,            true);
        return hash_hmac('sha256', 'aws4_request', $kService, true);
    }

    /**
     * Returns the form fields for one MessageAttributes entry at index N.
     *
     * @return array<string, string>
     */
    private function messageAttributeEntry(int $n, string $name, string $dataType, string $value): array
    {
        return [
            "MessageAttributes.entry.{$n}.Name"             => $name,
            "MessageAttributes.entry.{$n}.Value.DataType"   => $dataType,
            "MessageAttributes.entry.{$n}.Value.StringValue" => $value,
        ];
    }

    /**
     * Flatten the SNS XML response shapes we care about.
     *
     * @return array{MessageId?: string, Code?: string, Message?: string, RequestId?: string, MonthlySpendLimit?: string}
     */
    private function parseSnsXml(string $body): array
    {
        if ($body === '') {
            return [];
        }

        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($body, \SimpleXMLElement::class, LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_use_internal_errors($previous);

        if ($xml === false) {
            return [];
        }

        $out = [];

        // PublishResponse → PublishResult → MessageId; ResponseMetadata → RequestId
        if (isset($xml->PublishResult->MessageId)) {
            $out['MessageId'] = (string) $xml->PublishResult->MessageId;
        }
        if (isset($xml->ResponseMetadata->RequestId)) {
            $out['RequestId'] = (string) $xml->ResponseMetadata->RequestId;
        }

        // <ErrorResponse><Error><Code/>/<Message/></Error><RequestId/></ErrorResponse>
        if (isset($xml->Error)) {
            if (isset($xml->Error->Code))    $out['Code']    = (string) $xml->Error->Code;
            if (isset($xml->Error->Message)) $out['Message'] = (string) $xml->Error->Message;
        }
        if (isset($xml->RequestId) && !isset($out['RequestId'])) {
            $out['RequestId'] = (string) $xml->RequestId;
        }

        // GetSMSAttributesResponse → GetSMSAttributesResult → attributes → entry[]
        if (isset($xml->GetSMSAttributesResult->attributes->entry)) {
            foreach ($xml->GetSMSAttributesResult->attributes->entry as $entry) {
                if ((string) $entry->key === 'MonthlySpendLimit') {
                    $out['MonthlySpendLimit'] = (string) $entry->value;
                    break;
                }
            }
        }

        return $out;
    }

    /** Overridable in tests to pin SigV4 timestamp for deterministic signature assertions. */
    protected function now(): string
    {
        return gmdate('Ymd\THis\Z');
    }
}
