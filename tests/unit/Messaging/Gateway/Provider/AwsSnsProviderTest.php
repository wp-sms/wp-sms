<?php

namespace WSms\Tests\Unit\Messaging\Gateway\Provider;

use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Gateway\Provider\AwsSnsProvider;
use WSms\Messaging\Message\Message;
use WSms\Tests\Unit\Messaging\Gateway\AbstractProviderTestCase;

/** Test double that pins the SigV4 timestamp so we can assert deterministic signatures. */
class TestableAwsSnsProvider extends AwsSnsProvider
{
    public string $pinnedNow = '20260509T120000Z';

    protected function now(): string
    {
        return $this->pinnedNow;
    }
}

class AwsSnsProviderTest extends AbstractProviderTestCase
{
    // Canonical AWS test-vector creds — well-known public examples, never live.
    private const ACCESS_KEY = 'AKIAIOSFODNN7EXAMPLE';
    private const SECRET_KEY = 'wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY';

    protected function createProvider(): AbstractProvider
    {
        return new AwsSnsProvider();
    }

    private function configureProvider(array $sharedOverrides = [], array $smsOverrides = []): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'awssns' => [
                'shared' => array_merge([
                    'access_key_id'     => self::ACCESS_KEY,
                    'secret_access_key' => self::SECRET_KEY,
                    'region'            => 'us-east-1',
                ], $sharedOverrides),
                'channels' => [
                    'sms' => $smsOverrides,
                ],
            ],
        ];
    }

    private function mockSnsResponse(string $xml, int $code = 200): void
    {
        $GLOBALS['_test_wp_remote_post'] = [
            'body'     => $xml,
            'response' => ['code' => $code],
        ];
    }

    private function successXml(string $messageId, string $requestId = 'req-1'): string
    {
        return <<<XML
<?xml version="1.0"?>
<PublishResponse xmlns="http://sns.amazonaws.com/doc/2010-03-31/">
  <PublishResult>
    <MessageId>{$messageId}</MessageId>
  </PublishResult>
  <ResponseMetadata>
    <RequestId>{$requestId}</RequestId>
  </ResponseMetadata>
</PublishResponse>
XML;
    }

    private function errorXml(string $code, string $message, string $requestId = 'req-err'): string
    {
        return <<<XML
<?xml version="1.0"?>
<ErrorResponse xmlns="http://sns.amazonaws.com/doc/2010-03-31/">
  <Error>
    <Type>Sender</Type>
    <Code>{$code}</Code>
    <Message>{$message}</Message>
  </Error>
  <RequestId>{$requestId}</RequestId>
</ErrorResponse>
XML;
    }

    private function getSmsAttributesXml(?string $monthlySpendLimit = null): string
    {
        $entry = $monthlySpendLimit !== null
            ? "<entry><key>MonthlySpendLimit</key><value>{$monthlySpendLimit}</value></entry>"
            : '';
        return <<<XML
<?xml version="1.0"?>
<GetSMSAttributesResponse xmlns="http://sns.amazonaws.com/doc/2010-03-31/">
  <GetSMSAttributesResult>
    <attributes>{$entry}</attributes>
  </GetSMSAttributesResult>
  <ResponseMetadata>
    <RequestId>req-attrs</RequestId>
  </ResponseMetadata>
</GetSMSAttributesResponse>
XML;
    }

    private function createMessage(string $body = 'Hello', string $recipient = '+15551234567', array $meta = []): Message
    {
        return new Message('sms', $recipient, $body, null, $meta);
    }

    /**
     * Decode a URL-encoded SNS request body into a flat key=>value map.
     *
     * Why not parse_str? PHP's parse_str converts dots in top-level keys to
     * underscores ("MessageAttributes.entry.1.Name" → "MessageAttributes_entry_1_Name"),
     * which mangles the AWS query-string convention for nested attributes.
     *
     * @return array<string, string>
     */
    private function parseBody(string $body): array
    {
        $out = [];
        foreach (explode('&', $body) as $pair) {
            if ($pair === '') {
                continue;
            }
            [$key, $value] = array_pad(explode('=', $pair, 2), 2, '');
            $out[urldecode($key)] = urldecode($value);
        }
        return $out;
    }

    /**
     * Group MessageAttributes.entry.N.* keys back into a name => [DataType, StringValue] map.
     *
     * @param array<string, string> $body
     * @return array<string, array{DataType: string, StringValue: string}>
     */
    private function extractMessageAttributes(array $body): array
    {
        $byIndex = [];
        foreach ($body as $key => $value) {
            if (preg_match('/^MessageAttributes\.entry\.(\d+)\.(Name|Value\.DataType|Value\.StringValue)$/', $key, $m)) {
                $byIndex[$m[1]][$m[2]] = $value;
            }
        }

        $byName = [];
        foreach ($byIndex as $entry) {
            if (isset($entry['Name'])) {
                $byName[$entry['Name']] = [
                    'DataType'    => $entry['Value.DataType'] ?? '',
                    'StringValue' => $entry['Value.StringValue'] ?? '',
                ];
            }
        }
        return $byName;
    }

    private function bodyHasMessageAttributes(array $body): bool
    {
        foreach (array_keys($body) as $key) {
            if (str_starts_with($key, 'MessageAttributes.entry.')) {
                return true;
            }
        }
        return false;
    }

    // --- Identity & schema ---

    public function testTestedFlagIsFalseUntilManuallyVerified(): void
    {
        $this->assertFalse(AwsSnsProvider::TESTED);
    }

    public function testGetSupportedChannelsReturnsSms(): void
    {
        $this->assertSame(['sms'], $this->createProvider()->getSupportedChannels());
    }

    // --- Send ---

    public function testDoSendReturnsQueuedOnSuccess(): void
    {
        $this->configureProvider();
        $this->mockSnsResponse($this->successXml('abc-123'));

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertTrue($result->success);
        $this->assertSame('queued', $result->status);
        $this->assertSame('abc-123', $result->providerId);
    }

    public function testDoSendIncludesSenderIdInMessageAttributes(): void
    {
        $this->configureProvider([], ['sender_id' => 'MYBRAND']);
        $this->mockSnsResponse($this->successXml('id-1'));

        $this->createProvider()->send($this->createMessage());

        $body = $this->parseBody($GLOBALS['_test_wp_remote_post_last_args']['body']);
        $byName = $this->extractMessageAttributes($body);

        $this->assertSame('String', $byName['AWS.SNS.SMS.SenderID']['DataType']);
        $this->assertSame('MYBRAND', $byName['AWS.SNS.SMS.SenderID']['StringValue']);
    }

    public function testDoSendIncludesSmsTypeAndMaxPriceWithCorrectDataTypes(): void
    {
        $this->configureProvider([], ['sms_type' => 'Promotional', 'max_price_usd' => '0.25']);
        $this->mockSnsResponse($this->successXml('id-2'));

        $this->createProvider()->send($this->createMessage());

        $body = $this->parseBody($GLOBALS['_test_wp_remote_post_last_args']['body']);
        $byName = $this->extractMessageAttributes($body);

        $this->assertSame('String', $byName['AWS.SNS.SMS.SMSType']['DataType']);
        $this->assertSame('Promotional', $byName['AWS.SNS.SMS.SMSType']['StringValue']);
        $this->assertSame('Number', $byName['AWS.SNS.SMS.MaxPrice']['DataType']);
        $this->assertSame('0.25', $byName['AWS.SNS.SMS.MaxPrice']['StringValue']);
    }

    public function testDoSendOmitsOptionalAttributesWhenAbsent(): void
    {
        // No sender_id, no max_price_usd, and clear the default sms_type by passing empty meta override.
        $this->configureProvider([], []);
        $this->mockSnsResponse($this->successXml('id-3'));

        $this->createProvider()->send($this->createMessage('Hi', '+15551112222', ['sms_type' => '']));

        $body = $this->parseBody($GLOBALS['_test_wp_remote_post_last_args']['body']);
        $this->assertFalse($this->bodyHasMessageAttributes($body));
    }

    public function testDoSendAllowsPerMessageMetaOverride(): void
    {
        $this->configureProvider([], ['sender_id' => 'CHANNELDEFAULT']);
        $this->mockSnsResponse($this->successXml('id-4'));

        $this->createProvider()->send($this->createMessage('Hi', '+15551112222', ['sender_id' => 'OVERRIDE']));

        $body = $this->parseBody($GLOBALS['_test_wp_remote_post_last_args']['body']);
        $byName = $this->extractMessageAttributes($body);

        $this->assertSame('OVERRIDE', $byName['AWS.SNS.SMS.SenderID']['StringValue']);
    }

    public function testDoSendReturnsFailedOnAwsError(): void
    {
        $this->configureProvider();
        $this->mockSnsResponse($this->errorXml('AuthorizationError', 'Access denied'), 403);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertSame('AuthorizationError', $result->meta['aws_error_code']);
        $this->assertStringContainsString('AuthorizationError', $result->error);
    }

    public function testDoSendFailsWhenCredentialsMissing(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('not configured', $result->error);
    }

    public function testDoSendUsesCorrectRegionInHostAndSignature(): void
    {
        $this->configureProvider(['region' => 'eu-west-1']);
        $this->mockSnsResponse($this->successXml('id-region'));

        $this->createProvider()->send($this->createMessage());

        $this->assertSame('https://sns.eu-west-1.amazonaws.com/', $GLOBALS['_test_wp_remote_post_last_url']);

        $auth = $GLOBALS['_test_wp_remote_post_last_args']['headers']['Authorization'];
        $this->assertMatchesRegularExpression(
            '#Credential=' . preg_quote(self::ACCESS_KEY, '#') . '/\d{8}/eu-west-1/sns/aws4_request#',
            $auth,
        );
    }

    public function testDoSendIgnoresMediaUrlsMeta(): void
    {
        $this->configureProvider();
        $this->mockSnsResponse($this->successXml('id-mms'));

        $result = $this->createProvider()->send($this->createMessage('Pic', '+15551112222', [
            'media_urls' => ['https://example.com/a.jpg'],
        ]));

        $body = $GLOBALS['_test_wp_remote_post_last_args']['body'];
        $this->assertStringNotContainsString('MediaUrl', $body);
        $this->assertStringNotContainsString('media_urls', $body);
        $this->assertTrue($result->success);
    }

    // --- testConnection ---

    public function testDoTestConnectionReturnsOkOn200(): void
    {
        $this->configureProvider();
        $this->mockSnsResponse($this->getSmsAttributesXml());

        $result = $this->createProvider()->testConnection();

        $this->assertTrue($result->success);
    }

    public function testDoTestConnectionSurfacesMonthlySpendLimit(): void
    {
        $this->configureProvider();
        $this->mockSnsResponse($this->getSmsAttributesXml('50'));

        $result = $this->createProvider()->testConnection();

        $this->assertTrue($result->success);
        $this->assertSame('50', $result->details['monthly_spend_limit']);
        $this->assertStringContainsString('50', $result->message);
    }

    public function testDoTestConnectionFailsOnAuthorizationError(): void
    {
        $this->configureProvider();
        $this->mockSnsResponse($this->errorXml('InvalidClientTokenId', 'The security token included in the request is invalid'), 403);

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid AWS credentials', $result->message);
    }

    public function testTestConnectionRequiresCredentials(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('required', $result->message);
    }

    // --- SigV4 signing ---

    public function testSigV4HeadersIncludeXAmzDateAndAuthorization(): void
    {
        $this->configureProvider();
        $this->mockSnsResponse($this->successXml('id-headers'));

        $this->createProvider()->send($this->createMessage());

        $headers = $GLOBALS['_test_wp_remote_post_last_args']['headers'];
        $this->assertArrayHasKey('X-Amz-Date', $headers);
        $this->assertMatchesRegularExpression('/^\d{8}T\d{6}Z$/', $headers['X-Amz-Date']);
        $this->assertArrayHasKey('Authorization', $headers);
        $this->assertStringStartsWith('AWS4-HMAC-SHA256 Credential=', $headers['Authorization']);
        $this->assertStringContainsString('SignedHeaders=content-type;host;x-amz-date', $headers['Authorization']);
        $this->assertStringContainsString('Signature=', $headers['Authorization']);
        $this->assertSame('application/x-www-form-urlencoded', $headers['Content-Type']);
        $this->assertSame('sns.us-east-1.amazonaws.com', $headers['Host']);
    }

    public function testSigV4SignatureIsDeterministic(): void
    {
        $this->configureProvider([], []);
        $this->mockSnsResponse($this->successXml('id-det'));

        $provider = new TestableAwsSnsProvider();
        // Pin sms_type override to empty so MessageAttributes don't shift the form payload.
        $provider->send($this->createMessage('Hello', '+15551234567', ['sms_type' => '']));

        $args = $GLOBALS['_test_wp_remote_post_last_args'];
        $body = $args['body'];

        // Independently recompute the signature from the AWS spec — same inputs MUST produce
        // the same signature each run, and a separate implementation guards against drift.
        $expected = $this->computeExpectedV4Signature(
            secretKey: self::SECRET_KEY,
            accessKey: self::ACCESS_KEY,
            region:    'us-east-1',
            host:      'sns.us-east-1.amazonaws.com',
            amzDate:   '20260509T120000Z',
            payload:   $body,
        );

        $this->assertStringContainsString('Signature=' . $expected, $args['headers']['Authorization']);
    }

    public function testRegionDefaultsToUsEast1WhenMissing(): void
    {
        // Configure WITHOUT a region (overwrite the default region).
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'awssns' => [
                'shared' => [
                    'access_key_id'     => self::ACCESS_KEY,
                    'secret_access_key' => self::SECRET_KEY,
                ],
                'channels' => ['sms' => []],
            ],
        ];
        $this->mockSnsResponse($this->successXml('id-default-region'));

        $this->createProvider()->send($this->createMessage());

        $this->assertSame('https://sns.us-east-1.amazonaws.com/', $GLOBALS['_test_wp_remote_post_last_url']);
    }

    /** Independent SigV4 implementation — kept apart from the provider so we can detect drift. */
    private function computeExpectedV4Signature(
        string $secretKey,
        string $accessKey,
        string $region,
        string $host,
        string $amzDate,
        string $payload,
    ): string {
        $dateStamp = substr($amzDate, 0, 8);

        $canonicalRequest = "POST\n/\n\n"
            . "content-type:application/x-www-form-urlencoded\n"
            . "host:{$host}\n"
            . "x-amz-date:{$amzDate}\n"
            . "\n"
            . "content-type;host;x-amz-date\n"
            . hash('sha256', $payload);

        $stringToSign = "AWS4-HMAC-SHA256\n"
            . "{$amzDate}\n"
            . "{$dateStamp}/{$region}/sns/aws4_request\n"
            . hash('sha256', $canonicalRequest);

        $kDate    = hash_hmac('sha256', $dateStamp,     'AWS4' . $secretKey, true);
        $kRegion  = hash_hmac('sha256', $region,        $kDate,              true);
        $kService = hash_hmac('sha256', 'sns',          $kRegion,            true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService,           true);

        return hash_hmac('sha256', $stringToSign, $kSigning);
    }
}
