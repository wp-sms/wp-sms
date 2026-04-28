<?php

namespace WSms\Tests\Unit\Messaging\Gateway\Provider;

use WSms\Messaging\Gateway\AbstractProvider;
use WSms\Messaging\Gateway\Provider\AfricasTalkingProvider;
use WSms\Messaging\Message\Message;
use WSms\Tests\Unit\Messaging\Gateway\AbstractProviderTestCase;

class AfricasTalkingProviderTest extends AbstractProviderTestCase
{
    private const API_KEY = 'atsk_test_key_12345';
    private const SANDBOX_USERNAME = 'sandbox';
    private const LIVE_USERNAME = 'liveuser';

    protected function createProvider(): AbstractProvider
    {
        return new AfricasTalkingProvider();
    }

    private function configureSandbox(array $sharedOverrides = [], array $smsOverrides = []): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [
            'africastalking' => [
                'shared'   => array_merge([
                    'username' => self::SANDBOX_USERNAME,
                    'api_key'  => self::API_KEY,
                ], $sharedOverrides),
                'channels' => [
                    'sms' => $smsOverrides,
                ],
            ],
        ];
    }

    private function configureLive(): void
    {
        $this->configureSandbox(['username' => self::LIVE_USERNAME]);
    }

    private function createMessage(string $recipient = '+254711000000', string $body = 'Hello'): Message
    {
        return new Message('sms', $recipient, $body);
    }

    private function mockHttpPost(array $responseBody, int $statusCode = 201): void
    {
        $GLOBALS['_test_wp_remote_post'] = [
            'body'     => json_encode($responseBody),
            'response' => ['code' => $statusCode],
        ];
    }

    private function mockHttpGet(array $responseBody, int $statusCode = 200): void
    {
        $GLOBALS['_test_wp_remote_get'] = [
            'body'     => json_encode($responseBody),
            'response' => ['code' => $statusCode],
        ];
    }

    private function expectedToken(string $apiKey = self::API_KEY): string
    {
        return hash_hmac('sha256', 'africastalking-callback', $apiKey);
    }

    // --- Identity & schema ---

    public function testIdIsAfricastalking(): void
    {
        $this->assertSame('africastalking', $this->createProvider()->getId());
    }

    public function testTestedFlagIsFalseUntilManuallyVerified(): void
    {
        $this->assertFalse(AfricasTalkingProvider::TESTED);
    }

    public function testSupportedChannelsIsSmsOnly(): void
    {
        $this->assertSame(['sms'], $this->createProvider()->getSupportedChannels());
    }

    public function testConfigSchemaHasUsernameAndApiKey(): void
    {
        $schema = $this->createProvider()->getConfigSchema();

        $this->assertArrayHasKey('username', $schema['shared']);
        $this->assertArrayHasKey('api_key', $schema['shared']);
        $this->assertTrue($schema['shared']['username']['required']);
        $this->assertTrue($schema['shared']['api_key']['required']);
        $this->assertSame('secret', $schema['shared']['api_key']['type']);
        $this->assertArrayHasKey('from', $schema['channels']['sms']);
        $this->assertFalse((bool) ($schema['channels']['sms']['from']['required'] ?? true));
    }

    public function testIsConfiguredWithFullConfig(): void
    {
        $this->configureSandbox();
        $this->assertTrue($this->createProvider()->isConfigured());
    }

    // --- Send ---

    public function testSendReturnsSuccessWithMessageIdAndCost(): void
    {
        $this->configureSandbox();
        $this->mockHttpPost([
            'SMSMessageData' => [
                'Message'    => 'Sent to 1/1 Total Cost: KES 0.8000',
                'Recipients' => [[
                    'statusCode' => 101,
                    'number'     => '+254711000000',
                    'status'     => 'Success',
                    'cost'       => 'KES 0.8000',
                    'messageId'  => 'ATPid_abcd1234',
                ]],
            ],
        ]);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertTrue($result->success);
        $this->assertSame('sent', $result->status);
        $this->assertSame('ATPid_abcd1234', $result->providerId);
        $this->assertSame(0.8, $result->cost);
    }

    public function testSendUsesSandboxUrlWhenUsernameIsSandbox(): void
    {
        $this->configureSandbox();
        $this->mockHttpPost([
            'SMSMessageData' => [
                'Recipients' => [[
                    'statusCode' => 101,
                    'status'     => 'Success',
                    'messageId'  => 'ATPid_1',
                ]],
            ],
        ]);

        $this->createProvider()->send($this->createMessage());

        $this->assertSame(
            'https://api.sandbox.africastalking.com/version1/messaging',
            $GLOBALS['_test_wp_remote_post_last_url'],
        );
    }

    public function testSendUsesLiveUrlForNonSandboxUsername(): void
    {
        $this->configureLive();
        $this->mockHttpPost([
            'SMSMessageData' => [
                'Recipients' => [[
                    'statusCode' => 101,
                    'status'     => 'Success',
                    'messageId'  => 'ATPid_2',
                ]],
            ],
        ]);

        $this->createProvider()->send($this->createMessage());

        $this->assertSame(
            'https://api.africastalking.com/version1/messaging',
            $GLOBALS['_test_wp_remote_post_last_url'],
        );
    }

    public function testSendPassesFormBodyAndApiKeyHeader(): void
    {
        $this->configureSandbox(smsOverrides: ['from' => 'AFTKNG']);
        $this->mockHttpPost([
            'SMSMessageData' => [
                'Recipients' => [[
                    'statusCode' => 101,
                    'status'     => 'Success',
                    'messageId'  => 'ATPid_3',
                ]],
            ],
        ]);

        $this->createProvider()->send($this->createMessage('+254711000000', 'Hi there'));

        $args = $GLOBALS['_test_wp_remote_post_last_args'];
        $this->assertSame(self::API_KEY, $args['headers']['apiKey']);
        $this->assertSame('application/json', $args['headers']['Accept']);
        $this->assertSame('sandbox', $args['body']['username']);
        $this->assertSame('+254711000000', $args['body']['to']);
        $this->assertSame('Hi there', $args['body']['message']);
        $this->assertSame('AFTKNG', $args['body']['from']);
    }

    public function testSendOmitsFromWhenNotConfigured(): void
    {
        $this->configureSandbox();
        $this->mockHttpPost([
            'SMSMessageData' => [
                'Recipients' => [[
                    'statusCode' => 101,
                    'status'     => 'Success',
                    'messageId'  => 'ATPid_4',
                ]],
            ],
        ]);

        $this->createProvider()->send($this->createMessage());

        $body = $GLOBALS['_test_wp_remote_post_last_args']['body'];
        $this->assertArrayNotHasKey('from', $body);
    }

    public function testSendReturnsFailedWhenCredentialsMissing(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('not configured', $result->error);
    }

    public function testSendReturnsFailedOn401(): void
    {
        $this->configureSandbox();
        $this->mockHttpPost(['errorMessage' => 'Authentication Failed'], 401);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid', $result->error);
    }

    public function testSendReturnsFailedWhenRecipientStatusIsBlacklisted(): void
    {
        $this->configureSandbox();
        $this->mockHttpPost([
            'SMSMessageData' => [
                'Message'    => 'Sent to 0/1',
                'Recipients' => [[
                    'statusCode' => 406,
                    'number'     => '+254711000000',
                    'status'     => 'UserInBlackList',
                    'cost'       => '0',
                    'messageId'  => 'None',
                ]],
            ],
        ]);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertSame('UserInBlackList', $result->error);
        $this->assertSame(406, $result->meta['at_status_code']);
    }

    public function testSendReturnsFailedOnInvalidPhoneNumber(): void
    {
        $this->configureSandbox();
        $this->mockHttpPost([
            'SMSMessageData' => [
                'Recipients' => [[
                    'statusCode' => 403,
                    'status'     => 'InvalidPhoneNumber',
                    'cost'       => '0',
                    'messageId'  => 'None',
                ]],
            ],
        ]);

        $result = $this->createProvider()->send($this->createMessage('not-a-number'));

        $this->assertFalse($result->success);
        $this->assertSame('InvalidPhoneNumber', $result->error);
        $this->assertSame(403, $result->meta['at_status_code']);
    }

    public function testSendReturnsFailedWhenNoRecipients(): void
    {
        $this->configureSandbox();
        $this->mockHttpPost([
            'SMSMessageData' => ['Message' => 'No recipients', 'Recipients' => []],
        ]);

        $result = $this->createProvider()->send($this->createMessage());

        $this->assertFalse($result->success);
        $this->assertSame('No recipients', $result->error);
    }

    // --- Credit ---

    public function testGetCreditReturnsBalanceString(): void
    {
        $this->configureSandbox();
        $this->mockHttpGet(['UserData' => ['balance' => 'KES 1500.0000']]);

        $this->assertSame('KES 1500.0000', $this->createProvider()->getCredit());
    }

    public function testGetCreditReturnsNullWhenUnconfigured(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];
        $this->assertNull($this->createProvider()->getCredit());
    }

    public function testGetCreditUsesUserEndpointWithUsername(): void
    {
        $this->configureLive();
        $capturedUrl = null;
        $capturedArgs = null;
        $GLOBALS['_test_wp_remote_get'] = function (string $url, array $args) use (&$capturedUrl, &$capturedArgs) {
            $capturedUrl = $url;
            $capturedArgs = $args;
            return [
                'body'     => json_encode(['UserData' => ['balance' => 'USD 5.0000']]),
                'response' => ['code' => 200],
            ];
        };

        $this->createProvider()->getCredit();

        $this->assertSame(
            'https://api.africastalking.com/version1/user?username=' . self::LIVE_USERNAME,
            $capturedUrl,
        );
        $this->assertSame(self::API_KEY, $capturedArgs['headers']['apiKey']);
    }

    // --- Test connection ---

    public function testTestConnectionReturnsOkWithBalance(): void
    {
        $this->configureSandbox();
        $this->mockHttpGet(['UserData' => ['balance' => 'KES 1000.0000']]);

        $result = $this->createProvider()->testConnection();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('KES 1000.0000', $result->message);
        $this->assertSame('KES 1000.0000', $result->details['balance']);
    }

    public function testTestConnectionReturnsErrorOn401(): void
    {
        $this->configureSandbox();
        $this->mockHttpGet(['errorMessage' => 'unauth'], 401);

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Invalid', $result->message);
    }

    public function testTestConnectionReturnsErrorWhenCredentialsMissing(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];

        $result = $this->createProvider()->testConnection();

        $this->assertFalse($result->success);
        $this->assertStringContainsString('required', $result->message);
    }

    // --- Status callback ---

    public function testValidateStatusCallbackAcceptsMatchingToken(): void
    {
        $this->configureSandbox();
        $request = new \WP_REST_Request('POST', '/wsms/v1/callbacks/africastalking/status');
        $request->set_param('token', $this->expectedToken());

        $this->assertTrue($this->createProvider()->validateStatusCallback($request));
    }

    public function testValidateStatusCallbackRejectsBadToken(): void
    {
        $this->configureSandbox();
        $request = new \WP_REST_Request('POST');
        $request->set_param('token', 'totally-wrong');

        $this->assertFalse($this->createProvider()->validateStatusCallback($request));
    }

    public function testValidateStatusCallbackRejectsWhenNotConfigured(): void
    {
        $GLOBALS['_test_options']['wsms_gateway_configs'] = [];
        $request = new \WP_REST_Request('POST');
        $request->set_param('token', 'anything');

        $this->assertFalse($this->createProvider()->validateStatusCallback($request));
    }

    public function testParseStatusCallbackMapsDeliveredStatus(): void
    {
        $request = new \WP_REST_Request('POST');
        $request->set_param('id', 'ATPid_xyz');
        $request->set_param('status', 'Success');
        $request->set_param('phoneNumber', '+254711000000');
        $request->set_param('networkCode', '63902');

        $updates = $this->createProvider()->parseStatusCallback($request);

        $this->assertCount(1, $updates);
        $this->assertSame('ATPid_xyz', $updates[0]->providerId);
        $this->assertSame('delivered', $updates[0]->status);
        $this->assertFalse($updates[0]->permanent);
    }

    public function testParseStatusCallbackMapsAllStatuses(): void
    {
        $cases = [
            'Sent'      => ['sent', false],
            'Submitted' => ['sent', false],
            'Buffered'  => ['queued', false],
            'Queued'    => ['queued', false],
            'Success'   => ['delivered', false],
            'Delivered' => ['delivered', false],
            'Rejected'  => ['failed', true],
            'Failed'    => ['failed', false],
            'Expired'   => ['failed', true],
        ];

        $provider = $this->createProvider();

        foreach ($cases as $atStatus => [$expected, $permanent]) {
            $request = new \WP_REST_Request('POST');
            $request->set_param('id', 'msg-' . $atStatus);
            $request->set_param('status', $atStatus);

            $updates = $provider->parseStatusCallback($request);
            $this->assertCount(1, $updates, "no update for {$atStatus}");
            $this->assertSame($expected, $updates[0]->status, "wrong status for {$atStatus}");
            $this->assertSame($permanent, $updates[0]->permanent, "wrong permanent for {$atStatus}");
        }
    }

    public function testParseStatusCallbackReturnsEmptyForMissingFields(): void
    {
        $request = new \WP_REST_Request('POST');
        $this->assertSame([], $this->createProvider()->parseStatusCallback($request));
    }

    public function testParseStatusCallbackIncludesFailureReason(): void
    {
        $request = new \WP_REST_Request('POST');
        $request->set_param('id', 'ATPid_failed');
        $request->set_param('status', 'Failed');
        $request->set_param('failureReason', 'DND_quiet_hours');

        $updates = $this->createProvider()->parseStatusCallback($request);

        $this->assertSame('failed', $updates[0]->status);
        $this->assertSame('DND_quiet_hours', $updates[0]->errorCode);
        $this->assertStringContainsString('Failed', $updates[0]->errorMessage);
        $this->assertStringContainsString('DND_quiet_hours', $updates[0]->errorMessage);
    }

    // --- Inbound callback ---

    public function testValidateInboundCallbackAcceptsMatchingToken(): void
    {
        $this->configureSandbox();
        $request = new \WP_REST_Request('POST');
        $request->set_param('token', $this->expectedToken());

        $this->assertTrue($this->createProvider()->validateInboundCallback($request));
    }

    public function testValidateInboundCallbackRejectsBadToken(): void
    {
        $this->configureSandbox();
        $request = new \WP_REST_Request('POST');
        $request->set_param('token', 'wrong');

        $this->assertFalse($this->createProvider()->validateInboundCallback($request));
    }

    public function testParseInboundCallbackProducesInboundMessage(): void
    {
        $request = new \WP_REST_Request('POST');
        $request->set_param('from', '+254711000000');
        $request->set_param('to', '12345');
        $request->set_param('text', 'STOP');
        $request->set_param('id', 'ATXid_inbound1');
        $request->set_param('date', '2026-04-28 10:00:00');
        $request->set_param('linkId', 'link-abc');
        $request->set_param('networkCode', '63902');

        $messages = $this->createProvider()->parseInboundCallback($request);

        $this->assertCount(1, $messages);
        $msg = $messages[0];
        $this->assertSame('+254711000000', $msg->from);
        $this->assertSame('12345', $msg->to);
        $this->assertSame('STOP', $msg->body);
        $this->assertSame('ATXid_inbound1', $msg->providerId);
        $this->assertSame('2026-04-28 10:00:00', $msg->meta['date']);
        $this->assertSame('link-abc', $msg->meta['link_id']);
        $this->assertSame('63902', $msg->meta['network_code']);
    }

    public function testParseInboundCallbackReturnsEmptyWithoutFrom(): void
    {
        $request = new \WP_REST_Request('POST');
        $this->assertSame([], $this->createProvider()->parseInboundCallback($request));
    }

    // --- Opt-out detection ---

    public function testIsOptOutErrorTrueForUserInBlackList(): void
    {
        $result = \WSms\Messaging\Contracts\DeliveryResult::failed('UserInBlackList', ['at_status_code' => 406]);
        $this->assertTrue($this->createProvider()->isOptOutError($result));
    }

    public function testIsOptOutErrorTrueForDoNotDisturb(): void
    {
        $result = \WSms\Messaging\Contracts\DeliveryResult::failed('DoNotDisturbRejection', ['at_status_code' => 409]);
        $this->assertTrue($this->createProvider()->isOptOutError($result));
    }

    public function testIsOptOutErrorFalseForOtherFailure(): void
    {
        $result = \WSms\Messaging\Contracts\DeliveryResult::failed('InvalidPhoneNumber', ['at_status_code' => 403]);
        $this->assertFalse($this->createProvider()->isOptOutError($result));
    }

    public function testIsOptOutErrorFalseWhenNoCode(): void
    {
        $result = \WSms\Messaging\Contracts\DeliveryResult::failed('Generic');
        $this->assertFalse($this->createProvider()->isOptOutError($result));
    }
}
