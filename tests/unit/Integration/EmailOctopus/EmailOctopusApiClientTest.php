<?php

namespace WSms\Tests\Unit\Integration\EmailOctopus;

use PHPUnit\Framework\TestCase;
use WSms\Integration\EmailOctopus\EmailOctopusApiClient;

class EmailOctopusApiClientTest extends TestCase
{
    private EmailOctopusApiClient $client;

    protected function setUp(): void
    {
        $this->client = new EmailOctopusApiClient('test-api-key');
    }

    protected function tearDown(): void
    {
        unset(
            $GLOBALS['_test_wp_remote_request'],
            $GLOBALS['_test_wp_remote_request_last_url'],
            $GLOBALS['_test_wp_remote_request_last_args'],
        );
    }

    private function mockResponse(int $code = 200, array $body = [], array $headers = []): void
    {
        $GLOBALS['_test_wp_remote_request'] = [
            'response' => ['code' => $code],
            'body'     => wp_json_encode($body),
            'headers'  => $headers,
        ];
    }

    private function mockResponseCallable(callable $fn): void
    {
        $GLOBALS['_test_wp_remote_request'] = $fn;
    }

    private function lastUrl(): string
    {
        return $GLOBALS['_test_wp_remote_request_last_url'] ?? '';
    }

    private function lastArgs(): array
    {
        return $GLOBALS['_test_wp_remote_request_last_args'] ?? [];
    }

    private function lastBody(): array
    {
        $args = $this->lastArgs();

        return json_decode($args['body'] ?? '{}', true);
    }

    // --- contactId ---

    public function testContactIdIsMd5OfLowercaseTrimmedEmail(): void
    {
        $this->assertSame(
            md5('user@example.com'),
            EmailOctopusApiClient::contactId('  User@Example.COM  '),
        );
    }

    // --- upsertContact ---

    public function testUpsertContactHitsListLevelEndpoint(): void
    {
        $this->mockResponse(200, ['id' => 'abc']);

        $this->client->upsertContact('list-1', 'test@example.com', ['FirstName' => 'John']);

        $this->assertSame('https://api.emailoctopus.com/lists/list-1/contacts', $this->lastUrl());
        $this->assertSame('PUT', $this->lastArgs()['method']);
    }

    public function testUpsertContactSendsArrayTags(): void
    {
        $this->mockResponse(200, ['id' => 'abc']);

        $this->client->upsertContact('list-1', 'test@example.com', [], ['vip', 'newsletter']);

        $body = $this->lastBody();
        $this->assertSame(['vip', 'newsletter'], $body['tags']);
    }

    public function testUpsertContactSendsFieldsAndStatus(): void
    {
        $this->mockResponse(200, ['id' => 'abc']);

        $this->client->upsertContact('list-1', 'test@example.com', ['FirstName' => 'Jane'], [], 'subscribed');

        $body = $this->lastBody();
        $this->assertSame('test@example.com', $body['email_address']);
        $this->assertSame('Jane', $body['fields']->FirstName ?? $body['fields']['FirstName']);
        $this->assertSame('subscribed', $body['status']);
    }

    public function testUpsertContactOmitsTagsWhenEmpty(): void
    {
        $this->mockResponse(200, ['id' => 'abc']);

        $this->client->upsertContact('list-1', 'test@example.com');

        $body = $this->lastBody();
        $this->assertArrayNotHasKey('tags', $body);
    }

    // --- updateContact ---

    public function testUpdateContactHitsContactIdEndpoint(): void
    {
        $this->mockResponse(200, ['id' => 'abc']);
        $email = 'test@example.com';
        $expectedId = md5($email);

        $this->client->updateContact('list-1', $email);

        $this->assertSame(
            "https://api.emailoctopus.com/lists/list-1/contacts/{$expectedId}",
            $this->lastUrl(),
        );
        $this->assertSame('PUT', $this->lastArgs()['method']);
    }

    public function testUpdateContactSendsObjectTags(): void
    {
        $this->mockResponse(200, ['id' => 'abc']);

        $this->client->updateContact('list-1', 'test@example.com', [], ['vip' => true, 'old' => false]);

        $body = $this->lastBody();
        $this->assertTrue($body['tags']['vip']);
        $this->assertFalse($body['tags']['old']);
    }

    public function testUpdateContactOmitsEmptyFields(): void
    {
        $this->mockResponse(200, ['id' => 'abc']);

        $this->client->updateContact('list-1', 'test@example.com', [], [], 'unsubscribed');

        $body = $this->lastBody();
        $this->assertArrayNotHasKey('fields', $body);
        $this->assertArrayNotHasKey('tags', $body);
        $this->assertSame('unsubscribed', $body['status']);
    }

    // --- queueIntoAutomation ---

    public function testQueueIntoAutomationSendsContactId(): void
    {
        $this->mockResponse(204);
        $email = 'test@example.com';

        $this->client->queueIntoAutomation('auto-1', $email);

        $body = $this->lastBody();
        $this->assertSame(md5($email), $body['contact_id']);
        $this->assertArrayNotHasKey('email_address', $body);
        $this->assertArrayNotHasKey('fields', $body);
    }

    public function testQueueIntoAutomationUsesPost(): void
    {
        $this->mockResponse(204);

        $this->client->queueIntoAutomation('auto-1', 'test@example.com');

        $this->assertStringContainsString('/automations/auto-1/queue', $this->lastUrl());
        $this->assertSame('POST', $this->lastArgs()['method']);
    }

    // --- batchContacts ---

    public function testBatchContactsUsesPut(): void
    {
        $this->mockResponse(200, ['success' => [], 'errors' => []]);

        $this->client->batchContacts('list-1', [['email_address' => 'a@b.com']]);

        $this->assertSame('PUT', $this->lastArgs()['method']);
        $this->assertStringContainsString('/contacts/batch', $this->lastUrl());
    }

    // --- 204 handling ---

    public function testHandles204NoContent(): void
    {
        $GLOBALS['_test_wp_remote_request'] = [
            'response' => ['code' => 204],
            'body'     => '',
            'headers'  => [],
        ];

        $this->client->deleteContact('list-1', 'test@example.com');
        // deleteContact returns void; no exception means success
        $this->assertTrue(true);
    }

    // --- Error parsing ---

    public function testErrorParsingUsesRfc7807Detail(): void
    {
        $this->mockResponse(400, [
            'type'   => 'https://emailoctopus.com/api-documentation/v2#error-code',
            'title'  => 'Bad Request',
            'detail' => 'The email address is invalid.',
            'status' => 400,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('The email address is invalid.');

        $this->client->validateKey();
    }

    public function testErrorParsingFallsBackToTitle(): void
    {
        $this->mockResponse(403, [
            'type'   => 'https://emailoctopus.com/api-documentation/v2#error-code',
            'title'  => 'Access Denied',
            'status' => 403,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Access Denied');

        $this->client->validateKey();
    }

    public function testValidationErrorIncludesFieldErrors(): void
    {
        $this->mockResponse(422, [
            'type'   => 'https://emailoctopus.com/api-documentation/v2#error-code',
            'title'  => 'Unprocessable Content',
            'detail' => 'Validation failed',
            'status' => 422,
            'errors' => [
                ['detail' => 'must be a valid email', 'pointer' => '/email_address'],
                ['detail' => 'is required', 'pointer' => '/fields/FirstName'],
            ],
        ]);

        try {
            $this->client->upsertContact('list-1', 'bad');
            $this->fail('Expected RuntimeException');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Validation failed', $e->getMessage());
            $this->assertStringContainsString('/email_address: must be a valid email', $e->getMessage());
            $this->assertStringContainsString('/fields/FirstName: is required', $e->getMessage());
        }
    }

    // --- Rate limiting ---

    public function testRateLimitUsesRetryAfterHeader(): void
    {
        $GLOBALS['_test_wp_remote_request'] = [
            'response' => ['code' => 429],
            'body'     => wp_json_encode(['title' => 'Too Many Requests']),
            'headers'  => ['retry-after' => '10'],
        ];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Retry after 10s');

        $this->client->validateKey();
    }

    // --- getContact 404 ---

    public function testGetContactReturnsNullOn404(): void
    {
        $this->mockResponse(404, [
            'type'   => 'https://emailoctopus.com/api-documentation/v2#not-found',
            'title'  => 'Not Found',
            'detail' => 'Contact not found.',
            'status' => 404,
        ]);

        $result = $this->client->getContact('list-1', 'missing@example.com');
        $this->assertNull($result);
    }

    // --- Auth header ---

    public function testRequestIncludesBearerAuth(): void
    {
        $this->mockResponse(200, ['id' => 'acct-1']);

        $this->client->validateKey();

        $headers = $this->lastArgs()['headers'];
        $this->assertSame('Bearer test-api-key', $headers['Authorization']);
    }
}
